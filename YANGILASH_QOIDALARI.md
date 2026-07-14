# UZDUB PLATFORMASINI YANGILASH QOIDALARI

## 1. Ma'lumotlar bazasini import qilish (XAMPP orqali)

### 1-usul: phpMyAdmin orqali
1. XAMPP Control Panel dan Apache va MySQL ni ishga tushiring
2. Brauzerda `http://localhost/phpmyadmin` oching
3. Chap tomondan `uzdub` bazasini tanlang
4. Yuqoridagi menuda **SQL** tugmasini bosing
5. Quyidagi SQL kodni nusxalab, joylashtiring va **Go** tugmasini bosing:

```sql
-- Janrlar jadvali
CREATE TABLE IF NOT EXISTS genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bildirimlar jadvali
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('like', 'comment', 'message', 'system', 'premium') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    link VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Layklar jadvali
CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT DEFAULT NULL,
    comment_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (user_id, content_id, comment_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ko'rish tarixi
CREATE TABLE IF NOT EXISTS watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress_seconds INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_user_history (user_id, watched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Watchlist
CREATE TABLE IF NOT EXISTS watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_watchlist (user_id, content_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Foydalanuvchilar jadvaliga yangi ustunlar
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT 'default.png' AFTER email;
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT AFTER avatar;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_premium TINYINT(1) DEFAULT 0 AFTER bio;
ALTER TABLE users ADD COLUMN IF NOT EXISTS premium_expires_at DATETIME NULL AFTER is_premium;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME NULL AFTER premium_expires_at;

-- Sozlamalar jadvali
CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    theme VARCHAR(20) DEFAULT 'light',
    notifications_enabled TINYINT(1) DEFAULT 1,
    privacy_profile TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dastlabki janrlar
INSERT INTO genres (name, slug) VALUES 
('Action', 'action'), ('Comedy', 'comedy'), ('Drama', 'drama'), 
('Horror', 'horror'), ('Sci-Fi', 'sci-fi'), ('Romance', 'romance')
ON DUPLICATE KEY UPDATE name=name;
```

### 2-usul: XAMPP Shell orqali
1. XAMPP Control Panel da **Shell** tugmasini bosing
2. Quyidagi buyruqlarni ketma-ket bajaring:

```bash
cd C:\xampp\mysql\bin
mysql -u root -p uzdub < C:\xampp\htdocs\uzdub\database.sql
```

## 2. Fayllarni XAMPP ga ko'chirish

Loyiha fayllarini quyidagi papkaga ko'chiring:
```
C:\xampp\htdocs\uzdub\
```

Kerakli fayllar:
- `config/database.php` - Baza sozlamalari
- `includes/functions.php` - Asosiy funksiyalar
- `includes/header_new.php` - Yangi header
- `includes/footer_new.php` - Yangi footer
- `css/style.css` - Yangi dizayn
- `js/main.js` - Yangi JavaScript
- `api/notifications.php` - Bildirimlar API
- `api/likes.php` - Layklar API
- `api/watchlist.php` - Watchlist API
- `index_new.php` - Yangi bosh sahifa

## 3. Konfiguratsiyani tekshirish

`config/database.php` faylida quyidagilarni tekshiring:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'uzdub');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP da odatda parol bo'sh
```

## 4. Loyihani ishga tushirish

1. XAMPP Control Panel da Apache ni ishga tushiring
2. Brauzerda `http://localhost/u zdub` manziliga kiring
3. Yangi dizayn va funksiyalarni tekshiring:
   - Bildirimlar tizimi
   - Layk bosish
   - Watchlist qo'shish
   - Zamonaviy dizayn

## 5. Yangi funksiyalar

### ✅ Qo'shilgan funksiyalar:
1. **Bildirimlar** - Layk, sharh, xabar uchun real-time bildirimlar
2. **Layklar** - Kontent va sharhlarga layk bosish
3. **Sharhlar** - Ierarxik sharhlar tizimi
4. **Watchlist** - Keyinroq ko'rish uchun saqlash
5. **Ko'rish tarixi** - Avval ko'rilgan videolar
6. **Premium status** - Pullik obuna tizimi
7. **Foydalanuvchi sozlamalari** - Mavzu, bildirimlar, maxfiylik

### 🎨 Dizayn yangiliklari:
- Zamonaviy gradient ranglar
- Responsive dizayn (mobil qurilmalar uchun)
- Animatsiyalar va hover effektlar
- Qorong'u mavzu (dark mode)
- Yangi header va footer

### 🔒 Xavfsizlik:
- CSRF token himoyasi
- XSS hujumlaridan himoya
- Prepared statements (SQL injection oldini olish)
- Session boshqaruvi

## 6. Muammolarni hal qilish

### Agar sahifa ochilmasa:
1. XAMPP da Apache va MySQL ishlaganligini tekshiring
2. `config/database.php` da baza nomini tekshiring
3. Browser konsolda xatoliklarni tekshiring (F12)

### Agar baza xatoligi bo'lsa:
1. phpMyAdmin da `uzdub` bazasi borligini tekshiring
2. Jadvallar yaratilganligini tekshiring
3. Users jadvalida kerakli ustunlar borligini tekshiring

## 7. Keyingi qadamlar

1. Eski `index.php` ni `index_new.php` bilan almashtirish
2. Har bir sahifada yangi header/footer ishlatish
3. Watch.php sahifasida sharhlar va layklarni qo'shish
4. Profile.php sahifasida sozlamalarni qo'shish
5. Admin panelni yangilash

---
**Muallif:** Uzdub Team
**Sana:** 2024
