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
        WHERE w.id != ?
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
                    'is_online' => (bool)$writer['is_online'],
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
 * Enhanced status text generation
 */
function getStatusText($isOnline, $lastSeen) {
    if ($isOnline) {
        return 'Online';
    }

    if (!$lastSeen || $lastSeen === '0000-00-00 00:00:00') {
        return 'Offline';
    }

    $timeDiff = time() - strtotime($lastSeen);

    if ($timeDiff < 300) { // 5 minutes
        return 'Just now';
    } elseif ($timeDiff < 3600) { // 1 hour
        return floor($timeDiff / 60) . ' minutes ago';
    } elseif ($timeDiff < 86400) { // 1 day
        return floor($timeDiff / 3600) . ' hours ago';
    } else {
        return 'Last seen ' . date('M j, Y, g:i a', strtotime($lastSeen));
    }
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
                    <small class="text-muted">
                        <?php echo count(array_filter($users, function($u) { return $u['is_online']; })); ?> online
                    </small>
                </div>
            </div>
        </div>
    </div>

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
                            $statusClass = $user['is_online'] ? 'status-online' : 'status-offline';
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
                                                    $messageTime = strtotime($user['latest_message_time']);
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
                                            <?php echo htmlspecialchars($user['username']); ?>
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

                <!-- Enhanced message input form with better validation -->
                <form class="chat-editor-area" method="post" enctype="multipart/form-data" onsubmit="return submitMessage(event);">
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
                           accept="image/*"
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

    <script>
        // FIXED Chat JavaScript - Simplified and more compatible

        let lastTimestamp = '0000-00-00 00:00:00';
        let currentReceiver = null;
        let currentReceiverType = null;
        let currentIndex = null;
        let pollInterval = null;

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

            fetchMessages(id, type, index);
            updateReadStatus(id);
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
                    const messageDate = new Date(message.timestamp);
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

                    const messageTime = new Date(message.timestamp);
                    const timeString = messageTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

                    let fileHtml = '';
                    if (message.file_url) {
                        fileHtml = `
                    <div class="mt-2">
                        <a href="../taskfiles/${escapeHtml(message.file_url)}" class="glightbox" data-gallery="gallery-3">
                            <img class="rounded" src="../taskfiles/${escapeHtml(message.file_url)}" alt="Shared image" width="150" loading="lazy">
                        </a>
                    </div>
                `;
                    }

                    messageElement.innerHTML = `
                <div class="flex-1 ${isCurrentUser ? 'd-flex justify-content-end' : ''}">
                    <div class="w-100 w-xxl-75">
                        <div class="hover-actions-trigger d-flex ${isCurrentUser ? 'flex-end-center' : 'align-items-center'}">
                            <div class="chat-message ${isCurrentUser ? 'bg-primary text-white' : 'bg-info text-white'} p-2 rounded-2">
                                ${escapeHtml(message.message)}
                                ${fileHtml}
                            </div>
                        </div>
                        <div class="text-400 fs-11 ${isCurrentUser ? 'text-end' : ''} mt-1">
                            <span>${timeString}</span>
                            ${isCurrentUser ? `<span class="${message.is_read ? 'text-success' : 'text-muted'} fas fa-check ms-1"></span>` : ''}
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
                                    sender_id: <?php echo isset($currentUserId) ? $currentUserId : '0'; ?>,
                                    message: messageContent,
                                    timestamp: new Date().toISOString().slice(0, 19).replace('T', ' '),
                                    file_url: data.file_url || null,
                                    is_read: false
                                });

                                chatContent.appendChild(messageElement);
                                chatContent.scrollTop = chatContent.scrollHeight;
                            }

                            showMessage('Message sent successfully', 'success');

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

            const messageTime = new Date(message.timestamp);
            const timeString = messageTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

            let fileHtml = '';
            if (message.file_url) {
                fileHtml = `
            <div class="mt-2">
                <a href="../taskfiles/${escapeHtml(message.file_url)}" class="glightbox">
                    <img class="rounded" src="../taskfiles/${escapeHtml(message.file_url)}" alt="Shared image" width="150">
                </a>
            </div>
        `;
            }

            messageElement.innerHTML = `
        <div class="flex-1 ${isCurrentUser ? 'd-flex justify-content-end' : ''}">
            <div class="w-100 w-xxl-75">
                <div class="hover-actions-trigger d-flex ${isCurrentUser ? 'flex-end-center' : 'align-items-center'}">
                    <div class="chat-message ${isCurrentUser ? 'bg-primary text-white' : 'bg-info text-white'} p-2 rounded-2">
                        ${escapeHtml(message.message)}
                        ${fileHtml}
                    </div>
                </div>
                <div class="text-400 fs-11 ${isCurrentUser ? 'text-end' : ''} mt-1">
                    <span>${timeString}</span>
                    ${isCurrentUser ? `<span class="${message.is_read ? 'text-success' : 'text-muted'} fas fa-check ms-1"></span>` : ''}
                </div>
            </div>
        </div>
    `;

            return messageElement;
        }

        function pollMessages() {
            if (!currentReceiver) return;

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
                            if (message.sender_id == currentReceiver) {
                                const chatContent = document.getElementById(`chat-content-${currentReceiver}`);
                                if (chatContent) {
                                    const messageElement = createMessageElement(message);
                                    chatContent.appendChild(messageElement);
                                    chatContent.scrollTop = chatContent.scrollHeight;
                                }
                            }
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
        function handleFileUpload(input) {
            const file = input.files[0];
            if (!file) {
                hideFilePreview();
                return;
            }

            // Validate file size (10MB)
            if (file.size > 10 * 1024 * 1024) {
                showMessage('File size must be less than 10MB', 'error');
                input.value = '';
                return;
            }

            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showMessage('Only image files are allowed', 'error');
                input.value = '';
                return;
            }

            showFilePreview(file);
        }

        function showFilePreview(file) {
            const previewContainer = document.getElementById('file-preview');
            previewContainer.style.display = 'block';
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

            // Set up contact search
            setupContactSearch();

            // Set up file upload
            const fileInput = document.getElementById('chat-file-upload');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    handleFileUpload(this);
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