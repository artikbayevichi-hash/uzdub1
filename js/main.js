// Uzdub Platform - Asosiy JavaScript funksiyalar

document.addEventListener('DOMContentLoaded', function() {
    initNotifications();
    initLikeButtons();
    initWatchlist();
    initSearch();
});

/**
 * Bildirimlar funksiyalari
 */
function initNotifications() {
    const notificationBtn = document.querySelector('.notification-btn');
    const dropdown = document.querySelector('.notifications-dropdown');
    
    if (!notificationBtn || !dropdown) return;
    
    notificationBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('show');
        loadNotifications();
    });
    
    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target) && e.target !== notificationBtn) {
            dropdown.classList.remove('show');
        }
    });
}

function loadNotifications() {
    fetch('/api/notifications.php')
        .then(response => response.json())
        .then(data => {
            const list = document.querySelector('.notification-list');
            if (!list) return;
            
            if (data.notifications.length === 0) {
                list.innerHTML = '<li class="notification-item"><p>Yangi bildirimlar yo\'q</p></li>';
                return;
            }
            
            list.innerHTML = data.notifications.map(notif => `
                <li class="notification-item ${notif.is_read ? '' : 'unread'}" 
                    onclick="markAsRead(${notif.id})">
                    <div class="notification-type ${notif.type}">${notif.type}</div>
                    <div class="notification-title">${escapeHtml(notif.title)}</div>
                    <div class="notification-message">${escapeHtml(notif.message)}</div>
                    <div class="notification-time">${timeAgo(notif.created_at)}</div>
                </li>
            `).join('');
            
            updateBadgeCount(data.unread_count);
        })
        .catch(err => console.error('Bildirimlarni yuklashda xatolik:', err));
}

function markAsRead(notificationId) {
    fetch('/api/notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_read',
            notification_id: notificationId,
            csrf_token: getCsrfToken()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    });
}

function markAllAsRead() {
    fetch('/api/notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_all_read',
            csrf_token: getCsrfToken()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    });
}

function updateBadgeCount(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }
}

/**
 * Layk funksiyalari
 */
function initLikeButtons() {
    document.querySelectorAll('.like-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const contentId = this.dataset.contentId;
            const commentId = this.dataset.commentId;
            
            toggleLike(contentId, commentId, this);
        });
    });
}

function toggleLike(contentId, commentId, btn) {
    const url = commentId ? '/api/likes.php' : '/api/likes.php';
    const data = {
        csrf_token: getCsrfToken()
    };
    
    if (contentId) data.content_id = contentId;
    if (commentId) data.comment_id = commentId;
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const countEl = btn.querySelector('.like-count');
            if (data.liked) {
                btn.classList.add('liked');
                if (countEl) countEl.textContent = parseInt(countEl.textContent) + 1;
            } else {
                btn.classList.remove('liked');
                if (countEl) countEl.textContent = parseInt(countEl.textContent) - 1;
            }
        } else if (data.error) {
            showAlert(data.error, 'error');
        }
    })
    .catch(err => {
        console.error('Xatolik:', err);
        showAlert('Xatolik yuz berdi', 'error');
    });
}

/**
 * Watchlist funksiyalari
 */
function initWatchlist() {
    document.querySelectorAll('.watchlist-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const contentId = this.dataset.contentId;
            toggleWatchlist(contentId, this);
        });
    });
}

function toggleWatchlist(contentId, btn) {
    fetch('/api/watchlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            content_id: contentId,
            csrf_token: getCsrfToken()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.added) {
                btn.classList.add('added');
                btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> Qo\'shildi';
            } else {
                btn.classList.remove('added');
                btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> Keyinroq';
            }
        }
    });
}

/**
 * Qidiruv funksiyalari
 */
function initSearch() {
    const searchForm = document.querySelector('.search-bar form');
    if (!searchForm) return;
    
    const input = searchForm.querySelector('input');
    let timeout;
    
    input.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            performSearch(this.value);
        }, 500);
    });
}

function performSearch(query) {
    if (query.length < 2) {
        hideSearchResults();
        return;
    }
    
    fetch(`/api/search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            showSearchResults(data.results);
        });
}

function showSearchResults(results) {
    // Search results dropdown ko'rsatish
    console.log('Qidiruv natijalari:', results);
}

function hideSearchResults() {
    // Search results dropdown yashirish
}

/**
 * Yordamchi funksiyalar
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'Hozirgina';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' daqiqa oldin';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' soat oldin';
    if (seconds < 604800) return Math.floor(seconds / 86400) + ' kun oldin';
    
    return date.toLocaleDateString('uz-UZ');
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <span>${escapeHtml(message)}</span>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;margin-left:auto;">&times;</button>
    `;
    
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        setTimeout(() => alertDiv.remove(), 5000);
    }
}

// Service Worker ro'yxatdan o'tkazish (agar mavjud bo'lsa)
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js')
        .then(registration => {
            console.log('ServiceWorker registered:', registration.scope);
        })
        .catch(error => {
            console.log('ServiceWorker registration failed:', error);
        });
}
