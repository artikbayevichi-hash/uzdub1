<?php
// Ma'lumotlar bazasi sozlamalari
define('DB_HOST', 'localhost');
define('DB_NAME', 'uzdub');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP da odatda parol bo'sh
define('DB_CHARSET', 'utf8mb4');

// Baza bilan bog'lanish
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Bazaga ulanishda xatolik: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Foydalanuvchi sessiyasini boshlash
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Foydalanuvchi ma'lumotlarini olish
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, username, email, avatar, is_premium, premium_expires_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Xavfsizlik: XSS hujumidan himoya
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Xavfsizlik: CSRF token yaratish
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Xavfsizlik: CSRF token tekshirish
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
