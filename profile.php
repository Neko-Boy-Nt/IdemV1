<?php
/**
 * Page de profil utilisateur IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

SessionManager::requireLogin();

$db = initDatabase();
$currentUser = SessionManager::getCurrentUser();

// Récupérer l'ID du profil à afficher
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : $currentUser['id'];
$isOwnProfile = ($profileId === $currentUser['id']);

// Récupérer les informations du profil
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$profileId]);
    $profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$profileUser) {
        redirect('feed.php?error=user_not_found');
    }
} catch (Exception $e) {
    redirect('feed.php?error=database_error');
}

$pageTitle = $isOwnProfile ? "Mon profil" : $profileUser['first_name'] . " " . $profileUser['last_name'];
$pageDescription = "Profil de " . $profileUser['first_name'] . " sur IDEM";
$bodyClass = "profile-page";

include 'includes/header.php';
?>

<div class="profile-container">
    <div class="profile-layout">
        <!-- Header du profil -->
        <div class="profile-header">
            <div class="cover-photo">
                <img src="uploads/covers/<?= htmlspecialchars($profileUser['cover_photo'] ?: 'default-cover.jpg') ?>" 
                     alt="Photo de couverture" class="cover-image">
                <?php if ($isOwnProfile): ?>
                <button class="change-cover-btn" title="Changer la photo de couverture">
                    <i class="fas fa-camera"></i>
                </button>
                <?php endif; ?>
            </div>
            
            <div class="profile-info">
                <div class="profile-avatar-section">
                    <div class="profile-avatar-container">
                        <img src="uploads/avatars/<?= htmlspecialchars($profileUser['avatar'] ?: 'default-avatar.svg') ?>"
                             alt="<?= htmlspecialchars($profileUser['first_name']) ?>"
                             class="profile-avatar">
                        <?php if ($isOwnProfile): ?>
                        <button class="change-avatar-btn" title="Changer la photo de profil">
                            <i class="fas fa-camera"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="profile-details">
                    <h1 class="profile-name">
                        <?= htmlspecialchars($profileUser['first_name'] . ' ' . $profileUser['last_name']) ?>
                    </h1>
                    <p class="profile-username">@<?= htmlspecialchars($profileUser['username']) ?></p>
                    
                    <?php if (!empty($profileUser['bio'])): ?>
                    <p class="profile-bio"><?= htmlspecialchars($profileUser['bio']) ?></p>
                    <?php endif; ?>
                    
                    <div class="profile-meta">
                        <span class="profile-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($profileUser['location'] ?: 'Non spécifié') ?>
                        </span>
                        <span class="profile-joined">
                            <i class="fas fa-calendar"></i>
                            Membre depuis <?= date('F Y', strtotime($profileUser['created_at'])) ?>
                        </span>
                    </div>
                </div>
                
                <div class="profile-actions">
                    <?php if ($isOwnProfile): ?>
                    <button class="btn btn-primary" onclick="editProfile()">
                        <i class="fas fa-edit"></i>
                        Modifier le profil
                    </button>
                    <?php else: ?>
                    <button class="btn btn-primary" onclick="sendFriendRequest(<?= $profileId ?>)">
                        <i class="fas fa-user-plus"></i>
                        Ajouter
                    </button>
                    <button class="btn btn-secondary" onclick="sendMessage(<?= $profileId ?>)">
                        <i class="fas fa-comment"></i>
                        Message
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="profile-stats">
                <div class="stat-item">
                    <span class="stat-number" id="posts-count">0</span>
                    <span class="stat-label">Publications</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="friends-count">0</span>
                    <span class="stat-label">Amis</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="photos-count">0</span>
                    <span class="stat-label">Photos</span>
                </div>
            </div>
        </div>
        
        <!-- Contenu du profil -->
        <div class="profile-content">
            <div class="profile-main">
                <!-- Navigation des onglets -->
                <div class="profile-tabs">
                    <button class="tab-btn active" data-tab="posts">
                        <i class="fas fa-stream"></i>
                        Publications
                    </button>
                    <button class="tab-btn" data-tab="photos">
                        <i class="fas fa-images"></i>
                        Photos
                    </button>
                    <button class="tab-btn" data-tab="friends">
                        <i class="fas fa-users"></i>
                        Amis
                    </button>
                    <?php if ($isOwnProfile): ?>
                    <button class="tab-btn" data-tab="saved">
                        <i class="fas fa-bookmark"></i>
                        Sauvegardés
                    </button>
                    <?php endif; ?>
                </div>
                
                <!-- Contenu des onglets -->
                <div class="tab-content">
                    <div class="tab-pane active" id="posts-tab">
                        <?php if ($isOwnProfile): ?>
                        <!-- Créer une publication -->
                        <div class="create-post-card">
                            <div class="create-post-header">
                                <img src="uploads/avatars/<?= htmlspecialchars($currentUser['avatar'] ?: 'default-avatar.svg') ?>"
                                     alt="<?= htmlspecialchars($currentUser['first_name']) ?>"
                                     class="create-post-avatar">
                                <textarea placeholder="Quoi de neuf, <?= htmlspecialchars($currentUser['first_name']) ?> ?"
                                          id="post-content"
                                          rows="3"></textarea>
                            </div>
                            <div class="create-post-actions">
                                <div class="post-media-buttons">
                                    <button class="media-btn" id="photo-btn">
                                        <i class="fas fa-camera"></i>
                                        Photo
                                    </button>
                                    <button class="media-btn" id="video-btn">
                                        <i class="fas fa-video"></i>
                                        Vidéo
                                    </button>
                                </div>
                                <button class="btn btn-primary" id="publish-btn" disabled>
                                    Publier
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Posts du profil -->
                        <div class="profile-posts" id="profile-posts">
                            <div class="loading-posts">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Chargement des publications...</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane" id="photos-tab">
                        <div class="photos-grid" id="photos-grid">
                            <div class="loading-photos">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Chargement des photos...</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane" id="friends-tab">
                        <div class="friends-grid" id="friends-grid">
                            <div class="loading-friends">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Chargement des amis...</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($isOwnProfile): ?>
                    <div class="tab-pane" id="saved-tab">
                        <div class="saved-posts" id="saved-posts">
                            <div class="loading-saved">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Chargement des publications sauvegardées...</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sidebar du profil -->
            <aside class="profile-sidebar">
                <div class="sidebar-section">
                    <h3>Informations</h3>
                    <div class="info-items">
                        <?php if (!empty($profileUser['email']) && $isOwnProfile): ?>
                        <div class="info-item">
                            <i class="fas fa-envelope"></i>
                            <span><?= htmlspecialchars($profileUser['email']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($profileUser['phone'])): ?>
                        <div class="info-item">
                            <i class="fas fa-phone"></i>
                            <span><?= htmlspecialchars($profileUser['phone']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($profileUser['website'])): ?>
                        <div class="info-item">
                            <i class="fas fa-globe"></i>
                            <a href="<?= htmlspecialchars($profileUser['website']) ?>" target="_blank">
                                <?= htmlspecialchars($profileUser['website']) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <i class="fas fa-birthday-cake"></i>
                            <span><?= htmlspecialchars($profileUser['birth_date'] ? date('d/m/Y', strtotime($profileUser['birth_date'])) : 'Non spécifié') ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="sidebar-section">
                    <h3>Amis récents</h3>
                    <div class="recent-friends" id="recent-friends">
                        <div class="loading-recent-friends">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </div>
                </div>
                
                <div class="sidebar-section">
                    <h3>Photos récentes</h3>
                    <div class="recent-photos" id="recent-photos">
                        <div class="loading-recent-photos">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<style>
/* Styles pour la page de profil */
.profile-page .main-content {
    padding: 0;
    background: var(--bg-secondary);
}

