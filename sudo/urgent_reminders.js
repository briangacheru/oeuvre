class UrgentReminderSystem {
    constructor() {
        this.checkInterval = null;
        this.activeToasts = new Set();
        this.snoozeOptions = [];
        this.maxSnoozeCount = 5;
        this.init();
    }

    init() {
        // Create toast container if it doesn't exist
        this.createToastContainer();

        // Load snooze configuration
        this.loadSnoozeOptions();

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

    // Load snooze options from server
    async loadSnoozeOptions() {
        try {
            const formData = new FormData();
            formData.append('action', 'get_snooze_options');

            const response = await fetch('../../sudo/reminders.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                this.snoozeOptions = data.options;
                this.maxSnoozeCount = data.max_snooze_count;
            } else {
                // Fallback to default options
                this.snoozeOptions = [
                    {minutes: 15, label: '15 minutes', icon: 'fas fa-clock'},
                    {minutes: 30, label: '30 minutes', icon: 'fas fa-clock'},
                    {minutes: 60, label: '1 hour', icon: 'fas fa-hourglass-half'},
                    {minutes: 120, label: '2 hours', icon: 'fas fa-hourglass-half'}
                ];
            }
        } catch (error) {
            console.error('Error loading snooze options:', error);
            // Use fallback options
            this.snoozeOptions = [
                {minutes: 15, label: '15 minutes', icon: 'fas fa-clock'},
                {minutes: 30, label: '30 minutes', icon: 'fas fa-clock'},
                {minutes: 60, label: '1 hour', icon: 'fas fa-hourglass-half'},
                {minutes: 120, label: '2 hours', icon: 'fas fa-hourglass-half'}
            ];
        }
    }

    startChecking() {
        // Check every minute
        this.checkInterval = setInterval(() => {
            this.checkDueReminders();
        }, 60000);
    }

    stopChecking() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
    }

    async checkDueReminders() {
        try {
            const response = await fetch('../../sudo/check_reminder.php');
            const data = await response.json();

            if (data.error) {
                console.error('Error checking reminders:', data.error);
                return;
            }

            data.forEach(reminder => {
                const hoursRemaining = reminder.hours_remaining;

                // Show persistent toast for reminders with less than 10 hours remaining
                // Skip if snoozed
                if (hoursRemaining <= 10 && hoursRemaining > 0 && !reminder.is_completed &&
                    (!reminder.is_snoozed || (reminder.snooze_until && new Date(reminder.snooze_until) <= new Date()))) {
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

        // Snooze information
        const snoozeCount = reminder.snooze_count || 0;
        const snoozeBadge = snoozeCount > 0 ?
            `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                <i class="fas fa-clock me-1"></i>Snoozed ${snoozeCount}x
            </span>` : '';

        const toast = `
        <div class="toast urgent-reminder-toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true" 
             data-bs-autohide="false" data-is-instance="${reminder.is_instance || false}" 
             data-instance-id="${reminder.instance_id || ''}" data-snooze-count="${snoozeCount}">
            <div class="toast-header bg-${priorityClass} text-white">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong class="me-auto">Urgent Reminder</strong>
                <small class="text-white-50">${timeText} remaining</small>
                <button type="button" class="btn-close btn-close-white ms-2" onclick="urgentReminderSystem.closeToast('${toastId}')" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <div class="d-flex align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1 fw-bold">${reminder.title}</h6>
                        ${reminder.description ? `<p class="mb-2 text-muted small">${reminder.description}</p>` : ''}
                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                            <span class="badge bg-${priorityClass} bg-opacity-10 text-${priorityClass} border border-${priorityClass} border-opacity-25">
                                <i class="fas fa-flag me-1"></i>${reminder.priority.charAt(0).toUpperCase() + reminder.priority.slice(1)}
                            </span>
                            <span class="badge bg-light text-dark border">
                                <i class="fas fa-tag me-1"></i>${reminder.category.charAt(0).toUpperCase() + reminder.category.slice(1)}
                            </span>
                            ${recurringBadge}
                            ${snoozeBadge}
                        </div>
                        <div class="d-flex align-items-center text-muted small mb-2">
                            <i class="fas fa-calendar me-1"></i>
                            <span>${new Date(reminder.reminder_date + ' ' + reminder.reminder_time).toLocaleString()}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <button class="btn btn-success btn-sm flex-fill" onclick="urgentReminderSystem.completeReminder(${reminder.id}, '${toastId}')">
                        <i class="fas fa-check me-1"></i>Complete
                    </button>
                    <button class="btn btn-warning btn-sm flex-fill" onclick="urgentReminderSystem.dismissReminder(${reminder.id}, '${toastId}')">
                        <i class="fas fa-eye-slash me-1"></i>Dismiss
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="urgentReminderSystem.snoozeReminder(${reminder.id}, '${toastId}', {isInstance: ${reminder.is_instance || false}, instanceId: ${reminder.instance_id || 'null'}, snoozeCount: ${snoozeCount}})" 
                            ${snoozeCount >= this.maxSnoozeCount ? 'title="Snooze limit reached - click for options"' : ''}>
                        <i class="fas fa-clock me-1"></i>${snoozeCount >= this.maxSnoozeCount ? 'Options' : 'Snooze'}
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
            const response = await fetch('../../sudo/reminders.php', {
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
            const response = await fetch('../../sudo/reminders.php', {
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

    // Enhanced snooze functionality
    snoozeReminder(id, toastId, reminderData = null) {
        // Get reminder data from toast element if not provided
        if (!reminderData) {
            const toastElement = document.getElementById(toastId);
            reminderData = {
                isInstance: toastElement.dataset.isInstance === 'true',
                instanceId: toastElement.dataset.instanceId,
                snoozeCount: parseInt(toastElement.dataset.snoozeCount || '0')
            };
        }

        // Check if max snooze limit reached
        if (reminderData.snoozeCount >= this.maxSnoozeCount) {
            this.showAdvancedSnoozeModal(id, toastId, reminderData);
            return;
        }

        // Show snooze options modal
        this.showSnoozeModal(id, toastId, reminderData);
    }

    showSnoozeModal(id, toastId, reminderData) {
        // Create enhanced snooze modal
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
                            <div class="snooze-info mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">Snooze Count:</small>
                                    <span class="badge ${reminderData.snoozeCount >= this.maxSnoozeCount - 1 ? 'bg-warning' : 'bg-info'}">
                                        ${reminderData.snoozeCount}/${this.maxSnoozeCount}
                                    </span>
                                </div>
                                ${reminderData.snoozeCount >= this.maxSnoozeCount - 1 ?
            '<div class="alert alert-warning py-2"><small><i class="fas fa-exclamation-triangle me-1"></i>Last snooze available!</small></div>' : ''
        }
                            </div>
                            
                            <p class="mb-3">How long would you like to snooze this reminder?</p>
                            
                            <div class="row g-2" id="snoozeOptionsGrid">
                                ${this.generateSnoozeOptionsHTML()}
                            </div>
                            
                            <div class="mt-3 pt-3 border-top">
                                <div class="d-flex justify-content-center">
                                    <button class="btn btn-outline-info btn-sm" onclick="urgentReminderSystem.showCustomSnoozeInput()">
                                        <i class="fas fa-edit me-1"></i>Custom Time
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Custom snooze input (initially hidden) -->
                            <div class="mt-3 d-none" id="customSnoozeSection">
                                <div class="card card-body bg-light">
                                    <div class="row g-2">
                                        <div class="col-8">
                                            <input type="number" class="form-control form-control-sm" id="customSnoozeMinutes" 
                                                   placeholder="Minutes" min="1" max="1440">
                                        </div>
                                        <div class="col-4">
                                            <button class="btn btn-info btn-sm w-100" onclick="urgentReminderSystem.applyCustomSnooze(${id}, '${toastId}', ${JSON.stringify(reminderData).replace(/"/g, '&quot;')})">
                                                Apply
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted mt-1">Enter 1-1440 minutes (max 24 hours)</small>
                                </div>
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

        // Add click handlers for snooze options
        this.snoozeOptions.forEach(option => {
            const button = document.getElementById(`snooze-${option.minutes}`);
            if (button) {
                button.addEventListener('click', () => {
                    modal.hide();
                    this.applySnooze(id, toastId, option.minutes, reminderData);
                });
            }
        });

        // Clean up modal when hidden
        document.getElementById('urgentSnoozeModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    generateSnoozeOptionsHTML() {
        return this.snoozeOptions.map(option => `
            <div class="col-6">
                <button type="button" class="btn btn-outline-primary w-100 py-2" id="snooze-${option.minutes}">
                    <i class="${option.icon} mb-1 d-block"></i>
                    <small>${option.label}</small>
                </button>
            </div>
        `).join('');
    }

    showCustomSnoozeInput() {
        const section = document.getElementById('customSnoozeSection');
        section.classList.toggle('d-none');
        if (!section.classList.contains('d-none')) {
            document.getElementById('customSnoozeMinutes').focus();
        }
    }

    applyCustomSnooze(id, toastId, reminderData) {
        const minutes = parseInt(document.getElementById('customSnoozeMinutes').value);

        if (!minutes || minutes < 1 || minutes > 1440) {
            this.showSimpleToast('Invalid Input', 'Please enter a value between 1 and 1440 minutes', 'warning');
            return;
        }

        const modal = bootstrap.Modal.getInstance(document.getElementById('urgentSnoozeModal'));
        modal.hide();

        this.applySnooze(id, toastId, minutes, reminderData);
    }

    async applySnooze(id, toastId, minutes, reminderData) {
        const formData = new FormData();
        formData.append('action', 'snooze_reminder');
        formData.append('id', id);
        formData.append('minutes', minutes);
        formData.append('source_type', reminderData.isInstance ? 'instance' : 'reminder');

        if (reminderData.instanceId) {
            formData.append('instance_id', reminderData.instanceId);
        }

        // Show loading state
        this.showSimpleToast('Snoozing...', 'Processing your snooze request', 'info');

        try {
            const response = await fetch('../../sudo/reminders.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Hide the urgent toast
                this.closeToast(toastId);

                // Show success message with snooze details
                const snoozeUntil = new Date(data.snooze_until).toLocaleString();
                this.showSnoozeSuccessToast(data.message, snoozeUntil, data.snooze_count, data.max_snoozes);

                // Schedule re-check when snooze expires
                const snoozeEndTime = new Date(data.snooze_until).getTime();
                const now = new Date().getTime();
                const timeUntilUnsnooze = snoozeEndTime - now;

                if (timeUntilUnsnooze > 0 && timeUntilUnsnooze <= 24 * 60 * 60 * 1000) { // Max 24 hours
                    setTimeout(() => {
                        this.checkDueReminders(); // Re-check for due reminders when snooze expires
                    }, timeUntilUnsnooze + 5000); // Add 5 seconds buffer
                }

            } else {
                this.showSimpleToast('Snooze Failed', data.message, 'danger');
            }
        } catch (error) {
            this.showSimpleToast('Error', 'Failed to snooze reminder', 'danger');
            console.error('Snooze error:', error);
        }
    }

    showSnoozeSuccessToast(message, snoozeUntil, snoozeCount, maxSnoozes) {
        const toastId = 'snooze-success-' + Date.now();
        const progressPercent = (snoozeCount / maxSnoozes) * 100;

        const toast = `
            <div class="toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="8000">
                <div class="toast-body bg-success text-white d-flex flex-column p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Reminder Snoozed!</strong>
                        </div>
                        <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="toast"></button>
                    </div>
                    
                    <div class="mb-2">
                        <small>${message}</small>
                        <br>
                        <small><i class="fas fa-clock me-1"></i>Will remind again: ${snoozeUntil}</small>
                    </div>
                    
                    <div class="snooze-progress mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small>Snooze Usage</small>
                            <small>${snoozeCount}/${maxSnoozes}</small>
                        </div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-light" role="progressbar" style="width: ${progressPercent}%" 
                                 aria-valuenow="${progressPercent}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        ${snoozeCount >= maxSnoozes - 1 ? '<small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Approaching snooze limit!</small>' : ''}
                    </div>
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

    showAdvancedSnoozeModal(id, toastId, reminderData) {
        const advancedModal = `
            <div class="modal fade" id="urgentAdvancedSnoozeModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title text-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>Snooze Limit Reached
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning d-flex align-items-center mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>This reminder has been snoozed ${this.maxSnoozeCount} times. Consider taking action!</small>
                            </div>
                            
                            <p class="mb-3">What would you like to do with this reminder?</p>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-success" onclick="urgentReminderSystem.completeReminder(${id}, '${toastId}')">
                                    <i class="fas fa-check me-2"></i>Mark as Complete
                                </button>
                                
                                <button class="btn btn-warning" onclick="urgentReminderSystem.dismissReminder(${id}, '${toastId}')">
                                    <i class="fas fa-eye-slash me-2"></i>Dismiss Reminder
                                </button>
                                
                                <button class="btn btn-primary" onclick="urgentReminderSystem.goToReminders()">
                                    <i class="fas fa-edit me-2"></i>Edit Reminder
                                </button>
                                
                                <button class="btn btn-outline-secondary" onclick="urgentReminderSystem.resetSnoozeCount(${id}, '${toastId}', ${JSON.stringify(reminderData).replace(/"/g, '&quot;')})">
                                    <i class="fas fa-undo me-2"></i>Reset Snooze Count
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        document.getElementById('urgentAdvancedSnoozeModal')?.remove();

        document.body.insertAdjacentHTML('beforeend', advancedModal);
        const modal = new bootstrap.Modal(document.getElementById('urgentAdvancedSnoozeModal'));
        modal.show();

        document.getElementById('urgentAdvancedSnoozeModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    async resetSnoozeCount(id, toastId, reminderData) {
        if (!confirm('Are you sure you want to reset the snooze count? This will allow snoozing again.')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'reset_snooze_count');
        formData.append('id', id);
        formData.append('source_type', reminderData.isInstance ? 'instance' : 'reminder');

        if (reminderData.instanceId) {
            formData.append('instance_id', reminderData.instanceId);
        }

        try {
            const response = await fetch('../../sudo/reminders.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('urgentAdvancedSnoozeModal'));
                modal.hide();
                this.showSimpleToast('Reset Successful', 'Snooze count has been reset', 'success');

                // Update reminder data and show snooze modal
                reminderData.snoozeCount = 0;
                this.snoozeReminder(id, toastId, reminderData);
            } else {
                this.showSimpleToast('Reset Failed', data.message, 'danger');
            }
        } catch (error) {
            this.showSimpleToast('Error', 'Failed to reset snooze count', 'danger');
        }
    }

    goToReminders() {
        // Navigate to reminders page
        window.location.href = '../../sudo/reminders.php';
    }

    showSimpleToast(title, message, type = 'info') {
        const toastId = 'simple-toast-' + Date.now();
        const toast = `
        <div class="toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
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

    // Helper function to format snooze duration for display
    formatSnoozeDuration(minutes) {
        if (minutes < 60) {
            return minutes + ' min';
        } else if (minutes < 1440) {
            const hours = Math.floor(minutes / 60);
            const remainingMinutes = minutes % 60;
            return hours + 'h' + (remainingMinutes > 0 ? ' ' + remainingMinutes + 'm' : '');
        } else {
            const days = Math.floor(minutes / 1440);
            const remainingHours = Math.floor((minutes % 1440) / 60);
            return days + 'd' + (remainingHours > 0 ? ' ' + remainingHours + 'h' : '');
        }
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

// Add CSS styles for enhanced snooze functionality
const style = document.createElement('style');
style.textContent = `
/* Enhanced Toast Styling */
.urgent-reminder-toast {
    min-width: 400px;
    max-width: 500px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border: none;
    border-radius: 12px;
    overflow: hidden;
}

.urgent-reminder-toast .toast-header {
    border-radius: 12px 12px 0 0;
    border-bottom: none;
    padding: 12px 16px;
}

.urgent-reminder-toast .toast-body {
    padding: 16px;
    background: white;
    border-radius: 0 0 12px 12px;
}

/* Snooze Modal Styling */
#urgentSnoozeModal .modal-content,
#urgentAdvancedSnoozeModal .modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}

#urgentSnoozeModal .modal-header {
    border-radius: 16px 16px 0 0;
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    border-bottom: none;
}

#urgentSnoozeModal .btn-close,
#urgentAdvancedSnoozeModal .btn-close {
    filter: brightness(0) invert(1);
}

/* Snooze Options Grid */
#snoozeOptionsGrid .btn {
    height: 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    border-radius: 12px;
    transition: all 0.3s ease;
}

#snoozeOptionsGrid .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,123,255,0.3);
}

#snoozeOptionsGrid .btn i {
    font-size: 1.2rem;
    margin-bottom: 4px;
}

/* Custom Snooze Input */
#customSnoozeSection .card {
    border: 2px dashed #dee2e6;
    transition: all 0.3s ease;
}

#customSnoozeSection .card:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

/* Snooze Progress */
.snooze-progress {
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 8px;
}

.snooze-progress .progress {
    background: rgba(255,255,255,0.2);
    border-radius: 2px;
}

.snooze-progress .progress-bar {
    border-radius: 2px;
    transition: width 0.3s ease;
}

/* Snooze Info */
.snooze-info {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    padding: 12px;
    border-left: 4px solid #ffc107;
}

/* Advanced Modal Styling */
#urgentAdvancedSnoozeModal .alert {
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
}

#urgentAdvancedSnoozeModal .btn {
    border-radius: 10px;
    padding: 12px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
}

#urgentAdvancedSnoozeModal .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Responsive Design */
@media (max-width: 768px) {
    .urgent-reminder-toast {
        min-width: 300px;
        max-width: 350px;
    }
    
    #snoozeOptionsGrid .btn {
        height: 60px;
        font-size: 0.85rem;
    }
    
    #snoozeOptionsGrid .btn i {
        font-size: 1rem;
    }
    
    .urgent-reminder-toast .d-flex.gap-2 {
        flex-direction: column;
        gap: 8px !important;
    }
    
    .urgent-reminder-toast .btn {
        width: 100%;
        margin-bottom: 4px;
    }
}

@media (max-width: 576px) {
    #snoozeOptionsGrid {
        grid-template-columns: 1fr 1fr;
    }
}

/* Animation */
.fade-in {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Pulse Animation for High Priority */
.urgent-reminder-toast.bg-danger {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
    50% { box-shadow: 0 8px 25px rgba(220,53,69,0.4); }
    100% { box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
}

/* Loading States */
.loading-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* High Contrast Mode Support */
@media (prefers-contrast: high) {
    .urgent-reminder-toast {
        border: 2px solid;
    }
    
    .btn {
        border-width: 2px;
    }
}

/* Reduced Motion Support */
@media (prefers-reduced-motion: reduce) {
    .urgent-reminder-toast,
    .btn,
    .fade-in {
        animation: none;
        transition: none;
    }
    
    .urgent-reminder-toast:hover,
    .btn:hover {
        transform: none;
    }
}
`;

document.head.appendChild(style);