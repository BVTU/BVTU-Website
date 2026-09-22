<?php
/**
 * xlsx-writer.php — writes a real .xlsx with no dependencies.
 *
 * An .xlsx is a ZIP of XML parts, so ZipArchive is all that is needed. Written
 * rather than pulled in because this project has no Composer and PhpSpreadsheet
 * would mean hand-vendoring a large dependency tree.
 *
 * Covers what the contact export needs: one sheet, bold header, frozen header
 * row, autofilter, column widths, inline strings throughout (no shared-string
 * table, which keeps this short and is valid).
 */

function xlsxEscape(string $v): string {
    // Strip control characters Excel refuses to open, then XML-escape.
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v);
    return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** "A" for 0, "Z" for 25, "AA" for 26. */
function xlsxCol(int $i): string {
    $s = '';
    $i++;
    while ($i > 0) {
        $m = ($i - 1) % 26;
        $s = chr(65 + $m) . $s;
        $i = intdiv($i - 1, 26);
    }
    return $s;
}

/**
 * Leading = + - @ make Excel treat a cell as a formula, so a contact whose name
 * begins with one could execute on open. Prefix a single quote, which Excel
 * shows as text. Applied to every string cell, not only suspicious-looking ones.
 */
function xlsxSafeText(string $v): string {
    return (isset($v[0]) && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
}

/**
 * The CSV counterpart of xlsxSafeText(). A leading = + - @ makes Excel treat the
 * cell as a formula, so a name or note starting with one could execute when the
 * file is opened. Shared rather than copied: this rule must not drift between
 * the exports that rely on it.
 */
function csvSafeText($v): string {
    $v = (string)$v;
    return (isset($v[0]) && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
}

/**
 * @param string   $path    file to write
 * @param string[] $headers column headings
 * @param array[]  $rows    rows of scalar values
 * @param string   $sheet   sheet name
 * @param int[]    $widths  column widths in characters
 */
function xlsxWrite(string $path, array $headers, array $rows, string $sheet = 'Sheet1', array $widths = []): bool {
    if (!class_exists('ZipArchive')) return false;
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;

    $nCols = count($headers);
    $nRows = count($rows) + 1;
    $lastCol = xlsxCol(max(0, $nCols - 1));

    // ── sheet rows ──
    $sheetRows = '<row r="1">';
    foreach ($headers as $i => $h) {
        $sheetRows .= '<c r="' . xlsxCol($i) . '1" t="inlineStr" s="1"><is><t>'
                    . xlsxEscape((string)$h) . '</t></is></c>';
    }
    $sheetRows .= '</row>';

    $r = 1;
    foreach ($rows as $row) {
        $r++;
        $sheetRows .= '<row r="' . $r . '">';
        $i = 0;
        foreach ($row as $v) {
            $ref = xlsxCol($i) . $r;
            if (is_int($v) || is_float($v)) {
                $sheetRows .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
            } else {
                $sheetRows .= '<c r="' . $ref . '" t="inlineStr"><is><t>'
                            . xlsxEscape(xlsxSafeText((string)$v)) . '</t></is></c>';
            }
            $i++;
        }
        $sheetRows .= '</row>';
    }

    $colsXml = '';
    if ($widths) {
        $colsXml = '<cols>';
        foreach ($widths as $i => $w) {
            $colsXml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)$w . '" customWidth="1"/>';
        }
        $colsXml .= '</cols>';
    }

    $sheetXml =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<sheetViews><sheetView workbookViewId="0">'
    // freeze everything above row 2
    . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
    . '</sheetView></sheetViews>'
    . $colsXml
    . '<sheetData>' . $sheetRows . '</sheetData>'
    . '<autoFilter ref="A1:' . $lastCol . $nRows . '"/>'
    . '</worksheet>';

    // ── the fixed parts ──
    $zip->addFromString('[Content_Types].xml',
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '</Types>');

    $zip->addFromString('_rels/.rels',
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>');

    $zip->addFromString('xl/workbook.xml',
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
    . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="' . xlsxEscape(substr($sheet, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets>'
    . '</workbook>');

    $zip->addFromString('xl/_rels/workbook.xml.rels',
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>');

    // Two styles: 0 plain, 1 bold — used by the header row.
    $zip->addFromString('xl/styles.xml',
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
    . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
    . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
    . '<borders count="1"><border/></borders>'
    . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
    . '<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs>'
    // Excel is stricter than some readers and expects a named default style.
    . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
    . '</styleSheet>');

    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    return $zip->close();
}
