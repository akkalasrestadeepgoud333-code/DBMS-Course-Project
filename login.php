<?php
require_once __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect(is_admin() ? 'admin/index.php' : 'dashboard.php');
}

$email = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $user = db_one($conn, 'SELECT user_id, name, email, phone, password_hash, role FROM users WHERE email = ?', 's', [$email]);
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true); // prevent session fixation
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            $next = $_SESSION['after_login'] ?? '';
            unset($_SESSION['after_login']);
            flash('success', 'Welcome back, ' . $user['name'] . '!');
            // Only follow the saved URL if it is a local path inside this app and suits the role
            if ($next !== '' && str_starts_with($next, app_path() . '/') && !str_contains($next, '//')
                && (str_contains($next, '/admin/') === ($user['role'] === 'admin'))) {
                header('Location: ' . $next);
                exit;
            }
            redirect($user['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php');
        }
        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Login';
$activeNav = 'login';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-card card-x">
        <div class="card-body-x p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="brand-mark mb-2" style="width:48px;height:48px;font-size:1.3rem"><i class="bi bi-box-seam-fill"></i></span>
                <h3 class="fw-bold mb-1">Welcome back</h3>
                <p class="text-muted mb-0">Log in to book and manage your parcels</p>
            </div>
            <?php if ($error): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-octagon"></i> <?= e($error) ?></div><?php endif; ?>
            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus>
                    <div class="invalid-feedback">Enter a valid email.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                    <div class="invalid-feedback">Enter your password.</div>
                </div>
                <button class="btn btn-primary w-100 py-2" type="submit">Log in</button>
            </form>
            <p class="text-center mt-3 mb-4 small">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
            <div class="demo-box">
                <div class="fw-semibold mb-1"><i class="bi bi-info-circle"></i> Demo accounts</div>
                Admin: <code>admin@courier.com</code> / <code>admin123</code><br>
                Customer: <code>customer@courier.com</code> / <code>customer123</code>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
