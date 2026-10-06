<?php
/*
 * Admin delete actions (POST only).
 *  - Parcel:   DELETE FROM parcels → MySQL cascades to tracking_history and payments (ON DELETE CASCADE).
 *  - Customer: DELETE FROM users   → MySQL refuses if the customer still has parcels (RESTRICT, error 1451).
 */
require_once __DIR__ . '/../includes/init.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    flash('danger', 'Invalid request. Please try again.');
    redirect('admin/index.php');
}

$type = $_POST['type'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if ($type === 'parcel') {
    $parcel = db_one($conn, 'SELECT tracking_id,
            (SELECT COUNT(*) FROM tracking_history WHERE parcel_id = p.parcel_id) AS events,
            (SELECT COUNT(*) FROM payments WHERE parcel_id = p.parcel_id) AS pays
        FROM parcels p WHERE parcel_id = ?', 'i', [$id]);
    if (!$parcel) {
        flash('danger', 'Parcel not found.');
        redirect('admin/parcels.php');
    }
    $stmt = $conn->prepare('DELETE FROM parcels WHERE parcel_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    flash('success', 'Deleted parcel ' . $parcel['tracking_id'] . '. MySQL also removed its ' . $parcel['events']
        . ' tracking event(s) and ' . $parcel['pays'] . ' payment(s) automatically (ON DELETE CASCADE).');
    redirect('admin/parcels.php');
}

if ($type === 'customer') {
    $user = db_one($conn, "SELECT name FROM users WHERE user_id = ? AND role = 'customer'", 'i', [$id]);
    if (!$user) {
        flash('danger', 'Customer not found.');
        redirect('admin/customers.php');
    }
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'customer'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        flash('success', 'Deleted customer ' . $user['name'] . '.');
    } catch (mysqli_sql_exception $ex) {
        if ($ex->getCode() !== 1451) {
            throw $ex;
        }
        $n = db_one($conn, 'SELECT COUNT(*) AS n FROM parcels WHERE customer_id = ?', 'i', [$id])['n'];
        flash('danger', 'MySQL refused to delete ' . $user['name'] . ': they still have ' . $n
            . ' parcel(s). The foreign key parcels.customer_id is ON DELETE RESTRICT (error 1451), so booking records cannot be orphaned.');
    }
    redirect('admin/customers.php');
}

flash('danger', 'Unknown delete request.');
redirect('admin/index.php');
