class SessionManager {
    constructor() {
        // FOR TESTING: Use shorter timeouts
        this.sessionTimeout = 3600; // 5 minutes for testing (change to 3600 for production)
        this.warningTime = 300; // Show warning 2 minutes before expiry
        this.countdownInterval = null;
        this.sessionCheckInterval = null;
        this.lastActivity = Date.now();
        this.isTestMode = false; // Set to false for production

        this.init();
    }

    init() {
        // Track user activity
        this.trackActivity();

        // Check session status every 10 seconds for testing (30 seconds for production)
        const checkInterval = this.isTestMode ? 10000 : 30000;
        this.sessionCheckInterval = setInterval(() => {
            this.checkSessionStatus();
        }, checkInterval);

        // Set up modal event listeners
        this.setupModalEvents();
    }

    trackActivity() {
        // Removed mousedown and mousemove events
        const events = ['keypress', 'scroll', 'touchstart', 'click'];

        events.forEach(event => {
            document.addEventListener(event, () => {
                this.lastActivity = Date.now();
            }, true);
        });
    }

    checkSessionStatus() {
        const now = Date.now();
        const timeSinceActivity = (now - this.lastActivity) / 1000;
        const timeUntilExpiry = this.sessionTimeout - timeSinceActivity;

        // Show warning when specified time remains
        if (timeUntilExpiry <= this.warningTime && timeUntilExpiry > 0 && !this.isModalShown()) {
            this.showWarningModal(Math.floor(timeUntilExpiry));
        }

        // Auto logout when time expires
        if (timeUntilExpiry <= 0) {
            this.forceLogout();
        }
    }

    isModalShown() {
        const modal = document.getElementById('sessionWarningModal');
        return modal.classList.contains('show');
    }

    showWarningModal(secondsLeft) {
        const modal = new bootstrap.Modal(document.getElementById('sessionWarningModal'));
        modal.show();

        this.startCountdown(secondsLeft);
    }

    startCountdown(seconds) {
        const timerElement = document.getElementById('countdownTimer');
        let timeLeft = seconds;

        // Clear any existing countdown
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }

        this.countdownInterval = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            const secs = timeLeft % 60;

            timerElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;

            if (timeLeft <= 0) {
                clearInterval(this.countdownInterval);
                this.forceLogout();
                return;
            }

            timeLeft--;
        }, 1000);
    }

    setupModalEvents() {
        // Extend session button
        document.getElementById('extendSessionBtn').addEventListener('click', () => {
            this.extendSession();
        });

        // Logout now button
        document.getElementById('logoutNowBtn').addEventListener('click', () => {
            this.forceLogout();
        });
    }

    extendSession() {
        fetch('extend-session.php?extend=true', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'extend=true'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reset activity timer
                    this.lastActivity = Date.now();

                    // Clear countdown and hide modal
                    if (this.countdownInterval) {
                        clearInterval(this.countdownInterval);
                    }

                    const modal = bootstrap.Modal.getInstance(document.getElementById('sessionWarningModal'));
                    modal.hide();

                    // Show success message
                    this.showNotification('Session extended successfully!', 'success');
                } else {
                    this.forceLogout();
                }
            })
            .catch(error => {
                this.forceLogout();
            });
    }

    forceLogout() {
        // Clear intervals
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        if (this.sessionCheckInterval) {
            clearInterval(this.sessionCheckInterval);
        }

        // Redirect to logout
        window.location.href = 'logout';
    }

    showNotification(message, type = 'info') {
        // Define Font Awesome icons for different notification types
        const icons = {
            'success': '<i class="fas fa-check-circle text-success me-2"></i>',
            'error': '<i class="fas fa-times-circle text-danger me-2"></i>',
            'warning': '<i class="fas fa-exclamation-triangle text-warning me-2"></i>',
            'info': '<i class="fas fa-info-circle text-info me-2"></i>'
        };

        const icon = icons[type] || icons['info'];

        // Create Bootstrap alert with icon
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${icon}${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(alertDiv);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Method to manually trigger warning for testing
    triggerWarningForTesting() {
        this.showWarningModal(120);
    }
}

// Initialize session manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.sessionManager = new SessionManager();

    // For testing: Add a button to manually trigger the warning
    if (window.sessionManager.isTestMode) {
        // Optional: Add a test button to the page
        const testButton = document.createElement('button');
        testButton.textContent = 'Test Session Warning';
        testButton.className = 'btn btn-warning btn-sm position-fixed';
        testButton.style.cssText = 'top: 10px; left: 10px; z-index: 9999;';
        testButton.onclick = () => window.sessionManager.triggerWarningForTesting();
        document.body.appendChild(testButton);
    }
});