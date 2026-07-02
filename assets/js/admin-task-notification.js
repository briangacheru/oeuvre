// Track shown notifications to prevent duplicates (persistent across checks)
let shownNotifications = new Set();

// Play custom task notification sound
function playTaskNotificationSound() {
    try {
        const audio = new Audio('../audio/task-notification.mp3');
        audio.volume = 0.8;
        audio.play().catch(e => {
            // Fallback: try alternative notification
            if (window.speechSynthesis) {
                const utterance = new SpeechSynthesisUtterance('Task submitted');
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
                // Filter out tasks we've already shown toasts for
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
function handleTaskClick(event, taskId, encodedId) {
    event.preventDefault(); // Stop immediate navigation

    fetch('mark_task_read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `task_id=${taskId}`
    })
        .then(response => response.json())
        .then(data => {
            // Optionally check data for success here
            window.location.href = `view-task?task_id=${encodedId}`; // Navigate after update
        })
        .catch(error => {
            // Even if error, navigate anyway or handle differently
            window.location.href = `view-task?task_id=${encodedId}`;
        });
}

// Mark all tasks as read
function markAllAsRead() {
    fetch('mark_all_tasks_read', {
        method: 'POST'
    })
        .then(response => response.json())
        .then(data => {
            updateNotificationBadge();
        })
        .catch(error => {
            // Silent error handling
        });
}

// Optional: Clear shown notifications (only use when needed)
function clearShownNotifications() {
    shownNotifications.clear();
}

// Optional: Clear notifications only when user logs out or session ends
function onUserLogout() {
    clearShownNotifications();
}

// Start checking for new tasks when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check immediately
    checkForNewTasks();

    // Then check every 30 seconds
    setInterval(checkForNewTasks, 30000);
});

// Optional: Manual function to clear all toasts and reset tracking (for testing)
function clearAllToastsAndReset() {
    const toasts = document.querySelectorAll('.custom-toast');
    toasts.forEach(toast => toast.remove());
    clearShownNotifications();
}