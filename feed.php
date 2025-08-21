<?php
/**
 * Page de fil d'actualité IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

SessionManager::requireLogin();

$pageTitle = "Fil d'actualité";
$pageDescription = "Découvrez les dernières publications de vos amis";
$bodyClass = "feed-page";

$db = initDatabase();
$currentUser = SessionManager::getCurrentUser();

include 'includes/header.php';
?>

<div class="feed-container">
    <div class="feed-layout">
        <!-- Sidebar gauche -->
        <aside class="feed-sidebar left-sidebar">
            <div class="sidebar-section">
                <div class="user-card">
                    <img src="uploads/avatars/<?php echo htmlspecialchars($currentUser['avatar']) ?: 'default-avatar.svg'; ?>"
                         alt="<?= htmlspecialchars($currentUser['first_name']) ?>" class="user-card-avatar">
                    <div class="user-card-info">
                        <h3><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h3>
                        <p class="username">@<?= htmlspecialchars($currentUser['username']) ?></p>
                        <?php if (!empty($currentUser['bio'])): ?>
                            <p class="bio"><?= htmlspecialchars($currentUser['bio']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sidebar-section">
                <h4>Raccourcis</h4>
                <nav class="sidebar-nav">
                    <a href="feed.php" class="nav-item active">
                        <i class="fas fa-home"></i>
                        <span>Accueil</span>
                    </a>
                    <a href="friends.php" class="nav-item">
                        <i class="fas fa-user-friends"></i>
                        <span>Amis</span>
                    </a>
                    <a href="groups.php" class="nav-item">
                        <i class="fas fa-layer-group"></i>
                        <span>Groupes</span>
                    </a>
                    <a href="messages.php" class="nav-item">
                        <i class="fas fa-comments"></i>
                        <span>Messages</span>
                    </a>
                    <a href="profile.php" class="nav-item">
                        <i class="fas fa-user"></i>
                        <span>Mon profil</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Contenu principal -->
        <main class="feed-main">
            <!-- Créer une publication -->
            <div class="card create-post-card">
                <form id="new-post-form">
                    <div class="card-body">
                        <div class="create-post-header">
                            <img src="uploads/avatars/<?= htmlspecialchars($currentUser['avatar'] ?: 'default-avatar.svg') ?>"
                                 alt="<?= htmlspecialchars($currentUser['first_name']) ?>" class="post-avatar">
                            <div class="create-post-input">
                                <textarea placeholder="Quoi de neuf, <?= htmlspecialchars($currentUser['first_name']) ?> ?"
                                          class="post-textarea" rows="3"></textarea>
                                <!-- Ajoutez ceci dans votre formulaire, après le textarea -->
                                <input type="file" class="post-image-input" accept="image/*" multiple style="display:none;">
                            </div>
                            <!-- Ajouter ce modal pour les émojis -->
                            <div id="emoji-modal" class="emoji-modal" style="display:none;">
                                <div class="emoji-container">
                                    <!-- Catégories d'émojis -->
                                    <div class="emoji-categories">
                                        <button data-category="smileys" type="button">😀</button>
                                        <button data-category="animals" type="button">🐻</button>
                                        <button data-category="food" type="button">🍎</button>
                                        <button data-category="travel" type="button">🚗</button>
                                    </div>

                                    <!-- Liste d'émojis -->
                                    <div class="emoji-list" id="emoji-list">
                                        <!-- Chargé dynamiquement -->
                                    </div>

                                    <button class="close-emoji-modal" type="button">Fermer</button>
                                </div>
                            </div>
                            <!-- Ajouter ce modal pour la localisation -->
                            <div id="location-modal" class="location-modal" style="display:none;">
                                <div class="location-container">
                                    <input type="text" id="location-search" placeholder="Rechercher un lieu...">
                                    <div class="location-results" id="location-results">
                                        <!-- Résultats de recherche -->
                                    </div>
                                    <button class="close-location-modal"  type="button">Fermer</button>
                                </div>
                            </div>
                        </div>

                        <div class="image-preview" style="display:none;"></div>

                        <div class="create-post-actions">
                            <div class="post-options">
                                <button type="button" class="post-option" id="photo-btn">
                                    <i class="fas fa-camera"></i>
                                    <span>Photo</span>
                                </button>
                                <button type="button" class="post-option" id="emoji-btn">
                                    <i class="fas fa-smile"></i>
                                    <span>Emoji</span>
                                </button>
<!--                                <button type="button" class="post-option" id="location-btn">-->
<!--                                    <i class="fas fa-map-marker-alt"></i>-->
<!--                                    <span>Lieu</span>-->
<!--                                </button>-->
                            </div>
                            <div class="post-privacy">
                                <select class="privacy-select" id="post-privacy" name="privacy">
                                    <option value="public">Public</option>
                                    <option value="friends" selected>Amis</option>
                                    <option value="private">Privé</option>
                                </select>
                                <button type="submit" class="btn btn-primary post-submit-btn" disabled>
                                    Publier
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Feed des publications -->
            <div id="feed-container" class="posts-container">
                <div class="loading-posts">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Chargement des publications...</p>
                </div>
            </div>

            <!-- Bouton charger plus -->
            <div class="load-more-container" id="load-more-container" style="display:none;">
                <button class="btn btn-outline" id="load-more-btn">
                    Charger plus de publications
                </button>
            </div>
        </main>

        <!-- Sidebar droite -->
        <aside class="feed-sidebar right-sidebar">
            <div class="sidebar-section">
                <h4>Suggestions d'amis</h4>
                <div class="suggestions-list" id="friend-suggestions">
                    <div class="loading-suggestions">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </div>
            </div>

            <div class="sidebar-section">
                <h4>Tendances</h4>
                <div class="trending-list" id="trending-topics">
                    <div class="loading-trends">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </div>
            </div>

            <div class="sidebar-section">
                <h4>Activité récente</h4>
                <div class="activity-list" id="recent-activity">
                    <!-- Activité chargée dynamiquement -->
                </div>
            </div>
        </aside>
    </div>
</div>

<input type="file" id="media-file-input" accept="image/*" multiple style="display:none;">

<style>
    <?php
    // On intègre directement le CSS du deuxième fichier
    include 'assets/css/feed-style.css';
    ?>
</style>

<?php include 'includes/footer2.php'; ?>
<script src="assets/js/PostMenu.js"></script>
<script src="assets/js/ThemeSwitcher.js"></script>
