<?php
/** Build a display-only document from the log's already-redacted HTML body. */
function emailLogPreviewDocument(string $body): string {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        // No external document/entity fetching while parsing stored mail.
        $doc->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    // Retain formatting, but remove active content and navigation. The iframe
    // sandbox and CSP below are the security boundaries; this also makes links
    // and buttons in a logged message clearly non-interactive.
    $blocked = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed',
                'form', 'input', 'button', 'textarea', 'select', 'meta', 'base', 'link'];
    $nodes = $doc->getElementsByTagName('*');
    for ($i = $nodes->length - 1; $i >= 0; $i--) {
        $node = $nodes->item($i);
        if (in_array(strtolower($node->nodeName), $blocked, true)) {
            if ($node->parentNode) $node->parentNode->removeChild($node);
            continue;
        }
        for ($j = $node->attributes->length - 1; $j >= 0; $j--) {
            $attr = $node->attributes->item($j);
            $name = strtolower($attr->localName);
            // HTML parsing can retain a prefix in an SVG attribute's name.
            if (strpos($name, ':') !== false) $name = substr($name, strrpos($name, ':') + 1);
            if (strpos($name, 'on') === 0 || in_array($name,
                    ['href', 'target', 'ping', 'download', 'action', 'formaction', 'srcdoc'], true)) {
                $node->removeAttributeNode($attr);
            }
        }
    }

    $head = $doc->getElementsByTagName('head')->item(0);
    if (!$head) {
        $head = $doc->createElement('head');
        $doc->documentElement->insertBefore($head, $doc->documentElement->firstChild);
    }
    $policy = $doc->createElement('meta');
    $policy->setAttribute('http-equiv', 'Content-Security-Policy');
    $policy->setAttribute('content', "default-src 'none'; style-src 'unsafe-inline'; "
        . "img-src data:; font-src data:; base-uri 'none'; form-action 'none'");
    $head->insertBefore($policy, $head->firstChild);
    $charset = $doc->createElement('meta');
    $charset->setAttribute('charset', 'UTF-8');
    $head->insertBefore($charset, $head->firstChild);

    return '<!doctype html>' . $doc->saveHTML($doc->documentElement);
}
