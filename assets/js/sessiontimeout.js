// Session timeout warning system
class SessionTimeoutWarning {
    constructor() {
        this.sessionTimeout = 86400; // 24 hrs
        this.warningTime = 300; // Show warning 5 minutes before timeout
        this.countdownInterval = null;
        this.warningShown = false;
        this.checkInterval = null;
        this.actualCurrentPage = window.location.href;

        this.init();
    }

    init() {
        this.trackPageNavigation(); // Add this line

        this.checkInterval = setInterval(() => {
            this.checkSessionTimeout();
        }, 30000);

        this.resetTimerOnActivity();
        this.createWarningModal();
    }

    checkSessionTimeout() {
        fetch('check-session-status.php')
            .then(response => response.json())
            .then(data => {
                if (!data.loggedIn) {
                    this.forceLogout();
                    return;
                }

                // Show warning when 2 minutes or less remaining
                if (data.timeRemaining <= this.warningTime && data.timeRemaining > 0 && !this.warningShown) {
                    this.showWarning(data.timeRemaining);
                } else if (data.timeRemaining <= 0) {
                    this.forceLogout();
                }
            })
            .catch(error => {
                // Silent error handling - could add error notification if needed
            });
    }

    showWarning(timeRemaining) {
        this.warningShown = true;

        // Stop the regular session checks while warning is shown
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }

        const modal = document.getElementById('sessionWarningModal');

        // Show modal
        const bootstrapModal = new bootstrap.Modal(modal, {
            backdrop: 'static',
            keyboard: false,
            focus: true
        });
        bootstrapModal.show();

        // Start countdown with the actual time remaining
        let remainingSeconds = Math.max(timeRemaining, 0);
        this.updateCountdownDisplay(remainingSeconds);

        this.countdownInterval = setInterval(() => {
            remainingSeconds--;
            this.updateCountdownDisplay(remainingSeconds);

            if (remainingSeconds <= 0) {
                clearInterval(this.countdownInterval);
                this.forceLogout();
            }
        }, 1000);
    }

    updateCountdownDisplay(seconds) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        const display = `${minutes}:${secs.toString().padStart(2, '0')}`;
        document.getElementById('sessionCountdown').textContent = display;
    }

    extendSession() {
        fetch('extend-session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ extend: true })
        })
            .then(response => {
                // Check if response is actually JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server returned non-JSON response');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.hideWarning();
                    this.warningShown = false;

                    // Show success notification
                    this.showNotification('Session extended successfully!', 'success');

                    // Restart regular session checks
                    this.checkInterval = setInterval(() => {
                        this.checkSessionTimeout();
                    }, 30000);
                } else {
                    this.showNotification('Session extension failed: ' + (data.error || 'Unknown error'), 'danger');
                    setTimeout(() => {
                        this.forceLogout();
                    }, 2000);
                }
            })
            .catch(error => {
                this.showNotification('Session extension failed: ' + error.message, 'danger');
                setTimeout(() => {
                    this.forceLogout();
                }, 2000);
            });
    }

    showNotification(message, type = 'info') {
        // Create Bootstrap alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;';

        // Choose appropriate icon based on type
        let icon = 'bi-info-circle';
        if (type === 'success') icon = 'bi-check-circle';
        if (type === 'danger' || type === 'error') icon = 'bi-exclamation-circle';
        if (type === 'warning') icon = 'bi-exclamation-triangle';

        alertDiv.innerHTML = `
            <i class="bi ${icon} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        document.body.appendChild(alertDiv);

        // Auto remove after 4 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 4000);
    }

    // Add this method to track real page changes
    trackPageNavigation() {
        // Store the current page when user actually navigates
        this.actualCurrentPage = window.location.href;

        // Listen for actual page navigation (not AJAX)
        window.addEventListener('beforeunload', () => {
            this.actualCurrentPage = window.location.href;
        });

        // Track pushstate/popstate for SPA navigation
        const originalPushState = history.pushState;
        const originalReplaceState = history.replaceState;

        history.pushState = function(...args) {
            originalPushState.apply(history, args);
            sessionWarning.actualCurrentPage = window.location.href;
        };

        history.replaceState = function(...args) {
            originalReplaceState.apply(history, args);
            sessionWarning.actualCurrentPage = window.location.href;
        };

        window.addEventListener('popstate', () => {
            this.actualCurrentPage = window.location.href;
        });
    }

    // Modify the forceLogout method
    forceLogout() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }

        // Store the ACTUAL page user was on, not the AJAX endpoint
        fetch('store-last-page.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                lastPage: this.actualCurrentPage // Use tracked page instead
            })
        }).finally(() => {
            window.location.href = 'logout.php?logout=1&timeout=1';
        });
    }

    hideWarning() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('sessionWarningModal'));
        if (modal) {
            modal.hide();
        }
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
    }

    resetTimerOnActivity() {
        // Track user activity events (removed mousedown and mousemove)
        const events = ['keypress', 'scroll', 'touchstart', 'click'];
        let lastActivity = Date.now();

        events.forEach(event => {
            document.addEventListener(event, () => {
                const now = Date.now();
                // Only send request if more than 30 seconds since last activity
                if (now - lastActivity > 30000) {
                    fetch('update-activity.php', { method: 'POST' });
                    lastActivity = now;
                }
            }, true);
        });
    }

    createWarningModal() {
        const modalHTML = `
        <div class="modal fade" id="sessionWarningModal" tabindex="-1" aria-labelledby="sessionWarningModalLabel" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="sessionWarningModalLabel">
                            <i class="bi bi-exclamation-triangle me-2"></i>Session Expiring Soon
                        </h5>
                    </div>
                    <div class="modal-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-clock text-warning" style="font-size: 3rem;"></i>
                        </div>
                        <h6>Your session will expire in:</h6>
                        <div class="countdown-display mb-3">
                            <span id="sessionCountdown" class="badge bg-danger fs-4">2:00</span>
                        </div>
                        <p class="text-muted small">Click "Continue Session" to stay logged in, or "Logout Now" to end your session.</p>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-success me-2" id="continueSessionBtn" onclick="sessionWarning.extendSession()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Extend Session
                        </button>
                        <button type="button" class="btn btn-secondary" id="logoutNowBtn" onclick="sessionWarning.forceLogout()">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout Now
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    window.sessionWarning = new SessionTimeoutWarning();
});