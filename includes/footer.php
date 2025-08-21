    </main>
    
    <?php if (SessionManager::isLoggedIn()): ?>
    <!-- Chat flottant -->
    <div class="floating-chat" id="floating-chat">
        <div class="chat-header">
            <h4>Messages</h4>
            <button class="chat-minimize" id="chat-minimize">
                <i class="fas fa-minus"></i>
            </button>
        </div>
        <div class="chat-body">
            <div class="recent-conversations" id="recent-conversations">
                <!-- Conversations récentes chargées en AJAX -->
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>IDEM</h4>
                    <p>Connectez-vous avec vos amis et découvrez de nouvelles personnes.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h5>Navigation</h5>
                    <ul>
                        <li><a href="feed.php">Accueil</a></li>
                        <li><a href="friends.php">Amis</a></li>
                        <li><a href="groups.php">Groupes</a></li>
                        <li><a href="search.php">Recherche</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h5>Aide</h5>
                    <ul>
                        <li><a href="help.php">Centre d'aide</a></li>
                        <li><a href="privacy.php">Confidentialité</a></li>
                        <li><a href="terms.php">Conditions d'utilisation</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h5>Communauté</h5>
                    <ul>
                        <li><a href="blog.php">Blog</a></li>
                        <li><a href="events.php">Événements</a></li>
                        <li><a href="developers.php">Développeurs</a></li>
                        <li><a href="feedback.php">Feedback</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="footer-copyright">
                    <p>&copy; <?php echo date('Y'); ?> IDEM. Tous droits réservés.</p>
                </div>
                <div class="footer-links">
                    <a href="privacy.php">Politique de confidentialité</a>
                    <a href="terms.php">Conditions d'utilisation</a>
                    <a href="cookies.php">Politique des cookies</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts JavaScript -->
    <script src="assets/js/utils.js"></script>
    <script src="assets/js/notifications.js"></script>
    <?php if (SessionManager::isLoggedIn()): ?>
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
    
</body>
</html>