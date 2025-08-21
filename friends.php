<?php
/**
 * Page de gestion des amis IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

SessionManager::requireLogin();

$pageTitle = "Amis";
$pageDescription = "Gérez vos amitiés et découvrez de nouvelles personnes sur IDEM";
$bodyClass = "friends-page";

$db = initDatabase();
$currentUser = SessionManager::getCurrentUser();

include 'includes/header.php';
?>

<div class="friends-container">
    <div class="friends-layout">
        <!-- Sidebar des filtres -->
        <aside class="friends-sidebar">
            <div class="sidebar-section">
                <h3>Filtres</h3>
                <div class="filter-options">
                    <button class="filter-btn active" data-filter="all">
                        <i class="fas fa-users"></i>
                        Tous les amis
                    </button>
                    <button class="filter-btn" data-filter="online">
                        <i class="fas fa-circle text-success"></i>
                        En ligne
                    </button>
                    <button class="filter-btn" data-filter="recent">
                        <i class="fas fa-clock"></i>
                        Récents
                    </button>
                    <button class="filter-btn" data-filter="requests">
                        <i class="fas fa-user-plus"></i>
                        Demandes
                        <span class="badge" id="requests-count">0</span>
                    </button>
                    <button class="filter-btn" data-filter="suggestions">
                        <i class="fas fa-magic"></i>
                        Suggestions
                    </button>
                </div>
            </div>

            <div class="sidebar-section">
                <h3>Recherche rapide</h3>
                <div class="search-input-group">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher un ami..." id="friends-search">
                </div>
            </div>

            <div class="sidebar-section">
                <h3>Actions</h3>
                <div class="action-buttons">
                    <button class="btn btn-primary" id="find-friends-btn">
                        <i class="fas fa-user-plus"></i>
                        Trouver des amis
                    </button>
                    <button class="btn btn-secondary" id="invite-friends-btn">
                        <i class="fas fa-envelope"></i>
                        Inviter par email
                    </button>
                </div>
            </div>
        </aside>

        <!-- Contenu principal -->
        <main class="friends-main">
            <!-- En-tête avec statistiques -->
            <div class="friends-header">
                <div class="friends-stats">
                    <div class="stat-card">
                        <h3 id="total-friends">0</h3>
                        <p>Amis</p>
                    </div>
                    <div class="stat-card">
                        <h3 id="online-friends">0</h3>
                        <p>En ligne</p>
                    </div>
                    <div class="stat-card">
                        <h3 id="pending-requests">0</h3>
                        <p>Demandes</p>
                    </div>
                </div>

                <div class="view-options">
                    <button class="view-btn active" data-view="grid">
                        <i class="fas fa-th"></i>
                    </button>
                    <button class="view-btn" data-view="list">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>

            <!-- Zone de contenu -->
            <div class="friends-content">
                <!-- Demandes d'amitié en attente -->
                <div class="content-section" id="friend-requests-section" style="display: none;">
                        <div class="requests-tabs">
                            <button class="tab-btn active" data-tab="received">Demandes reçues (<span id="received-count">0</span>)</button>
                            <button class="tab-btn" data-tab="sent">Demandes envoyées (<span id="sent-count">0</span>)</button>
                        </div>

                        <div class="tab-content active" id="received-requests">
                            <!-- Liste des demandes reçues -->
                            <div class="empty-state">
                                <i class="fas fa-user-clock"></i>
                                <p>Aucune demande reçue</p>
                            </div>
                        </div>

                        <div class="tab-content" id="sent-requests">
                            <!-- Liste des demandes envoyées -->
                            <div class="empty-state">
                                <i class="fas fa-paper-plane"></i>
                                <p>Aucune demande envoyée</p>
                            </div>
                        </div>
                </div>

                <!-- Suggestions d'amis -->
                <div class="content-section" id="friend-suggestions-section" style="display: none;">
                    <h2>Personnes que vous pourriez connaître</h2>
                    <div class="friend-suggestions" id="friend-suggestions">
                        <div class="loading-suggestions">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Chargement des suggestions...</p>
                        </div>
                    </div>
                </div>

                <!-- Liste des amis -->
                <div class="content-section" id="friends-list-section">
                    <h2>Mes amis</h2>
                    <div class="friends-grid" id="friends-grid">
                        <div class="loading-friends">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Chargement des amis...</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>


    <link rel="stylesheet" href="assets/css/friend-style.css">

    <script src="assets/js/friends.js"></script>

<?php include 'includes/footer2.php'; ?>