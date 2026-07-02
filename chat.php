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
$isOnline = $currentUser['is_online'];
$lastSeen = $currentUser['last_seen'];

// Determine online status or last seen
$statusText = $isOnline ? 'Online' : ($lastSeen ? 'Last seen ' . date('M j, Y, g:i a', strtotime($lastSeen)) : 'Offline');

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
            'is_online' => $admin['is_online'],
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
                        $statusText = $isOnline ? 'Online' : ($lastSeen ? 'Last seen ' . date('M j, Y, g:i a', strtotime($lastSeen)) : 'Offline');
                        $statusClass = $isOnline ? 'status-online' : 'status-offline';
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
                                        <h6 class="mb-0 chat-contact-title"><?php echo $user['username']; ?> (<?php echo ucfirst($user['type']); ?>)</h6>
                                        <span class="message-time fs-11"><?php echo $user['latest_message_time'] ? (date('Y-m-d') === date('Y-m-d', strtotime($user['latest_message_time'])) ? 'Today' : date('l', strtotime($user['latest_message_time']))) : ''; ?></span>
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
                                    <h5 class="mb-0 text-truncate fs-9"><?php echo $user['username']; ?> (<?php echo ucfirst($user['type']); ?>)</h5>
                                    <div class="fs-11 text-400"><?php echo $statusText; ?></div>
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
            <form class="chat-editor-area" method="post" action="send_message" enctype="multipart/form-data" onsubmit="return submitMessage();">
                <div class="emojiarea-editor outline-none scrollbar" contenteditable="true" id="messageInput"></div>
                <input type="hidden" name="message" id="messageField">
                <input type="hidden" name="receiver_id" id="receiverIdField">
                <input type="hidden" name="receiver_type" id="receiverTypeField">
                <input type="file" id="chat-file-upload" name="file" class="d-none" accept="image/*">
                <label class="chat-file-upload cursor-pointer" for="chat-file-upload"><span class="fas fa-paperclip"></span></label>
                <div id="file-preview" class="file-preview"></div> <!-- Preview area -->
                <div class="chat-emoji-picker">
                    <div class="btn btn-link emoji-icon" data-emoji-mart="data-emoji-mart" data-emoji-mart-input-target="#messageInput"><span class="far fa-laugh-beam"></span></div>
                </div>
                <button class="btn btn-sm btn-send shadow-none" type="submit">Send</button>
            </form>
        </div>
    </div>
</div>

