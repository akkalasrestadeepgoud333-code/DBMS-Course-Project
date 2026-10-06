<?php
require_once __DIR__ . '/includes/init.php';
require_login();
if (is_admin()) {
    redirect('admin/parcel.php?id=' . (int)($_GET['id'] ?? 0));
}

$uid = (int)current_user()['user_id'];
$id  = (int)($_GET['id'] ?? 0);

// Customers can only open their own parcels (customer_id = logged-in user)
$parcel = db_one($conn, 'SELECT * FROM parcels WHERE parcel_id = ? AND customer_id = ?', 'ii', [$id, $uid]);

$pageTitle = 'Parcel details';
$activeNav = 'parcels';

if (!$parcel) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<div class="card-x"><div class="empty-state"><i class="bi bi-box2"></i>Parcel not found, or it does not belong to your account.<br>'
        . '<a class="btn btn-primary btn-sm mt-3" href="' . e(url('my_parcels.php')) . '">Back to My Parcels</a></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$payments = db_all($conn, 'SELECT * FROM payments WHERE parcel_id = ? ORDER BY payment_date DESC', 'i', [$id]);
$history  = db_all($conn, 'SELECT status, location, remarks, created_at FROM tracking_history WHERE parcel_id = ?
                           ORDER BY created_at DESC, history_id DESC', 'i', [$id]);
$isNew = isset($_GET['new']);

require __DIR__ . '/includes/header.php';
?>
<?php if ($isNew): ?>
    <div class="card-x mb-4" style="border-color:#bbf7d0;background:#f0fdf4">
        <div class="card-body-x d-flex flex-wrap align-items-center gap-3">
            <div class="stat-icon bg-soft-green"><i class="bi bi-check2-circle"></i></div>
            <div class="flex-fill">
                <h5 class="fw-bold mb-1">Booking confirmed!</h5>
                <div>Tracking ID <span class="tid fs-5"><?= e($parcel['tracking_id']) ?></span>
                    <button class="btn btn-sm btn-light ms-1" onclick="copyText(this, '<?= e($parcel['tracking_id']) ?>')"><i class="bi bi-clipboard"></i> Copy</button></div>
                <div class="small text-muted mt-1">Status: <b><?= e($parcel['current_status']) ?></b> · Delivery charge: <b><?= money($parcel['delivery_charge']) ?></b> · Payment: <b><?= e($parcel['payment_status']) ?></b></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <a href="<?= e(url('my_parcels.php')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> My Parcels</a>
        <h2 class="page-title mt-1">Parcel <span class="tid"><?= e($parcel['tracking_id']) ?></span></h2>
    </div>
    <div class="d-flex gap-2">
        <?= status_badge($parcel['current_status']) ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('track.php?tracking_id=' . $parcel['tracking_id'])) ?>"><i class="bi bi-geo-alt"></i> Public tracking page</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-x mb-4">
            <div class="card-head"><h6>Shipment details</h6><span class="small text-muted">Booked <?= e(fmt_date($parcel['booking_date'], true)) ?></span></div>
            <div class="card-body-x">
                <div class="route mb-4">
                    <div><div class="small text-muted">From</div><div class="city"><?= e($parcel['sender_city']) ?></div></div>
                    <div class="line"><i class="bi bi-truck"></i></div>
                    <div class="text-end"><div class="small text-muted">To</div><div class="city"><?= e($parcel['receiver_city']) ?></div></div>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="fw-bold mb-2"><i class="bi bi-person-up text-primary"></i> Sender</div>
                        <div><?= e($parcel['sender_name']) ?></div>
                        <div class="small text-muted"><?= e($parcel['sender_phone']) ?></div>
                        <div class="small"><?= e($parcel['sender_address']) ?>, <?= e($parcel['sender_city']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-bold mb-2"><i class="bi bi-person-down text-primary"></i> Receiver</div>
                        <div><?= e($parcel['receiver_name']) ?></div>
                        <div class="small text-muted"><?= e($parcel['receiver_phone']) ?></div>
                        <div class="small"><?= e($parcel['receiver_address']) ?>, <?= e($parcel['receiver_city']) ?></div>
                    </div>
                </div>
                <hr>
                <div class="kv" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
                    <div><div class="k">Parcel type</div><div class="v"><?= e($parcel['parcel_type']) ?></div></div>
                    <div><div class="k">Weight</div><div class="v"><?= e((float)$parcel['weight_kg']) ?> kg</div></div>
                    <div><div class="k">Expected delivery</div><div class="v"><?= e(fmt_date($parcel['expected_delivery_date'])) ?></div></div>
                    <div><div class="k">Delivery charge</div><div class="v"><?= money($parcel['delivery_charge']) ?></div></div>
                </div>
            </div>
        </div>

        <div class="card-x mb-4">
            <div class="card-head"><h6>Payment</h6><span class="pay-badge pay-<?= strtolower($parcel['payment_status']) ?>"><?= e($parcel['payment_status']) ?></span></div>
            <div class="table-responsive">
                <table class="table table-x">
                    <thead><tr><th>Reference</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $pay): ?>
                        <tr><td class="small tid"><?= e($pay['transaction_ref']) ?></td><td><?= e($pay['payment_method']) ?></td><td><?= money($pay['amount']) ?></td>
                            <td><span class="pay-badge pay-<?= strtolower($pay['payment_status']) ?>"><?= e($pay['payment_status']) ?></span></td><td class="small"><?= e(fmt_date($pay['payment_date'], true)) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-x">
            <div class="card-head"><h6><i class="bi bi-clock-history"></i> Tracking history</h6><span class="small text-muted"><?= count($history) ?> event(s)</span></div>
            <div class="card-body-x"><?php require __DIR__ . '/includes/timeline.php'; ?></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-x">
            <div class="card-head"><h6><i class="bi bi-qr-code"></i> Parcel QR code</h6></div>
            <div class="card-body-x"><?php require __DIR__ . '/includes/qr_card.php'; ?></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
