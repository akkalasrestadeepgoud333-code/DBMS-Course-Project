<?php
/*
 * Loaded at the top of every page: settings, session, DB connection, helpers.
 */
require_once __DIR__ . '/../config/config.php';

ini_set('display_errors', DEBUG ? '1' : '0');
error_reporting(E_ALL);

// Never show raw PHP/SQL errors to users – log them and show a friendly page.
set_exception_handler(function (Throwable $e) {
    error_log('[Courier] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (DEBUG) {
        echo '<pre>' . htmlspecialchars((string)$e) . '</pre>';
    } else {
        echo '<div style="font-family:sans-serif;max-width:520px;margin:80px auto;padding:24px;border:1px solid #e5e7eb;border-radius:12px">'
            . '<h2 style="margin-top:0">Something went wrong</h2><p>We could not complete your request. Please go back and try again.</p>'
            . '<a href="javascript:history.back()">&larr; Go back</a></div>';
    }
    exit;
});

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
