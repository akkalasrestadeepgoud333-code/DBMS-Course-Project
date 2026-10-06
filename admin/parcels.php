<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$customerId = (int)($_GET['customer_id'] ?? 0);
if ($status !== 'active' && !in_array($status, PARCEL_STATUSES, true)) $status = '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// ---- Build a safe WHERE clause (placeholders only) ----
$conds = [];
$types = '';
$params = [];
if ($q !== '') {
    $conds[] = '(p.tracking_id LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR p.sender_name LIKE ? OR p.receiver_name LIKE ?
                 OR p.sender_phone LIKE ? OR p.receiver_phone LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $q . '%';
    $types .= str_repeat('s', 8);
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}
if ($status === 'active') {
    $conds[] = "p.current_status IN ('" . implode("','", ACTIVE_STATUSES) . "')"; // constants, not user input
} elseif ($status !== '') {
    $conds[] = 'p.current_status = ?';
    $types .= 's';
    $params[] = $status;
}
if ($customerId > 0) {
    $conds[] = 'p.customer_id = ?';
    $types .= 'i';
    $params[] = $customerId;
}
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$total = (int)db_one($conn, "SELECT COUNT(*) AS n FROM parcels p JOIN users u ON u.user_id = p.customer_id $where", $types, $params)['n'];
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$parcels = db_all($conn, "SELECT p.parcel_id, p.tracking_id, u.name AS customer, u.phone AS customer_phone, p.sender_city,
        p.receiver_name, p.receiver_phone, p.receiver_city, p.parcel_type, p.weight_kg, p.booking_date,
        p.delivery_charge, p.payment_status, p.current_status
    FROM parcels p JOIN users u ON u.user_id = p.customer_id
    $where ORDER BY p.booking_date DESC, p.parcel_id DESC LIMIT $perPage OFFSET $offset", $types, $params);

$customerName = $customerId ? (db_one($conn, 'SELECT name FROM users WHERE user_id = ?', 'i', [$customerId])['name'] ?? null) : null;

function page_link(int $n): string
{
    $qs = $_GET;
    $qs['page'] = $n;
    return url('admin/parcels.php?' . http_build_query($qs));
}

$pageTitle = 'Manage Parcels';
$activeNav = 'admin-parcels';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4">
    <h2 class="page-title">Parcels</h2>
    <p class="text-muted mb-0"><?= $total ?> parcel(s) found<?= $customerName ? ' for customer <b>' . e($customerName) . '</b>' : '' ?>.</p>
</div>

<div class="card-x">
    <div class="card-head">
        <form class="row g-2 w-100" method="get">
            <?php if ($customerId): ?><input type="hidden" name="customer_id" value="<?= $customerId ?>"><?php endif; ?>
            <div class="col-md-6"><input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Tracking ID, customer, sender, receiver or phone"></div>
            <div class="col-8 col-md-4">
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (not yet delivered)</option>
                    <?php foreach (PARCEL_STATUSES as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-4 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                <?php if ($q !== '' || $status !== '' || $customerId): ?><a href="<?= e(url('admin/parcels.php')) ?>" class="btn btn-light" title="Clear filters"><i class="bi bi-x-lg"></i></a><?php endif; ?></div>
        </form>
    </div>
    <?php if (!$parcels): ?>
        <div class="empty-state"><i class="bi bi-search"></i>No parcels match these filters.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-x">
                <thead><tr><th>Tracking ID</th><th>Customer</th><th>Receiver</th><th>Route</th><th>Type</th><th>Booked</th><th>Charge</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach ($parcels as $p): ?>
                    <tr>
                        <td class="tid"><?= e($p['tracking_id']) ?></td>
                        <td><?= e($p['customer']) ?><div class="small text-muted"><?= e($p['customer_phone']) ?></div></td>
                        <td><?= e($p['receiver_name']) ?><div class="small text-muted"><?= e($p['receiver_phone']) ?></div></td>
                        <td class="small text-nowrap"><?= e($p['sender_city']) ?> <i class="bi bi-arrow-right text-muted"></i> <?= e($p['receiver_city']) ?></td>
                        <td class="small"><?= e($p['parcel_type']) ?><div class="text-muted"><?= e((float)$p['weight_kg']) ?> kg</div></td>
                        <td class="small text-nowrap"><?= e(fmt_date($p['booking_date'])) ?></td>
                        <td class="text-nowrap"><?= money($p['delivery_charge']) ?><div><span class="pay-badge pay-<?= strtolower($p['payment_status']) ?>"><?= e($p['payment_status']) ?></span></div></td>
                        <td><?= status_badge($p['current_status']) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= e(url('admin/parcel.php?id=' . $p['parcel_id'])) ?>">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="px-3 py-2 border-top"><ul class="pagination pagination-sm mb-0 flex-wrap">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(page_link($i)) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
