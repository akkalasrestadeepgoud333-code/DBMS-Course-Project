<?php
/*
 * ------------------------------------------------------------------
 *  Application configuration – edit this file only.
 * ------------------------------------------------------------------
 */

// ---- Database (XAMPP default: user "root", empty password) ----
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'courier_management');
// Ports tried in order. XAMPP normally uses 3306; some installs use 3307.
define('DB_PORTS', [3306, 3307]);

// ---- Public base URL used inside QR codes ----
// Leave empty ('') to auto-detect. When you open the site on "localhost",
// the QR code automatically uses this computer's Wi-Fi/LAN IP so that a
// phone on the same Wi-Fi can open it.
// To force a specific address, set it, e.g. 'http://192.168.1.10/courier'
define('QR_BASE_URL', '');

// ---- Delivery charge rules (in Rupees) ----
define('BASE_CHARGE', 50);       // flat booking charge
define('PER_KG_CHARGE', 20);     // per kilogram
define('TYPE_SURCHARGE', [       // extra handling charge per parcel type
    'Document'    => 0,
    'Package'     => 0,
    'Electronics' => 30,
    'Fragile'     => 40,
    'Other'       => 0,
]);
define('MAX_WEIGHT_KG', 50);

// ---- Parcel statuses (must match the ENUM in the database) ----
define('PARCEL_STATUSES', ['Booked', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered', 'Failed', 'Returned']);
define('FINAL_STATUSES', ['Delivered', 'Returned']);
define('ACTIVE_STATUSES', ['Booked', 'Picked Up', 'In Transit', 'Out for Delivery']);
// Which new statuses an admin may choose from each current status.
// "In Transit -> In Transit" allows recording arrival at another hub.
define('ALLOWED_TRANSITIONS', [
    'Booked'           => ['Picked Up', 'Failed'],
    'Picked Up'        => ['In Transit', 'Failed'],
    'In Transit'       => ['In Transit', 'Out for Delivery', 'Failed'],
    'Out for Delivery' => ['Delivered', 'Failed'],
    'Failed'           => ['In Transit', 'Out for Delivery', 'Returned'],
    'Delivered'        => [],
    'Returned'         => [],
]);

define('PAYMENT_METHODS', ['Cash on Pickup', 'UPI', 'Card', 'Net Banking']);

// Show detailed PHP/SQL errors? Keep false for demos.
define('DEBUG', false);

define('APP_NAME', 'SwiftCourier');
date_default_timezone_set('Asia/Kolkata');
