<?php
/**
 * Bosh sahifa - Yangilangan dizayn bilan
 */
$currentPage = 'home';
$pageTitle = 'Uzdub - Bosh sahifa';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Eng so'nggi videolarni olish
$pdo = getDBConnection();
$stmt = $pdo->query("
    SELECT c.*, u.username, 
           (SELECT COUNT(*) FROM likes WHERE content_id = c.id) as like_count,
           (SELECT COUNT(*) FROM comments WHERE content_id = c.id) as comment_count
    FROM content c
    JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
    LIMIT 20
");
$videos = $stmt->fetchAll();

// Premium foydalanuvchilar uchun tavsiyalar
$recommendations = [];
if ($user = getCurrentUser()) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.username,
               (SELECT COUNT(*) FROM likes WHERE content_id = c.id) as like_count
        FROM content c
        JOIN users u ON c.user_id = u.id
        WHERE c.id NOT IN (SELECT content_id FROM watch_history WHERE user_id = ?)
        ORDER BY RAND()
        LIMIT 8
    ");
    $stmt->execute([$user['id']]);
    $recommendations = $stmt->fetchAll();
}

include __DIR__ . '/includes/header_new.php';
?>

<style>
.hero-section {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(236, 72, 153, 0.2));
    border-radius: var(--border-radius);
    padding: 3rem;
    margin: 2rem 0;
    text-align: center;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 2rem 0 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.premium-badge {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #000;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}
</style>

<div class="hero-section">
    <h1 style="font-size: 2.5rem; margin-bottom: 1rem; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Xush kelibsiz!</h1>
    <p style="color: var(--text-secondary); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Eng so'nggi va eng mashhur videolarni ko'ring, layk bosing, sharh qoldiring va do'stlaringiz bilan baham ko'ring.</p>
    
    <?php if (!$user): ?>
        <div style="margin-top: 2rem;">
            <a href="/auth/register.php" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">Bepul ro'yxatdan o'tish</a>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($recommendations) && $user && isPremium($user['id'])): ?>
<section>
    <h2 class="section-title">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="color: #fbbf24;">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
        </svg>
        Siz uchun tavsiyalar
        <span class="premium-badge">Premium</span>
    </h2>
    
    <div class="video-grid">
        <?php foreach ($recommendations as $video): ?>
            <div class="video-card">
                <a href="/watch.php?id=<?php echo $video['id']; ?>">
                    <div class="video-thumbnail">
                        <img src="/uploads/thumbnails/<?php echo escape($video['thumbnail'] ?? 'default.jpg'); ?>" alt="<?php echo escape($video['title']); ?>">
                        <?php if ($video['duration']): ?>
                            <span class="video-duration"><?php echo escape($video['duration']); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="video-info">
                    <a href="/watch.php?id=<?php echo $video['id']; ?>" style="text-decoration: none;">
                        <h3 class="video-title"><?php echo escape($video['title']); ?></h3>
                    </a>
                    <div class="video-meta">
                        <span><?php echo escape($video['username']); ?></span>
                        <span><?php echo number_format($video['views'] ?? 0); ?> ko'rish</span>
                    </div>
                    <div class="video-stats">
                        <button class="like-btn" data-content-id="<?php echo $video['id']; ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                            <span class="like-count"><?php echo $video['like_count']; ?></span>
                        </button>
                        <span class="stat-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            <?php echo $video['comment_count'] ?? 0; ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section>
    <h2 class="section-title">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
            <polyline points="2 17 12 22 22 17"></polyline>
            <polyline points="2 12 12 17 22 12"></polyline>
        </svg>
        Eng so'nggi videolar
    </h2>
    
    <div class="video-grid">
        <?php foreach ($videos as $video): ?>
            <div class="video-card">
                <a href="/watch.php?id=<?php echo $video['id']; ?>">
                    <div class="video-thumbnail">
                        <img src="/uploads/thumbnails/<?php echo escape($video['thumbnail'] ?? 'default.jpg'); ?>" alt="<?php echo escape($video['title']); ?>">
                        <?php if ($video['duration']): ?>
                            <span class="video-duration"><?php echo escape($video['duration']); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="video-info">
                    <a href="/watch.php?id=<?php echo $video['id']; ?>" style="text-decoration: none;">
                        <h3 class="video-title"><?php echo escape($video['title']); ?></h3>
                    </a>
                    <div class="video-meta">
                        <span><?php echo escape($video['username']); ?></span>
                        <span><?php echo number_format($video['views'] ?? 0); ?> ko'rish</span>
                    </div>
                    <div class="video-stats">
                        <button class="like-btn" data-content-id="<?php echo $video['id']; ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                            <span class="like-count"><?php echo $video['like_count']; ?></span>
                        </button>
                        <span class="stat-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                            <?php echo $video['comment_count'] ?? 0; ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (empty($videos)): ?>
        <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity: 0.5; margin-bottom: 1rem;">
                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                <polyline points="2 17 12 22 22 17"></polyline>
                <polyline points="2 12 12 17 22 12"></polyline>
            </svg>
            <p>Hozircha videolar yo'q. Birinchi bo'lib video yuklang!</p>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer_new.php'; ?>
