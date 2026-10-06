<?php
require_once __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect(is_admin() ? 'admin/index.php' : 'dashboard.php');
}

$name = $email = $phone = '';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = clean_phone($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!csrf_valid())                                   $errors[] = 'Your session expired. Please try again.';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100)  $errors[] = 'Name must be 2–100 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))       $errors[] = 'Enter a valid email address.';
    if (!valid_phone($phone))                            $errors[] = 'Enter a valid phone number (10–13 digits).';
    if (strlen($password) < 6)                           $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)                          $errors[] = 'Passwords do not match.';

    if (!$errors && db_one($conn, 'SELECT user_id FROM users WHERE email = ?', 's', [$email])) {
        $errors[] = 'An account with this email already exists. Please log in.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')");
        $stmt->bind_param('ssss', $name, $email, $phone, $hash);
        try {
            $stmt->execute();
            session_regenerate_id(true);
            $_SESSION['user'] = ['user_id' => $conn->insert_id, 'name' => $name, 'email' => $email, 'phone' => $phone, 'role' => 'customer'];
            flash('success', 'Account created successfully. Welcome, ' . $name . '!');
            redirect('dashboard.php');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) { // duplicate key (UNIQUE email)
                $errors[] = 'An account with this email already exists. Please log in.';
            } else {
                throw $ex;
            }
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
    <div class="auth-card card-x" style="max-width:500px">
        <div class="card-body-x p-4 p-md-5">
            <div class="text-center mb-4">
                <h3 class="fw-bold mb-1">Create your account</h3>
                <p class="text-muted mb-0">Start booking and tracking parcels</p>
            </div>
            <?php if ($errors): ?>
                <div class="alert alert-danger py-2"><ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="name">Full name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= e($name) ?>" minlength="2" maxlength="100" required>
                    <div class="invalid-feedback">Enter your name.</div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" maxlength="150" required>
                        <div class="invalid-feedback">Enter a valid email.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($phone) ?>" pattern="\+?[0-9\s\-]{10,16}" required>
                        <div class="invalid-feedback">10–13 digit number.</div>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" minlength="6" required>
                        <div class="invalid-feedback">At least 6 characters.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="confirm_password">Confirm password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                </div>
                <button class="btn btn-primary w-100 py-2" type="submit">Create account</button>
            </form>
            <p class="text-center mt-3 mb-0 small">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
