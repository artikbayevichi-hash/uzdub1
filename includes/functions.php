<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Bildirimlar funksiyalari
 */
function addNotification($userId, $type, $title, $message, $link = null) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$userId, $type, $title, $message, $link]);
}

function getUnreadNotificationsCount($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getNotifications($userId, $limit = 10) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function markNotificationAsRead($notificationId, $userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$notificationId, $userId]);
}

function markAllNotificationsAsRead($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    return $stmt->execute([$userId]);
}

/**
 * Layklar funksiyalari
 */
function toggleLike($userId, $contentId = null, $commentId = null) {
    $pdo = getDBConnection();
    
    // Agar allaqachon like bosilgan bo'lsa, o'chiramiz
    if ($contentId) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND content_id = ?");
        $stmt->execute([$userId, $contentId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
            $stmt->execute([$existing['id']]);
            return false; // Like o'chirildi
        } else {
            $stmt = $pdo->prepare("INSERT INTO likes (user_id, content_id) VALUES (?, ?)");
            $stmt->execute([$userId, $contentId]);
            
            // Kontent egasiga bildirim yuborish
            $stmt = $pdo->prepare("SELECT user_id FROM content WHERE id = ?");
            $stmt->execute([$contentId]);
            $contentOwner = $stmt->fetch();
            if ($contentOwner && $contentOwner['user_id'] != $userId) {
                addNotification($contentOwner['user_id'], 'like', 'Yangi layk', 'Sizning kontentingizga layk bosildi', '/watch.php?id=' . $contentId);
            }
            
            return true; // Like qo'shildi
        }
    } elseif ($commentId) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND comment_id = ?");
        $stmt->execute([$userId, $commentId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
            $stmt->execute([$existing['id']]);
            return false;
        } else {
            $stmt = $pdo->prepare("INSERT INTO likes (user_id, comment_id) VALUES (?, ?)");
            $stmt->execute([$userId, $commentId]);
            return true;
        }
    }
    
    return false;
}

function getLikeCount($contentId = null, $commentId = null) {
    $pdo = getDBConnection();
    
    if ($contentId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE content_id = ?");
        $stmt->execute([$contentId]);
    } elseif ($commentId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE comment_id = ?");
        $stmt->execute([$commentId]);
    } else {
        return 0;
    }
    
    return (int)$stmt->fetchColumn();
}

function isLikedByUser($userId, $contentId = null, $commentId = null) {
    $pdo = getDBConnection();
    
    if ($contentId) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND content_id = ?");
        $stmt->execute([$userId, $contentId]);
    } elseif ($commentId) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND comment_id = ?");
        $stmt->execute([$userId, $commentId]);
    } else {
        return false;
    }
    
    return (bool)$stmt->fetch();
}

/**
 * Sharhlar funksiyalari
 */
function addComment($userId, $contentId, $comment, $parentId = null) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO comments (user_id, content_id, comment, parent_id) VALUES (?, ?, ?, ?)");
    
    if ($stmt->execute([$userId, $contentId, $comment, $parentId])) {
        $commentId = $pdo->lastInsertId();
        
        // Kontent egasiga bildirim (agar o'zi bo'lmasa)
        $stmt = $pdo->prepare("SELECT user_id FROM content WHERE id = ?");
        $stmt->execute([$contentId]);
        $contentOwner = $stmt->fetch();
        
        if ($contentOwner && $contentOwner['user_id'] != $userId) {
            addNotification($contentOwner['user_id'], 'comment', 'Yangi sharh', 'Sizning kontentingizga yangi sharh qoldirildi', '/watch.php?id=' . $contentId);
        }
        
        return $commentId;
    }
    
    return false;
}

function getComments($contentId, $parentId = null) {
    $pdo = getDBConnection();
    
    if ($parentId === null) {
        $stmt = $pdo->prepare("
            SELECT c.*, u.username, u.avatar, 
                   (SELECT COUNT(*) FROM likes WHERE comment_id = c.id) as like_count
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.content_id = ? AND c.parent_id IS NULL
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$contentId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, u.username, u.avatar,
                   (SELECT COUNT(*) FROM likes WHERE comment_id = c.id) as like_count
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.parent_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$parentId]);
    }
    
    return $stmt->fetchAll();
}

/**
 * Ko'rish tarixi funksiyalari
 */
function addToWatchHistory($userId, $contentId, $progressSeconds = 0) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO watch_history (user_id, content_id, progress_seconds) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE progress_seconds = ?, watched_at = CURRENT_TIMESTAMP");
    return $stmt->execute([$userId, $contentId, $progressSeconds, $progressSeconds]);
}

function getWatchHistory($userId, $limit = 20) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT wh.*, c.title, c.thumbnail, c.duration
        FROM watch_history wh
        JOIN content c ON wh.content_id = c.id
        WHERE wh.user_id = ?
        ORDER BY wh.watched_at DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Watchlist funksiyalari
 */
function toggleWatchlist($userId, $contentId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND content_id = ?");
    $stmt->execute([$userId, $contentId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $pdo->prepare("DELETE FROM watchlist WHERE id = ?");
        $stmt->execute([$existing['id']]);
        return false; // O'chirildi
    } else {
        $stmt = $pdo->prepare("INSERT INTO watchlist (user_id, content_id) VALUES (?, ?)");
        $stmt->execute([$userId, $contentId]);
        return true; // Qo'shildi
    }
}

function isInWatchlist($userId, $contentId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND content_id = ?");
    $stmt->execute([$userId, $contentId]);
    return (bool)$stmt->fetch();
}

function getWatchlist($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT w.*, c.title, c.thumbnail, c.duration, c.rating
        FROM watchlist w
        JOIN content c ON w.content_id = c.id
        WHERE w.user_id = ?
        ORDER BY w.added_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Premium status tekshirish
 */
function isPremium($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT is_premium, premium_expires_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || !$user['is_premium']) {
        return false;
    }
    
    // Muddati tugaganmi?
    if ($user['premium_expires_at'] && strtotime($user['premium_expires_at']) < time()) {
        $stmt = $pdo->prepare("UPDATE users SET is_premium = 0, premium_expires_at = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        return false;
    }
    
    return true;
}

/**
 * Foydalanuvchi sozlamalari
 */
function getUserSettings($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Default sozlamalar yaratish
        $stmt = $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        return getUserSettings($userId);
    }
    
    return $settings;
}

function updateUserSettings($userId, $settings) {
    $pdo = getDBConnection();
    
    $allowedSettings = ['theme', 'notifications_enabled', 'privacy_profile'];
    $updates = [];
    $values = [];
    
    foreach ($settings as $key => $value) {
        if (in_array($key, $allowedSettings)) {
            $updates[] = "$key = ?";
            $values[] = $value;
        }
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $values[] = $userId;
    $sql = "UPDATE user_settings SET " . implode(', ', $updates) . " WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($values);
}
?>
