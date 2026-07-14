<?php
/**
 * UZDUB - Yangilangan funksiyalar
 * Bildirimlar, Layklar, Sharhlar, Ko'rish tarixi
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/lang.php';

// ===== Xavfsizlik funksiyalari =====
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize_input($input) {
    if (is_array($input)) {
        return array_map('sanitize_input', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// CSRF Token
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// Rate limiting (SPAMdan himoya)
function rate_limit($action, $user_id, $limit = 5, $timeframe = 60) {
    $key = "rate_{$action}_{$user_id}";
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'time' => $now];
        return true;
    }
    
    if ($now - $_SESSION[$key]['time'] > $timeframe) {
        $_SESSION[$key] = ['count' => 1, 'time' => $now];
        return true;
    }
    
    if ($_SESSION[$key]['count'] >= $limit) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

// ===== Foydalanuvchi funksiyalari =====
function is_user() { 
    return isset($_SESSION['user_id']); 
}

function current_user() { 
    return $_SESSION['user_data'] ?? null; 
}

function require_user() {
    if (!is_user()) { 
        header('Location: /uzdub/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); 
        exit; 
    }
}

function get_user_by_id($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function get_user_by_uid($pdo, $uid) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$uid]);
    return $stmt->fetch();
}

// Premium muddatini tekshirish
function check_premium_expiry($pdo, $user_db_id) {
    $stmt = $pdo->prepare("SELECT is_premium, premium_expires_at FROM users WHERE id = ?");
    $stmt->execute([$user_db_id]);
    $u = $stmt->fetch();
    
    if ($u && $u['is_premium'] && $u['premium_expires_at'] && strtotime($u['premium_expires_at']) < time()) {
        $pdo->prepare("UPDATE users SET is_premium=0, premium_expires_at=NULL WHERE id=?")->execute([$user_db_id]);
        if (isset($_SESSION['user_data'])) {
            $_SESSION['user_data']['is_premium'] = 0;
            $_SESSION['user_data']['premium_expires_at'] = null;
        }
    }
}

function refresh_user_session($pdo, $user_db_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_db_id]);
    $u = $stmt->fetch();
    if ($u) {
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['user_data'] = $u;
    }
}

// ===== Bildirimlar (Notifications) =====
function create_notification($pdo, $user_id, $type, $title, $message, $link = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $type, $title, $message, $link]);
}

function get_unread_notifications_count($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

function get_notifications($pdo, $user_id, $limit = 20) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll();
}

function mark_notification_read($pdo, $notification_id, $user_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$notification_id, $user_id]);
}

function mark_all_notifications_read($pdo, $user_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    return $stmt->execute([$user_id]);
}

// ===== Layklar (Likes) =====
function toggle_content_like($pdo, $user_id, $content_id) {
    // Avval mavjud laykni tekshirish
    $stmt = $pdo->prepare("SELECT * FROM content_likes WHERE user_id = ? AND content_id = ?");
    $stmt->execute([$user_id, $content_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Agar like bo'lsa - o'chirish, dislike bo'lsa - yangilash
        if ($existing['type'] === 'like') {
            $stmt = $pdo->prepare("DELETE FROM content_likes WHERE user_id = ? AND content_id = ?");
            $stmt->execute([$user_id, $content_id]);
            
            // Kontent likes sonini kamaytirish
            $pdo->prepare("UPDATE content SET likes = GREATEST(likes - 1, 0) WHERE id = ?")->execute([$content_id]);
            return 'removed';
        } else {
            $stmt = $pdo->prepare("UPDATE content_likes SET type = 'like', created_at = NOW() WHERE user_id = ? AND content_id = ?");
            $stmt->execute([$user_id, $content_id]);
            
            $pdo->prepare("UPDATE content SET likes = likes + 1, dislikes = GREATEST(dislikes - 1, 0) WHERE id = ?")->execute([$content_id]);
            return 'liked';
        }
    } else {
        // Yangi like qo'shish
        $stmt = $pdo->prepare("INSERT INTO content_likes (user_id, content_id, type) VALUES (?, ?, 'like')");
        $stmt->execute([$user_id, $content_id]);
        
        $pdo->prepare("UPDATE content SET likes = likes + 1 WHERE id = ?")->execute([$content_id]);
        
        // Kontent egasiga bildirim yuborish
        $content = $pdo->prepare("SELECT * FROM content WHERE id = ?");
        $content->execute([$content_id]);
        $c = $content->fetch();
        
        return 'liked';
    }
}

function toggle_content_dislike($pdo, $user_id, $content_id) {
    $stmt = $pdo->prepare("SELECT * FROM content_likes WHERE user_id = ? AND content_id = ?");
    $stmt->execute([$user_id, $content_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        if ($existing['type'] === 'dislike') {
            $stmt = $pdo->prepare("DELETE FROM content_likes WHERE user_id = ? AND content_id = ?");
            $stmt->execute([$user_id, $content_id]);
            
            $pdo->prepare("UPDATE content SET dislikes = GREATEST(dislikes - 1, 0) WHERE id = ?")->execute([$content_id]);
            return 'removed';
        } else {
            $stmt = $pdo->prepare("UPDATE content_likes SET type = 'dislike', created_at = NOW() WHERE user_id = ? AND content_id = ?");
            $stmt->execute([$user_id, $content_id]);
            
            $pdo->prepare("UPDATE content SET likes = GREATEST(likes - 1, 0), dislikes = dislikes + 1 WHERE id = ?")->execute([$content_id]);
            return 'disliked';
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO content_likes (user_id, content_id, type) VALUES (?, ?, 'dislike')");
        $stmt->execute([$user_id, $content_id]);
        
        $pdo->prepare("UPDATE content SET dislikes = dislikes + 1 WHERE id = ?")->execute([$content_id]);
        return 'disliked';
    }
}

function get_user_content_vote($pdo, $user_id, $content_id) {
    $stmt = $pdo->prepare("SELECT type FROM content_likes WHERE user_id = ? AND content_id = ?");
    $stmt->execute([$user_id, $content_id]);
    $result = $stmt->fetch();
    return $result ? $result['type'] : null;
}

// ===== Sharhlar (Comments) =====
function add_comment($pdo, $user_id, $content_id, $message, $parent_id = null) {
    // Spamdan himoya
    if (!rate_limit('comment', $user_id, 10, 60)) {
        return ['success' => false, 'error' => 'Juda ko\'p sharhlar. Iltimos, kutib turing.'];
    }
    
    // Moderatsiya tekshiruvi
    $message = trim($message);
    if (strlen($message) < 3) {
        return ['success' => false, 'error' => 'Sharh juda qisqa.'];
    }
    
    if (strlen($message) > 2000) {
        return ['success' => false, 'error' => 'Sharh juda uzun (max 2000 belgi).'];
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO comments (user_id, content_id, parent_id, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $content_id, $parent_id, $message]);
        $comment_id = $pdo->lastInsertId();
        
        // Agar ota-kommentga javob bo'lsa, bildirim yuborish
        if ($parent_id) {
            $parent_stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
            $parent_stmt->execute([$parent_id]);
            $parent = $parent_stmt->fetch();
            
            if ($parent && $parent['user_id'] != $user_id) {
                create_notification($pdo, $parent['user_id'], 'reply', 'Yangi javob', 'Sizning sharhingizga javob berildi.', "/uzdub/watch.php?id={$content_id}#comment-{$comment_id}");
            }
        }
        
        // Kontent egasiga bildirim (agar u boshqa odam bo'lsa)
        $content_stmt = $pdo->prepare("SELECT * FROM content WHERE id = ?");
        // Bu yerda content jadvalida user_id yo'q, shuning uchun o'tkazib yuboramiz
        
        return ['success' => true, 'comment_id' => $comment_id];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Xatolik yuz berdi.'];
    }
}

function get_comments($pdo, $content_id, $limit = 50) {
    // Asosiy kommentlarni olish (parent_id IS NULL)
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.avatar, u.is_premium
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.content_id = ? AND c.parent_id IS NULL AND c.is_deleted = 0
        ORDER BY c.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$content_id, $limit]);
    return $stmt->fetchAll();
}

function get_comment_replies($pdo, $parent_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.avatar, u.is_premium
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.parent_id = ? AND c.is_deleted = 0
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$parent_id]);
    return $stmt->fetchAll();
}

function toggle_comment_like($pdo, $user_id, $comment_id) {
    $stmt = $pdo->prepare("SELECT * FROM comment_likes WHERE user_id = ? AND comment_id = ?");
    $stmt->execute([$user_id, $comment_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE user_id = ? AND comment_id = ?");
        $stmt->execute([$user_id, $comment_id]);
        
        $pdo->prepare("UPDATE comments SET likes = GREATEST(likes - 1, 0) WHERE id = ?")->execute([$comment_id]);
        return 'removed';
    } else {
        $stmt = $pdo->prepare("INSERT INTO comment_likes (user_id, comment_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $comment_id]);
        
        $pdo->prepare("UPDATE comments SET likes = likes + 1 WHERE id = ?")->execute([$comment_id]);
        return 'liked';
    }
}

function delete_comment($pdo, $comment_id, $user_id) {
    // Faqat muallif yoki admin o'chira oladi
    $stmt = $pdo->prepare("SELECT * FROM comments WHERE id = ?");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();
    
    if (!$comment) {
        return false;
    }
    
    // Admin tekshiruvi (admin jadvalidan)
    $is_admin = false;
    if (isset($_SESSION['admin_id'])) {
        $is_admin = true;
    }
    
    if ($comment['user_id'] != $user_id && !$is_admin) {
        return false;
    }
    
    $stmt = $pdo->prepare("UPDATE comments SET is_deleted = 1 WHERE id = ?");
    return $stmt->execute([$comment_id]);
}

// ===== Ko'rish tarixi (Watch History) =====
function add_to_watch_history($pdo, $user_id, $content_id, $episode_id = null, $progress = 0) {
    if (!$user_id) return; // Faqat ro'yxatdan o'tgan foydalanuvchilar uchun
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO watch_history (user_id, content_id, episode_id, progress_percent) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                watched_at = CURRENT_TIMESTAMP,
                progress_percent = VALUES(progress_percent)
        ");
        return $stmt->execute([$user_id, $content_id, $episode_id, $progress]);
    } catch (PDOException $e) {
        return false;
    }
}

function get_watch_history($pdo, $user_id, $limit = 20) {
    $stmt = $pdo->prepare("
        SELECT wh.*, c.title, c.poster, c.category_id
        FROM watch_history wh
        JOIN content c ON wh.content_id = c.id
        WHERE wh.user_id = ?
        ORDER BY wh.watched_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll();
}

function clear_watch_history($pdo, $user_id) {
    $stmt = $pdo->prepare("DELETE FROM watch_history WHERE user_id = ?");
    return $stmt->execute([$user_id]);
}

// ===== Watchlist (Mening ro'yxatim) =====
function toggle_watchlist($pdo, $user_id, $content_id) {
    $stmt = $pdo->prepare("SELECT * FROM watchlist WHERE user_id = ? AND content_id = ?");
    $stmt->execute([$user_id, $content_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND content_id = ?");
        return $stmt->execute([$user_id, $content_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO watchlist (user_id, content_id) VALUES (?, ?)");
        return $stmt->execute([$user_id, $content_id]);
    }
}

function get_watchlist($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT w.*, c.title, c.poster, c.category_id, c.rating
        FROM watchlist w
        JOIN content c ON w.content_id = c.id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function is_in_watchlist($pdo, $user_id, $content_id) {
    $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND content_id = ?");
    $stmt->execute([$user_id, $content_id]);
    return $stmt->fetch() !== false;
}

// ===== Views sonini oshirish =====
function increment_view($pdo, $content_id, $episode_id = null) {
    // IP va session orqali takrorlanishni oldini olish
    $view_key = "view_{$content_id}";
    if (isset($_SESSION[$view_key])) {
        return; // Allaqachon ko'rilgan
    }
    
    $pdo->prepare("UPDATE content SET views = views + 1 WHERE id = ?")->execute([$content_id]);
    
    if ($episode_id) {
        $pdo->prepare("UPDATE episodes SET views = views + 1 WHERE id = ?")->execute([$episode_id]);
    }
    
    $_SESSION[$view_key] = time();
}

// ===== Avatar URL =====
function avatar_url($avatar, $base = '/uzdub/') {
    if ($avatar) {
        $path = $base . 'uploads/avatars/' . e($avatar);
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uzdub/uploads/avatars/' . $avatar)) {
            return $path;
        }
    }
    return $base . 'assets/default-avatar.svg';
}

// ===== Vaqtni chiroyli ko'rsatish =====
function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    
    if ($diff < 60) {
        return 'Hozirgina';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' daqiqa' . ($mins > 1 ? 'lar' : '') . ' oldin';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' soat' . ($hours > 1 ? 'lar' : '') . ' oldin';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' kun' . ($days > 1 ? 'lar' : '') . ' oldin';
    } else {
        return date('d.m.Y H:i', strtotime($datetime));
    }
}

// ===== YouTube ID olish =====
function get_youtube_id($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^\"&?\/\s]{11})/';
    if (preg_match($pattern, $url, $matches)) {
        return $matches[1];
    }
    return null;
}

// ===== Video player render qilish =====
function render_player($video_type, $video_url, $base_path = 'uploads/videos/') {
    if ($video_type === 'youtube') {
        $yt_id = get_youtube_id($video_url);
        if ($yt_id) {
            return '<div class="player-wrap"><iframe src="https://www.youtube.com/embed/' . e($yt_id) . '" allowfullscreen allow="autoplay; encrypted-media"></iframe></div>';
        }
        return '<p class="player-error">YouTube havolasi noto\'g\'ri.</p>';
    } elseif ($video_type === 'cloud') {
        return '<div class="player-wrap"><iframe src="' . e($video_url) . '" allowfullscreen></iframe></div>';
    } elseif ($video_type === 'file') {
        return '<div class="player-wrap"><video controls autoplay src="' . e($base_path) . e($video_url) . '"></video></div>';
    }
    return '';
}

// ===== Fayl yuklash =====
function upload_file($file_input_name, $target_dir, $allowed_ext, $max_size = 5242880) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $file = $_FILES[$file_input_name];
    
    // Hajmini tekshirish
    if ($file['size'] > $max_size) {
        return false;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed_ext)) {
        return false;
    }
    
    $new_name = uniqid('f_', true) . '.' . $ext;
    $target_path = $target_dir . $new_name;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $new_name;
    }
    
    return false;
}

// ===== Generate unique IDs =====
function generate_user_id($pdo) {
    do {
        $uid = str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $exists = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
        $exists->execute([$uid]);
    } while ($exists->fetch());
    return $uid;
}

function generate_content_code($pdo, $category_slug) {
    $prefix_map = ['kino' => 'KN', 'anime' => 'AN', 'multfilm' => 'MF', 'serial' => 'SR'];
    $prefix = $prefix_map[$category_slug] ?? 'CN';
    
    do {
        $num = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $code = $prefix . $num;
        $exists = $pdo->prepare("SELECT id FROM content WHERE content_code = ?");
        $exists->execute([$code]);
    } while ($exists->fetch());
    
    return $code;
}

// ===== Email validatsiyasi =====
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ===== Parol kuchi tekshiruvi =====
function check_password_strength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Parol kamida 8 belgidan iborat bo\'lishi kerak.';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Parol kamida bitta katta harfni o\'z ichiga olishi kerak.';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Parol kamida bitta kichik harfni o\'z ichiga olishi kerak.';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Parol kamida bitta raqamni o\'z ichiga olishi kerak.';
    }
    
    return empty($errors) ? ['valid' => true] : ['valid' => false, 'errors' => $errors];
}

// ===== JSON response =====
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== IP adres olish =====
function get_client_ip() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

// ===== Browser cache uchun =====
function set_cache_headers($duration = 3600) {
    header("Cache-Control: public, max-age={$duration}");
    header("Expires: " . gmdate('D, d M Y H:i:s', time() + $duration) . ' GMT');
}

// ===== Debug logging =====
function log_debug($message, $file = 'debug.log') {
    $log_file = __DIR__ . '/../logs/' . $file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