.profile-container {
    max-width: 1200px;
    margin: 0 auto;
}

.profile-header {
    background: var(--bg-primary);
    border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    overflow: hidden;
    position: relative;
    margin-bottom: var(--spacing-lg);
}

.cover-photo {
    position: relative;
    height: 300px;
    overflow: hidden;
}

.cover-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.change-cover-btn {
    position: absolute;
    bottom: var(--spacing-md);
    right: var(--spacing-md);
    background: rgba(0,0,0,0.7);
    color: white;
    border: none;
    border-radius: var(--radius-full);
    width: 40px;
    height: 40px;
    cursor: pointer;
}

.profile-info {
    padding: var(--spacing-xl);
    display: flex;
    align-items: flex-end;
    gap: var(--spacing-lg);
    margin-top: -80px;
    position: relative;
    z-index: 1;
}

.profile-avatar-container {
    position: relative;
}

.profile-avatar {
    width: 160px;
    height: 160px;
    border-radius: var(--radius-full);
    border: 4px solid var(--bg-primary);
    object-fit: cover;
}

.change-avatar-btn {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: var(--radius-full);
    width: 32px;
    height: 32px;
    cursor: pointer;
}

.profile-details {
    flex: 1;
}

.profile-name {
    font-size: var(--text-3xl);
    margin-bottom: var(--spacing-xs);
}

