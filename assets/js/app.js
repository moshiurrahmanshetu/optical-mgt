/**
 * Optical Shop Management CMS (optical-mgt)
 * Main JavaScript Application Script
 */

document.addEventListener('DOMContentLoaded', () => {
    // --------------------------------------------------------------------------
    // 1. Sidebar Collapsed State Persistence (Desktop)
    // --------------------------------------------------------------------------
    const STORAGE_KEY = 'optical_sidebar_collapsed';
    const isCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';

    // Apply saved state on desktop screen widths
    if (window.innerWidth >= 992 && isCollapsed) {
        document.body.classList.add('sidebar-collapsed');
    }

    // Toggle Desktop Sidebar
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();

            if (window.innerWidth < 992) {
                // Mobile behavior: toggle drawer
                document.body.classList.toggle('sidebar-mobile-open');
            } else {
                // Desktop behavior: toggle collapsed state
                document.body.classList.toggle('sidebar-collapsed');
                const currentlyCollapsed = document.body.classList.contains('sidebar-collapsed');
                localStorage.setItem(STORAGE_KEY, currentlyCollapsed ? 'true' : 'false');
                updateTooltips();
            }
        });
    }

    // Mobile Sidebar Backdrop Close
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', () => {
            document.body.classList.remove('sidebar-mobile-open');
        });
    }

    // Close mobile drawer on ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && document.body.classList.contains('sidebar-mobile-open')) {
            document.body.classList.remove('sidebar-mobile-open');
        }
    });

    // --------------------------------------------------------------------------
    // 2. Initialize Bootstrap Tooltips
    // --------------------------------------------------------------------------
    let tooltipInstances = [];

    function updateTooltips() {
        // Destroy existing
        tooltipInstances.forEach(instance => instance.dispose());
        tooltipInstances = [];

        // Re-initialize for all elements with data-bs-toggle="tooltip"
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipInstances = tooltipTriggerList.map(tooltipTriggerEl => {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover',
                boundary: 'window'
            });
        });
    }

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        updateTooltips();
    }

    // --------------------------------------------------------------------------
    // 3. Avatar Upload Instant Client-side Preview
    // --------------------------------------------------------------------------
    const avatarInput = document.getElementById('avatar-input');
    const avatarPreview = document.getElementById('avatar-preview');

    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                // Validate file size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size exceeds 2MB limit. Please choose a smaller image.');
                    this.value = '';
                    return;
                }

                // Validate image type
                const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Invalid file format. Please upload JPG, PNG, or WebP image.');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    avatarPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // --------------------------------------------------------------------------
    // 4. Auto-dismiss Flash Alerts (After 6 Seconds)
    // --------------------------------------------------------------------------
    const autoAlerts = document.querySelectorAll('.alert-dismissible');
    autoAlerts.forEach(alertEl => {
        setTimeout(() => {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
                if (bsAlert) {
                    bsAlert.close();
                }
            }
        }, 6000);
    });
});
