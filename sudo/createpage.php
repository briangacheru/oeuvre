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

// Fetch writers and admins for chat excluding the current user
$writersQuery = mysqli_query($con, "SELECT id, username FROM tblwriters WHERE id != $currentUserId");
$adminsQuery = mysqli_query($con, "SELECT id, username FROM tbladmin WHERE id != $currentUserId");

$users = [];
while ($writer = mysqli_fetch_assoc($writersQuery)) {
    $users[] = ['id' => $writer['id'], 'username' => $writer['username'], 'type' => 'writer'];
}
while ($admin = mysqli_fetch_assoc($adminsQuery)) {
    $users[] = ['id' => $admin['id'], 'username' => $admin['username'], 'type' => 'admin'];
}

// Get the latest message for each user and sort by timestamp
foreach ($users as &$user) {
    $userId = $user['id'];
    $userType = $user['type'];
    $latestMessageQuery = mysqli_query($con, "
        SELECT message, timestamp FROM chat_messages 
        WHERE (sender_id = $userId AND receiver_id = $currentUserId)
           OR (receiver_id = $userId AND sender_id = $currentUserId)
        ORDER BY timestamp DESC LIMIT 1
    ");
    $latestMessage = mysqli_fetch_assoc($latestMessageQuery);
    $user['latest_message'] = $latestMessage ? $latestMessage['message'] : "No messages yet.";
    $user['latest_message_time'] = $latestMessage ? $latestMessage['timestamp'] : null;
}

// Sort users by latest message timestamp in descending order
usort($users, function($a, $b) {
    return strtotime($b['latest_message_time'] ?? '0000-00-00 00:00:00') - strtotime($a['latest_message_time'] ?? '0000-00-00 00:00:00');
});
?>


<div class="card shadow-none border mb-3">
    <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(assets/img/illustrations/corner-6.png);"></div>
    <div class="card-header z-1">
        <div class="row flex-between-center gx-0">
            <div class="col-lg-auto d-flex align-items-center">
                <h4 class="mb-0 text-primary fw-bold">My <span class="text-info fw-medium"> Chats</span></h4>
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
                    // Assuming you have fetched the user data including 'is_online' and 'last_seen'
                    foreach ($users as $index => $user): ?>
                        <?php
                        $isOnline = isset($user['is_online']) ? $user['is_online'] : false;
                        $lastSeen = isset($user['last_seen']) ? $user['last_seen'] : null;
                        $statusText = $isOnline ? 'Online' : ($lastSeen ? 'Last seen ' . date('M j, Y, g:i a', strtotime($lastSeen)) : 'Offline');
                        $statusClass = $isOnline ? 'status-online' : 'status-offline';
                        ?>
                        <div class="hover-actions-trigger chat-contact nav-item <?php echo $index === 0 ? 'active' : ''; ?>" role="tab" id="chat-link-<?php echo $index; ?>" data-bs-toggle="tab" data-bs-target="#chat-<?php echo $user['id']; ?>" data-index="<?php echo $index; ?>" aria-controls="chat-<?php echo $user['id']; ?>" aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>" onclick="setReceiver(<?php echo $user['id']; ?>, '<?php echo $user['type']; ?>', <?php echo $index; ?>)">
                            <div class="d-flex p-3">
                                <div class="avatar avatar-xl <?php echo $statusClass; ?>">
                                    <?php if (isset($user['photo']) && $user['photo'] != "avatar.png"): ?>
                                        <img class="rounded-circle" src="<?php echo $user['type'] === 'admin' ? '../profileimages/' : 'profileimages/'; ?><?php echo htmlspecialchars($user['photo']); ?>" alt="" />
                                    <?php else: ?>
                                        <div class="avatar-name rounded-circle">
                                            <span><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 chat-contact-body ms-2 d-md-none d-lg-block">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-0 chat-contact-title"><?php echo $user['username']; ?> (<?php echo ucfirst($user['type']); ?>)</h6>
                                        <span class="message-time fs-11"><?php echo $user['latest_message_time'] ? (date('Y-m-d') === date('Y-m-d', strtotime($user['latest_message_time'])) ? 'Today' : date('l', strtotime($user['latest_message_time']))) : ''; ?></span>
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
            <?php foreach ($users as $index => $user): ?>
                <div class="tab-pane card-chat-pane <?php echo $index === 0 ? 'active' : ''; ?>" id="chat-<?php echo $user['id']; ?>" role="tabpanel" aria-labelledby="chat-link-<?php echo $index; ?>">
                    <div class="chat-content-header">
                        <div class="row flex-between-center">
                            <div class="col-6 col-sm-8 d-flex align-items-center"><a class="pe-3 text-700 d-md-none contacts-list-show" href="#!"><div class="fas fa-chevron-left"></div></a>
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
            <form class="chat-editor-area" method="post" action="send_message.php" enctype="multipart/form-data" onsubmit="return submitMessage();">
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
    function setReceiver(id, type, index) {
        document.getElementById('receiverIdField').value = id;
        document.getElementById('receiverTypeField').value = type;
        fetchMessages(id, type, index);
    }

    function fetchMessages(userId, userType, index) {
        fetch(`fetch_messages?user_id=${userId}&user_type=${userType}`)
            .then(response => response.json())
            .then(messages => {
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

                    const isCurrentUser = message.sender_id == <?php echo $currentUserId; ?> && message.sender_type == '<?php echo $currentUserType; ?>';
                    const messageElement = document.createElement('div');
                    messageElement.classList.add('d-flex', 'p-3', isCurrentUser ? 'justify-content-end' : 'justify-content-start');
                    messageElement.innerHTML = `
                    <div class="flex-1 ${isCurrentUser ? 'd-flex justify-content-end' : ''}">
                        <div class="w-100 w-xxl-75">
                            <div class="hover-actions-trigger d-flex ${isCurrentUser ? 'flex-end-center' : 'align-items-center'}">
                                <div class="chat-message ${isCurrentUser ? 'bg-primary text-white' : 'bg-200'} p-2 rounded-2">
                                    ${message.message}
                                    ${message.file_url ? `
                                    <a href="taskfiles/${message.file_url}" class="glightbox" data-gallery="gallery-3">
                                        <img class="rounded" src="taskfiles/${message.file_url}" alt="" width="150">
                                    </a>` : ''}
                                </div>
                            </div>
                            <div class="text-400 fs-11 ${isCurrentUser ? 'text-end' : ''}">
                                <span>${messageDate.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}</span>
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
            });
    }


    function submitMessage() {
        const messageContent = document.getElementById('messageInput').innerText.trim();
        const receiverId = document.getElementById('receiverIdField').value;
        const receiverType = document.getElementById('receiverTypeField').value;
        const formData = new FormData();

        if (messageContent) {
            formData.append('message', messageContent);
        }
        formData.append('receiver_id', receiverId);
        formData.append('receiver_type', receiverType);

        const fileInput = document.getElementById('chat-file-upload');
        if (fileInput.files.length > 0) {
            formData.append('file', fileInput.files[0]);
        }

        fetch('send_message.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Create and append the new message
                    const chatContent = document.getElementById(`chat-content-${receiverId}`);
                    const messageElement = document.createElement('div');
                    messageElement.classList.add('d-flex', 'p-3', 'justify-content-end');

                    let messageHTML = `
                <div class="flex-1 d-flex justify-content-end">
                    <div class="w-100 w-xxl-75">
                        <div class="hover-actions-trigger d-flex flex-end-center">
                            <div class="chat-message bg-primary text-white p-2 rounded-2">
                                ${messageContent}
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
                        </div>
                    </div>
                </div>
            `;

                    messageElement.innerHTML = messageHTML;
                    chatContent.appendChild(messageElement);
                    chatContent.scrollTop = chatContent.scrollHeight;

                    document.getElementById('messageInput').innerText = '';
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
</script>

<script>
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
</script>

<?php
include "footer.php";
?>