<script>
    let lastTimestamp = '0000-00-00 00:00:00'; // Initialize last timestamp

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

        fetchMessages(id, type, index);
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
            const messageDate = new Date(message.timestamp);
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
            messageElement.innerHTML = `
        <div class="flex-1 ${isCurrentUser ? 'd-flex justify-content-end' : ''}">
            <div class="w-100 w-xxl-75">
                <div class="hover-actions-trigger d-flex ${isCurrentUser ? 'flex-end-center' : 'align-items-center'}">
                    <div class="chat-message ${isCurrentUser ? 'bg-primary text-white' : 'bg-info text-white'} p-2 rounded-2">
                        ${message.message}
                        ${message.file_url ? `
                        <a href="taskfiles/${message.file_url}" class="glightbox" data-gallery="gallery-3">
                            <img class="rounded" src="taskfiles/${message.file_url}" alt="" width="150">
                        </a>` : ''}
                    </div>
                </div>
                <div class="text-400 fs-11 ${isCurrentUser ? 'text-end' : ''}">
                    <span>${messageDate.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}</span>
                    <span class="${message.is_read ? 'text-success' : 'text-muted'} fas fa-check"></span>
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
                    const chatContent = document.getElementById(`chat-content-${receiverId}`);
                    messages.forEach(message => {
                        const messageElement = document.createElement('div');
                        const isCurrentUser = message.sender_id == <?php echo $currentUser['id']; ?>;
                        messageElement.classList.add('d-flex', 'p-3', isCurrentUser ? 'justify-content-end' : 'justify-content-start');
                        messageElement.innerHTML = `
                    <div class="flex-1 ${isCurrentUser ? 'd-flex justify-content-end' : ''}">
                        <div class="w-100 w-xxl-75">
                            <div class="hover-actions-trigger d-flex ${isCurrentUser ? 'flex-end-center' : 'align-items-center'}">
                                <div class="chat-message ${isCurrentUser ? 'bg-primary text-white' : 'bg-info text-white'} p-2 rounded-2">
                                    ${message.message}
                                    ${message.file_url ? `
                                    <a href="taskfiles/${message.file_url}" class="glightbox" data-gallery="gallery-3">
                                        <img class="rounded" src="taskfiles/${message.file_url}" alt="" width="150">
                                    </a>` : ''}
                                    <span class="${message.is_read ? 'text-success' : 'text-muted'} fas fa-check"></span>
                                </div>
                            </div>
                            <div class="text-400 fs-11 ${isCurrentUser ? 'text-end' : ''}">
                                <span>${new Date(message.timestamp).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}</span>
                            </div>
                        </div>
                    </div>
                `;
                        chatContent.appendChild(messageElement);
                        chatContent.scrollTop = chatContent.scrollHeight;
                    });
                    lastTimestamp = messages[messages.length - 1].timestamp;

                    // Play ding sound
                    const dingSound = document.getElementById('dingSound');
                    dingSound.play();

                    // Update the read status of messages
                    updateReadStatus(receiverId);
                }
                setTimeout(pollMessages, 3000); // Poll every 3 seconds
            })
            .catch(error => {
                console.error('Error polling messages:', error);
                setTimeout(pollMessages, 5000); // Retry after 5 seconds on error
            });
    }

    function updateReadStatus(userId) {
        fetch(`update_read_status?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update the UI to reflect that messages have been read
                    const chatContent = document.getElementById(`chat-content-${userId}`);
                    const messageElements = chatContent.querySelectorAll('.chat-message');

                    messageElements.forEach(messageElement => {
                        const readIndicator = messageElement.querySelector('.fas.fa-check');
                        if (readIndicator) {
                            readIndicator.classList.remove('text-muted');
                            readIndicator.classList.add('text-success');
                        }
                    });
                } else {
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

        if (encodedMessageContent) {
            formData.append('message', encodedMessageContent); // Append the encoded message
        }
        formData.append('receiver_id', receiverId);
        formData.append('receiver_type', receiverType);

        const fileInput = document.getElementById('chat-file-upload');
        if (fileInput.files.length > 0) {
            formData.append('file', fileInput.files[0]);
        }

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

                    let messageHTML = `
                <div class="flex-1 d-flex justify-content-end">
                    <div class="w-100 w-xxl-75">
                        <div class="hover-actions-trigger d-flex flex-end-center">
                            <div class="chat-message bg-primary text-white p-2 rounded-2">
                                ${decodeURIComponent(encodedMessageContent)}
            `;

                    if (fileInput.files.length > 0) {
                        const fileUrl = URL.createObjectURL(fileInput.files[0]);
                        messageHTML += `
                    <a href="${fileUrl}" class="glightbox" data-gallery="gallery-3">
                        <img class="rounded" src="${fileUrl}" alt="" width="150">
                    </a>
                `;
                    }

                    messageHTML += `
                        </div>
                    </div>
                    <div class="text-400 fs-11 text-end">
                        <span>${new Date().toLocaleTimeString()}</span>
                        <span class="text-muted fas fa-check"></span>
                    </div>
                </div>
            </div>
            `;

                    messageElement.innerHTML = messageHTML;
                    chatContent.appendChild(messageElement);
                    chatContent.scrollTop = chatContent.scrollHeight;

                    messageInput.innerText = '';
                    fileInput.value = ''; // Clear the file input
                    document.getElementById('file-preview').innerHTML = ''; // Clear the preview area

                    GLightbox(); // Re-initialize GLightbox for new content
                } else {
                    alert(data.message);
                }
            });

        return false;
    }


    document.getElementById('chat-file-upload').addEventListener('change', function(event) {
        const file = event.target.files[0];
        const previewContainer = document.getElementById('file-preview');
        previewContainer.innerHTML = ''; // Clear existing preview
        const spinner = document.createElement('div');
        spinner.className = 'spinner-border';
        previewContainer.appendChild(spinner); // Show loading spinner

        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewContainer.innerHTML = ''; // Clear spinner
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = 'Preview';
                img.style.maxWidth = '100px';
                previewContainer.appendChild(img);
            };
            reader.readAsDataURL(file);
        } else {
            previewContainer.innerHTML = ''; // Clear spinner
            alert('Please select an image file.');
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
        pollMessages(); // Start polling messages

        // Show the default content when the page loads
        document.getElementById('default-content').classList.add('active');
    });
</script>

<?php
include "footer.php";
?>
