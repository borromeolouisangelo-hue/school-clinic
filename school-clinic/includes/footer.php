<?php
/**
 * Legacy backward-compatibility wrapper for footer.
 * Provides the footer layout for old-style pages.
 * Requires that config/db.php has been included.
 */
?>
<footer class="shell-footer">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <small>&copy; <?= date('Y') ?> School Clinic IMS. All rights reserved.</small>
            </div>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
</body>
</html>