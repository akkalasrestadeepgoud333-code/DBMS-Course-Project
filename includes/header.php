<?php
/** Shared page header + navbar. Set $pageTitle (and optionally $activeNav) before including. */
$user = current_user();
$activeNav = $activeNav ?? '';
function nav_item(string $href, string $label, string $icon, string $key, string $active): string
{
    $cls = $key === $active ? ' active' : '';
    return '<li class="nav-item"><a class="nav-link' . $cls . '" href="' . e(url($href)) . '"><i class="bi bi-' . $icon . '"></i> ' . e($label) . '</a></li>';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($pageTitle ?? 'Home') . ' · ' . APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<nav class="navbar navbar-expand-lg app-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url($user ? (is_admin() ? 'admin/index.php' : 'dashboard.php') : 'index.php')) ?>">
            <span class="brand-mark"><i class="bi bi-box-seam-fill"></i></span> <?= e(APP_NAME) ?>
            <?php if (is_admin()): ?><span class="badge text-bg-warning ms-1 small">Admin</span><?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <?php if (!$user): ?>
                    <?= nav_item('index.php', 'Home', 'house', 'home', $activeNav) ?>
                    <?= nav_item('track.php', 'Track Parcel', 'search', 'track', $activeNav) ?>
                    <?= nav_item('login.php', 'Login', 'box-arrow-in-right', 'login', $activeNav) ?>
                    <li class="nav-item ms-lg-2"><a class="btn btn-primary btn-sm px-3" href="<?= e(url('register.php')) ?>">Create account</a></li>
                <?php elseif (is_admin()): ?>
                    <?= nav_item('admin/index.php', 'Dashboard', 'speedometer2', 'admin-dash', $activeNav) ?>
                    <?= nav_item('admin/parcels.php', 'Parcels', 'boxes', 'admin-parcels', $activeNav) ?>
                    <?= nav_item('admin/customers.php', 'Customers', 'people', 'admin-customers', $activeNav) ?>
                    <?= nav_item('track.php', 'Public Tracking', 'search', 'track', $activeNav) ?>
                <?php else: ?>
                    <?= nav_item('dashboard.php', 'Dashboard', 'speedometer2', 'dash', $activeNav) ?>
                    <?= nav_item('book_parcel.php', 'Book Parcel', 'plus-circle', 'book', $activeNav) ?>
                    <?= nav_item('my_parcels.php', 'My Parcels', 'boxes', 'parcels', $activeNav) ?>
                    <?= nav_item('track.php', 'Track', 'search', 'track', $activeNav) ?>
                <?php endif; ?>
                <?php if ($user): ?>
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle user-chip" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span> <?= e(explode(' ', $user['name'])[0]) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><span class="dropdown-item-text small text-muted"><?= e($user['email']) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= e(url('logout.php?token=' . csrf_token())) ?>"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="app-main">
<?php if (empty($noContainer)): ?><div class="container py-4"><?= render_flash() ?><?php endif; ?>
