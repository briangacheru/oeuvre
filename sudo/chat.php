<?php include "head.php";
?>
    <title>iTasker | Chat</title>
<?php
include "navi.php";

// Enhanced session and security check
if (!isset($_SESSION['odmsaid']) || empty($_SESSION['odmsaid'])) {
    header('Location: login');
    exit();
}

// Generate CSRF token for forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$aid = $_SESSION['odmsaid'];

// Enhanced error handling and database connection check
if (!isset($con) || !$con) {
    die('Database connection failed');
}

/**
 * Get current user with prepared statement (SECURITY FIX)
 */
function getCurrentUser($con, $email) {
    $stmt = mysqli_prepare($con, "
        SELECT id, 'admin' as type, is_online, last_seen, username FROM tbladmin WHERE email = ?
        UNION 
        SELECT id, 'writer' as type, is_online, last_seen, username FROM tblwriters WHERE email = ?
    ");

    if (!$stmt) {
        error_log("MySQL prepare failed: " . mysqli_error($con));
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $email, $email);

    if (!mysqli_stmt_execute($stmt)) {
        error_log("MySQL execute failed: " . mysqli_stmt_error($stmt));
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $user;
}

/**
 * Get chat users with optimized query (PERFORMANCE FIX)
 */
function getChatUsers($con, $currentUserId, $currentUserType) {
    $users = [];

    if ($currentUserType !== 'admin') {
        return $users; // Only admins can see writers for now
    }

    // Optimized single query to get writers with unread counts and latest messages
    $stmt = mysqli_prepare($con, "
        SELECT 
            w.id, 
            w.username, 
            w.Photo as photo, 
            w.is_online, 
            w.last_seen,
            COALESCE(unread.unread_count, 0) as unread_count,
            latest.latest_message,
            latest.latest_timestamp,
            latest.is_read as latest_message_read
        FROM tblwriters w
        LEFT JOIN (
            SELECT 
                sender_id, 
                COUNT(*) as unread_count
            FROM chat_messages 
            WHERE receiver_id = ? AND is_read = 0
            GROUP BY sender_id
        ) unread ON w.id = unread.sender_id
        LEFT JOIN (
            SELECT 
                user_id,
                message as latest_message,
                timestamp as latest_timestamp,
                is_read
            FROM (
                SELECT 
                    CASE 
                        WHEN sender_id = ? THEN receiver_id 
                        ELSE sender_id 
                    END as user_id,
                    message,
                    timestamp,
                    is_read,
                    ROW_NUMBER() OVER (
                        PARTITION BY CASE 
                            WHEN sender_id = ? THEN receiver_id 
                            ELSE sender_id 
                        END 
                        ORDER BY timestamp DESC
                    ) as rn
                FROM chat_messages 
                WHERE sender_id = ? OR receiver_id = ?
            ) ranked_messages
            WHERE rn = 1
        ) latest ON w.id = latest.user_id
        WHERE w.id != ? AND w.is_deleted = 0 AND w.is_active = 1 AND w.is_verified = 1
        ORDER BY latest.latest_timestamp DESC, w.username ASC
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iiiiii',
            $currentUserId, $currentUserId, $currentUserId,
            $currentUserId, $currentUserId, $currentUserId
        );

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);

            while ($writer = mysqli_fetch_assoc($result)) {
                $users[] = [
                    'id' => (int)$writer['id'],
                    'username' => htmlspecialchars($writer['username'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'is_online' => isRecentlyOnline($writer['last_seen']),
                    'last_seen' => $writer['last_seen'],
                    'type' => 'writer',
                    'photo' => $writer['photo'] ?? 'default.jpg',
                    'unread_count' => (int)$writer['unread_count'],
                    'latest_message' => $writer['latest_message'] ?? "No messages yet.",
                    'latest_message_time' => $writer['latest_timestamp'],
                    'latest_message_read' => (bool)$writer['latest_message_read']
                ];
            }
        } else {
            error_log("Failed to fetch chat users: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    }

    return $users;
}

/**
 * Enhanced status text generation - last_seen wording/timezone handling
 * matches sudo/writer.php (see getLastSeenText() in shared-functions.php)
 */
function getStatusText($isOnline, $lastSeen) {
    if ($isOnline) {
        return 'Online';
    }

    return getLastSeenText($lastSeen);
}

// Main execution with error handling
try {
    $currentUser = getCurrentUser($con, $aid);
    if (!$currentUser) {
        throw new Exception('User not found or database error');
    }

    $currentUserId = (int)$currentUser['id'];
    $currentUserType = $currentUser['type'];
    $users = getChatUsers($con, $currentUserId, $currentUserType);

} catch (Exception $e) {
    error_log('Chat initialization error: ' . $e->getMessage());
    echo '<div class="alert alert-danger">Error loading chat. Please refresh the page.</div>';
    exit();
}
?>

    <div class="card shadow-none border mb-3">
        <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);"></div>
        <div class="card-header z-1">
            <div class="row flex-between-center gx-0">
                <div class="col-lg-auto d-flex align-items-center">
                    <h4 class="mb-0 text-primary fw-bold">My <span class="text-info fw-medium">Chats</span></h4>
                </div>
                <div class="col-lg-auto">
                    <?php $onlineCount = count(array_filter($users, function($u) { return $u['is_online']; })); ?>
                    <span class="badge rounded-pill ms-2 badge-subtle-success"><?php echo $onlineCount; ?> online</span>
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
                        <?php if (empty($users)): ?>
                            <div class="p-3 text-center text-muted">
                                <i class="fas fa-comments fa-2x mb-2"></i>
                                <p>No contacts available</p>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($users as $index => $user): ?>
                            <?php
                            $statusText = getStatusText($user['is_online'], $user['last_seen']);
                            $statusClass = getPresenceStatusClass($user['is_online'], $user['last_seen']);
                            $avatarSrc = '../profileimages/' . htmlspecialchars($user['photo'], ENT_QUOTES, 'UTF-8');
                            $unreadCount = $user['unread_count'];

                            // Ensure avatar file exists, otherwise use default
                            if (!file_exists($avatarSrc) || empty($user['photo'])) {
                                $avatarSrc = '../profileimages/default.jpg';
                            }
                            ?>
                            <div class="hover-actions-trigger chat-contact nav-item <?php echo $index === 0 ? 'active' : ''; ?>"
                                 role="tab"
                                 id="chat-link-<?php echo $index; ?>"
                                 data-bs-toggle="tab"
                                 data-bs-target="#chat-<?php echo $user['id']; ?>"
                                 data-index="<?php echo $index; ?>"
                                 aria-controls="chat-<?php echo $user['id']; ?>"
                                 aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                                 onclick="setReceiver(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['type']); ?>', <?php echo $index; ?>)">

                                <div class="d-flex p-3">
                                    <div class="avatar avatar-xl <?php echo $statusClass; ?>">
                                        <img class="rounded-circle"
                                             src="<?php echo $avatarSrc; ?>"
                                             alt="<?php echo htmlspecialchars($user['username']); ?>"
                                             onerror="this.src='../profileimages/default.jpg'" />
                                    </div>
                                    <div class="flex-1 chat-contact-body ms-2 d-md-none d-lg-block">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="mb-0 chat-contact-title">
                                                <?php echo htmlspecialchars($user['username']); ?>
                                                <small class="text-muted">(<?php echo ucfirst($user['type']); ?>)</small>
                                            </h6>
                                            <div class="d-flex align-items-center">
                                                <?php if ($unreadCount > 0): ?>
                                                    <span class="badge bg-info me-2"><?php echo $unreadCount; ?></span>
                                                <?php endif; ?>
                                                <span class="message-time fs-11">
                                                <?php
                                                if ($user['latest_message_time']) {
                                                    $messageTime = utcToNairobiTimestamp($user['latest_message_time']);
                                                    echo date('Y-m-d') === date('Y-m-d', $messageTime) ?
                                                        date('g:i A', $messageTime) :
                                                        date('M j', $messageTime);
                                                }
                                                ?>
                                            </span>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="chat-contact-content pe-3 text-truncate" id="latest-message-<?php echo $index; ?>">
                                                <?php echo htmlspecialchars($user['latest_message']); ?>
                                                <?php if ($user['latest_message_read']): ?>
                                                    <span class="text-success">
                                                    <i class="fas fa-check ms-1"></i>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="fs-11 text-400">
                                                <?php echo $statusText; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Enhanced search with better UX -->
                <form class="contacts-search-wrapper">
                    <div class="form-group mb-0 position-relative d-md-none d-lg-block w-100 h-100">
                        <input class="form-control form-control-sm chat-contacts-search border-0 h-100"
                               type="text"
                               placeholder="Search contacts..."
                               autocomplete="off" />
                        <span class="fas fa-search contacts-search-icon"></span>
                    </div>
                    <button class="btn btn-sm btn-transparent d-none d-md-inline-block d-lg-none" type="button">
                        <span class="fas fa-search fs-10"></span>
                    </button>
                </form>
            </div>

            <div class="tab-content card-chat-content">
                <!-- Enhanced default content -->
                <div class="tab-pane card-chat-pane active" id="default-content" role="tabpanel">
                    <div class="chat-content-body" style="display: flex; align-items: center; justify-content: center; height: 100%;">
                        <div class="text-center">
                            <audio id="dingSound" preload="auto">
                                <source src="../audio/livechat.mp3" type="audio/mpeg">
                                <source src="../audio/livechat.ogg" type="audio/ogg">
                                Your browser does not support the audio element.
                            </audio>
                            <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">Select a chat to start messaging</h5>
                            <p class="text-muted">Choose from your contacts on the left to begin</p>
                        </div>
                    </div>
                </div>

                <?php foreach ($users as $index => $user): ?>
                    <?php $statusText = getStatusText($user['is_online'], $user['last_seen']); ?>
                    <div class="tab-pane card-chat-pane"
                         id="chat-<?php echo $user['id']; ?>"
                         role="tabpanel"
                         aria-labelledby="chat-link-<?php echo $index; ?>">

                        <!-- Enhanced chat header -->
                        <div class="chat-content-header">
                            <div class="row flex-between-center">
                                <div class="col-6 col-sm-8 d-flex align-items-center">
                                    <a class="pe-3 text-700 d-md-none contacts-list-show" href="#!">
                                        <div class="fas fa-chevron-left"></div>
                                    </a>
                                    <div class="min-w-0">
                                        <h5 class="mb-0 text-truncate fs-9">
                                            <a class="text-900" href="writer.php?writerID=<?php echo encode_writer_id($user['id']); ?>">
                                                <?php echo htmlspecialchars($user['username']); ?>
                                            </a>
                                            <small class="text-muted">(<?php echo ucfirst($user['type']); ?>)</small>
                                        </h5>
                                        <div class="fs-11 text-400" id="user-status-<?php echo $user['id']; ?>">
                                            <?php echo $statusText; ?>
                                        </div>
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

                        <!-- Chat content area -->
                        <div class="chat-content-body" style="display: inherit;">
                            <div class="chat-content-scroll-area scrollbar" id="chat-content-<?php echo $user['id']; ?>">
                                <div class="text-center py-3">
                                    <div class="spinner"></div>
                                    <small class="text-muted">Loading messages...</small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div id="typing-indicator" class="fs-11 text-muted fst-italic px-3 d-none"></div>

                <div id="linked-task-banner" class="fs-11 px-3 py-1 bg-info-subtle d-none d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-tasks me-1"></i><span id="linked-task-label"></span></span>
                    <button type="button" class="btn-close btn-close-sm" onclick="clearLinkedTask()" title="Unlink task"></button>
                </div>

                <!-- Enhanced message input form with better validation -->
                <form class="chat-editor-area d-none" method="post" enctype="multipart/form-data" onsubmit="return submitMessage(event);">
<?= csrf_field() ?>
                    <!-- CSRF token for security -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="emojiarea-editor outline-none scrollbar"
                         contenteditable="true"
                         id="messageInput"
                         placeholder="Type your message..."
                         data-placeholder="Type your message..."></div>

                    <input type="hidden" name="message" id="messageField">
                    <input type="hidden" name="receiver_id" id="receiverIdField">
                    <input type="hidden" name="receiver_type" id="receiverTypeField">

                    <!-- Enhanced file input with validation -->
                    <input type="file"
                           id="chat-file-upload"
                           name="file"
                           class="d-none"
                           accept="image/*,.heic,.heif,.avif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                           onchange="handleFileUpload(this)">

                    <label class="chat-file-upload cursor-pointer" for="chat-file-upload">
                        <span class="fas fa-paperclip"></span>
                    </label>

                    <!-- File preview area -->
                    <div id="file-preview" class="file-preview" style="display: none;"></div>

                    <!-- Emoji picker button -->

                    <div class="chat-emoji-picker">
                        <div class="btn btn-link emoji-icon" data-emoji-mart="data-emoji-mart" data-emoji-mart-input-target="#messageInput"><span class="far fa-laugh-beam"></span></div>
                    </div>

                    <!-- Enhanced send button -->
                    <button class="btn btn-sm btn-send shadow-none" type="submit" id="sendButton">
                        <span class="send-text">Send</span>
                        <span class="send-spinner spinner d-none"></span>
                    </button>

                    <!-- Error/success message display -->
                    <div id="message-status" class="mt-2" style="display: none;"></div>
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
        // FIXED Chat JavaScript - Simplified and more compatible

        // Starts at "now" rather than the epoch, so the first poll cycle
        // doesn't fire a desktop notification for every already-unread
        // message (those are already reflected by the sidebar's unread
        // badges from the initial page load).
        let lastTimestamp = '<?php echo gmdate('Y-m-d H:i:s'); ?>';
        let currentReceiver = null;
        let currentReceiverType = null;
        let currentIndex = null;
        let pollInterval = null;
        let linkedTaskId = null;
        let linkedTaskEncodedId = null;

        const CONTACT_NAMES = <?php echo json_encode(array_column($users, 'username', 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function setReceiver(id, type, index) {
            currentReceiver = id;
            currentReceiverType = type;
            currentIndex = index;

            document.getElementById('receiverIdField').value = id;
            document.getElementById('receiverTypeField').value = type;

            // Update UI
            document.querySelectorAll('.chat-contact').forEach(contact => {
                contact.classList.remove('active');
            });

            const activeContact = document.getElementById(`chat-link-${index}`);
            if (activeContact) {
                activeContact.classList.add('active');
            }

            // Hide default content and show selected chat
            const defaultContent = document.getElementById('default-content');
            if (defaultContent) {
                defaultContent.classList.remove('active');
            }

            document.querySelectorAll('.card-chat-pane').forEach(pane => {
                pane.classList.remove('active');
            });

            const selectedChat = document.getElementById(`chat-${id}`);
            if (selectedChat) {
                selectedChat.classList.add('active');
            }

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
            updateReadStatus(id);
        }

        function refreshChat(userId) {
            if (currentReceiver != userId || !currentReceiverType) return;
            fetchMessages(currentReceiver, currentReceiverType, currentIndex);
        }

        function fetchMessages(userId, userType, index) {
            const chatContent = document.getElementById(`chat-content-${userId}`);
            if (!chatContent) {
                console.error('Chat content element not found for user:', userId);
                return;
            }

            // Show loading indicator
            chatContent.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm" role="status"></div><small class="text-muted ms-2">Loading messages...</small></div>';

            fetch(`fetch_messages?user_id=${encodeURIComponent(userId)}&user_type=${encodeURIComponent(userType)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text(); // Get as text first to debug
                })
                .then(text => {
                    console.log('Response text:', text); // Debug log

                    try {
                        const messages = JSON.parse(text);

                        if (messages.error) {
                            throw new Error(messages.error);
                        }

                        updateChatContent(userId, messages, index);

                        if (messages.length > 0) {
                            lastTimestamp = messages[messages.length - 1].timestamp;
                        }

                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        console.error('Response text:', text);
                        throw new Error('Invalid response format');
                    }
                })
                .catch(error => {
                    console.error('Error fetching messages:', error);
                    showErrorInChat(userId, 'Failed to load messages: ' + error.message);
                });
        }

        function updateChatContent(userId, messages, index) {
            const chatContent = document.getElementById(`chat-content-${userId}`);
            if (!chatContent) return;

            chatContent.innerHTML = '';

            if (!messages || messages.length === 0) {
                chatContent.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="fas fa-comment-slash fa-2x mb-2"></i>
                <p>No messages yet. Start the conversation!</p>
            </div>
        `;
                return;
            }

            let lastDate = '';
            const currentUserId = <?php echo isset($currentUserId) ? $currentUserId : '0'; ?>;

            messages.forEach(message => {
                try {
                    const messageDate = parseDbTimestamp(message.timestamp);
                    const messageDateString = messageDate.toLocaleDateString();

                    // Add date separator
                    if (messageDateString !== lastDate) {
                        lastDate = messageDateString;

                        const dateElement = document.createElement('div');
                        dateElement.className = 'text-center fs-11 text-500 mt-3 mb-3';

                        let dateText;
                        const today = new Date();
                        const yesterday = new Date(today.getTime() - 24 * 60 * 60 * 1000);

                        if (messageDate.toDateString() === today.toDateString()) {
                            dateText = 'Today';
                        } else if (messageDate.toDateString() === yesterday.toDateString()) {
                            dateText = 'Yesterday';
                        } else {
                            dateText = messageDate.toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                        }

                        dateElement.innerHTML = `<span class="badge bg-light text-dark">${dateText}</span>`;
                        chatContent.appendChild(dateElement);
                    }

                    // Create message element
                    const isCurrentUser = message.sender_id == currentUserId;
                    const messageElement = document.createElement('div');
                    messageElement.className = `d-flex p-3 ${isCurrentUser ? 'justify-content-end' : 'justify-content-start'}`;
                    if (message.id) {
                        messageElement.dataset.messageId = message.id;
                    }

                    const messageTime = parseDbTimestamp(message.timestamp);
                    const timeString = messageTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

                    const fileHtml = fileAttachmentHtml(message.file_url, message.original_file_name);

                    messageElement.innerHTML = `
                <div class="flex-1 ${isCurrentUser ? 'd-flex justify-content-end' : ''}">
                    <div class="w-100 w-xxl-75">
                        <div class="hover-actions-trigger d-flex ${isCurrentUser ? 'flex-end-center' : 'align-items-center'}">
                            ${isCurrentUser && message.id ? messageActionsHtml(message.id) : ''}
                            <div class="chat-message ${isCurrentUser ? 'bg-primary text-white' : 'bg-info text-white'} p-2 rounded-2">
                                ${taskChipHtml(message.related_task_id, message.encoded_task_id)}<span class="message-text">${escapeHtml(message.message)}</span>
                                ${message.is_edited ? '<span class="edited-tag fs-11 fst-italic ms-1 opacity-75">(edited)</span>' : ''}
                                ${fileHtml}
                            </div>
                        </div>
                        <div class="text-400 fs-11 ${isCurrentUser ? 'text-end' : ''} mt-1">
                            <span>${timeString}</span>
                            ${isCurrentUser ? readReceiptTicksHtml(message.is_read) : ''}
                        </div>
                    </div>
                </div>
            `;

                    chatContent.appendChild(messageElement);

                } catch (error) {
                    console.error('Error creating message element:', error, message);
                }
            });

            // Scroll to bottom
            chatContent.scrollTop = chatContent.scrollHeight;

            // Update latest message in sidebar
            if (index !== null && messages.length > 0) {
                const latestMessage = messages[messages.length - 1].message || "File shared";
                const latestMessageEl = document.getElementById(`latest-message-${index}`);
                if (latestMessageEl) {
                    latestMessageEl.textContent = truncateText(latestMessage, 30);
                }
            }

            // Initialize lightbox if available
            if (typeof GLightbox !== 'undefined') {
                GLightbox();
            }
        }

        function showErrorInChat(userId, errorMessage) {
            const chatContent = document.getElementById(`chat-content-${userId}`);
            if (chatContent) {
                chatContent.innerHTML = `
            <div class="text-center py-4 text-danger">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <p>${escapeHtml(errorMessage)}</p>
                <button class="btn btn-sm btn-outline-primary" onclick="fetchMessages(${userId}, '${currentReceiverType}', ${currentIndex})">
                    <i class="fas fa-retry me-1"></i>Try Again
                </button>
            </div>
        `;
            }
        }

        function submitMessage() {
            const messageInput = document.getElementById('messageInput');
            const messageContent = messageInput.textContent.trim();
            const receiverId = document.getElementById('receiverIdField').value;
            const receiverType = document.getElementById('receiverTypeField').value;
            const fileInput = document.getElementById('chat-file-upload');

            // Validation
            if (!messageContent && fileInput.files.length === 0) {
                showMessage('Please enter a message or select a file', 'error');
                messageInput.focus();
                return false;
            }

            if (!receiverId) {
                showMessage('Please select a conversation first', 'error');
                return false;
            }

            // Show loading state
            const sendButton = document.getElementById('sendButton');
            const originalText = sendButton.innerHTML;
            sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            sendButton.disabled = true;

            const formData = new FormData();
            formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
            formData.append('message', messageContent);
            formData.append('receiver_id', receiverId);
            formData.append('receiver_type', receiverType);

            if (fileInput.files.length > 0) {
                formData.append('file', fileInput.files[0]);
            }

            if (linkedTaskId) {
                formData.append('related_task_id', linkedTaskId);
            }
            const sentTaskId = linkedTaskId;
            const sentTaskEncodedId = linkedTaskEncodedId;

            fetch('send_message', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);

                        if (data.status === 'success') {
                            // Clear form
                            messageInput.textContent = '';
                            fileInput.value = '';
                            hideFilePreview();

                            // Add message to current chat immediately
                            const chatContent = document.getElementById(`chat-content-${receiverId}`);
                            if (chatContent) {
                                const messageElement = createMessageElement({
                                    id: data.message_id || null,
                                    sender_id: <?php echo isset($currentUserId) ? $currentUserId : '0'; ?>,
                                    message: messageContent,
                                    timestamp: new Date().toISOString().slice(0, 19).replace('T', ' '),
                                    file_url: data.file_url || null,
                                    original_file_name: data.original_file_name || null,
                                    is_read: false,
                                    related_task_id: sentTaskId,
                                    encoded_task_id: sentTaskEncodedId
                                });

                                chatContent.appendChild(messageElement);
                                chatContent.scrollTop = chatContent.scrollHeight;
                            }

                        } else {
                            throw new Error(data.message || 'Failed to send message');
                        }

                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        console.error('Response text:', text);
                        throw new Error('Invalid response format');
                    }
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                    showMessage('Failed to send message: ' + error.message, 'error');
                })
                .finally(() => {
                    // Reset button
                    sendButton.innerHTML = originalText;
                    sendButton.disabled = false;
                });

            return false;
        }

        function createMessageElement(message) {
            const isCurrentUser = message.sender_id == <?php echo isset($currentUserId) ? $currentUserId : '0'; ?>;
            const messageElement = document.createElement('div');
            messageElement.className = `d-flex p-3 ${isCurrentUser ? 'justify-content-end' : 'justify-content-start'}`;
            if (message.id) {
                messageElement.dataset.messageId = message.id;
            }

            const messageTime = parseDbTimestamp(message.timestamp);
            const timeString = messageTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

            const fileHtml = fileAttachmentHtml(message.file_url, message.original_file_name);

            messageElement.innerHTML = `
        <div class="flex-1 ${isCurrentUser ? 'd-flex justify-content-end' : ''}">
            <div class="w-100 w-xxl-75">
                <div class="hover-actions-trigger d-flex ${isCurrentUser ? 'flex-end-center' : 'align-items-center'}">
                    ${isCurrentUser && message.id ? messageActionsHtml(message.id) : ''}
                    <div class="chat-message ${isCurrentUser ? 'bg-primary text-white' : 'bg-info text-white'} p-2 rounded-2">
                        ${taskChipHtml(message.related_task_id, message.encoded_task_id)}<span class="message-text">${escapeHtml(message.message)}</span>
                        ${message.is_edited ? '<span class="edited-tag fs-11 fst-italic ms-1 opacity-75">(edited)</span>' : ''}
                        ${fileHtml}
                    </div>
                </div>
                <div class="text-400 fs-11 ${isCurrentUser ? 'text-end' : ''} mt-1">
                    <span>${timeString}</span>
                    ${isCurrentUser ? readReceiptTicksHtml(message.is_read) : ''}
                </div>
            </div>
        </div>
    `;

            return messageElement;
        }

        function pollMessages() {
            // poll_messages returns everything addressed to me, regardless of
            // which conversation (if any) is currently open, so a message
            // from someone other than the open contact still surfaces here.
            refreshReadReceipts();
            refreshMessageEdits();
            refreshTypingIndicator();

            fetch(`poll_messages?last_timestamp=${encodeURIComponent(lastTimestamp)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(messages => {
                    if (messages.length > 0) {
                        messages.forEach(message => {
                            if (currentReceiver && message.sender_id == currentReceiver) {
                                const chatContent = document.getElementById(`chat-content-${currentReceiver}`);
                                if (chatContent) {
                                    const messageElement = createMessageElement(message);
                                    chatContent.appendChild(messageElement);
                                    chatContent.scrollTop = chatContent.scrollHeight;
                                }
                            }
                            notifyNewMessage(message);
                        });

                        lastTimestamp = messages[messages.length - 1].timestamp;

                        // Play notification sound
                        const audio = document.getElementById('dingSound');
                        if (audio) {
                            audio.play().catch(() => {}); // Ignore errors
                        }
                    }
                })
                .catch(error => {
                    console.error('Error polling messages:', error);
                });
        }

        // Desktop notification for an incoming message, unless the viewer is
        // already actively looking at that exact conversation.
        function notifyNewMessage(message) {
            if (!('Notification' in window) || Notification.permission !== 'granted') return;
            if (document.hasFocus() && currentReceiver && message.sender_id == currentReceiver) return;

            const senderName = CONTACT_NAMES[message.sender_id] || 'Someone';
            const body = message.message ? message.message : (message.file_url ? 'Sent a file' : 'New message');

            const notification = new Notification(`New message from ${senderName}`, {
                body: body,
                icon: '../assets/img/favicons/favicon-32x32.png',
                tag: `chat-message-${message.sender_id}`
            });
            notification.onclick = function() {
                window.focus();
                notification.close();
            };
        }

        // Flips single ticks to double green ticks on my own sent messages
        // once the currently-open partner has read them.
        function refreshReadReceipts() {
            if (!currentReceiver) return;

            fetch(`get_sent_read_status?partner_id=${encodeURIComponent(currentReceiver)}`)
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
            if (!currentReceiver) return;

            fetch(`get_message_edits?partner_id=${encodeURIComponent(currentReceiver)}`)
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
            if (!currentReceiver || !currentReceiverType) return;

            const formData = new FormData();
            formData.append('receiver_id', currentReceiver);
            formData.append('receiver_type', currentReceiverType);

            fetch('set_typing_status', { method: 'POST', body: formData })
                .catch(error => console.error('Error sending typing signal:', error));
        }

        function refreshTypingIndicator() {
            if (!currentReceiver) return;

            const indicator = document.getElementById('typing-indicator');
            if (!indicator) return;

            fetch(`get_typing_status?partner_id=${encodeURIComponent(currentReceiver)}`)
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
            const href = `../taskfiles/${escapeHtml(fileUrl)}`;
            if (SHARED_FILE_IMAGE_EXTENSIONS.includes(extension)) {
                return `<div class="mt-2"><a href="${href}" class="glightbox" data-gallery="gallery-3" title="${escapeHtml(displayName)}"><img class="rounded" src="${href}" alt="${escapeHtml(displayName)}" width="150" loading="lazy"></a></div>`;
            }
            return `<div class="mt-2"><a href="${href}" target="_blank" download="${escapeHtml(displayName)}" class="d-flex align-items-center text-decoration-none bg-light rounded p-2" style="max-width:200px;"><i class="fas ${SHARED_FILE_ICON_MAP[extension] || 'fa-file text-secondary'} fa-lg me-2"></i><span class="text-truncate fs-11 text-dark">${escapeHtml(displayName)}</span></a></div>`;
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
                        const fileHref = `../taskfiles/${encodeURIComponent(file.file_url)}`;
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
                        modalBody.innerHTML = '<p class="text-muted text-center py-4">No tasks found for this writer.</p>';
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
            fetch(`update_read_status?user_id=${encodeURIComponent(userId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Remove unread badge
                        const contact = document.querySelector(`[data-bs-target="#chat-${userId}"]`);
                        if (contact) {
                            const badge = contact.querySelector('.badge.bg-info');
                            if (badge) badge.remove();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating read status:', error);
                });
        }

        // File upload handling
        // Allowed: office docs, zip, pdf, and photos (matches
        // validateChatAttachment() in shared-functions.php). Extension-based
        // rather than file.type, since browsers commonly report an empty
        // MIME type for newer photo formats like heic/avif.
        const ALLOWED_CHAT_FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'avif', 'bmp', 'tiff', 'tif',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'];
        const MAX_CHAT_FILE_SIZE = 50 * 1024 * 1024; // 50MB

        function handleFileUpload(input) {
            const file = input.files[0];
            if (!file) {
                hideFilePreview();
                return;
            }

            if (file.size > MAX_CHAT_FILE_SIZE) {
                showMessage('File size must be less than 50MB', 'error');
                input.value = '';
                hideFilePreview();
                return;
            }

            const extension = file.name.split('.').pop().toLowerCase();
            if (!ALLOWED_CHAT_FILE_EXTENSIONS.includes(extension)) {
                showMessage('File type not allowed. Allowed: Word, Excel, PowerPoint, ZIP, PDF, and photos.', 'error');
                input.value = '';
                hideFilePreview();
                return;
            }

            showFilePreview(file);
        }

        function showFilePreview(file) {
            const previewContainer = document.getElementById('file-preview');
            previewContainer.style.display = 'block';

            const extension = file.name.split('.').pop().toLowerCase();
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

            function renderWithIcon() {
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
                renderWithIcon();
            }
        }

        function hideFilePreview() {
            const previewContainer = document.getElementById('file-preview');
            previewContainer.style.display = 'none';
            previewContainer.innerHTML = '';
        }

        function clearFilePreview() {
            document.getElementById('chat-file-upload').value = '';
            hideFilePreview();
        }

        // Utility functions
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

        // Edit/delete dropdown, shown only on the sender's own messages.
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

        // chat_messages.timestamp is stored in UTC (MySQL's NOW() reflects the
        // DB server's own timezone). A bare `new Date("YYYY-MM-DD HH:mm:ss")`
        // is parsed by the browser as LOCAL time, not UTC, which threw every
        // displayed time off by the Nairobi UTC+3 offset. Marking it as UTC
        // explicitly lets the browser correctly convert to the viewer's own
        // local time from there.
        function parseDbTimestamp(ts) {
            if (!ts) return new Date();
            return new Date(ts.replace(' ', 'T') + 'Z');
        }

        function truncateText(text, maxLength) {
            if (!text || text.length <= maxLength) return text;
            return text.substring(0, maxLength) + '...';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function showMessage(message, type) {
            const alertClass = type === 'error' ? 'alert-danger' : 'alert-success';
            const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show mt-2" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

            // Remove existing alerts
            document.querySelectorAll('.alert').forEach(alert => alert.remove());

            // Add new alert
            const form = document.querySelector('.chat-editor-area');
            if (form) {
                form.insertAdjacentHTML('afterend', alertHtml);

                // Auto remove after 5 seconds
                setTimeout(() => {
                    document.querySelectorAll('.alert').forEach(alert => alert.remove());
                }, 5000);
            }
        }

        // Contact search
        function setupContactSearch() {
            const searchInput = document.querySelector('.chat-contacts-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase();
                    const contacts = document.querySelectorAll('.chat-contact');

                    contacts.forEach(contact => {
                        const name = contact.querySelector('.chat-contact-title');
                        if (name) {
                            const isVisible = name.textContent.toLowerCase().includes(query);
                            contact.style.display = isVisible ? 'flex' : 'none';
                        }
                    });
                });
            }
        }

        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Chat system initializing...');

            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }

            // Set up contact search
            setupContactSearch();

            // Set up file upload
            const fileInput = document.getElementById('chat-file-upload');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    handleFileUpload(this);
                });
            }

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

            // Start polling for new messages
            setInterval(pollMessages, 3000);

            // Show default content initially
            const defaultContent = document.getElementById('default-content');
            if (defaultContent) {
                defaultContent.classList.add('active');
            }

            console.log('Chat system initialized successfully');
        });
    </script>

<?php include "footer.php"; ?>