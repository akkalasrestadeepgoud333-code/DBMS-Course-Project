<?php
require_once __DIR__ . '/includes/init.php';

// The logout link carries the CSRF token so other sites cannot log users out.
if (current_user() && hash_equals(csrf_token(), (string)($_GET['token'] ?? ''))) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
    flash('success', 'You have been logged out.');
}
redirect('index.php');
