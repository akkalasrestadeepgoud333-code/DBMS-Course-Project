<?php
/*
 * PUBLIC tracking page – no login required. This is the page the QR code opens.
 * All data is read live from MySQL (parcels + tracking_history).
 */
require_once __DIR__ . '/includes/init.php';

$tid = strtoupper(preg_replace('/\s+/', '', $_GET['tracking_id'] ?? ''));
$parcel = null;
$history = [];
$error = '';

if ($tid !== '') {
    if (!valid_tracking_id($tid)) {
        $error = 'That does not look like a valid tracking ID. It should look like CR202610050001.';
    } else {
        $parcel = db_one($conn, 'SELECT parcel_id, tracking_id, sender_name, sender_city, receiver_name, receiver_city,
                parcel_type, weight_kg, booking_date, expected_delivery_date, current_status
            FROM parcels WHERE tracking_id = ?', 's', [$tid]);
        if (!$parcel) {
            $error = 'No parcel found with tracking ID ' . $tid . '. Please check and try again.';
        } else {
            $history = db_all($conn, 'SELECT status, location, remarks, created_at FROM tracking_history
                                      WHERE parcel_id = ? ORDER BY created_at DESC, history_id DESC', 'i', [$parcel['parcel_id']]);
        }
    }
}

// Progress stepper for the main delivery flow
$flow = ['Booked', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered'];
$pageTitle = $parcel ? 'Tracking ' . $parcel['tracking_id'] : 'Track Parcel';
$activeNav = 'track';
require __DIR__ . '/includes/header.php';
?>
<div class="mx-auto" style="max-width:860px">
    <div class="text-center mb-3">
        <h2 class="page-title">Track your parcel</h2>
        <p class="text-muted mb-3">Enter the tracking ID from your booking receipt or scan the parcel QR code.</p>
    </div>
    <form method="get" class="track-box mb-4">
        <input type="text" name="tracking_id" class="form-control" value="<?= e($tid) ?>" placeholder="e.g. CR202610010001" maxlength="20" required aria-label="Tracking ID">
        <button class="btn btn-primary px-4" type="submit"><i class="bi bi-search"></i> Track</button>
    </form>

    <?php if ($error): ?>
        <div class="alert alert-warning d-flex gap-2"><i class="bi bi-exclamation-triangle"></i><div><?= e($error) ?></div></div>
    <?php endif; ?>

    <?php if ($parcel):
        $status = $parcel['current_status'];
        $latest = $history[0] ?? null;
        $stepIndex = array_search($status, $flow, true);
        if ($stepIndex === false) { // Failed / Returned: show progress up to the last normal step reached
            $stepIndex = 0;
            foreach ($history as $h) {
                $i = array_search($h['status'], $flow, true);
                if ($i !== false && $i > $stepIndex) $stepIndex = $i;
            }
        }
        $deliveredAt = null;
        foreach ($history as $h) { if ($h['status'] === 'Delivered') { $deliveredAt = $h['created_at']; break; } }
        $pct = $stepIndex / (count($flow) - 1) * 84; // bar spans 8%..92%
    ?>
        <div class="card-x mb-4">
            <div class="card-body-x">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <div class="small text-muted">Tracking ID</div>
                        <div class="tid fs-4"><?= e($parcel['tracking_id']) ?></div>
                    </div>
                    <div class="text-end">
                        <?= status_badge($status) ?>
                        <div class="small text-muted mt-1">Updated <?= e(fmt_date($latest['created_at'] ?? $parcel['booking_date'], true)) ?></div>
                    </div>
                </div>

                <?php if ($status === 'Delivered'): ?>
                    <div class="alert alert-success py-2 mb-3"><i class="bi bi-house-check"></i> Delivered on <b><?= e(fmt_date($deliveredAt, true)) ?></b></div>
                <?php elseif ($status === 'Failed'): ?>
                    <div class="alert alert-danger py-2 mb-3"><i class="bi bi-x-octagon"></i> Delivery attempt failed<?= $latest && $latest['remarks'] ? ': ' . e($latest['remarks']) : '' ?>.</div>
                <?php elseif ($status === 'Returned'): ?>
                    <div class="alert alert-secondary py-2 mb-3"><i class="bi bi-arrow-return-left"></i> This parcel was returned to the sender.</div>
                <?php else: ?>
                    <div class="alert alert-primary py-2 mb-3" style="background:var(--brand-soft);border-color:#c7d7fe;color:var(--brand-dark)"><i class="bi bi-calendar-check"></i> Expected delivery by <b><?= e(fmt_date($parcel['expected_delivery_date'])) ?></b></div>
                <?php endif; ?>

                <div class="progress-steps mb-2">
                    <div class="bar" style="width:<?= round($pct, 1) ?>%"></div>
                    <?php foreach ($flow as $i => $s): ?>
                        <div class="ps <?= $i <= $stepIndex ? 'done' : '' ?>"><div class="c"><i class="bi bi-<?= status_icon($s) ?>"></i></div><?= e($s) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card-x h-100">
                    <div class="card-head"><h6><i class="bi bi-box-seam"></i> Shipment</h6></div>
                    <div class="card-body-x">
                        <div class="route mb-4">
                            <div><div class="small text-muted">Origin</div><div class="city"><?= e($parcel['sender_city']) ?></div></div>
                            <div class="line"><i class="bi bi-truck"></i></div>
                            <div class="text-end"><div class="small text-muted">Destination</div><div class="city"><?= e($parcel['receiver_city']) ?></div></div>
                        </div>
                        <div class="kv">
                            <div><div class="k">Sender</div><div class="v"><?= e($parcel['sender_name']) ?></div></div>
                            <div><div class="k">Receiver</div><div class="v"><?= e($parcel['receiver_name']) ?></div></div>
                            <div><div class="k">Parcel type</div><div class="v"><?= e($parcel['parcel_type']) ?></div></div>
                            <div><div class="k">Weight</div><div class="v"><?= e((float)$parcel['weight_kg']) ?> kg</div></div>
                            <div><div class="k">Booking date</div><div class="v"><?= e(fmt_date($parcel['booking_date'])) ?></div></div>
                            <div><div class="k">Expected delivery</div><div class="v"><?= e(fmt_date($parcel['expected_delivery_date'])) ?></div></div>
                            <div><div class="k">Current location</div><div class="v"><?= e($latest['location'] ?? $parcel['sender_city']) ?></div></div>
                            <div><div class="k">Delivery</div><div class="v"><?= $deliveredAt ? 'Delivered ' . e(fmt_date($deliveredAt)) : ($status === 'Returned' ? 'Returned to sender' : 'To ' . e($parcel['receiver_city'])) ?></div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card-x h-100">
                    <div class="card-head"><h6><i class="bi bi-clock-history"></i> Tracking history</h6>
                        <a href="<?= e(url('track.php?tracking_id=' . $parcel['tracking_id'])) ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-clockwise"></i> Refresh</a></div>
                    <div class="card-body-x"><?php require __DIR__ . '/includes/timeline.php'; ?></div>
                </div>
            </div>
        </div>
    <?php elseif ($tid === ''): ?>
        <div class="card-x"><div class="empty-state"><i class="bi bi-qr-code-scan"></i>Tip: scanning a parcel's QR code opens this page with the tracking details already filled in.</div></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
