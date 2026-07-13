// Track shown notifications to prevent duplicates (persistent across checks)
let shownNotifications = new Set();

// Play custom task notification sound
function playTaskNotificationSound() {
    try {
        const audio = new Audio('audio/task-notification.mp3');
        audio.volume = 0.7;
        audio.play().catch(e => {
            // Fallback: try alternative notification
            if (window.speechSynthesis) {
                const utterance = new SpeechSynthesisUtterance('New task assigned');
                utterance.rate = 1.2;
                utterance.volume = 0.3;
                window.speechSynthesis.speak(utterance);
            }
        });
    } catch (error) {
        // Silent fallback
    }
}

// Check for new tasks every 30 seconds
function checkForNewTasks() {
    fetch('check_new_tasks')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.tasks && data.tasks.length > 0) {
                // Filter out tasks we've already handled
                const newTasks = data.tasks.filter(task => !shownNotifications.has(`task-${task.id}`));

                if (newTasks.length > 0) {
                    // Mark tasks as handled without showing toast
                    newTasks.forEach(task => {
                        shownNotifications.add(`task-${task.id}`);
                    });

                    // Update badge after slight delay
                    setTimeout(() => {
                        updateNotificationBadge();
                    }, 1000);
                }
            }
        })
        .catch(error => {
            // Silent error handling
        });
}


// Update notification badge count
function updateNotificationBadge() {
    fetch('get_notification_counts')
        .then(response => response.json())
        .then(data => {
            // Update new tasks badge
            const newTasksBadge = document.querySelector('#navbarDropdownNewTasks .notification-indicator-number');
            if (data.newTasksCount > 0) {
                if (newTasksBadge) {
                    newTasksBadge.textContent = data.newTasksCount;
                } else {
                    // Create badge if it doesn't exist
                    const badge = document.createElement('span');
                    badge.className = 'notification-indicator-number';
                    badge.textContent = data.newTasksCount;
                    document.querySelector('#navbarDropdownNewTasks').appendChild(badge);
                }
            } else {
                if (newTasksBadge) {
                    newTasksBadge.remove();
                }
            }

            // Update late tasks badge
            const lateTasksBadge = document.querySelector('#navbarDropdownNotification .notification-indicator-number');
            if (lateTasksBadge) {
                lateTasksBadge.textContent = data.lateTasksCount;
            }
        })
        .catch(error => {
            // Silent error handling
        });
}

// Mark individual task as read
function markTaskAsRead(taskId) {
    fetch('mark_task_read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `task_id=${taskId}&csrf_token=${encodeURIComponent(GLOBAL_CSRF_TOKEN)}`
    })
        .then(response => response.json())
        .catch(error => {
            // Silent error handling
        });
}

// Mark all tasks as read

// Optional: Clear shown notifications (only use when needed)
function clearShownNotifications() {
    shownNotifications.clear();
}

// Optional: Clear notifications only when user logs out or session ends
function onUserLogout() {
    clearShownNotifications();
}

// Update sidebar badge counts (All Tasks, Unconfirmed, In Progress, In
// Revision, Submitted, Completed, Unpaid, Paid, Chat unread) - these are
// only rendered once at page load otherwise, so they go stale until refresh.
const SIDEBAR_BADGE_IDS = {
    all_tasks: 'sidebar-badge-all-tasks',
    unconfirmed: 'sidebar-badge-unconfirmed',
    in_progress: 'sidebar-badge-in-progress',
    in_revision: 'sidebar-badge-in-revision',
    submitted: 'sidebar-badge-submitted',
    completed: 'sidebar-badge-completed',
    unpaid: 'sidebar-badge-unpaid',
    paid: 'sidebar-badge-paid',
    unread_messages: 'sidebar-badge-unread-messages'
};

function updateSidebarBadges() {
    fetch('get_sidebar_counts')
        .then(response => response.json())
        .then(data => {
            if (!data || data.status !== 'success' || !data.counts) return;

            Object.keys(SIDEBAR_BADGE_IDS).forEach(key => {
                const el = document.getElementById(SIDEBAR_BADGE_IDS[key]);
                if (el && key in data.counts) {
                    el.textContent = data.counts[key];
                }
            });
        })
        .catch(error => {
            // Silent error handling
        });
}

// Start checking for new tasks when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check immediately
    checkForNewTasks();
    updateSidebarBadges();

    // Then check every 30 seconds
    setInterval(checkForNewTasks, 30000);
    setInterval(updateSidebarBadges, 30000);
});

// Optional: Manual function to clear all toasts and reset tracking (for testing)
function clearAllToastsAndReset() {
    const toasts = document.querySelectorAll('.custom-toast');
    toasts.forEach(toast => toast.remove());
    clearShownNotifications();
}