/*
 * qr-img.js — draw a QR code into an <img> without leaving the browser.
 *
 * The receipt uploader's QR encodes a URL carrying an upload token, and that
 * token lets whoever holds it post a receipt with no login. The code used to be
 * fetched from api.qrserver.com with the URL in the query string, which handed
 * the token to an outside service and put it in that service's logs. Everything
 * needed to draw it locally already ships with the site, so nothing has to be
 * sent anywhere.
 *
 * Requires js/qrcode.js. Returns true when the code was drawn.
 */
function bvtuQrInto(img, text, px, dark, light) {
    if (!img || !text || typeof qrcode !== 'function') return false;

    // The encoder defaults to one byte per JS character, which mangles anything
    // non-ASCII. A token URL is plain ASCII, but the fix costs nothing and the
    // next caller may not be.
    if (qrcode.stringToBytesFuncs && qrcode.stringToBytesFuncs['UTF-8']) {
        qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];
    }

    var qr;
    try {
        qr = qrcode(0, 'M');        // 0 = the smallest version the data fits
        qr.addData(text);
        qr.make();
    } catch (e) {
        return false;               // too long to encode
    }

    var margin = 2,
        count  = qr.getModuleCount(),
        target = px || 180,
        cell   = Math.max(1, Math.floor(target / (count + margin * 2))),
        size   = (count + margin * 2) * cell;

    var cv = document.createElement('canvas');
    cv.width = cv.height = size;
    var g = cv.getContext('2d');
    g.fillStyle = light || '#ffffff';
    g.fillRect(0, 0, size, size);
    g.fillStyle = dark || '#1a2e1a';
    for (var r = 0; r < count; r++) {
        for (var c = 0; c < count; c++) {
            if (qr.isDark(r, c)) {
                g.fillRect((c + margin) * cell, (r + margin) * cell, cell, cell);
            }
        }
    }

    img.src = cv.toDataURL('image/png');
    img.width = img.height = size;
    return true;
}
