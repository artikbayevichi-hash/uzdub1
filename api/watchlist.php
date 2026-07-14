<?php
/**
 * API: Watchlist
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
$contentId = isset($input['content_id']) ? (int)$input['content_id'] : 0;

if ($contentId <= 0) {
    echo json_encode(['error' => 'Kontent ID talab qilinadi', 'success' => false]);
    exit;
}

$added = toggleWatchlist($userId, $contentId);

echo json_encode([
    'success' => true,
    'added' => $added,
    'in_watchlist' => isInWatchlist($userId, $contentId)
]);
?>
