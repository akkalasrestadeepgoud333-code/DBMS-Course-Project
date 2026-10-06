<?php
require_once __DIR__ . '/includes/init.php';
require_customer();

$user = current_user();
$types = array_keys(TYPE_SURCHARGE);
$f = array_fill_keys(['sender_name', 'sender_phone', 'sender_address', 'sender_city', 'receiver_name', 'receiver_phone',
    'receiver_address', 'receiver_city', 'parcel_type', 'weight', 'expected_delivery_date', 'payment_method'], '');
$errors = [];
$minDate = date('Y-m-d', strtotime('+1 day'));
$maxDate = date('Y-m-d', strtotime('+60 days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) {
        $f[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $f['sender_phone']   = clean_phone($f['sender_phone']);
    $f['receiver_phone'] = clean_phone($f['receiver_phone']);

    // ---- Validation ----
    if (!csrf_valid()) $errors[] = 'Your session expired. Please submit the form again.';
    foreach (['sender_name' => 'Sender name', 'receiver_name' => 'Receiver name', 'sender_city' => 'Sender city', 'receiver_city' => 'Receiver city'] as $k => $label) {
        if (mb_strlen($f[$k]) < 2 || mb_strlen($f[$k]) > ($k === 'sender_city' || $k === 'receiver_city' ? 60 : 100)) $errors[] = "$label is required (2–100 characters).";
    }
    foreach (['sender_address' => 'Sender address', 'receiver_address' => 'Receiver address'] as $k => $label) {
        if (mb_strlen($f[$k]) < 5 || mb_strlen($f[$k]) > 255) $errors[] = "$label must be 5–255 characters.";
    }
    if (!valid_phone($f['sender_phone']))   $errors[] = 'Sender phone must be a valid 10–13 digit number.';
    if (!valid_phone($f['receiver_phone'])) $errors[] = 'Receiver phone must be a valid 10–13 digit number.';
    if (!in_array($f['parcel_type'], $types, true)) $errors[] = 'Choose a valid parcel type.';
    if (!in_array($f['payment_method'], PAYMENT_METHODS, true)) $errors[] = 'Choose a payment method.';
    $weight = filter_var($f['weight'], FILTER_VALIDATE_FLOAT);
    if ($weight === false || $weight <= 0 || $weight > MAX_WEIGHT_KG) $errors[] = 'Weight must be between 0.01 and ' . MAX_WEIGHT_KG . ' kg.';
    $d = DateTime::createFromFormat('Y-m-d', $f['expected_delivery_date']);
    if (!$d || $d->format('Y-m-d') !== $f['expected_delivery_date'] || $f['expected_delivery_date'] < $minDate || $f['expected_delivery_date'] > $maxDate) {
        $errors[] = 'Expected delivery date must be between ' . fmt_date($minDate) . ' and ' . fmt_date($maxDate) . '.';
    }

    if (!$errors) {
        $weight = round($weight, 2);
        $charge = calculate_charge($weight, $f['parcel_type']);   // always recalculated on the server
        $isCash = $f['payment_method'] === 'Cash on Pickup';
        $payStatus = $isCash ? 'Pending' : 'Paid';                 // simulated payment – no real gateway
        $uid = (int)$user['user_id'];

        // ---- Transaction: parcel + first tracking event + payment, all or nothing ----
        $conn->begin_transaction();
        try {
            $trackingId = generate_tracking_id($conn);

            $stmt = $conn->prepare('INSERT INTO parcels (tracking_id, customer_id, sender_name, sender_phone, sender_address, sender_city,
                    receiver_name, receiver_phone, receiver_address, receiver_city, parcel_type, weight_kg, expected_delivery_date,
                    delivery_charge, payment_status, current_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'Booked\')');
            $stmt->bind_param('sisssssssssdsds', $trackingId, $uid, $f['sender_name'], $f['sender_phone'], $f['sender_address'], $f['sender_city'],
                $f['receiver_name'], $f['receiver_phone'], $f['receiver_address'], $f['receiver_city'], $f['parcel_type'], $weight,
                $f['expected_delivery_date'], $charge, $payStatus);
            $stmt->execute();
            $parcelId = $conn->insert_id;

            $stmt = $conn->prepare("INSERT INTO tracking_history (parcel_id, status, location, remarks, updated_by) VALUES (?, 'Booked', ?, 'Parcel booked online', ?)");
            $stmt->bind_param('isi', $parcelId, $f['sender_city'], $uid);
            $stmt->execute();

            $txnRef = ($isCash ? 'COD' : 'TXN') . date('YmdHis') . str_pad((string)$parcelId, 4, '0', STR_PAD_LEFT);
            $stmt = $conn->prepare('INSERT INTO payments (parcel_id, amount, payment_method, payment_status, transaction_ref) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('idsss', $parcelId, $charge, $f['payment_method'], $payStatus, $txnRef);
            $stmt->execute();

            $conn->commit();
            flash('success', 'Parcel booked successfully! Your tracking ID is ' . $trackingId . '.');
            redirect('parcel_details.php?id=' . $parcelId . '&new=1');
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            error_log('[Courier] booking failed: ' . $ex->getMessage());
            $errors[] = 'We could not save your booking right now. Please try again.';
        }
    }
}

$pageTitle = 'Book a Parcel';
$activeNav = 'book';
require __DIR__ . '/includes/header.php';
?>
<div class="mb-4">
    <h2 class="page-title">Book a Parcel</h2>
    <p class="text-muted mb-0">Fill in the details below. You'll get a tracking ID and QR code instantly.</p>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><div class="fw-semibold mb-1">Please fix the following:</div><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" id="bookingForm" class="needs-validation" novalidate
      data-base="<?= BASE_CHARGE ?>" data-perkg="<?= PER_KG_CHARGE ?>" data-surcharge='<?= e(json_encode(TYPE_SURCHARGE)) ?>'>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card-x mb-4">
                <div class="card-head">
                    <h6><i class="bi bi-person-up text-primary"></i> Sender details</h6>
                    <div class="form-check small mb-0">
                        <input class="form-check-input" type="checkbox" id="fillSender" data-name="<?= e($user['name']) ?>" data-phone="<?= e($user['phone']) ?>">
                        <label class="form-check-label" for="fillSender">Use my details</label>
                    </div>
                </div>
                <div class="card-body-x row g-3">
                    <div class="col-md-6"><label class="form-label">Sender name *</label><input name="sender_name" class="form-control" value="<?= e($f['sender_name']) ?>" minlength="2" maxlength="100" required><div class="invalid-feedback">Required.</div></div>
                    <div class="col-md-6"><label class="form-label">Sender phone *</label><input type="tel" name="sender_phone" class="form-control" value="<?= e($f['sender_phone']) ?>" pattern="\+?[0-9\s\-]{10,16}" required><div class="invalid-feedback">10–13 digit number.</div></div>
                    <div class="col-md-8"><label class="form-label">Pickup address *</label><input name="sender_address" class="form-control" value="<?= e($f['sender_address']) ?>" minlength="5" maxlength="255" placeholder="House no., street, area" required><div class="invalid-feedback">At least 5 characters.</div></div>
                    <div class="col-md-4"><label class="form-label">City *</label><input name="sender_city" class="form-control" value="<?= e($f['sender_city']) ?>" minlength="2" maxlength="60" required><div class="invalid-feedback">Required.</div></div>
                </div>
            </div>
            <div class="card-x mb-4">
                <div class="card-head"><h6><i class="bi bi-person-down text-primary"></i> Receiver details</h6></div>
                <div class="card-body-x row g-3">
                    <div class="col-md-6"><label class="form-label">Receiver name *</label><input name="receiver_name" class="form-control" value="<?= e($f['receiver_name']) ?>" minlength="2" maxlength="100" required><div class="invalid-feedback">Required.</div></div>
                    <div class="col-md-6"><label class="form-label">Receiver phone *</label><input type="tel" name="receiver_phone" class="form-control" value="<?= e($f['receiver_phone']) ?>" pattern="\+?[0-9\s\-]{10,16}" required><div class="invalid-feedback">10–13 digit number.</div></div>
                    <div class="col-md-8"><label class="form-label">Delivery address *</label><input name="receiver_address" class="form-control" value="<?= e($f['receiver_address']) ?>" minlength="5" maxlength="255" placeholder="House no., street, area" required><div class="invalid-feedback">At least 5 characters.</div></div>
                    <div class="col-md-4"><label class="form-label">City *</label><input name="receiver_city" class="form-control" value="<?= e($f['receiver_city']) ?>" minlength="2" maxlength="60" required><div class="invalid-feedback">Required.</div></div>
                </div>
            </div>
            <div class="card-x">
                <div class="card-head"><h6><i class="bi bi-box-seam text-primary"></i> Parcel details</h6></div>
                <div class="card-body-x row g-3">
                    <div class="col-md-4"><label class="form-label">Parcel type *</label>
                        <select name="parcel_type" class="form-select" required>
                            <option value="">Select…</option>
                            <?php foreach ($types as $t): ?><option value="<?= e($t) ?>" <?= $f['parcel_type'] === $t ? 'selected' : '' ?>><?= e($t) ?><?= TYPE_SURCHARGE[$t] ? ' (+₹' . TYPE_SURCHARGE[$t] . ')' : '' ?></option><?php endforeach; ?>
                        </select><div class="invalid-feedback">Choose a type.</div></div>
                    <div class="col-md-4"><label class="form-label">Weight (kg) *</label><input type="number" name="weight" class="form-control" value="<?= e($f['weight']) ?>" step="0.01" min="0.01" max="<?= MAX_WEIGHT_KG ?>" required><div class="invalid-feedback">0.01 – <?= MAX_WEIGHT_KG ?> kg.</div></div>
                    <div class="col-md-4"><label class="form-label">Expected delivery *</label><input type="date" name="expected_delivery_date" class="form-control" value="<?= e($f['expected_delivery_date'] ?: date('Y-m-d', strtotime('+4 days'))) ?>" min="<?= $minDate ?>" max="<?= $maxDate ?>" required><div class="invalid-feedback">Choose a future date.</div></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-x" style="position:sticky;top:84px">
                <div class="card-head"><h6><i class="bi bi-receipt text-primary"></i> Charges</h6></div>
                <div class="card-body-x">
                    <div class="charge-box mb-3">
                        <div class="d-flex justify-content-between small mb-1"><span>Base charge</span><span>₹<?= number_format(BASE_CHARGE, 2) ?></span></div>
                        <div class="d-flex justify-content-between small mb-1"><span id="cWeightLbl">Weight</span><span id="cWeight">₹0.00</span></div>
                        <div class="d-flex justify-content-between small mb-2"><span>Type surcharge</span><span id="cType">₹0.00</span></div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center"><span class="fw-semibold">Total</span><span class="total" id="cTotal">₹0.00</span></div>
                    </div>
                    <p class="small text-muted">Charge = ₹<?= BASE_CHARGE ?> base + ₹<?= PER_KG_CHARGE ?>/kg + type surcharge. The server recalculates it when you book.</p>
                    <label class="form-label">Payment method *</label>
                    <select name="payment_method" class="form-select mb-2" required>
                        <?php foreach (PAYMENT_METHODS as $m): ?><option value="<?= e($m) ?>" <?= ($f['payment_method'] ?: 'UPI') === $m ? 'selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?>
                    </select>
                    <p class="small text-muted"><i class="bi bi-info-circle"></i> Simulated payment for this academic project: online methods are marked <b>Paid</b>; Cash on Pickup stays <b>Pending</b> until delivery.</p>
                    <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-check2-circle"></i> Confirm Booking</button>
                </div>
            </div>
        </div>
    </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
