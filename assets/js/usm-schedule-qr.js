/**
 * USM Schedule – QR Code modal handler.
 *
 * @package USM
 */

/* global qrcode */

function usmShowQR(url, title) {
    'use strict';

    document.getElementById('usm-qr-title').textContent = title;
    var qrDiv = document.getElementById('usm-qr-code');
    qrDiv.innerHTML = '';
    var qr = qrcode(0, 'M');
    qr.addData(url);
    qr.make();
    qrDiv.innerHTML = qr.createSvgTag(6, 0);
    document.getElementById('usm-qr-modal').style.display = 'flex';
}
