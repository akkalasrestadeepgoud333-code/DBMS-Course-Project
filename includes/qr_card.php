<?php
/** QR code card for a parcel. Expects $parcel (needs tracking_id). The QR encodes the public tracking URL. */
$qrUrl = tracking_url($parcel['tracking_id']);
$qrId  = 'qr-' . $parcel['tracking_id'];
?>
<div class="text-center">
    <div class="qr-frame mb-2"><canvas id="<?= e($qrId) ?>" data-qr="<?= e($qrUrl) ?>" data-size="320" aria-label="QR code for <?= e($parcel['tracking_id']) ?>"></canvas></div>
    <div class="tid mb-1"><?= e($parcel['tracking_id']) ?></div>
    <div class="qr-url mb-3">Scan to track: <a href="<?= e($qrUrl) ?>" target="_blank" rel="noopener"><?= e($qrUrl) ?></a></div>
    <div class="d-flex flex-wrap justify-content-center gap-2 no-print">
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('qr.php?tracking_id=' . urlencode($parcel['tracking_id']))) ?>"><i class="bi bi-arrows-fullscreen"></i> View</a>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="downloadQR('<?= e($qrId) ?>', 'QR-<?= e($parcel['tracking_id']) ?>')"><i class="bi bi-download"></i> Download</button>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('qr.php?tracking_id=' . urlencode($parcel['tracking_id']) . '&print=1')) ?>"><i class="bi bi-printer"></i> Print</a>
    </div>
</div>