.profile-username {
    color: var(--text-secondary);
    margin-bottom: var(--spacing-sm);
}

.profile-bio {
    margin-bottom: var(--spacing-md);
    line-height: 1.6;
}

.profile-meta {
    display: flex;
    gap: var(--spacing-lg);
    color: var(--text-secondary);
    font-size: var(--text-sm);
}

.profile-actions {
    display: flex;
    gap: var(--spacing-sm);
}

.profile-stats {
    display: flex;
    gap: var(--spacing-xl);
    padding: var(--spacing-lg);
    border-top: 1px solid var(--border-color);
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: var(--text-xl);
    font-weight: 600;
    color: var(--primary-color);
}

.stat-label {
    font-size: var(--text-sm);
    color: var(--text-secondary);
}

.profile-content {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: var(--spacing-lg);
    padding: 0 var(--spacing-lg);
}

.profile-tabs {
    display: flex;
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    margin-bottom: var(--spacing-lg);
    overflow: hidden;
}

.tab-btn {
    flex: 1;
    padding: var(--spacing-md);
    background: none;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
    transition: all var(--transition-fast);
}

.tab-btn:hover,
.tab-btn.active {
    background: var(--primary-color);
    color: white;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

.profile-sidebar {
    position: sticky;
    top: 100px;
}

.sidebar-section {
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-md);
}

.sidebar-section h3 {
    margin-bottom: var(--spacing-md);
    padding-bottom: var(--spacing-sm);
    border-bottom: 1px solid var(--border-color);
}

.info-items {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.info-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    font-size: var(--text-sm);
}

.info-item i {
    color: var(--text-secondary);
    width: 16px;
}

@media (max-width: 768px) {
    .profile-content {
        grid-template-columns: 1fr;
    }
    
    .profile-info {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .profile-stats {
        justify-content: center;
    }
    
    .profile-tabs {
        flex-wrap: wrap;
    }
}
</style>

<script>
// Gestion des onglets du profil
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.dataset.tab;
            
            // Retirer la classe active de tous les boutons et panneaux
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));
            
            // Ajouter la classe active au bouton cliqué et au panneau correspondant
            this.classList.add('active');
            document.getElementById(targetTab + '-tab').classList.add('active');
        });
    });
});

function editProfile() {
    window.location.href = 'settings.php';
}

function sendFriendRequest(userId) {
    // TODO: Implémenter l'envoi de demande d'ami
    console.log('Envoi demande d\'ami à l\'utilisateur', userId);
}

function sendMessage(userId) {
    window.location.href = 'messages.php?user=' + userId;
}
</script>

<?php include 'includes/footer2.php'; ?>