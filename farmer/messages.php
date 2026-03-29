<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a farmer
if (!isLoggedIn() || $_SESSION['user_type'] != 'farmer') {
    setFlashMessage('error', 'You must be logged in as a farmer to access this page');
    redirect('../login.php');
    exit();
}

$farmerId = $_SESSION['user_id'];
$farmerName = $_SESSION['username'];

// Handle new message submission
if (isset($_POST['send_message']) && isset($_POST['recipient_id']) && isset($_POST['message'])) {
    $recipientId = intval($_POST['recipient_id']);
    $message = sanitizeInput($_POST['message']);
    
    if (empty($message)) {
        setFlashMessage('error', 'Message cannot be empty');
    } else {
        // Insert the message
        $query = "INSERT INTO messages (sender_id, recipient_id, message, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iis", $farmerId, $recipientId, $message);
        
        if ($stmt->execute()) {
            // Success, no need for a message as we'll refresh the conversation
        } else {
            setFlashMessage('error', 'Failed to send message: ' . $conn->error);
        }
    }
    
    // Redirect to avoid form resubmission
    redirect('messages.php' . (isset($_GET['conversation']) ? '?conversation=' . $recipientId : ''));
    exit();
}

// Get all conversations for this farmer
$query = "SELECT 
            u.id as user_id, 
            u.username, 
            u.profile_image,
            MAX(m.created_at) as last_message_time,
            (SELECT message FROM messages 
             WHERE (sender_id = u.id AND recipient_id = ?) 
                OR (sender_id = ? AND recipient_id = u.id) 
             ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM messages 
             WHERE sender_id = u.id AND recipient_id = ? AND is_read = 0) as unread_count
          FROM users u
          JOIN messages m ON (m.sender_id = u.id AND m.recipient_id = ?) 
                          OR (m.sender_id = ? AND m.recipient_id = u.id)
          WHERE u.id != ?
          GROUP BY u.id
          ORDER BY last_message_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiiiii", $farmerId, $farmerId, $farmerId, $farmerId, $farmerId, $farmerId);
$stmt->execute();
$result = $stmt->get_result();
$conversations = [];

while ($row = $result->fetch_assoc()) {
    $conversations[] = $row;
}

// Check if we're viewing a specific conversation
$currentConversation = null;
$messages = [];

if (isset($_GET['conversation']) && is_numeric($_GET['conversation'])) {
    $userId = intval($_GET['conversation']);
    
    // Get user details
    $query = "SELECT id, username, profile_image FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $currentConversation = $result->fetch_assoc();
        
        // Mark messages as read
        $query = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND recipient_id = ? AND is_read = 0";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $userId, $farmerId);
        $stmt->execute();
        
        // Get messages for this conversation
        $query = "SELECT m.*, 
                  CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as message_type,
                  u.username as sender_name
                  FROM messages m
                  JOIN users u ON m.sender_id = u.id
                  WHERE (m.sender_id = ? AND m.recipient_id = ?) 
                     OR (m.sender_id = ? AND m.recipient_id = ?)
                  ORDER BY m.created_at ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiii", $farmerId, $farmerId, $userId, $userId, $farmerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }
} elseif (isset($_GET['new']) && is_numeric($_GET['new'])) {
    // Starting a new conversation
    $userId = intval($_GET['new']);
    
    // Get user details
    $query = "SELECT id, username, profile_image FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $currentConversation = $result->fetch_assoc();
        // No messages yet for a new conversation
    }
}

include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <h3><?php echo htmlspecialchars($_SESSION['username']); ?></h3>
            <p>Farmer</p>
        </div>
        <nav class="dashboard-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-leaf"></i> My Products</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-basket"></i> Orders</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li class="active"><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <div class="content-header">
            <h1>Messages</h1>
        </div>
        
        <?php displayFlashMessages(); ?>
        
        <div class="messages-container">
            <div class="conversations-list">
                <div class="conversations-header">
                    <h2>Conversations</h2>
                </div>
                
                <div class="conversations-body">
                    <?php if (empty($conversations) && !isset($currentConversation)): ?>
                        <div class="no-conversations">
                            <p>No conversations yet</p>
                            <p>When customers message you, they will appear here</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        // If we have a current conversation that's not in the list (new), add it
                        if (isset($currentConversation) && !in_array($currentConversation['id'], array_column($conversations, 'user_id'))) {
                            array_unshift($conversations, [
                                'user_id' => $currentConversation['id'],
                                'username' => $currentConversation['username'],
                                'profile_image' => $currentConversation['profile_image'],
                                'last_message' => 'New conversation',
                                'unread_count' => 0
                            ]);
                        }
                        ?>
                        
                        <?php foreach ($conversations as $conversation): ?>
                            <a href="?conversation=<?php echo $conversation['user_id']; ?>" 
                               class="conversation-item <?php echo (isset($currentConversation) && $currentConversation['id'] == $conversation['user_id']) ? 'active' : ''; ?>">
                                <div class="conversation-avatar">
                                    <?php if (!empty($conversation['profile_image'])): ?>
                                        <img src="<?php echo '../' . htmlspecialchars($conversation['profile_image']); ?>" alt="<?php echo htmlspecialchars($conversation['username']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-info">
                                    <div class="conversation-name">
                                        <?php echo htmlspecialchars($conversation['username']); ?>
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conversation-preview">
                                        <?php echo htmlspecialchars(substr($conversation['last_message'], 0, 30)) . (strlen($conversation['last_message']) > 30 ? '...' : ''); ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="message-content">
                <?php if (isset($currentConversation)): ?>
                    <div class="message-header">
                        <div class="message-recipient">
                            <div class="recipient-avatar">
                                <?php if (!empty($currentConversation['profile_image'])): ?>
                                    <img src="<?php echo '../' . htmlspecialchars($currentConversation['profile_image']); ?>" alt="<?php echo htmlspecialchars($currentConversation['username']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="recipient-name">
                                <?php echo htmlspecialchars($currentConversation['username']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="message-body" id="messageBody">
                        <?php if (empty($messages)): ?>
                            <div class="no-messages">
                                <p>No messages yet</p>
                                <p>Start the conversation by sending a message below</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="message-bubble <?php echo $message['message_type']; ?>">
                                    <div class="message-text">
                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                    </div>
                                    <div class="message-meta">
                                        <span class="message-time">
                                            <?php echo date('M d, h:i A', strtotime($message['created_at'])); ?>
                                        </span>
                                        <?php if ($message['message_type'] == 'sent'): ?>
                                            <span class="message-status">
                                                <?php echo $message['is_read'] ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="message-input">
                        <form action="" method="POST">
                            <input type="hidden" name="recipient_id" value="<?php echo $currentConversation['id']; ?>">
                            <div class="input-group">
                                <textarea name="message" id="messageInput" placeholder="Type your message..." required></textarea>
                                <button type="submit" name="send_message" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="no-conversation-selected">
                        <div class="no-conversation-content">
                            <i class="fas fa-comments"></i>
                            <h2>Select a conversation</h2>
                            <p>Choose a conversation from the list or start a new one</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Scroll to bottom of messages
        const messageBody = document.getElementById('messageBody');
        if (messageBody) {
            messageBody.scrollTop = messageBody.scrollHeight;
        }
        
        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>