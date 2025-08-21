<!-- Scripts JavaScript -->
<script src="assets/js/utils.js"></script>
<script src="assets/js/notifications.js"></script>
<?php if (SessionManager::isLoggedIn()): ?>
    <script>
        window.app = window.app || {}
        window.app.websocketUrl = "ws://localhost:8080"
    </script>

    <script src="assets/js/chat.js"></script>
    <script src="assets/js/typing-indicator.js"></script>
    <script src="assets/js/feeds.js"></script>
    <script src="assets/js/search.js"></script>
<?php endif; ?>
<script src="assets/js/main.js"></script>

<!-- Script pour les notifications temps réel et UI -->
<?php if (SessionManager::isLoggedIn()): ?>
    <script>
        // Configuration AJAX
        const userId = <?php echo SessionManager::getUserId(); ?>;
        const csrfToken = '<?php echo SessionManager::getCsrfToken(); ?>';

        // Gestion du menu dropdown utilisateur
        document.addEventListener('DOMContentLoaded', function() {
            const userMenuBtn = document.getElementById('user-menu-btn');
            const userDropdown = document.getElementById('user-dropdown');

            if (userMenuBtn && userDropdown) {
                userMenuBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Toggle du menu
                    const isVisible = userDropdown.style.display === 'block';
                    userDropdown.style.display = isVisible ? 'none' : 'block';
                });

                // Fermer le menu si on clique ailleurs
                document.addEventListener('click', function(e) {
                    if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.style.display = 'none';
                    }
                });
            }

            // Fermeture des messages flash
            document.querySelectorAll('.close-alert').forEach(button => {
                button.addEventListener('click', function() {
                    this.parentElement.remove();
                });
            });
        });

        // Démarrer les vérifications temps réel
        startRealTimeUpdates();
    </script>
<?php endif; ?>