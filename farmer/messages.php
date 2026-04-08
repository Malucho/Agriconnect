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
$farmerName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Handle new message submission
if (isset($_POST['send_message']) && isset($_POST['recipient_id']) && isset($_POST['message'])) {
    $recipientId = intval($_POST['recipient_id']);
    $message = sanitize($_POST['message']);
    
    if (empty($message)) {
        setFlashMessage('error', 'Message cannot be empty');
    } else {
        // Insert the message
        $query = "INSERT INTO messages (sender_id, receiver_id, message, sent_date) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iis", $farmerId, $recipientId, $message);
        
        if ($stmt->execute()) {
            // Success
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
            u.first_name, u.last_name,
            u.profile_image,
            MAX(m.sent_date) as last_message_time,
            (SELECT message FROM messages 
             WHERE (sender_id = u.id AND receiver_id = ?) 
                OR (sender_id = ? AND receiver_id = u.id) 
             ORDER BY sent_date DESC LIMIT 1) as last_message,
            (SELECT COUNT(*) FROM messages 
             WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
          FROM users u
          JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = ?) 
                          OR (m.sender_id = ? AND m.receiver_id = u.id)
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
    $query = "SELECT id, first_name, last_name, profile_image FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $currentConversation = $result->fetch_assoc();
        
        // Mark messages as read
        $query = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $userId, $farmerId);
        $stmt->execute();
        
        // Get messages for this conversation
        $query = "SELECT m.*, 
                  CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as message_type
                  FROM messages m
                  WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                     OR (m.sender_id = ? AND m.receiver_id = ?)
                  ORDER BY m.sent_date ASC";
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
    $query = "SELECT id, first_name, last_name, profile_image FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $currentConversation = $result->fetch_assoc();
    }
}

$page_title = 'Messages';
include '../includes/head.php';
include '../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-sidebar">
        <div class="farmer-profile">
            <div class="farmer-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <h3><?php echo htmlspecialchars($farmerName); ?></h3>
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
        
        <div class="messages-container" style="display: flex; height: 600px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;">
            <div class="conversations-list" style="width: 300px; border-right: 1px solid #eee; display: flex; flex-direction: column;">
                <div class="conversations-header" style="padding: 20px; border-bottom: 1px solid #eee;">
                    <h2 style="margin: 0; font-size: 1.2rem;">Conversations</h2>
                </div>
                
                <div class="conversations-body" style="flex: 1; overflow-y: auto;">
                    <?php if (empty($conversations) && !isset($currentConversation)): ?>
                        <div class="no-conversations" style="padding: 20px; text-align: center; color: #777;">
                            <p>No conversations yet</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        if (isset($currentConversation) && !in_array($currentConversation['id'], array_column($conversations, 'user_id'))) {
                            array_unshift($conversations, [
                                'user_id' => $currentConversation['id'],
                                'first_name' => $currentConversation['first_name'],
                                'last_name' => $currentConversation['last_name'],
                                'profile_image' => $currentConversation['profile_image'],
                                'last_message' => 'New conversation',
                                'unread_count' => 0
                            ]);
                        }
                        ?>
                        
                        <?php foreach ($conversations as $conversation): ?>
                            <a href="?conversation=<?php echo $conversation['user_id']; ?>" 
                               style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #f9f9f9; color: inherit; text-decoration: none; <?php echo (isset($currentConversation) && $currentConversation['id'] == $conversation['user_id']) ? 'background: #f0f7f0; border-left: 4px solid #4CAF50;' : ''; ?>">
                                <div class="conversation-avatar" style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; margin-right: 12px; flex-shrink: 0; background: #eee; display: flex; align-items: center; justify-content: center;">
                                    <?php if (!empty($conversation['profile_image'])): ?>
                                        <img src="<?php echo '../uploads/profiles/' . htmlspecialchars($conversation['profile_image']); ?>" alt="User" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user" style="color: #ccc;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-info" style="flex: 1; min-width: 0;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                        <div style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($conversation['first_name'] . ' ' . $conversation['last_name']); ?>
                                        </div>
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span style="background: #4CAF50; color: #fff; border-radius: 10px; padding: 2px 8px; font-size: 0.7rem;"><?php echo $conversation['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #777; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($conversation['last_message'] ?? ''); ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="message-content" style="flex: 1; display: flex; flex-direction: column;">
                <?php if (isset($currentConversation)): ?>
                    <div class="message-header" style="padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; align-items: center;">
                        <div style="width: 35px; height: 35px; border-radius: 50%; overflow: hidden; margin-right: 12px; background: #eee; display: flex; align-items: center; justify-content: center;">
                            <?php if (!empty($currentConversation['profile_image'])): ?>
                                <img src="<?php echo '../uploads/profiles/' . htmlspecialchars($currentConversation['profile_image']); ?>" alt="User" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user" style="color: #ccc;"></i>
                            <?php endif; ?>
                        </div>
                        <div style="font-weight: 600;">
                            <?php echo htmlspecialchars($currentConversation['first_name'] . ' ' . $currentConversation['last_name']); ?>
                        </div>
                    </div>
                    
                    <div class="message-body" id="messageBody" style="flex: 1; overflow-y: auto; padding: 20px; background: #f9f9f9; display: flex; flex-direction: column; gap: 15px;">
                        <?php if (empty($messages)): ?>
                            <div style="text-align: center; color: #777; margin-top: 50px;">
                                <p>No messages yet. Send a message to start the conversation.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div style="max-width: 70%; padding: 12px 16px; border-radius: 12px; position: relative; <?php echo ($message['message_type'] == 'sent') ? 'align-self: flex-end; background: #4CAF50; color: #fff; border-bottom-right-radius: 2px;' : 'align-self: flex-start; background: #fff; border: 1px solid #eee; border-bottom-left-radius: 2px;'; ?>">
                                    <div style="margin-bottom: 4px;"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                    <div style="font-size: 0.7rem; opacity: 0.8; text-align: right;">
                                        <?php echo date('h:i A', strtotime($message['sent_date'])); ?>
                                        <?php if ($message['message_type'] == 'sent'): ?>
                                            <i class="fas <?php echo $message['is_read'] ? 'fa-check-double' : 'fa-check'; ?>" style="margin-left: 4px;"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="message-footer" style="padding: 20px; border-top: 1px solid #eee;">
                        <form action="" method="POST" style="display: flex; gap: 10px;">
                            <input type="hidden" name="recipient_id" value="<?php echo $currentConversation['id']; ?>">
                            <textarea name="message" placeholder="Type your message..." required style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; resize: none; height: 45px;"></textarea>
                            <button type="submit" name="send_message" class="btn btn-primary" style="padding: 0 20px;"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #777;">
                        <i class="fas fa-comments" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.2;"></i>
                        <p>Select a conversation to start messaging</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Scroll to bottom of message body
window.onload = function() {
    var messageBody = document.getElementById('messageBody');
    if (messageBody) {
        messageBody.scrollTop = messageBody.scrollHeight;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
