    </main>
    
    <footer style="background: rgba(30, 41, 59, 0.8); border-top: 1px solid rgba(255, 255, 255, 0.1); padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <a href="/" class="logo" style="font-size: 1.5rem;">Uzdub</a>
                    <p style="color: var(--text-secondary); margin-top: 0.5rem;">© 2024 Uzdub. Barcha huquqlar himoyalangan.</p>
                </div>
                <div style="display: flex; gap: 2rem;">
                    <a href="/about.php" style="color: var(--text-secondary); text-decoration: none;">Biz haqimizda</a>
                    <a href="/contact.php" style="color: var(--text-secondary); text-decoration: none;">Aloqa</a>
                    <a href="/privacy.php" style="color: var(--text-secondary); text-decoration: none;">Maxfiylik</a>
                    <a href="/terms.php" style="color: var(--text-secondary); text-decoration: none;">Shartlar</a>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="/js/main.js"></script>
    <script src="/js/ai-chat.js"></script>
    <script src="/js/voice-assistant.js"></script>
    <script>
        // Bildirimlar dropdown ochish/yopish
        function toggleNotifications() {
            const dropdown = document.querySelector('.notifications-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
                if (dropdown.classList.contains('show')) {
                    loadNotifications();
                }
            }
        }
        
        // Barcha bildirimlarni o'qilgan deb belgilash
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
                    const badge = document.querySelector('.notification-badge');
                    if (badge) badge.style.display = 'none';
                }
            });
        }
        
        // Tashqariga bosganda dropdown yopish
        document.addEventListener('click', function(e) {
            const dropdown = document.querySelector('.notifications-dropdown');
            const btn = document.querySelector('.notification-btn');
            if (dropdown && btn && !dropdown.contains(e.target) && e.target !== btn) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>
