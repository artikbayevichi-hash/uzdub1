<?php
/**
 * API: Bildirimlar
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Tizimga kiring']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Bildirimlarni olish
    $notifications = getNotifications($userId, 20);
    $unreadCount = getUnreadNotificationsCount($userId);
    
    echo json_encode([
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);
    
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'mark_read') {
        $notificationId = (int)($input['notification_id'] ?? 0);
        if ($notificationId > 0) {
            $success = markNotificationAsRead($notificationId, $userId);
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['error' => 'Noto\'g\'ri bildirim ID']);
        }
        
    } elseif ($action === 'mark_all_read') {
        $success = markAllNotificationsAsRead($userId);
        echo json_encode(['success' => $success]);
        
    } else {
        echo json_encode(['error' => 'Noto\'g\'ri amal']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Ruxsat etilmagan usul']);
}
?>
