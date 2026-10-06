/* SwiftCourier – small vanilla JS helpers */

/**
 * Draw a QR code on every <canvas data-qr="...text..."> using the local
 * qrcode-generator library (assets/js/qrcode.min.js, MIT licence).
 */
function renderQRCodes() {
    if (typeof qrcode === 'undefined') return;
    document.querySelectorAll('canvas[data-qr]').forEach(function (canvas) {
        var qr = qrcode(0, 'M');          // 0 = auto size, M = medium error correction
        qr.addData(canvas.dataset.qr);
        qr.make();
        var count = qr.getModuleCount();
        var quiet = 4;                    // white border (modules)
        var cell = Math.max(4, Math.floor(parseInt(canvas.dataset.size || '320', 10) / (count + quiet * 2)));
        var size = (count + quiet * 2) * cell;
        canvas.width = size;
        canvas.height = size;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, size, size);
        ctx.fillStyle = '#0f172a';
        for (var r = 0; r < count; r++) {
            for (var c = 0; c < count; c++) {
                if (qr.isDark(r, c)) ctx.fillRect((c + quiet) * cell, (r + quiet) * cell, cell, cell);
            }
        }
    });
}

/** Download the QR canvas as a PNG file. */
function downloadQR(canvasId, fileName) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = fileName + '.png';
    document.body.appendChild(link);
    link.click();
    link.remove();
}

/** Copy text to clipboard with a short confirmation on the button. */
function copyText(btn, text) {
    var done = function () {
        var old = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
        setTimeout(function () { btn.innerHTML = old; }, 1500);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done);
    } else {
        var t = document.createElement('textarea');
        t.value = text; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        t.remove();
    }
}

/** Live delivery-charge preview on the booking form (server recalculates on submit). */
function initChargeCalculator() {
    var form = document.getElementById('bookingForm');
    if (!form) return;
    var base = parseFloat(form.dataset.base), perKg = parseFloat(form.dataset.perkg);
    var surcharges = JSON.parse(form.dataset.surcharge);
    var weight = form.querySelector('[name=weight]'), type = form.querySelector('[name=parcel_type]');
    var update = function () {
        var w = parseFloat(weight.value) || 0;
        var s = surcharges[type.value] || 0;
        var total = w > 0 ? base + w * perKg + s : 0;
        document.getElementById('cWeight').textContent = '₹' + (w * perKg).toFixed(2);
        document.getElementById('cWeightLbl').textContent = w ? w + ' kg × ₹' + perKg : 'Weight';
        document.getElementById('cType').textContent = '₹' + s.toFixed(2);
        document.getElementById('cTotal').textContent = '₹' + total.toFixed(2);
    };
    weight.addEventListener('input', update);
    type.addEventListener('change', update);
    update();

    // "Same as my details" helper for the sender section
    var fill = document.getElementById('fillSender');
    if (fill) fill.addEventListener('change', function () {
        if (!this.checked) return;
        form.querySelector('[name=sender_name]').value = this.dataset.name;
        form.querySelector('[name=sender_phone]').value = this.dataset.phone;
    });
}

/** Bootstrap client-side validation styling. */
function initValidation() {
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!form.checkValidity()) { ev.preventDefault(); ev.stopPropagation(); }
            form.classList.add('was-validated');
        });
    });
}

/** Ask for confirmation on buttons/forms marked data-confirm. */
function initConfirm() {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (form.checkValidity() && !confirm(form.dataset.confirm)) ev.preventDefault();
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    renderQRCodes();
    initChargeCalculator();
    initValidation();
    initConfirm();
    // Tracking ID inputs: force uppercase
    document.querySelectorAll('input[name=tracking_id]').forEach(function (i) {
        i.addEventListener('input', function () { this.value = this.value.toUpperCase().replace(/\s/g, ''); });
    });
});
