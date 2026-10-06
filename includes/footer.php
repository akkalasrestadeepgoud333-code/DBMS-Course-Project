<?php if (empty($noContainer)): ?></div><?php endif; ?>
</main>
<footer class="app-footer">
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-2 small">
        <span><i class="bi bi-box-seam-fill text-primary"></i> <?= e(APP_NAME) ?> — Courier Parcel Booking &amp; Tracking Management System</span>
        <span class="text-muted">DBMS Project · PHP + MySQL</span>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/js/qrcode.min.js')) ?>"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
