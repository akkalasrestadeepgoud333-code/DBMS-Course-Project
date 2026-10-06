<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

/*
 * Every number on this page comes from these SQL queries.
 * They are also displayed at the bottom of the page for the DBMS demonstration.
 */
$sql = [
    'Total customers (COUNT + WHERE)' =>
        "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'",
    'Parcels by status (GROUP BY)' =>
        "SELECT current_status, COUNT(*) AS total FROM parcels GROUP BY current_status",
    'Revenue collected (SUM + WHERE)' =>
        "SELECT COALESCE(SUM(amount), 0) AS revenue FROM payments WHERE payment_status = 'Paid'",
    'Recent bookings (JOIN + ORDER BY + LIMIT)' =>
        "SELECT p.parcel_id, p.tracking_id, u.name AS customer, p.receiver_name, p.receiver_city,
       p.booking_date, p.current_status
FROM parcels p
JOIN users u ON u.user_id = p.customer_id
ORDER BY p.booking_date DESC, p.parcel_id DESC
LIMIT 8",
    'Top customers by bookings (JOIN + GROUP BY + HAVING)' =>
        "SELECT u.user_id, u.name, COUNT(p.parcel_id) AS bookings, SUM(p.delivery_charge) AS total_spent
FROM users u
JOIN parcels p ON p.customer_id = u.user_id
GROUP BY u.user_id, u.name
HAVING COUNT(p.parcel_id) > 0
ORDER BY bookings DESC, total_spent DESC
LIMIT 5",
    'Latest tracking updates (JOIN 3 tables)' =>
        "SELECT th.created_at, th.status, th.location, p.tracking_id, p.parcel_id, a.name AS updated_by
FROM tracking_history th
JOIN parcels p ON p.parcel_id = th.parcel_id
LEFT JOIN users a ON a.user_id = th.updated_by
ORDER BY th.created_at DESC, th.history_id DESC
LIMIT 6",
];
$queryKeys = array_keys($sql);

$totalCustomers = (int)db_one($conn, $sql[$queryKeys[0]])['total'];
$byStatus = array_fill_keys(PARCEL_STATUSES, 0);
foreach (db_all($conn, $sql[$queryKeys[1]]) as $r) {
    $byStatus[$r['current_status']] = (int)$r['total'];
}
$totalParcels = array_sum($byStatus);
$active = 0;
foreach (ACTIVE_STATUSES as $s) $active += $byStatus[$s];
$revenue = db_one($conn, $sql[$queryKeys[2]])['revenue'];
$recent = db_all($conn, $sql[$queryKeys[3]]);
$topCustomers = db_all($conn, $sql[$queryKeys[4]]);
$updates = db_all($conn, $sql[$queryKeys[5]]);

$cards = [
    ['Total customers', $totalCustomers, 'people', 'slate', 'admin/customers.php'],
    ['Total parcels', $totalParcels, 'boxes', 'blue', 'admin/parcels.php'],
    ['Active parcels', $active, 'activity', 'cyan', 'admin/parcels.php?status=active'],
    ['Pending bookings', $byStatus['Booked'], 'journal-check', 'slate', 'admin/parcels.php?status=Booked'],
    ['Picked up', $byStatus['Picked Up'], 'box-seam', 'violet', 'admin/parcels.php?status=Picked+Up'],
    ['In transit', $byStatus['In Transit'], 'truck', 'blue', 'admin/parcels.php?status=In+Transit'],
    ['Out for delivery', $byStatus['Out for Delivery'], 'bicycle', 'amber', 'admin/parcels.php?status=Out+for+Delivery'],
    ['Delivered', $byStatus['Delivered'], 'house-check', 'green', 'admin/parcels.php?status=Delivered'],
];
$barColors = ['Booked' => '#64748b', 'Picked Up' => '#7c3aed', 'In Transit' => '#1d4ed8', 'Out for Delivery' => '#d97706',
    'Delivered' => '#16a34a', 'Failed' => '#dc2626', 'Returned' => '#6b7280'];

