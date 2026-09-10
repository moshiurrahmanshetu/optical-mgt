<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Common Footer Include
 */
?>
    </main>

    <!-- Footer Bar -->
    <footer class="py-3 px-4 bg-white border-top text-muted small d-flex justify-content-between align-items-center">
      <span>&copy; <?= date('Y'); ?> <strong><?= e(APP_NAME); ?></strong>. All rights reserved.</span>
      <span>Version <?= e(APP_VERSION); ?></span>
    </footer>
  </div><!-- /.app-main -->
</div><!-- /.app-wrapper -->

<!-- Mobile Sidebar Backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- Bootstrap 5 Bundle JS (Includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Main App JavaScript -->
<script src="<?= ASSETS_URL; ?>js/app.js?v=<?= APP_VERSION; ?>"></script>
</body>
</html>
