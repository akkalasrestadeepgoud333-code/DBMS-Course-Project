<?php
require_once __DIR__ . '/includes/init.php';
require_customer();

$uid = (int)current_user()['user_id'];

// Aggregate counts for this customer (COUNT + conditional SUM)
$stats = db_one($conn, "SELECT COUNT(*) AS total,
        COALESCE(SUM(current_status IN ('Booked','Picked Up','In Transit','Out for Delivery')), 0) AS active,
        COALESCE(SUM(current_status = 'Delivered'), 0) AS delivered,
        COALESCE(SUM(delivery_charge), 0) AS spent
    FROM parcels WHERE customer_id = ?", 'i', [$uid]);

// Recent bookings with latest tracking location (correlated subquery)
$recent = db_all($conn, "SELECT p.parcel_id, p.tracking_id, p.receiver_name, p.receiver_city, p.booking_date,
        p.expected_delivery_date, p.current_status,
        (SELECT th.location FROM tracking_history th WHERE th.parcel_id = p.parcel_id
          ORDER BY th.created_at DESC, th.history_id DESC LIMIT 1) AS last_location
    FROM parcels p WHERE p.customer_id = ?
    ORDER BY p.booking_date DESC, p.parcel_id DESC LIMIT 5", 'i', [$uid]);

$pageTitle = 'Dashboard';
$activeNav = 'dash';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="page-title">Hello, <?= e(current_user()['name']) ?> 👋</h2>
        <p class="text-muted mb-0">Here's what's happening with your parcels.</p>
    </div>
    <a href="<?= e(url('book_parcel.php')) ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Book a Parcel</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card-x stat-card"><div class="stat-icon bg-soft-blue"><i class="bi bi-boxes"></i></div><div><div class="stat-value"><?= (int)$stats['total'] ?></div><div class="stat-label">Total bookings</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card-x stat-card"><div class="stat-icon bg-soft-amber"><i class="bi bi-truck"></i></div><div><div class="stat-value"><?= (int)$stats['active'] ?></div><div class="stat-label">Active parcels</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card-x stat-card"><div class="stat-icon bg-soft-green"><i class="bi bi-house-check"></i></div><div><div class="stat-value"><?= (int)$stats['delivered'] ?></div><div class="stat-label">Delivered</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card-x stat-card"><div class="stat-icon bg-soft-violet"><i class="bi bi-currency-rupee"></i></div><div><div class="stat-value" style="font-size:1.3rem"><?= money($stats['spent']) ?></div><div class="stat-label">Total charges</div></div></div></div>
</div>

<div class="card-x">
    <div class="card-head">
        <h5>Recent bookings</h5>
        <a href="<?= e(url('my_parcels.php')) ?>" class="btn btn-sm btn-outline-primary">View all</a>
    </div>
    <?php if (!$recent): ?>
        <div class="empty-state"><i class="bi bi-box2"></i>You haven't booked any parcels yet.<br>
            <a href="<?= e(url('book_parcel.php')) ?>" class="btn btn-primary btn-sm mt-3">Book your first parcel</a></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-x">
                <thead><tr><th>Tracking ID</th><th>Receiver</th><th>Booked on</th><th>Last location</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $p): ?>
                    <tr>
                        <td class="tid"><?= e($p['tracking_id']) ?></td>
                        <td><?= e($p['receiver_name']) ?><div class="small text-muted"><?= e($p['receiver_city']) ?></div></td>
                        <td><?= e(fmt_date($p['booking_date'])) ?></td>
                        <td class="small"><?= e($p['last_location'] ?? '—') ?></td>
                        <td><?= status_badge($p['current_status']) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light" href="<?= e(url('parcel_details.php?id=' . $p['parcel_id'])) ?>" title="View details"><i class="bi bi-eye"></i></a>
                            <a class="btn btn-sm btn-light" href="<?= e(url('track.php?tracking_id=' . $p['tracking_id'])) ?>" title="Track"><i class="bi bi-geo-alt"></i></a>
                            <a class="btn btn-sm btn-light" href="<?= e(url('qr.php?tracking_id=' . $p['tracking_id'])) ?>" title="QR code"><i class="bi bi-qr-code"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
