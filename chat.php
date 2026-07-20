<?php
include "head.php";

// Check session and set user ID
if (isset($_SESSION['sessionWriter'])) {
    $aid = $_SESSION['sessionWriter'];
} else {
    header('Location: login.php');
    exit();
}

// Fetch current user information
$currentUserQuery = mysqli_query($con, "
    SELECT id, 'admin' as type, is_online, last_seen FROM tbladmin WHERE email = '$aid'
    UNION 
    SELECT id, 'writer' as type, is_online, last_seen FROM tblwriters WHERE email = '$aid'
");

$currentUser = mysqli_fetch_assoc($currentUserQuery);
$currentUserId = $currentUser['id'];
$currentUserType = $currentUser['type'];
$lastSeen = $currentUser['last_seen'];
$isOnline = isRecentlyOnline($lastSeen);

// Determine online status or last seen (matches sudo/writer.php's wording/timezone handling)
$statusText = $isOnline ? 'Online' : getLastSeenText($lastSeen);

// Fetch users for chat excluding the current user
$users = [];
if ($currentUserType == 'writer') {
    // Fetch only admins if the current user is a writer
    $adminsQuery = mysqli_query($con, "SELECT id, username, Photo, is_online, last_seen FROM tbladmin WHERE id != $currentUserId");
    while ($admin = mysqli_fetch_assoc($adminsQuery)) {
        $users[] = [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'photo' => $admin['Photo'],
            'is_online' => isRecentlyOnline($admin['last_seen']),
            'last_seen' => $admin['last_seen'],
            'type' => 'admin'
        ];
    }
}

// Get the latest message for each user and sort by timestamp
foreach ($users as &$user) {
    $userId = $user['id'];
    $latestMessageQuery = mysqli_query($con, "
        SELECT message, timestamp, is_read FROM chat_messages 
        WHERE (sender_id = $userId AND receiver_id = $currentUserId)
           OR (receiver_id = $userId AND sender_id = $currentUserId)
        ORDER BY timestamp DESC LIMIT 1
    ");
    $latestMessage = mysqli_fetch_assoc($latestMessageQuery);
    $user['latest_message'] = $latestMessage ? $latestMessage['message'] : "No messages yet.";
    $user['latest_message_time'] = $latestMessage ? $latestMessage['timestamp'] : null;
    $user['is_read'] = $latestMessage ? $latestMessage['is_read'] : 1; // default to read if no message
}

// Sort users by latest message timestamp in descending order
usort($users, function($a, $b) {
    return strtotime($b['latest_message_time'] ?? '0000-00-00 00:00:00') - strtotime($a['latest_message_time'] ?? '0000-00-00 00:00:00');
});
?>
<title>Chat | iTasker</title>
<?php include "navi.php";?>
<div class="card shadow-none border mb-3">
    <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(assets/img/illustrations/corner-6.png);"></div>
    <div class="card-header z-1">
        <div class="row flex-between-center gx-0">
            <div class="col-lg-auto d-flex align-items-center">
                <h4 class="mb-0 text-primary fw-bold">My <span class="text-info fw-medium"> Chats</span></h4>
            </div>
            <div class="col-lg-auto pt-3 pt-lg-0">
                <form class="row flex-lg-column flex-xxl-row gx-3 gy-2 align-items-center align-items-lg-start align-items-xxl-center">
                    <div class="col-auto">
                    </div>
                    <div class="col-md-auto position-relative">
                        <h6 class="mb-1 badge rounded-pill badge-subtle-info"><?php echo date("jS F Y"); ?> | <span id="timeDisplay"></span></h6>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .card-chat {
        height: calc(80vh - var(--falcon-top-nav-height) - 0.625rem - 5rem);
    }

    /* Presence tiers beyond the theme's built-in status-online/status-offline,
       bucketed by how long ago last_seen was (see getPresenceStatusClass()). */
    .avatar.status-day:before { background-color: var(--falcon-info); }
    .avatar.status-week:before { background-color: var(--falcon-warning); }
    .avatar.status-fortnight:before { background-color: #c1440e; }
    .avatar.status-month:before { background-color: var(--falcon-danger); }
    .avatar.status-year:before { background-color: var(--falcon-secondary); }

    /* Sits just above the bubble on hover instead of overlapping its text. */
    .hover-actions-trigger .message-actions-dropdown.hover-actions {
        top: -22px;
        right: 0;
        left: auto;
    }
</style>
<div class="card card-chat overflow-hidden">
    <div class="card-body d-flex p-0 h-100">
        <div class="chat-sidebar">
            <div class="contacts-list scrollbar-overlay">
                <div class="nav nav-tabs border-0 flex-column" role="tablist" aria-orientation="vertical">
                    <?php
                    foreach ($users as $index => $user): ?>
                        <?php
                        $isOnline = isset($user['is_online']) ? $user['is_online'] : false;
                        $lastSeen = isset($user['last_seen']) ? $user['last_seen'] : null;
                        $statusText = $isOnline ? 'Online' : getLastSeenText($lastSeen);
                        $statusClass = getPresenceStatusClass($isOnline, $lastSeen);
                        $tickClass = $user['is_read'] ? 'text-success' : 'text-muted';
                        $photo = $user['photo']; // Assume 'Photo' field is always set
                        $avatarSrc = 'profileimages/' . htmlspecialchars($photo, ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="hover-actions-trigger chat-contact nav-item <?php echo $index === 0 ? 'active' : ''; ?>" role="tab" id="chat-link-<?php echo $index; ?>" data-bs-toggle="tab" data-bs-target="#chat-<?php echo $user['id']; ?>" data-index="<?php echo $index; ?>" aria-controls="chat-<?php echo $user['id']; ?>" aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>" onclick="setReceiver(<?php echo $user['id']; ?>, '<?php echo $user['type']; ?>', <?php echo $index; ?>)">
                            <div class="d-flex p-3">
                                <div class="avatar avatar-xl <?php echo $statusClass; ?>">
                                    <img class="rounded-circle" src="<?php echo $avatarSrc; ?>" alt="" />
                                </div>
                                <div class="flex-1 chat-contact-body ms-2 d-md-none d-lg-block">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-0 chat-contact-title"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo ucfirst($user['type']); ?>)</h6>
                                        <span class="message-time fs-11"><?php
                                            $latestMsgTs = $user['latest_message_time'] ? utcToNairobiTimestamp($user['latest_message_time']) : false;
                                            echo $latestMsgTs ? (date('Y-m-d') === date('Y-m-d', $latestMsgTs) ? 'Today' : date('l', $latestMsgTs)) : '';
                                        ?></span>
                                        <span class="<?php echo $tickClass; ?> fas fa-check"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="chat-contact-content pe-3" id="latest-message-<?php echo $index; ?>"><?php echo htmlspecialchars($user['latest_message']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>
            <form class="contacts-search-wrapper">
                <div class="form-group mb-0 position-relative d-md-none d-lg-block w-100 h-100">
                    <input class="form-control form-control-sm chat-contacts-search border-0 h-100" type="text" placeholder="Search contacts ..." /><span class="fas fa-search contacts-search-icon"></span>
                </div>
                <button class="btn btn-sm btn-transparent d-none d-md-inline-block d-lg-none"><span class="fas fa-search fs-10"></span></button>
            </form>
        </div>

        <div class="tab-content card-chat-content">
            <div class="tab-pane card-chat-pane active" id="default-content" role="tabpanel">
                <div class="chat-content-body" style="display: flex; align-items: center; justify-content: center; height: 100%;">
                    <div class="text-center">
                        <audio id="dingSound" src="audio/livechat.mp3" preload="auto"></audio>
                        <img src="assets/img/illustrations/settings.png" alt="Select a chat" style="max-width: 100%; height: auto;">
                        <h5 class="mt-3">Please select a chat to start messaging</h5>
                    </div>
                </div>
            </div>
            <?php foreach ($users as $index => $user): ?>
                <div class="tab-pane card-chat-pane" id="chat-<?php echo $user['id']; ?>" role="tabpanel" aria-labelledby="chat-link-<?php echo $index; ?>">
                    <div class="chat-content-header">
                        <div class="row flex-between-center">
                            <div class="col-6 col-sm-8 d-flex align-items-center">
                                <a class="pe-3 text-700 d-md-none contacts-list-show" href="#!"><div class="fas fa-chevron-left"></div></a>
                                <div class="min-w-0">
                                    <h5 class="mb-0 text-truncate fs-9"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo ucfirst($user['type']); ?>)</h5>
                                    <div class="fs-11 text-400"><?php echo $statusText; ?></div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4 text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="#!"
                                               onclick="refreshChat(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-sync-alt me-2"></i>Refresh
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#!"
                                               onclick="openSharedFiles(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-paperclip me-2"></i>Shared Files
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#!"
                                               onclick="openLinkTaskModal(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-tasks me-2"></i>Link to Task
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="chat-content-body" style="display: inherit;">
                        <div class="chat-content-scroll-area scrollbar" id="chat-content-<?php echo $user['id']; ?>">
                            <!-- Dynamic chat messages will be loaded here -->
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div id="typing-indicator" class="fs-11 text-muted fst-italic px-3 d-none"></div>

            <div id="linked-task-banner" class="fs-11 px-3 py-1 bg-info-subtle d-none d-flex align-items-center justify-content-between">
                <span><i class="fas fa-tasks me-1"></i><span id="linked-task-label"></span></span>
                <button type="button" class="btn-close btn-close-sm" onclick="clearLinkedTask()" title="Unlink task"></button>
            </div>

            <form class="chat-editor-area d-none" method="post" action="send_message" enctype="multipart/form-data" onsubmit="return submitMessage();">
<?= csrf_field() ?>
                <div class="emojiarea-editor outline-none scrollbar" contenteditable="true" id="messageInput"></div>
                <input type="hidden" name="message" id="messageField">
                <input type="hidden" name="receiver_id" id="receiverIdField">
                <input type="hidden" name="receiver_type" id="receiverTypeField">
                <input type="file" id="chat-file-upload" name="file" class="d-none" accept="image/*,.heic,.heif,.avif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip">
                <label class="chat-file-upload cursor-pointer" for="chat-file-upload"><span class="fas fa-paperclip"></span></label>
                <div id="file-preview" class="file-preview" style="display: none;"></div> <!-- Preview area -->
                <div class="chat-emoji-picker">
                    <div class="btn btn-link emoji-icon" data-emoji-mart="data-emoji-mart" data-emoji-mart-input-target="#messageInput"><span class="far fa-laugh-beam"></span></div>
                </div>
                <button class="btn btn-sm btn-send shadow-none" type="submit" id="sendButton">Send</button>
            </form>
        </div>
    </div>
</div>

<!-- Shared Files/Media modal (single instance, reused for whichever chat is open) -->
<div class="modal fade" id="sharedFilesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paperclip me-2"></i>Shared Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sharedFilesModalBody">
                <div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Link to Task modal (single instance, reused for whichever chat is open) -->
<div class="modal fade" id="linkTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-tasks me-2"></i>Link to Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="linkTaskModalBody">
                <div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Message modal -->
<div class="modal fade" id="editMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pen me-2"></i>Edit Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <textarea class="form-control" id="editMessageTextarea" rows="4"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmEditMessageBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Message confirmation modal -->
<div class="modal fade" id="deleteMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Delete Message</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete this message? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteMessageBtn">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Escape untrusted text before inserting into innerHTML (prevents stored XSS via chat messages)
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Single muted tick = sent; double green tick = read by the recipient.
    function readReceiptTicksHtml(isRead) {
        return isRead
            ? '<span class="text-success fas fa-check-double ms-1" title="Read"></span>'
            : '<span class="text-muted fas fa-check ms-1" title="Sent"></span>';
    }

    // Edit/delete hover buttons, shown only on the sender's own messages.
    function messageActionsHtml(messageId) {
        return `
        <div class="hover-actions message-actions-dropdown dropup me-1">
            <button type="button" class="btn btn-tertiary border-300 btn-sm p-1" data-bs-toggle="dropdown" aria-expanded="false" title="Message actions">
                <i class="fas fa-ellipsis-v fs-11"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#!" onclick="editMessage(${messageId}, this); return false;"><i class="fas fa-pen me-2"></i>Edit</a></li>
                <li><a class="dropdown-item text-danger" href="#!" onclick="deleteMessage(${messageId}, this); return false;"><i class="fas fa-trash me-2"></i>Delete</a></li>
            </ul>
        </div>
    `;
    }

    let pendingEditMessageId = null;
    let pendingEditBubble = null;
    let pendingEditTrigger = null;

    function editMessage(messageId, btn) {
        if (!messageId) return;
        const bubble = btn.closest('.hover-actions-trigger').querySelector('.message-text');
        if (!bubble) return;

        pendingEditMessageId = messageId;
        pendingEditBubble = bubble;
        pendingEditTrigger = btn;

        document.getElementById('editMessageTextarea').value = bubble.textContent;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editMessageModal')).show();
    }

    let pendingDeleteMessageId = null;
    let pendingDeleteTrigger = null;

    function deleteMessage(messageId, btn) {
        if (!messageId) return;
        pendingDeleteMessageId = messageId;
        pendingDeleteTrigger = btn;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteMessageModal')).show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const confirmEditBtn = document.getElementById('confirmEditMessageBtn');
        if (confirmEditBtn) {
            confirmEditBtn.addEventListener('click', function() {
                if (!pendingEditMessageId || !pendingEditBubble) return;
                const newText = document.getElementById('editMessageTextarea').value;
                const currentText = pendingEditBubble.textContent;
                const editModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editMessageModal'));
                if (newText.trim() === '' || newText === currentText) {
                    editModal.hide();
                    return;
                }

                const formData = new FormData();
                formData.append('message_id', pendingEditMessageId);
                formData.append('message', newText);
                formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);

                fetch('edit_message', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            pendingEditBubble.textContent = newText;
                            const chatMessage = pendingEditTrigger.closest('.hover-actions-trigger').querySelector('.chat-message');
                            if (chatMessage && !chatMessage.querySelector('.edited-tag')) {
                                const tag = document.createElement('span');
                                tag.className = 'edited-tag fs-11 fst-italic ms-1 opacity-75';
                                tag.textContent = '(edited)';
                                pendingEditBubble.insertAdjacentElement('afterend', tag);
                            }
                            editModal.hide();
                        } else {
                            alert('Failed to edit message: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => alert('Error editing message: ' + error.message));
            });
        }

        const confirmDeleteBtn = document.getElementById('confirmDeleteMessageBtn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function() {
                if (!pendingDeleteMessageId) return;
                const deleteModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteMessageModal'));

                const formData = new FormData();
                formData.append('message_id', pendingDeleteMessageId);
                formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);

                fetch('delete_message', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        deleteModal.hide();
                        if (data.status === 'success') {
                            const messageRow = pendingDeleteTrigger.closest('[data-message-id]');
                            if (messageRow) messageRow.remove();
                        } else {
                            alert('Failed to delete message: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        deleteModal.hide();
                        alert('Error deleting message: ' + error.message);
                    });
            });
        }
    });

    // chat_messages.timestamp is stored in UTC (MySQL's NOW() reflects the DB
    // server's own timezone). A bare `new Date("YYYY-MM-DD HH:mm:ss")` is
    // parsed by the browser as LOCAL time, not UTC, which threw every
    // displayed time off by the Nairobi UTC+3 offset. Marking it as UTC
    // explicitly lets the browser correctly convert to the viewer's own
    // local time from there.
    function parseDbTimestamp(ts) {
        if (!ts) return new Date();
        return new Date(ts.replace(' ', 'T') + 'Z');
    }

    // Starts at "now" rather than the epoch, so the first poll cycle doesn't
    // fire a desktop notification for every already-unread message (those
    // are already reflected by the sidebar's unread indicators from the
    // initial page load).
    let lastTimestamp = '<?php echo gmdate('Y-m-d H:i:s'); ?>';
    let linkedTaskId = null;
    let linkedTaskEncodedId = null;

    const CONTACT_NAMES = <?php echo json_encode(array_column($users, 'username', 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    function setReceiver(id, type, index) {
        document.getElementById('receiverIdField').value = id;
        document.getElementById('receiverTypeField').value = type;

        // Hide the default content
        document.getElementById('default-content').classList.remove('active');

        // Show the selected chat content
        document.querySelectorAll('.card-chat-pane').forEach(pane => {
            pane.classList.remove('active');
        });
        document.getElementById(`chat-${id}`).classList.add('active');

        // Only show the message editor once a chat is actually selected
        const editorArea = document.querySelector('.chat-editor-area');
        if (editorArea) {
            editorArea.classList.remove('d-none');
        }

        // Reset stale typing indicator from whatever was open before
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.classList.add('d-none');
        }

        // A linked task is specific to the conversation it was set in
        clearLinkedTask();

        fetchMessages(id, type, index);
    }

    function refreshChat(userId) {
        const receiverType = document.getElementById('receiverTypeField').value;
        const tabLink = document.querySelector(`[data-bs-target="#chat-${userId}"]`);
        const index = tabLink ? tabLink.dataset.index : 0;
        fetchMessages(userId, receiverType, index);
    }

    function fetchMessages(userId, userType, index) {
        fetch(`fetch_messages?user_id=${userId}&user_type=${userType}`)
            .then(response => response.json())
            .then(messages => {
                updateChatContent(userId, messages, index);
                lastTimestamp = messages.length ? messages[messages.length - 1].timestamp : lastTimestamp;

                // Update the read status of messages
                updateReadStatus(userId);
            })
            .catch(error => {
                console.error('Error fetching messages:', error);
            });
    }

    function updateChatContent(userId, messages, index) {
        const chatContent = document.getElementById(`chat-content-${userId}`);
        chatContent.innerHTML = '';

        let lastDate = '';

        messages.forEach(message => {
            const messageDate = parseDbTimestamp(message.timestamp);
            const messageDateString = messageDate.toLocaleDateString();

            // Check if the date has changed
            if (messageDateString !== lastDate) {
                lastDate = messageDateString;

                const dateElement = document.createElement('div');
                dateElement.classList.add('text-center', 'fs-11', 'text-500', 'mt-3');
                dateElement.innerHTML = `<span>${messageDate.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                })}</span>`;
                chatContent.appendChild(dateElement);
            }

            const isCurrentUser = message.sender_id == <?php echo $currentUser['id']; ?>;
            const messageElement = document.createElement('div');
            messageElement.classList.add('d-flex', 'p-3', isCurrentUser ? 'justify-content-end' : 'justify-content-start');
            if (message.id) {
                messageElement.dataset.messageId = message.id;
            }
            messageElement.innerHTML = `
        <div class="flex-1 ${isCurrentUser ? 'd-flex justify-content-end' : ''}">
            <div class="w-100 w-xxl-75">
                <div class="hover-actions-trigger d-flex ${isCurrentUser ? 'flex-end-center' : 'align-items-center'}">
                    ${isCurrentUser && message.id ? messageActionsHtml(message.id) : ''}
                    <div class="chat-message ${isCurrentUser ? 'bg-primary text-white' : 'bg-info text-white'} p-2 rounded-2">
                        ${taskChipHtml(message.related_task_id, message.encoded_task_id)}<span class="message-text">${escapeHtml(message.message)}</span>
                        ${message.is_edited ? '<span class="edited-tag fs-11 fst-italic ms-1 opacity-75">(edited)</span>' : ''}
                        ${fileAttachmentHtml(message.file_url, message.original_file_name)}
                    </div>
                </div>
                <div class="text-400 fs-11 ${isCurrentUser ? 'text-end' : ''}">
                    <span>${messageDate.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}</span>
                    ${isCurrentUser ? readReceiptTicksHtml(message.is_read) : ''}
                </div>
            </div>
        </div>
    `;
            chatContent.appendChild(messageElement);
        });

        chatContent.scrollTop = chatContent.scrollHeight;

        const latestMessage = messages.length > 0 ? messages[messages.length - 1].message : "No messages yet.";
        document.getElementById(`latest-message-${index}`).innerText = latestMessage;

        const lightbox = GLightbox(); // Initialize GLightbox
    }

    function pollMessages() {
        refreshReadReceipts();
        refreshMessageEdits();
        refreshTypingIndicator();

        fetch(`poll_messages?last_timestamp=${lastTimestamp}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(messages => {
                if (messages.length > 0) {
                    const receiverId = document.getElementById('receiverIdField').value;
                    const chatContent = receiverId ? document.getElementById(`chat-content-${receiverId}`) : null;
                    let sawMessageFromOpenReceiver = false;

                    messages.forEach(message => {
                        // Desktop notification regardless of which (if any)
                        // conversation is currently open.
                        notifyNewMessage(message);

                        // Only render into the DOM if this message actually
                        // belongs to the conversation that's currently open -
                        // otherwise a message from someone else would land
                        // inside the wrong thread.
                        if (!receiverId || message.sender_id != receiverId) {
                            return;
                        }
                        sawMessageFromOpenReceiver = true;

                        const messageElement = document.createElement('div');
                        const isCurrentUser = message.sender_id == <?php echo $currentUser['id']; ?>;
                        messageElement.classList.add('d-flex', 'p-3', isCurrentUser ? 'justify-content-end' : 'justify-content-start');
                        if (message.id) {
                            messageElement.dataset.messageId = message.id;
                        }
                        messageElement.innerHTML = `
                    <div class="flex-1 ${isCurrentUser ? 'd-flex justify-content-end' : ''}">
                        <div class="w-100 w-xxl-75">
                            <div class="hover-actions-trigger d-flex ${isCurrentUser ? 'flex-end-center' : 'align-items-center'}">
                                ${isCurrentUser && message.id ? messageActionsHtml(message.id) : ''}
                                <div class="chat-message ${isCurrentUser ? 'bg-primary text-white' : 'bg-info text-white'} p-2 rounded-2">
                                    ${taskChipHtml(message.related_task_id, message.encoded_task_id)}<span class="message-text">${escapeHtml(message.message)}</span>
                                    ${message.is_edited ? '<span class="edited-tag fs-11 fst-italic ms-1 opacity-75">(edited)</span>' : ''}
                                    ${fileAttachmentHtml(message.file_url, message.original_file_name)}
                                </div>
                            </div>
                            <div class="text-400 fs-11 ${isCurrentUser ? 'text-end' : ''}">
                                <span>${parseDbTimestamp(message.timestamp).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}</span>
                                ${isCurrentUser ? readReceiptTicksHtml(message.is_read) : ''}
                            </div>
                        </div>
                    </div>
                `;
                        if (chatContent) {
                            chatContent.appendChild(messageElement);
                            chatContent.scrollTop = chatContent.scrollHeight;
                        }
                    });

                    lastTimestamp = messages[messages.length - 1].timestamp;

                    if (sawMessageFromOpenReceiver) {
                        const dingSound = document.getElementById('dingSound');
                        if (dingSound) dingSound.play().catch(() => {});
                        updateReadStatus(receiverId);
                    }
                }

            })
            .catch(error => {
                console.error('Error polling messages:', error);
            });
    }

    // Flips single ticks to double green ticks on my own sent messages once
    // the currently-open partner has read them.
    // Desktop notification for an incoming message, unless the viewer is
    // already actively looking at that exact conversation.
    function notifyNewMessage(message) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        const receiverId = document.getElementById('receiverIdField').value;
        if (document.hasFocus() && receiverId && message.sender_id == receiverId) return;

        const senderName = CONTACT_NAMES[message.sender_id] || 'Someone';
        const body = message.message ? message.message : (message.file_url ? 'Sent a file' : 'New message');

        const notification = new Notification(`New message from ${senderName}`, {
            body: body,
            icon: 'assets/img/favicons/favicon-32x32.png',
            tag: `chat-message-${message.sender_id}`
        });
        notification.onclick = function() {
            window.focus();
            notification.close();
        };
    }

    function refreshReadReceipts() {
        const receiverId = document.getElementById('receiverIdField').value;
        if (!receiverId) return;

        fetch(`get_sent_read_status?partner_id=${encodeURIComponent(receiverId)}`)
            .then(response => response.json())
            .then(data => {
                if (!data || data.status !== 'success' || !Array.isArray(data.read_ids)) return;

                data.read_ids.forEach(id => {
                    const messageEl = document.querySelector(`[data-message-id="${id}"]`);
                    if (!messageEl) return;
                    const tick = messageEl.querySelector('.fa-check, .fa-check-double');
                    if (tick && !tick.classList.contains('fa-check-double')) {
                        tick.outerHTML = readReceiptTicksHtml(true);
                    }
                });
            })
            .catch(error => {
                console.error('Error refreshing read receipts:', error);
            });
    }

    // Syncs edits made on either side of the conversation into the DOM -
    // without this, editing a message only updates the editor's own screen,
    // and the other party never sees the new text until a full reload.
    function refreshMessageEdits() {
        const receiverId = document.getElementById('receiverIdField').value;
        if (!receiverId) return;

        fetch(`get_message_edits?partner_id=${encodeURIComponent(receiverId)}`)
            .then(response => response.json())
            .then(data => {
                if (!data || data.status !== 'success' || !Array.isArray(data.edits)) return;

                data.edits.forEach(edit => {
                    const messageEl = document.querySelector(`[data-message-id="${edit.id}"]`);
                    if (!messageEl) return;
                    const textEl = messageEl.querySelector('.message-text');
                    if (!textEl) return;

                    if (textEl.textContent !== edit.message) {
                        textEl.textContent = edit.message;
                    }

                    const chatMessage = messageEl.querySelector('.chat-message');
                    if (chatMessage && !chatMessage.querySelector('.edited-tag')) {
                        const tag = document.createElement('span');
                        tag.className = 'edited-tag fs-11 fst-italic ms-1 opacity-75';
                        tag.textContent = '(edited)';
                        textEl.insertAdjacentElement('afterend', tag);
                    }
                });
            })
            .catch(error => {
                console.error('Error refreshing message edits:', error);
            });
    }

    function sendTypingSignal() {
        const receiverId = document.getElementById('receiverIdField').value;
        const receiverType = document.getElementById('receiverTypeField').value;
        if (!receiverId || !receiverType) return;

        const formData = new FormData();
        formData.append('receiver_id', receiverId);
        formData.append('receiver_type', receiverType);

        fetch('set_typing_status', { method: 'POST', body: formData })
            .catch(error => console.error('Error sending typing signal:', error));
    }

    function refreshTypingIndicator() {
        const receiverId = document.getElementById('receiverIdField').value;
        const indicator = document.getElementById('typing-indicator');
        if (!receiverId || !indicator) return;

        fetch(`get_typing_status?partner_id=${encodeURIComponent(receiverId)}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.status === 'success' && data.typing) {
                    indicator.textContent = 'Typing...';
                    indicator.classList.remove('d-none');
                } else {
                    indicator.classList.add('d-none');
                }
            })
            .catch(error => console.error('Error checking typing status:', error));
    }

    // File-type icon for non-image shared files (same map used for the
    // attachment preview before sending).
    const SHARED_FILE_ICON_MAP = {
        pdf: 'fa-file-pdf text-danger',
        doc: 'fa-file-word text-primary', docx: 'fa-file-word text-primary',
        xls: 'fa-file-excel text-success', xlsx: 'fa-file-excel text-success',
        ppt: 'fa-file-powerpoint text-warning', pptx: 'fa-file-powerpoint text-warning',
        zip: 'fa-file-archive text-secondary'
    };
    const SHARED_FILE_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'avif', 'bmp', 'tiff', 'tif'];

    // Renders a message's attachment as an inline image preview if it's a
    // photo, otherwise as a file-type icon link (previously this always
    // rendered an <img>, so PDFs/docs/zips showed a broken image icon).
    function fileAttachmentHtml(fileUrl, originalFileName) {
        if (!fileUrl) return '';
        const displayName = originalFileName || fileUrl;
        const extension = (fileUrl.split('.').pop() || '').toLowerCase();
        const href = `taskfiles/${fileUrl}`;
        if (SHARED_FILE_IMAGE_EXTENSIONS.includes(extension)) {
            return `<a href="${href}" class="glightbox" data-gallery="gallery-3" title="${escapeHtml(displayName)}"><img class="rounded" src="${href}" alt="${escapeHtml(displayName)}" width="150"></a>`;
        }
        return `<a href="${href}" target="_blank" download="${escapeHtml(displayName)}" class="d-flex align-items-center text-decoration-none bg-light rounded p-2 mt-1" style="max-width:200px;"><i class="fas ${SHARED_FILE_ICON_MAP[extension] || 'fa-file text-secondary'} fa-lg me-2"></i><span class="text-truncate fs-11 text-dark">${escapeHtml(displayName)}</span></a>`;
    }

    function openSharedFiles(partnerId) {
        const modalBody = document.getElementById('sharedFilesModalBody');
        modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';

        const modalEl = document.getElementById('sharedFilesModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        fetch(`get_shared_files?partner_id=${encodeURIComponent(partnerId)}`)
            .then(response => response.json())
            .then(data => {
                if (!data || data.status !== 'success') {
                    modalBody.innerHTML = '<p class="text-danger text-center">Failed to load shared files.</p>';
                    return;
                }

                if (!data.files || data.files.length === 0) {
                    modalBody.innerHTML = '<p class="text-muted text-center py-4">No files shared yet.</p>';
                    return;
                }

                const grid = document.createElement('div');
                grid.className = 'row g-3';

                data.files.forEach(file => {
                    const displayName = file.original_file_name || file.file_url;
                    const extension = (file.file_url.split('.').pop() || '').toLowerCase();
                    const isImage = SHARED_FILE_IMAGE_EXTENSIONS.includes(extension);
                    const fileHref = `taskfiles/${encodeURIComponent(file.file_url)}`;
                    const dateText = parseDbTimestamp(file.timestamp).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

                    const col = document.createElement('div');
                    col.className = 'col-6 col-md-4';

                    const previewHtml = isImage
                        ? `<a href="${fileHref}" class="glightbox" data-gallery="shared-files" title="${escapeHtml(displayName)}"><img src="${fileHref}" class="rounded w-100" style="height:100px;object-fit:cover;" alt="${escapeHtml(displayName)}" loading="lazy"></a>`
                        : `<a href="${fileHref}" target="_blank" download="${escapeHtml(displayName)}" class="d-flex align-items-center justify-content-center rounded bg-light" style="height:100px;"><i class="fas ${SHARED_FILE_ICON_MAP[extension] || 'fa-file text-secondary'} fa-2x"></i></a>`;

                    col.innerHTML = `
                <div class="border rounded p-2 h-100">
                    ${previewHtml}
                    <div class="fs-11 text-muted mt-1 text-truncate">${escapeHtml(displayName)}</div>
                    <div class="fs-11 text-400">${dateText}</div>
                </div>
            `;
                    grid.appendChild(col);
                });

                modalBody.innerHTML = '';
                modalBody.appendChild(grid);

                if (typeof GLightbox !== 'undefined') {
                    GLightbox({ selector: '#sharedFilesModalBody .glightbox' });
                }
            })
            .catch(error => {
                console.error('Error loading shared files:', error);
                modalBody.innerHTML = '<p class="text-danger text-center">Failed to load shared files.</p>';
            });
    }

    function openLinkTaskModal(partnerId) {
        const modalBody = document.getElementById('linkTaskModalBody');
        modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';

        const modalEl = document.getElementById('linkTaskModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        fetch(`get_linkable_tasks?partner_id=${encodeURIComponent(partnerId)}`)
            .then(response => response.json())
            .then(data => {
                if (!data || data.status !== 'success') {
                    modalBody.innerHTML = '<p class="text-danger text-center">Failed to load tasks.</p>';
                    return;
                }

                if (!data.tasks || data.tasks.length === 0) {
                    modalBody.innerHTML = '<p class="text-muted text-center py-4">No tasks found.</p>';
                    return;
                }

                const list = document.createElement('div');
                list.className = 'list-group';
                data.tasks.forEach(task => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'list-group-item list-group-item-action';
                    item.textContent = `#${task.id} - ${task.topic}`;
                    item.onclick = function() {
                        selectLinkedTask(task.id, task.encoded_id, task.topic);
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    };
                    list.appendChild(item);
                });

                modalBody.innerHTML = '';
                modalBody.appendChild(list);
            })
            .catch(error => {
                console.error('Error loading linkable tasks:', error);
                modalBody.innerHTML = '<p class="text-danger text-center">Failed to load tasks.</p>';
            });
    }

    function selectLinkedTask(taskId, encodedTaskId, topic) {
        linkedTaskId = taskId;
        linkedTaskEncodedId = encodedTaskId;
        const banner = document.getElementById('linked-task-banner');
        const label = document.getElementById('linked-task-label');
        if (label) label.textContent = `Discussing: Task #${taskId} - ${topic}`;
        if (banner) banner.classList.remove('d-none');
    }

    function clearLinkedTask() {
        linkedTaskId = null;
        linkedTaskEncodedId = null;
        const banner = document.getElementById('linked-task-banner');
        if (banner) banner.classList.add('d-none');
    }

    // Small chip linking a message back to the task it was sent about. encodedTaskId
    // must come from the server (get_linkable_tasks / fetch_messages / poll_messages);
    // there's no client-side way to derive a valid task_id token from the raw id.
    function taskChipHtml(relatedTaskId, encodedTaskId) {
        if (!relatedTaskId) return '';
        if (!encodedTaskId) {
            return `<span class="badge bg-secondary-subtle text-800 d-inline-block mb-1"><i class="fas fa-tasks me-1"></i>Task #${relatedTaskId}</span><br>`;
        }
        return `<a href="view-task?task_id=${encodeURIComponent(encodedTaskId)}" class="badge bg-secondary-subtle text-800 text-decoration-none d-inline-block mb-1"><i class="fas fa-tasks me-1"></i>Task #${relatedTaskId}</a><br>`;
    }

    function updateReadStatus(userId) {
        fetch(`update_read_status?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    console.error(data.message);
                }
            })
            .catch(error => {
                console.error('Error updating read status:', error);
            });
    }


    function submitMessage() {
        const messageInput = document.getElementById('messageInput');
        const messageContent = messageInput.innerText.trim();
        const encodedMessageContent = encodeURIComponent(messageContent); // Encode the message content
        const receiverId = document.getElementById('receiverIdField').value;
        const receiverType = document.getElementById('receiverTypeField').value;
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);

        if (encodedMessageContent) {
            formData.append('message', encodedMessageContent); // Append the encoded message
        }
        formData.append('receiver_id', receiverId);
        formData.append('receiver_type', receiverType);

        const fileInput = document.getElementById('chat-file-upload');
        if (fileInput.files.length > 0) {
            formData.append('file', fileInput.files[0]);
        }

        if (linkedTaskId) {
            formData.append('related_task_id', linkedTaskId);
        }

        const sendButton = document.getElementById('sendButton');
        const originalButtonText = sendButton.innerHTML;
        sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        sendButton.disabled = true;

        fetch('send_message', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const chatContent = document.getElementById(`chat-content-${receiverId}`);
                    const messageElement = document.createElement('div');
                    messageElement.classList.add('d-flex', 'p-3', 'justify-content-end');
                    if (data.message_id) {
                        messageElement.dataset.messageId = data.message_id;
                    }

                    let messageHTML = `
                <div class="flex-1 d-flex justify-content-end">
                    <div class="w-100 w-xxl-75">
                        <div class="hover-actions-trigger d-flex flex-end-center">
                            ${data.message_id ? messageActionsHtml(data.message_id) : ''}
                            <div class="chat-message bg-primary text-white p-2 rounded-2">
                                ${taskChipHtml(linkedTaskId, linkedTaskEncodedId)}<span class="message-text">${escapeHtml(decodeURIComponent(encodedMessageContent))}</span>
            `;

                    if (data.file_url) {
                        messageHTML += fileAttachmentHtml(data.file_url, data.original_file_name);
                    }

                    messageHTML += `
                        </div>
                    </div>
                    <div class="text-400 fs-11 text-end">
                        <span>${new Date().toLocaleTimeString()}</span>
                        ${readReceiptTicksHtml(false)}
                    </div>
                </div>
            </div>
            `;

                    messageElement.innerHTML = messageHTML;
                    chatContent.appendChild(messageElement);
                    chatContent.scrollTop = chatContent.scrollHeight;

                    messageInput.innerText = '';
                    fileInput.value = ''; // Clear the file input
                    hideFilePreview();

                    GLightbox(); // Re-initialize GLightbox for new content
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                alert('An error occurred while sending the message.');
            })
            .finally(() => {
                sendButton.innerHTML = originalButtonText;
                sendButton.disabled = false;
            });

        return false;
    }


    // Allowed: office docs, zip, pdf, and photos (matches
    // validateChatAttachment() in shared-functions.php). Extension-based
    // rather than file.type, since browsers commonly report an empty MIME
    // type for newer photo formats like heic/avif.
    const ALLOWED_CHAT_FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'avif', 'bmp', 'tiff', 'tif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'];
    const MAX_CHAT_FILE_SIZE = 50 * 1024 * 1024; // 50MB

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function clearFilePreview() {
        document.getElementById('chat-file-upload').value = '';
        hideFilePreview();
    }

    function hideFilePreview() {
        const previewContainer = document.getElementById('file-preview');
        previewContainer.style.display = 'none';
        previewContainer.innerHTML = '';
    }

    document.getElementById('chat-file-upload').addEventListener('change', function(event) {
        const file = event.target.files[0];
        const previewContainer = document.getElementById('file-preview');
        previewContainer.innerHTML = '';

        if (!file) {
            hideFilePreview();
            return;
        }

        if (file.size > MAX_CHAT_FILE_SIZE) {
            alert('File size must be less than 50MB');
            event.target.value = '';
            hideFilePreview();
            return;
        }

        const extension = file.name.split('.').pop().toLowerCase();
        if (!ALLOWED_CHAT_FILE_EXTENSIONS.includes(extension)) {
            alert('File type not allowed. Allowed: Word, Excel, PowerPoint, ZIP, PDF, and photos.');
            event.target.value = '';
            hideFilePreview();
            return;
        }

        previewContainer.style.display = 'block';

        const browserRenderableImages = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        const iconMap = {
            pdf: 'fa-file-pdf text-danger',
            doc: 'fa-file-word text-primary', docx: 'fa-file-word text-primary',
            xls: 'fa-file-excel text-success', xlsx: 'fa-file-excel text-success',
            ppt: 'fa-file-powerpoint text-warning', pptx: 'fa-file-powerpoint text-warning',
            zip: 'fa-file-archive text-secondary',
            heic: 'fa-file-image text-info', heif: 'fa-file-image text-info', avif: 'fa-file-image text-info',
            tiff: 'fa-file-image text-info', tif: 'fa-file-image text-info'
        };

        if (browserRenderableImages.includes(extension)) {
            previewContainer.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Processing...';

            const reader = new FileReader();
            reader.onload = function(e) {
                previewContainer.innerHTML = `
                    <div class="d-flex align-items-center border rounded p-2 mb-2">
                        <img src="${e.target.result}" alt="Preview" style="width: 50px; height: 50px; object-fit: cover;" class="me-2 rounded">
                        <div class="flex-1">
                            <div class="fw-bold">${escapeHtml(file.name)}</div>
                            <small class="text-muted">${formatFileSize(file.size)}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFilePreview()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
        } else {
            const iconClass = iconMap[extension] || 'fa-file text-secondary';
            previewContainer.innerHTML = `
                <div class="d-flex align-items-center border rounded p-2 mb-2">
                    <i class="fas ${iconClass} fa-2x me-2"></i>
                    <div class="flex-1">
                        <div class="fw-bold">${escapeHtml(file.name)}</div>
                        <small class="text-muted">${formatFileSize(file.size)}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFilePreview()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }
    });

    document.querySelector('.chat-contacts-search').addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const contacts = document.querySelectorAll('.chat-contact');

        contacts.forEach(contact => {
            const name = contact.querySelector('.chat-contact-title').textContent.toLowerCase();
            if (name.includes(query)) {
                contact.style.display = 'flex';
            } else {
                contact.style.display = 'none';
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        pollMessages(); // Start polling messages
        setInterval(pollMessages, 3000); // Poll every 3 seconds

        // Show the default content when the page loads
        document.getElementById('default-content').classList.add('active');

        // Typing indicator: debounced poke while actively typing
        const messageInputEl = document.getElementById('messageInput');
        if (messageInputEl) {
            let typingDebounce = null;
            messageInputEl.addEventListener('input', function() {
                if (typingDebounce) return;
                sendTypingSignal();
                typingDebounce = setTimeout(() => { typingDebounce = null; }, 1500);
            });
        }
    });
</script>

<?php
include "footer.php";
?>
