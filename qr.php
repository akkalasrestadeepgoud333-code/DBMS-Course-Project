<?php
/*
 * Full-size QR code for a parcel (view / download / print).
 * The QR encodes the public tracking URL, so it is safe to show without login.
 */
require_once __DIR__ . '/includes/init.php';

$tid = strtoupper(trim($_GET['tracking_id'] ?? ''));
$parcel = valid_tracking_id($tid)
    ? db_one($conn, 'SELECT tracking_id, sender_city, receiver_name, receiver_city, parcel_type, current_status, booking_date
                     FROM parcels WHERE tracking_id = ?', 's', [$tid])
    : null;

$pageTitle = 'QR Code';
require __DIR__ . '/includes/header.php';

if (!$parcel): ?>
    <div class="card-x" style="max-width:520px;margin:2rem auto">
        <div class="empty-state"><i class="bi bi-qr-code"></i>No parcel found for that tracking ID.<br>
            <a class="btn btn-primary btn-sm mt-3" href="<?= e(url('track.php')) ?>">Track a parcel</a></div>
    </div>
<?php else:
    $qrUrl = tracking_url($parcel['tracking_id']);
    $isLocal = (bool)preg_match('#^https?://(localhost|127\.0\.0\.1)#i', $qrUrl);
?>
    <div class="print-area mx-auto" style="max-width:460px">
        <div class="card-x">
            <div class="card-body-x text-center p-4">
                <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
                    <span class="brand-mark"><i class="bi bi-box-seam-fill"></i></span><span class="fw-bold"><?= e(APP_NAME) ?></span>
                </div>
                <div class="qr-frame mb-3"><canvas id="bigQR" data-qr="<?= e($qrUrl) ?>" data-size="480" aria-label="QR code"></canvas></div>
                <div class="tid fs-4 mb-1"><?= e($parcel['tracking_id']) ?></div>
                <div class="text-muted small mb-2"><?= e($parcel['sender_city']) ?> <i class="bi bi-arrow-right"></i> <?= e($parcel['receiver_city']) ?> · <?= e($parcel['parcel_type']) ?> · To: <?= e($parcel['receiver_name']) ?></div>
                <div class="mb-3"><?= status_badge($parcel['current_status']) ?></div>
                <div class="qr-url">Scan with a phone camera to open live tracking<br><a href="<?= e($qrUrl) ?>" target="_blank" rel="noopener"><?= e($qrUrl) ?></a></div>
            </div>
        </div>
        <div class="d-flex flex-wrap justify-content-center gap-2 mt-3 no-print">
            <button class="btn btn-primary" onclick="downloadQR('bigQR', 'QR-<?= e($parcel['tracking_id']) ?>')"><i class="bi bi-download"></i> Download PNG</button>
            <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print label</button>
            <a class="btn btn-outline-primary" href="<?= e(url('track.php?tracking_id=' . $parcel['tracking_id'])) ?>"><i class="bi bi-geo-alt"></i> Open tracking</a>
        </div>
        <?php if ($isLocal): ?>
            <div class="alert alert-warning small mt-3 no-print">
                <i class="bi bi-phone"></i> This QR points to <b>localhost</b>, which a phone cannot open. Set <code>QR_BASE_URL</code> in
                <code>config/config.php</code> to your computer's Wi-Fi IP (e.g. <code>http://192.168.1.10/courier</code>), or open this page using that IP.
            </div>
        <?php else: ?>
            <p class="small text-muted text-center mt-3 no-print"><i class="bi bi-wifi"></i> Your phone must be on the same Wi-Fi network as this computer.</p>
        <?php endif; ?>
    </div>
    <?php if (isset($_GET['print'])): ?>
        <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
    <?php endif; ?>
<?php endif;
require __DIR__ . '/includes/footer.php';
