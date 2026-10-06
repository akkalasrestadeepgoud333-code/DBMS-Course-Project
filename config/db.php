<?php
/*
 * Database connection (MySQLi, object style).
 * Every query in the project uses prepared statements through $conn.
 */
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // throw exceptions on SQL errors

$conn = null;
foreach (DB_PORTS as $port) {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, $port);
        break;
    } catch (mysqli_sql_exception $e) {
        $conn = null; // try next port
    }
}

if (!$conn) {
    http_response_code(500);
    error_log('DB connection failed: ' . (isset($e) ? $e->getMessage() : 'unknown'));
    exit('<div style="font-family:sans-serif;max-width:560px;margin:80px auto;padding:24px;border:1px solid #e5e7eb;border-radius:12px">'
        . '<h2 style="margin-top:0">Database not available</h2>'
        . '<p>Could not connect to MySQL database <b>' . DB_NAME . '</b>.</p>'
        . '<ol><li>Start <b>MySQL</b> in the XAMPP Control Panel.</li>'
        . '<li>Import <code>database/courier_management.sql</code> in phpMyAdmin.</li>'
        . '<li>Check the settings in <code>config/config.php</code>.</li></ol></div>');
}

$conn->set_charset('utf8mb4');
// Strict mode: invalid ENUM values or too-long text are rejected instead of silently changed
$conn->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
$conn->query("SET time_zone = '+05:30'");