$pageTitle = 'Admin Dashboard';
$activeNav = 'admin-dash';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="page-title">Operations Dashboard</h2>
        <p class="text-muted mb-0">Live overview of the courier network · <?= e(date('d M Y, h:i A')) ?></p>
    </div>
    <form action="<?= e(url('admin/parcels.php')) ?>" method="get" class="d-flex gap-2">
        <input type="search" name="q" class="form-control" placeholder="Search tracking ID, name, phone…" style="min-width:240px">
        <button class="btn btn-primary"><i class="bi bi-search"></i></button>
    </form>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon, $color, $link]): ?>
        <div class="col-6 col-md-4 col-xl-3">
            <a href="<?= e(url($link)) ?>" class="text-decoration-none text-reset">
                <div class="card-x stat-card"><div class="stat-icon bg-soft-<?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></div>
                    <div><div class="stat-value"><?= (int)$value ?></div><div class="stat-label"><?= e($label) ?></div></div></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card-x h-100">
            <div class="card-head"><h5>Recent bookings</h5><a href="<?= e(url('admin/parcels.php')) ?>" class="btn btn-sm btn-outline-primary">All parcels</a></div>
            <div class="table-responsive">
                <table class="table table-x">
                    <thead><tr><th>Tracking ID</th><th>Customer</th><th>Receiver</th><th>Booked</th><th>Status</th><th class="text-end"></th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $p): ?>
                        <tr>
                            <td class="tid"><?= e($p['tracking_id']) ?></td>
                            <td class="text-nowrap"><?= e($p['customer']) ?></td>
                            <td><?= e($p['receiver_name']) ?><div class="small text-muted"><?= e($p['receiver_city']) ?></div></td>
                            <td class="small text-nowrap"><?= e(fmt_date($p['booking_date'])) ?><div class="text-muted"><?= e(date('h:i A', strtotime($p['booking_date']))) ?></div></td>
                            <td><?= status_badge($p['current_status']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= e(url('admin/parcel.php?id=' . $p['parcel_id'])) ?>" title="Manage parcel"><i class="bi bi-pencil-square"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?><tr><td colspan="6"><div class="empty-state">No bookings yet.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card-x mb-4">
            <div class="card-head"><h6>Parcels by status</h6><span class="small text-muted">GROUP BY</span></div>
            <div class="card-body-x">
                <?php foreach ($byStatus as $s => $n): ?>
                    <div class="bar-row"><span class="lbl"><?= e($s) ?></span>
                        <span class="track"><span class="fill d-block" style="width:<?= $totalParcels ? round($n / $totalParcels * 100) : 0 ?>%;background:<?= $barColors[$s] ?>"></span></span>
                        <span class="cnt"><?= $n ?></span></div>
                <?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-between"><span class="text-muted">Revenue collected</span><span class="fw-bold"><?= money($revenue) ?></span></div>
            </div>
        </div>
        <div class="card-x">
            <div class="card-head"><h6>Top customers</h6></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($topCustomers as $c): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a class="text-decoration-none text-reset" href="<?= e(url('admin/parcels.php?customer_id=' . $c['user_id'])) ?>"><?= e($c['name']) ?></a>
                        <span class="small text-muted"><?= (int)$c['bookings'] ?> parcels · <?= money($c['total_spent']) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$topCustomers): ?><li class="list-group-item text-muted small">No bookings yet.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<div class="card-x mb-4">
    <div class="card-head"><h6><i class="bi bi-broadcast"></i> Latest tracking updates</h6></div>
    <div class="table-responsive">
        <table class="table table-x">
            <thead><tr><th>Time</th><th>Tracking ID</th><th>Status</th><th>Location</th><th>Updated by</th></tr></thead>
            <tbody>
            <?php foreach ($updates as $u): ?>
                <tr><td class="small text-nowrap"><?= e(fmt_date($u['created_at'], true)) ?></td>
                    <td><a class="tid text-decoration-none" href="<?= e(url('admin/parcel.php?id=' . $u['parcel_id'])) ?>"><?= e($u['tracking_id']) ?></a></td>
                    <td><?= status_badge($u['status']) ?></td><td class="small"><?= e($u['location']) ?></td><td class="small"><?= e($u['updated_by'] ?? 'System') ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card-x">
    <div class="card-head">
        <h6><i class="bi bi-database"></i> SQL queries powering this dashboard</h6>
        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#sqlPanel">Show / hide</button>
    </div>
    <div class="collapse" id="sqlPanel">
        <div class="card-body-x">
            <?php foreach ($sql as $title => $query): ?>
                <div class="small fw-semibold"><?= e($title) ?></div>
                <pre class="sql"><?= e($query) ?>;</pre>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
