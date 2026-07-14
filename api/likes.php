<?php
/**
 * API: Layklar
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Tizimga kiring', 'success' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Ruxsat etilmagan usul', 'success' => false]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// CSRF tekshirish
if (!validateCSRFToken($input['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Xavfsizlik xatoligi', 'success' => false]);
    exit;
}

$userId = $_SESSION['user_id'];
$contentId = isset($input['content_id']) ? (int)$input['content_id'] : null;
$commentId = isset($input['comment_id']) ? (int)$input['comment_id'] : null;

if (!$contentId && !$commentId) {
    echo json_encode(['error' => 'Kontent yoki sharh ID talab qilinadi', 'success' => false]);
    exit;
}

$liked = toggleLike($userId, $contentId, $commentId);

echo json_encode([
    'success' => true,
    'liked' => $liked,
    'like_count' => getLikeCount($contentId, $commentId)
]);
?>
