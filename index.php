<?php
require_once __DIR__ . '/includes/init.php';

// Live numbers from MySQL for the hero panel
$stats = db_one($conn, "SELECT COUNT(*) AS total,
                               SUM(current_status = 'Delivered') AS delivered,
                               SUM(current_status IN ('Booked','Picked Up','In Transit','Out for Delivery')) AS active
                        FROM parcels");

$pageTitle = 'Home';
$activeNav = 'home';
$noContainer = true;
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="container">
        <?= render_flash() ?>
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="eyebrow"><i class="bi bi-lightning-charge-fill"></i> Book in minutes · Track in real time</span>
                <h1 class="mt-3 mb-3">Courier Parcel Booking &amp; Tracking</h1>
                <p class="lead mb-4">Book a parcel online, get an instant tracking ID and QR code, and follow every step of its journey — from pickup to doorstep.</p>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="<?= e(url(current_user() && !is_admin() ? 'book_parcel.php' : (is_admin() ? 'admin/index.php' : 'login.php'))) ?>" class="btn btn-primary btn-lg px-4"><i class="bi bi-plus-circle"></i> Book a Parcel</a>
                    <a href="<?= e(url('track.php')) ?>" class="btn btn-outline-primary btn-lg px-4"><i class="bi bi-geo-alt"></i> Track Parcel</a>
                </div>
                <form action="<?= e(url('track.php')) ?>" method="get" class="track-box" style="max-width:560px">
                    <input type="text" name="tracking_id" class="form-control" placeholder="Enter tracking ID, e.g. CR202610010001" maxlength="14" required aria-label="Tracking ID">
                    <button class="btn btn-primary px-4" type="submit"><i class="bi bi-search"></i> Track</button>
                </form>
            </div>
            <div class="col-lg-5">
                <div class="hero-visual">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0">Live network status</h6>
                        <span class="small text-muted"><i class="bi bi-database"></i> from MySQL</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-4"><div class="stat-icon bg-soft-blue mb-2"><i class="bi bi-boxes"></i></div><div class="stat-value"><?= (int)$stats['total'] ?></div><div class="stat-label">Parcels booked</div></div>
                        <div class="col-4"><div class="stat-icon bg-soft-amber mb-2"><i class="bi bi-truck"></i></div><div class="stat-value"><?= (int)$stats['active'] ?></div><div class="stat-label">On the move</div></div>
                        <div class="col-4"><div class="stat-icon bg-soft-green mb-2"><i class="bi bi-house-check"></i></div><div class="stat-value"><?= (int)$stats['delivered'] ?></div><div class="stat-label">Delivered</div></div>
                    </div>
                    <hr>
                    <div class="small text-muted"><i class="bi bi-qr-code"></i> Every parcel gets a QR code — scan it with any phone camera to open live tracking.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container py-5">
    <div class="text-center mb-4">
        <h2 class="fw-bold">How it works</h2>
        <p class="text-muted">Four simple steps from booking to delivery.</p>
    </div>
    <div class="steps">
        <div class="step"><div class="num bg-soft-blue"><i class="bi bi-pencil-square"></i></div><h6>1. Book</h6><p class="small text-muted mb-0">Enter sender, receiver and parcel details.</p></div>
        <div class="step"><div class="num bg-soft-violet"><i class="bi bi-upc-scan"></i></div><h6>2. Get Tracking ID</h6><p class="small text-muted mb-0">A unique ID and QR code are generated instantly.</p></div>
        <div class="step"><div class="num bg-soft-amber"><i class="bi bi-qr-code-scan"></i></div><h6>3. Track / Scan QR</h6><p class="small text-muted mb-0">Follow live status updates or scan the QR.</p></div>
        <div class="step"><div class="num bg-soft-green"><i class="bi bi-house-check"></i></div><h6>4. Delivery</h6><p class="small text-muted mb-0">Parcel reaches the receiver's doorstep.</p></div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
