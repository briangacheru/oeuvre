class UrgentReminderSystem {
    constructor() {
        this.checkInterval = null;
        this.activeToasts = new Set();
        this.init();
    }

    init() {
        // Create toast container if it doesn't exist
        this.createToastContainer();

        // Start checking for urgent reminders
        this.startChecking();

        // Check immediately on page load
        this.checkDueReminders();
    }

    createToastContainer() {
        if (!document.getElementById('urgent-toast-container')) {
            const container = document.createElement('div');
            container.id = 'urgent-toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '1055';
            container.style.maxHeight = '80vh';
            container.style.overflowY = 'auto';
            document.body.appendChild(container);
        }
    }

    startChecking() {
        // Check every minute
        this.checkInterval = setInterval(() => {
            this.checkDueReminders();
        }, 600000);
    }

    stopChecking() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
    }

    async checkDueReminders() {
        try {
            const response = await fetch('check_reminder'); // Adjust path as needed
            const data = await response.json();

            if (data.error) {
                console.error('Error checking reminders:', data.error);
                return;
            }

            data.forEach(reminder => {
                const hoursRemaining = reminder.hours_remaining;

                // Show persistent toast for reminders with less than 10 hours remaining
                if (hoursRemaining <= 10 && hoursRemaining > 0 && !reminder.is_completed) {
                    this.showUrgentReminderToast(reminder, hoursRemaining);
                }
            });
        } catch (error) {
            console.error('Error fetching urgent reminders:', error);
        }
    }

    showUrgentReminderToast(reminder, hoursRemaining) {
        const toastId = 'urgent-toast-' + reminder.id + (reminder.is_instance ? '-instance-' + reminder.instance_id : '');

        // Check if toast already exists for this reminder
        if (document.getElementById(toastId) || this.activeToasts.has(toastId)) {
            return; // Don't create duplicate toasts
        }

        this.activeToasts.add(toastId);

        // Format time remaining
        let timeText = '';
        if (hoursRemaining < 1) {
            const minutesRemaining = Math.floor((hoursRemaining * 60));
            timeText = minutesRemaining > 0 ? `${minutesRemaining} minutes` : 'Less than a minute';
        } else {
            const hours = Math.floor(hoursRemaining);
            const minutes = Math.floor((hoursRemaining - hours) * 60);
            timeText = minutes > 0 ? `${hours}h ${minutes}m` : `${hours} hours`;
        }

        const priorityClass = reminder.priority === 'high' ? 'danger' :
            reminder.priority === 'medium' ? 'warning' : 'info';

        const recurringBadge = reminder.is_recurring ?
            `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                <i class="fas fa-redo me-1"></i>${reminder.is_instance ? 'Recurring Instance' : 'Recurring'}
            </span>` : '';

        const toast = `
        <div class="toast urgent-reminder-toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true" 
             data-bs-autohide="false" data-is-instance="${reminder.is_instance || false}" 
             data-instance-id="${reminder.instance_id || ''}">
            <div class="toast-header bg-${priorityClass} text-white">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong class="me-auto">Urgent Reminder</strong>
                <small class="text-white">${timeText} remaining</small>
                <button type="button" class="btn-close btn-close-white ms-2" onclick="urgentReminderSystem.closeToast('${toastId}')" aria-label="Close"></button>
            </div>
            <div class="toast-body bg-body-quaternary">
                <div class="d-flex align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1 fw-bold">${reminder.title}</h6>
                        ${reminder.description ? `<p class="mb-2 text-muted small">${reminder.description}</p>` : ''}
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-${priorityClass} bg-opacity-10 text-${priorityClass} border border-${priorityClass} border-opacity-25">
                                <i class="fas fa-flag me-1"></i>${reminder.priority.charAt(0).toUpperCase() + reminder.priority.slice(1)}
                            </span>
                            <span class="badge bg-light text-dark border">
                                <i class="fas fa-tag me-1"></i>${reminder.category.charAt(0).toUpperCase() + reminder.category.slice(1)}
                            </span>
                            ${recurringBadge}
                        </div>
                        <div class="d-flex align-items-center text-muted small mb-2">
                            <i class="fas fa-calendar me-1"></i>
                            <span>${new Date(reminder.reminder_date + ' ' + reminder.reminder_time).toLocaleString()}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-success btn-sm flex-fill" onclick="urgentReminderSystem.completeReminder(${reminder.id}, '${toastId}')">
                        <i class="fas fa-check me-1"></i>Complete
                    </button>
                    <button class="btn btn-warning btn-sm flex-fill" onclick="urgentReminderSystem.dismissReminder(${reminder.id}, '${toastId}')">
                        <i class="fas fa-eye-slash me-1"></i>Dismiss
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="urgentReminderSystem.snoozeReminder(${reminder.id}, '${toastId}')">
                        <i class="fas fa-clock me-1"></i>Snooze
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="urgentReminderSystem.goToReminders()">
                        <i class="fas fa-external-link-alt me-1"></i>View All
                    </button>
                </div>
            </div>
        </div>
        `;

        document.getElementById('urgent-toast-container').insertAdjacentHTML('beforeend', toast);
        const toastElement = new bootstrap.Toast(document.getElementById(toastId));
        toastElement.show();

        // Handle toast hidden event
        document.getElementById(toastId).addEventListener('hidden.bs.toast', () => {
            this.activeToasts.delete(toastId);
            document.getElementById(toastId)?.remove();
        });
    }

    closeToast(toastId) {
        const toast = document.getElementById(toastId);
        if (toast) {
            bootstrap.Toast.getInstance(toast).hide();
        }
        this.activeToasts.delete(toastId);
    }

    async completeReminder(id, toastId) {
        const toastElement = document.getElementById(toastId);
        const isInstance = toastElement.dataset.isInstance === 'true';
        const instanceId = toastElement.dataset.instanceId;

        const formData = new FormData();

        if (isInstance) {
            formData.append('action', 'complete_reminder_instance');
            formData.append('instance_id', instanceId);
            formData.append('reminder_id', id);
        } else {
            formData.append('action', 'complete_reminder');
            formData.append('id', id);
        }

        try {
            const response = await fetch('reminders', { // Adjust path as needed
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.closeToast(toastId);
                const message = isInstance ? 'Reminder instance completed!' : 'Reminder completed!';
                this.showSimpleToast('Success!', message, 'success');
            } else {
                this.showSimpleToast('Error!', 'Failed to complete reminder', 'danger');
            }
        } catch (error) {
            this.showSimpleToast('Error!', 'Something went wrong!', 'danger');
        }
    }

    dismissReminder(id, toastId) {
        const toastElement = document.getElementById(toastId);
        const isInstance = toastElement.dataset.isInstance === 'true';

        // Create confirmation modal
        const confirmModal = `
            <div class="modal fade" id="urgentDismissModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title text-warning">
                                <i class="fas fa-eye-slash me-2"></i>Dismiss Urgent Reminder
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">Are you sure you want to dismiss this urgent reminder?</p>
                            <div class="alert alert-warning d-flex align-items-center" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>${isInstance ? 'This will dismiss only this instance of the recurring reminder.' : 'This reminder is due soon. Dismissing will hide it from your urgent notifications.'}</small>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancel
                            </button>
                            <button type="button" class="btn btn-warning" onclick="urgentReminderSystem.confirmDismiss(${id}, '${toastId}')">
                                <i class="fas fa-eye-slash me-1"></i>Yes, Dismiss
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        document.getElementById('urgentDismissModal')?.remove();

        document.body.insertAdjacentHTML('beforeend', confirmModal);
        const modal = new bootstrap.Modal(document.getElementById('urgentDismissModal'));
        modal.show();

        document.getElementById('urgentDismissModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    async confirmDismiss(id, toastId) {
        const toastElement = document.getElementById(toastId);
        const isInstance = toastElement.dataset.isInstance === 'true';
        const instanceId = toastElement.dataset.instanceId;

        const formData = new FormData();

        if (isInstance) {
            formData.append('action', 'dismiss_reminder_instance');
            formData.append('instance_id', instanceId);
            formData.append('reminder_id', id);
        } else {
            formData.append('action', 'dismiss_reminder');
            formData.append('id', id);
        }

        try {
            const response = await fetch('/reminders', { // Adjust path as needed
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('urgentDismissModal'));
                modal.hide();

                this.closeToast(toastId);
                const message = isInstance ? 'Reminder instance dismissed!' : 'Urgent reminder dismissed!';
                this.showSimpleToast('Success!', message, 'success');
            } else {
                this.showSimpleToast('Error!', 'Failed to dismiss reminder', 'danger');
            }
        } catch (error) {
            this.showSimpleToast('Error!', 'Something went wrong!', 'danger');
        }
    }

    snoozeReminder(id, toastId) {
        // Create snooze options modal
        const snoozeModal = `
            <div class="modal fade" id="urgentSnoozeModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title text-info">
                                <i class="fas fa-clock me-2"></i>Snooze Reminder
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">How long would you like to snooze this reminder?</p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" onclick="urgentReminderSystem.applySnooze(${id}, '${toastId}', 15)">
                                    <i class="fas fa-clock me-2"></i>15 minutes
                                </button>
                                <button class="btn btn-outline-primary" onclick="urgentReminderSystem.applySnooze(${id}, '${toastId}', 30)">
                                    <i class="fas fa-clock me-2"></i>30 minutes
                                </button>
                                <button class="btn btn-outline-primary" onclick="urgentReminderSystem.applySnooze(${id}, '${toastId}', 60)">
                                    <i class="fas fa-clock me-2"></i>1 hour
                                </button>
                                <button class="btn btn-outline-primary" onclick="urgentReminderSystem.applySnooze(${id}, '${toastId}', 120)">
                                    <i class="fas fa-clock me-2"></i>2 hours
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        document.getElementById('urgentSnoozeModal')?.remove();

        document.body.insertAdjacentHTML('beforeend', snoozeModal);
        const modal = new bootstrap.Modal(document.getElementById('urgentSnoozeModal'));
        modal.show();

        document.getElementById('urgentSnoozeModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    applySnooze(id, toastId, minutes) {
        // Hide the urgent toast temporarily
        this.closeToast(toastId);

        // Close snooze modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('urgentSnoozeModal'));
        modal.hide();

        this.showSimpleToast('Snoozed!', `Reminder snoozed for ${minutes} minutes`, 'info');

        // Set timeout to re-check reminders after snooze period
        setTimeout(() => {
            this.checkDueReminders();
        }, minutes * 60 * 1000);
    }

    goToReminders() {
        // Navigate to reminders page
        window.location.href = 'reminders'; // Adjust path as needed
    }

    showSimpleToast(title, message, type = 'info') {
        const toastId = 'simple-toast-' + Date.now();
        const toast = `
        <div class="toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="3000">
            <div class="toast-body bg-${type} text-white d-flex align-items-center justify-content-between p-3">
                <div class="d-flex align-items-center">
                    <strong class="me-2">${title}</strong>
                    <span>${message}</span>
                </div>
                <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        `;

        document.getElementById('urgent-toast-container').insertAdjacentHTML('beforeend', toast);
        const toastElement = new bootstrap.Toast(document.getElementById(toastId));
        toastElement.show();

        // Auto-remove after hiding
        document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    }
}

// Initialize the urgent reminder system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.urgentReminderSystem = new UrgentReminderSystem();
});

// Clean up when page is unloaded
window.addEventListener('beforeunload', function() {
    if (window.urgentReminderSystem) {
        window.urgentReminderSystem.stopChecking();
    }
});