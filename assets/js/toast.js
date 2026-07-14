// Shared toast notification helper (root + sudo interfaces).
//
// Historically nearly every page defined its own toast function
// (showToast, showBootstrapToast, showCommentToast, showSuccess/showError,
// each with its own container id, position, and markup) so toasts looked
// and behaved differently from page to page. This is the one canonical
// implementation - all pages should use showToast(message, type) instead
// of defining their own.
function getToastContainer() {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    return container;
}

function showToast(message, type = 'info') {
    const typeMap = {
        success: 'bg-success',
        danger: 'bg-danger',
        error: 'bg-danger',
        warning: 'bg-warning',
        info: 'bg-info',
        primary: 'bg-primary'
    };
    const iconMap = {
        success: 'fas fa-check-circle',
        danger: 'fas fa-exclamation-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle',
        primary: 'fas fa-bell'
    };

    const bgClass = typeMap[type] || 'bg-info';
    const icon = iconMap[type] || 'fas fa-info-circle';
    const toastId = 'toast-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
    const container = getToastContainer();

    const el = document.createElement('div');
    el.id = toastId;
    el.className = `toast align-items-center text-white ${bgClass} border-0`;
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'assertive');
    el.setAttribute('aria-atomic', 'true');
    el.setAttribute('data-bs-autohide', 'true');
    el.setAttribute('data-bs-delay', '5000');
    el.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="${icon} me-2"></i>${toastEscapeHtml(message)}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;

    container.appendChild(el);

    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(el);
        bsToast.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    } else {
        // Fallback if Bootstrap JS hasn't loaded for some reason
        el.style.display = 'block';
        setTimeout(() => el.remove(), 5000);
    }
}

// Named distinctly from the many page-local escapeHtml() helpers so this
// file never collides with (or depends on) whatever a given page defines.
function toastEscapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}
