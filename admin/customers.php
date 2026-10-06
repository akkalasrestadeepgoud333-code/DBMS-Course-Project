<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

$q = trim($_GET['q'] ?? '');
$types = '';
$params = [];
$where = "WHERE u.role = 'customer'";
if ($q !== '') {
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $q . '%';
    $types = 'sss';
    $params = [$like, $like, $like];
}

// LEFT JOIN so customers with zero bookings still appear; GROUP BY for per-customer counts
$customers = db_all($conn, "SELECT u.user_id, u.name, u.email, u.phone, u.created_at,
        COUNT(p.parcel_id) AS bookings,
        COALESCE(SUM(p.current_status IN ('Booked','Picked Up','In Transit','Out for Delivery')), 0) AS active,
        COALESCE(SUM(p.current_status = 'Delivered'), 0) AS delivered,
        COALESCE(SUM(p.delivery_charge), 0) AS total_charges,
        MAX(p.booking_date) AS last_booking
    FROM users u
    LEFT JOIN parcels p ON p.customer_id = u.user_id
    $where
    GROUP BY u.user_id, u.name, u.email, u.phone, u.created_at
    ORDER BY bookings DESC, u.name", $types, $params);

$pageTitle = 'Customers';
$activeNav = 'admin-customers';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-4">
    <h2 class="page-title">Customers</h2>
    <p class="text-muted mb-0"><?= count($customers) ?> registered customer(s) with booking counts.</p>
</div>

<div class="card-x">
    <div class="card-head">
        <form class="d-flex gap-2 w-100" method="get" style="max-width:520px">
            <input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search name, email or phone">
            <button class="btn btn-primary"><i class="bi bi-search"></i></button>
            <?php if ($q !== ''): ?><a href="<?= e(url('admin/customers.php')) ?>" class="btn btn-light"><i class="bi bi-x-lg"></i></a><?php endif; ?>
        </form>
    </div>
    <?php if (!$customers): ?>
        <div class="empty-state"><i class="bi bi-people"></i>No customers found.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-x">
                <thead><tr><th>Customer</th><th>Phone</th><th>Joined</th><th class="text-center">Bookings</th><th class="text-center">Active</th><th class="text-center">Delivered</th><th>Total charges</th><th>Last booking</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><div class="d-flex align-items-center gap-2"><span class="avatar"><?= e(strtoupper(substr($c['name'], 0, 1))) ?></span>
                            <div><?= e($c['name']) ?><div class="small text-muted"><?= e($c['email']) ?></div></div></div></td>
                        <td class="small"><?= e($c['phone']) ?></td>
                        <td class="small text-nowrap"><?= e(fmt_date($c['created_at'])) ?></td>
                        <td class="text-center fw-bold"><?= (int)$c['bookings'] ?></td>
                        <td class="text-center"><?= (int)$c['active'] ?></td>
                        <td class="text-center"><?= (int)$c['delivered'] ?></td>
                        <td><?= money($c['total_charges']) ?></td>
                        <td class="small text-nowrap"><?= e(fmt_date($c['last_booking'])) ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($c['bookings'] > 0): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/parcels.php?customer_id=' . $c['user_id'])) ?>">View</a><?php endif; ?>
                            <form method="post" action="<?= e(url('admin/delete.php')) ?>" class="d-inline" data-confirm="Delete customer <?= e($c['name']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="customer">
                                <input type="hidden" name="id" value="<?= (int)$c['user_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete customer"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
