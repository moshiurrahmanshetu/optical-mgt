/**
 * Optical Shop Management CMS (optical-mgt)
 * Installer Client-Side Helpers
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. SQL Source Selection Toggling
    const defaultSqlRadio = document.getElementById('sourceDefault');
    const customSqlRadio = document.getElementById('sourceUpload');
    const customUploadContainer = document.getElementById('customUploadContainer');
    const cardDefault = document.getElementById('cardDefault');
    const cardUpload = document.getElementById('cardUpload');

    function updateSqlSourceSelection() {
        if (!defaultSqlRadio || !customSqlRadio) return;

        if (customSqlRadio.checked) {
            if (customUploadContainer) customUploadContainer.style.display = 'block';
            if (cardUpload) cardUpload.classList.add('selected');
            if (cardDefault) cardDefault.classList.remove('selected');
        } else {
            if (customUploadContainer) customUploadContainer.style.display = 'none';
            if (cardDefault) cardDefault.classList.add('selected');
            if (cardUpload) cardUpload.classList.remove('selected');
        }
    }

    if (defaultSqlRadio && customSqlRadio) {
        defaultSqlRadio.addEventListener('change', updateSqlSourceSelection);
        customSqlRadio.addEventListener('change', updateSqlSourceSelection);
        updateSqlSourceSelection();
    }

    // 2. Submit Button Loading State
    const installForms = document.querySelectorAll('.installer-form');
    installForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...';
                
                // Allow form to submit normally
                setTimeout(function() {
                    form.submit();
                }, 50);
            }
        });
    });

    // 3. Password Visibility Toggle
    const toggleBtns = document.querySelectorAll('.toggle-password-btn');
    toggleBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            if (targetInput) {
                if (targetInput.type === 'password') {
                    targetInput.type = 'text';
                    this.innerHTML = '<i class="bi bi-eye-slash"></i>';
                } else {
                    targetInput.type = 'password';
                    this.innerHTML = '<i class="bi bi-eye"></i>';
                }
            }
        });
    });
});
