<?php
/**
 * Yangilangan Header - Zamonaviy dizayn bilan
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$user = getCurrentUser();
$unreadCount = $user ? getUnreadNotificationsCount($user['id']) : 0;
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
    <title><?php echo escape($pageTitle ?? 'Uzdub - Video Platform'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/ai-chat.css">
    <link rel="stylesheet" href="/css/voice-assistant.css">
</head>
<body>
    <header class="header">
        <div class="container header-content">
            <a href="/" class="logo">Uzdub</a>
            
            <nav>
                <ul class="nav-menu">
                    <li><a href="/" class="<?php echo ($currentPage ?? '') === 'home' ? 'active' : ''; ?>">Bosh sahifa</a></li>
                    <li><a href="/category.php" class="<?php echo ($currentPage ?? '') === 'category' ? 'active' : ''; ?>">Kategoriyalar</a></li>
                    <li><a href="/mylist.php" class="<?php echo ($currentPage ?? '') === 'mylist' ? 'active' : ''; ?>">Mening ro'yxatim</a></li>
                    <li><a href="/premium.php" class="<?php echo ($currentPage ?? '') === 'premium' ? 'active' : ''; ?>">Premium</a></li>
                </ul>
            </nav>
            
            <div class="user-actions">
                <?php if ($user): ?>
                    <div class="search-bar">
                        <form action="/search.php" method="GET">
                            <input type="text" name="q" placeholder="Qidiruv..." autocomplete="off">
                        </form>
                    </div>
                    
                    <button class="notification-btn" onclick="toggleNotifications()">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <?php if ($unreadCount > 0): ?>
                            <span class="notification-badge"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="notifications-dropdown">
                        <div class="notification-header">
                            <strong>Bildirimlar</strong>
                            <button class="btn btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.85rem;" onclick="markAllAsRead()">Barchasini o'qilgan deb belgilash</button>
                        </div>
                        <ul class="notification-list">
                            <li class="notification-item">Yuklanmoqda...</li>
                        </ul>
                    </div>
                    
                    <a href="/profile.php" class="btn btn-secondary">
                        <img src="/uploads/avatars/<?php echo escape($user['avatar'] ?? 'default.png'); ?>" 
                             alt="<?php echo escape($user['username']); ?>" 
                             style="width: 24px; height: 24px; border-radius: 50%; margin-right: 8px;">
                        <?php echo escape($user['username']); ?>
                    </a>
                    
                    <?php if ($user['is_premium']): ?>
                        <span class="btn btn-success" style="padding: 0.5rem 1rem; font-size: 0.85rem;">★ Premium</span>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <a href="/auth/login.php" class="btn btn-secondary">Kirish</a>
                    <a href="/auth/register.php" class="btn btn-primary">Ro'yxatdan o'tish</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <main class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <span><?php echo escape($_SESSION['success']); unset($_SESSION['success']); ?></span>
                <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;margin-left:auto;">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <span><?php echo escape($_SESSION['error']); unset($_SESSION['error']); ?></span>
                <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;margin-left:auto;">&times;</button>
            </div>
        <?php endif; ?>
