<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$adminId = (int)current_user()['user_id'];
$errors = [];
$form = ['status' => '', 'location' => '', 'remarks' => ''];

/* ---------- Status update: UPDATE parcels + INSERT tracking_history in ONE transaction ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['status']   = trim($_POST['status'] ?? '');
    $form['location'] = trim($_POST['location'] ?? '');
    $form['remarks']  = trim($_POST['remarks'] ?? '');

    if (!csrf_valid()) $errors[] = 'Your session expired. Please try again.';
    if (!in_array($form['status'], PARCEL_STATUSES, true) || $form['status'] === 'Booked') $errors[] = 'Choose a valid new status.';
    if (mb_strlen($form['location']) < 2 || mb_strlen($form['location']) > 120) $errors[] = 'Location is required (2–120 characters).';
    if (mb_strlen($form['remarks']) > 255) $errors[] = 'Remarks can be at most 255 characters.';

    if (!$errors) {
        $conn->begin_transaction();
        try {
            // Lock the parcel row so two admins cannot update it at the same moment
            $cur = db_one($conn, 'SELECT current_status, payment_status FROM parcels WHERE parcel_id = ? FOR UPDATE', 'i', [$id]);
            if (!$cur) {
                $errors[] = 'Parcel not found.';
            } elseif (in_array($cur['current_status'], FINAL_STATUSES, true)) {
                $errors[] = 'This parcel is already ' . $cur['current_status'] . ' — its status can no longer be changed.';
            } elseif (!in_array($form['status'], ALLOWED_TRANSITIONS[$cur['current_status']], true)) {
                $errors[] = 'Invalid status change: a parcel that is "' . $cur['current_status'] . '" cannot move to "' . $form['status'] . '".';
            }

            if ($errors) {
                $conn->rollback();
            } else {
                $stmt = $conn->prepare('UPDATE parcels SET current_status = ? WHERE parcel_id = ?');
                $stmt->bind_param('si', $form['status'], $id);
                $stmt->execute();

                $remarks = $form['remarks'] !== '' ? $form['remarks'] : null;
                $stmt = $conn->prepare('INSERT INTO tracking_history (parcel_id, status, location, remarks, updated_by) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('isssi', $id, $form['status'], $form['location'], $remarks, $adminId);
                $stmt->execute();

                // Cash on Pickup is collected at delivery – mark the simulated payment as Paid
                if ($form['status'] === 'Delivered' && $cur['payment_status'] === 'Pending') {
                    $stmt = $conn->prepare("UPDATE parcels SET payment_status = 'Paid' WHERE parcel_id = ?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt = $conn->prepare("UPDATE payments SET payment_status = 'Paid', payment_date = NOW() WHERE parcel_id = ? AND payment_status = 'Pending'");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                }

                $conn->commit();
                flash('success', 'Status updated to "' . $form['status'] . '" and a new tracking event was recorded.');
                redirect('admin/parcel.php?id=' . $id);
            }
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            error_log('[Courier] status update failed: ' . $ex->getMessage());
            $errors[] = 'The status could not be updated. No changes were saved.';
        }
    }
}

/* ---------- Load parcel (JOIN with customer) ---------- */
$parcel = db_one($conn, 'SELECT p.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
                         FROM parcels p JOIN users u ON u.user_id = p.customer_id WHERE p.parcel_id = ?', 'i', [$id]);

$pageTitle = 'Manage parcel';
$activeNav = 'admin-parcels';

if (!$parcel) {
    http_response_code(404);
    require __DIR__ . '/../includes/header.php';
    echo '<div class="card-x"><div class="empty-state"><i class="bi bi-box2"></i>Parcel not found.<br><a class="btn btn-primary btn-sm mt-3" href="'
        . e(url('admin/parcels.php')) . '">Back to parcels</a></div></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$history = db_all($conn, 'SELECT th.status, th.location, th.remarks, th.created_at, u.name AS updated_by_name, u.role AS updated_by_role
                          FROM tracking_history th LEFT JOIN users u ON u.user_id = th.updated_by
                          WHERE th.parcel_id = ? ORDER BY th.created_at DESC, th.history_id DESC', 'i', [$id]);
$payments = db_all($conn, 'SELECT * FROM payments WHERE parcel_id = ? ORDER BY payment_date DESC', 'i', [$id]);
$locked = in_array($parcel['current_status'], FINAL_STATUSES, true);
$showUpdatedBy = true;

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <a href="<?= e(url('admin/parcels.php')) ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> All parcels</a>
        <h2 class="page-title mt-1">Parcel <span class="tid"><?= e($parcel['tracking_id']) ?></span></h2>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <?= status_badge($parcel['current_status']) ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('track.php?tracking_id=' . $parcel['tracking_id'])) ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Public tracking</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Update status -->
        <div class="card-x mb-4">
            <div class="card-head"><h6><i class="bi bi-arrow-repeat text-primary"></i> Update status</h6>
                <span class="small text-muted">Adds a new row to <code>tracking_history</code></span></div>
            <div class="card-body-x">
                <?php if ($errors): ?><div class="alert alert-danger py-2"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <?php if ($locked): ?>
                    <div class="alert alert-secondary mb-0"><i class="bi bi-lock"></i> This parcel is <b><?= e($parcel['current_status']) ?></b>. Final statuses cannot be changed; the tracking history is kept for records.</div>
                <?php else: ?>
                    <form method="post" class="row g-3 needs-validation" novalidate data-confirm="Save this status update? A new tracking event will be added.">
                        <?= csrf_field() ?>
                        <div class="col-md-4">
                            <label class="form-label">New status *</label>
                            <select name="status" class="form-select" required>
                                <option value="">Select…</option>
                                <?php foreach (ALLOWED_TRANSITIONS[$parcel['current_status']] as $s): ?>
                                    <option <?= $form['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Choose a status.</div>
                            <div class="form-text">Only valid next steps are listed.</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Current location *</label>
                            <input name="location" class="form-control" value="<?= e($form['location']) ?>" placeholder="e.g. Hyderabad Sorting Centre" minlength="2" maxlength="120" required>
                            <div class="invalid-feedback">Enter the location.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <input name="remarks" class="form-control" value="<?= e($form['remarks']) ?>" maxlength="255" placeholder="Optional note shown on the tracking page">
                        </div>
                        <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="small text-muted">Current: <b><?= e($parcel['current_status']) ?></b><?= $history ? ' at ' . e($history[0]['location']) : '' ?></span>
                            <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Save update</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Details -->
        <div class="card-x mb-4">
            <div class="card-head"><h6>Shipment details</h6><span class="small text-muted">Booked <?= e(fmt_date($parcel['booking_date'], true)) ?></span></div>
            <div class="card-body-x">
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
                    <div><div class="k">Customer</div><div class="v"><a href="<?= e(url('admin/parcels.php?customer_id=' . $parcel['customer_id'])) ?>"><?= e($parcel['customer_name']) ?></a></div><div class="small text-muted"><?= e($parcel['customer_email']) ?></div></div>
                    <div><div class="k">Parcel type</div><div class="v"><?= e($parcel['parcel_type']) ?></div></div>
                    <div><div class="k">Weight</div><div class="v"><?= e((float)$parcel['weight_kg']) ?> kg</div></div>
                    <div><div class="k">Expected delivery</div><div class="v"><?= e(fmt_date($parcel['expected_delivery_date'])) ?></div></div>
                    <div><div class="k">Charge</div><div class="v"><?= money($parcel['delivery_charge']) ?></div></div>
                    <div><div class="k">Payment</div><div class="v"><span class="pay-badge pay-<?= strtolower($parcel['payment_status']) ?>"><?= e($parcel['payment_status']) ?></span></div></div>
                </div>
                <?php if ($payments): ?>
                    <div class="table-responsive mt-3">
                        <table class="table table-x table-sm border rounded">
                            <thead><tr><th>Transaction ref</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody><?php foreach ($payments as $pay): ?>
                                <tr><td class="small tid"><?= e($pay['transaction_ref']) ?></td><td class="small"><?= e($pay['payment_method']) ?></td><td><?= money($pay['amount']) ?></td>
                                    <td><span class="pay-badge pay-<?= strtolower($pay['payment_status']) ?>"><?= e($pay['payment_status']) ?></span></td><td class="small"><?= e(fmt_date($pay['payment_date'], true)) ?></td></tr>
                            <?php endforeach; ?></tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-x">
            <div class="card-head"><h6><i class="bi bi-clock-history"></i> Complete tracking history</h6><span class="small text-muted"><?= count($history) ?> event(s)</span></div>
            <div class="card-body-x"><?php require __DIR__ . '/../includes/timeline.php'; ?></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-x">
            <div class="card-head"><h6><i class="bi bi-qr-code"></i> Parcel QR code</h6></div>
            <div class="card-body-x"><?php require __DIR__ . '/../includes/qr_card.php'; ?></div>
        </div>
        <div class="card-x mt-4" style="border-color:#fecaca">
            <div class="card-head"><h6 class="text-danger"><i class="bi bi-trash"></i> Delete parcel</h6></div>
            <div class="card-body-x">
                <p class="small text-muted">Removes this parcel. MySQL also deletes its <?= count($history) ?> tracking event(s) and <?= count($payments) ?> payment(s) automatically (<code>ON DELETE CASCADE</code>).</p>
                <form method="post" action="<?= e(url('admin/delete.php')) ?>" data-confirm="Delete parcel <?= e($parcel['tracking_id']) ?> and all its tracking history and payments? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="type" value="parcel">
                    <input type="hidden" name="id" value="<?= (int)$parcel['parcel_id'] ?>">
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> Delete parcel</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
