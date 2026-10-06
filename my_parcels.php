<?php
require_once __DIR__ . '/includes/init.php';
require_customer();

$uid = (int)current_user()['user_id'];
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
if (!in_array($status, PARCEL_STATUSES, true)) $status = '';

// Build WHERE clause with placeholders only (no user text concatenated into SQL)
$where = 'p.customer_id = ?';
$types = 'i';
$params = [$uid];
if ($q !== '') {
    $where .= ' AND (p.tracking_id LIKE ? OR p.receiver_name LIKE ? OR p.receiver_phone LIKE ? OR p.receiver_city LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
if ($status !== '') {
    $where .= ' AND p.current_status = ?';
    $types .= 's';
    $params[] = $status;
}

$parcels = db_all($conn, "SELECT p.parcel_id, p.tracking_id, p.receiver_name, p.receiver_city, p.sender_city, p.parcel_type,
        p.weight_kg, p.booking_date, p.expected_delivery_date, p.delivery_charge, p.payment_status, p.current_status
    FROM parcels p WHERE $where ORDER BY p.booking_date DESC, p.parcel_id DESC", $types, $params);

$pageTitle = 'My Parcels';
$activeNav = 'parcels';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="page-title">My Parcels</h2>
        <p class="text-muted mb-0">All parcels you have booked.</p>
    </div>
    <a href="<?= e(url('book_parcel.php')) ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Book a Parcel</a>
</div>

<div class="card-x">
    <div class="card-head">
        <form class="row g-2 w-100" method="get">
            <div class="col-md-6"><input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search tracking ID, receiver, phone or city"></div>
            <div class="col-8 col-md-4">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <?php foreach (PARCEL_STATUSES as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-4 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i></button>
                <?php if ($q !== '' || $status !== ''): ?><a href="<?= e(url('my_parcels.php')) ?>" class="btn btn-light" title="Clear"><i class="bi bi-x-lg"></i></a><?php endif; ?></div>
        </form>
    </div>
    <?php if (!$parcels): ?>
        <div class="empty-state"><i class="bi bi-search"></i><?= ($q !== '' || $status !== '') ? 'No parcels match your search.' : 'You have not booked any parcels yet.' ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-x">
                <thead><tr><th>Tracking ID</th><th>Route</th><th>Receiver</th><th>Type / Weight</th><th>Booked</th><th>Charge</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($parcels as $p): ?>
                    <tr>
                        <td class="tid"><?= e($p['tracking_id']) ?></td>
                        <td class="small text-nowrap"><?= e($p['sender_city']) ?> <i class="bi bi-arrow-right text-muted"></i> <?= e($p['receiver_city']) ?></td>
                        <td><?= e($p['receiver_name']) ?></td>
                        <td class="small"><?= e($p['parcel_type']) ?><div class="text-muted"><?= e((float)$p['weight_kg']) ?> kg</div></td>
                        <td class="small text-nowrap"><?= e(fmt_date($p['booking_date'])) ?></td>
                        <td class="text-nowrap"><?= money($p['delivery_charge']) ?><div><span class="pay-badge pay-<?= strtolower($p['payment_status']) ?>"><?= e($p['payment_status']) ?></span></div></td>
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
        <div class="px-3 py-2 small text-muted border-top"><?= count($parcels) ?> parcel(s)</div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
