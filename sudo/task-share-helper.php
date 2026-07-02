<?php
/**
 * Task Share Link Generator
 *
 * This file provides functions to generate and validate shareable task links
 * that can be accessed without authentication.
 */

/**
 * Generate a shareable link for a task
 *
 * @param int $taskId The ID of the task
 * @return string The shareable URL
 */
function generateTaskShareLink($taskId) {
    // Create a unique token
    $randomString = bin2hex(random_bytes(8));
    $tokenData = $taskId . '_' . $randomString;
    $shareToken = base64_encode($tokenData);

    // Get the base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];

    // Get script path and clean it
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);

    // IMPORTANT: Remove BOTH /sudo and /share to prevent duplication
    $basePath = str_replace(['/sudo', '/share'], '', $scriptPath);

    // Build URL
    $baseUrl = $protocol . '://' . $host . $basePath;
    $baseUrl = rtrim($baseUrl, '/');

    // Add /share/ prefix
    $shareUrl = $baseUrl . '/share/task-view?token=' . urlencode($shareToken);

    return $shareUrl;
}

/**
 * Copy shareable link to clipboard (JavaScript function)
 * Add this to your task view page
 */
function getShareLinkJavaScript() {
    return <<<'JAVASCRIPT'
<script>
function copyShareLink(taskId) {
    // Generate share link via AJAX
    fetch('../share/generate-share-link?task_id=' + taskId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Copy to clipboard
                navigator.clipboard.writeText(data.shareUrl).then(() => {
                    // Show success message
                    showToast('Share link copied to clipboard!', 'success');
                }).catch(err => {
                    // Fallback: show the link
                    prompt('Copy this link:', data.shareUrl);
                });
            } else {
                showToast('Error generating share link: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            showToast('Error: ' + error, 'danger');
        });
}

function shareTask(taskId, platform) {
    // Generate share link via AJAX
    fetch('../share/generate-share-link?task_id=' + taskId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const shareUrl = encodeURIComponent(data.shareUrl);
                const shareText = encodeURIComponent(data.shareText);
                
                let url;
                switch(platform) {
                    case 'email':
                        url = `mailto:?subject=${shareText}&body=${shareUrl}`;
                        break;
                    case 'whatsapp':
                        url = `https://wa.me/?text=${shareText}%20${shareUrl}`;
                        break;
                    case 'twitter':
                        url = `https://twitter.com/intent/tweet?text=${shareText}&url=${shareUrl}`;
                        break;
                    default:
                        return;
                }
                window.open(url, '_blank');
            }
        });
}
</script>
JAVASCRIPT;
}

/**
 * HTML for share button
 * Add this to your task view page where you want the share button
 */
function getShareButtonHTML($taskId, $taskTopic) {
    $escapedTopic = htmlspecialchars($taskTopic);
    return <<<HTML
<div class="dropdown">
    <button class="btn btn-primary dropdown-toggle" type="button" id="shareDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-share-alt me-2"></i>Share Task
    </button>
    <ul class="dropdown-menu" aria-labelledby="shareDropdown">
        <li>
            <a class="dropdown-item" href="#" onclick="copyShareLink($taskId); return false;">
                <i class="fas fa-link me-2"></i>Copy Link
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="#" onclick="shareTask($taskId, 'email'); return false;">
                <i class="fas fa-envelope me-2"></i>Email
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="#" onclick="shareTask($taskId, 'whatsapp'); return false;">
                <i class="fab fa-whatsapp me-2"></i>WhatsApp
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="#" onclick="shareTask($taskId, 'twitter'); return false;">
                <i class="fab fa-twitter me-2"></i>Twitter
            </a>
        </li>
    </ul>
</div>
HTML;
}
?>