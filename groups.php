<?php
/**
 * Page de gestion des groupes IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

SessionManager::requireLogin();

$pageTitle = "Groupes";
$pageDescription = "Rejoignez des groupes et créez des communautés sur IDEM";
$bodyClass = "groups-page";

$db = initDatabase();
$currentUser = SessionManager::getCurrentUser();

include 'includes/header.php';
?>

<div class="groups-container">
    <div class="groups-layout">
        <!-- Sidebar des filtres -->
        <aside class="groups-sidebar">
            <div class="sidebar-section">
                <h3>Mes groupes</h3>
                <div class="filter-options">
                    <button class="filter-btn active" data-filter="my-groups">
                        <i class="fas fa-users"></i>
                        Tous mes groupes
                    </button>
                    <button class="filter-btn" data-filter="admin">
                        <i class="fas fa-crown"></i>
                        Je suis admin
                    </button>
                    <button class="filter-btn" data-filter="recent">
                        <i class="fas fa-clock"></i>
                        Activité récente
                    </button>
                </div>
            </div>
            
            <div class="sidebar-section">
                <h3>Découvrir</h3>
                <div class="filter-options">
                    <button class="filter-btn" data-filter="suggestions">
                        <i class="fas fa-magic"></i>
                        Suggestions
                    </button>
                    <button class="filter-btn" data-filter="popular">
                        <i class="fas fa-fire"></i>
                        Populaires
                    </button>
                    <button class="filter-btn" data-filter="new">
                        <i class="fas fa-star"></i>
                        Nouveaux
                    </button>
                </div>
            </div>
            
            <div class="sidebar-section">
                <h3>Recherche</h3>
                <div class="search-input-group">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher des groupes..." id="groups-search">
                </div>
            </div>
            
            <div class="sidebar-section">
                <button class="btn btn-primary w-100" id="create-group-btn">
                    <i class="fas fa-plus"></i>
                    Créer un groupe
                </button>
            </div>
        </aside>
        
        <!-- Contenu principal -->
        <main class="groups-main">
            <!-- En-tête -->
            <div class="groups-header">
                <div class="header-info">
                    <h1>Groupes</h1>
                    <p>Rejoignez des communautés qui partagent vos passions</p>
                </div>
                
                <div class="header-stats">
                    <div class="stat-item">
                        <span class="stat-number" id="my-groups-count">0</span>
                        <span class="stat-label">Mes groupes</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="total-members">0</span>
                        <span class="stat-label">Membres total</span>
                    </div>
                </div>
            </div>
            
            <!-- Contenu des groupes -->
            <div class="groups-content">
                <!-- Mes groupes -->
                <div class="content-section" id="my-groups-section">
                    <h2>Mes groupes</h2>
                    <div class="groups-grid" id="my-groups-grid">
                        <div class="loading-groups">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Chargement de vos groupes...</p>
                        </div>
                    </div>
                </div>
                
                <!-- Groupes suggérés -->
                <div class="content-section" id="suggested-groups-section" style="display: none;">
                    <h2>Groupes suggérés</h2>
                    <div class="groups-grid" id="suggested-groups-grid">
                        <div class="loading-groups">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Chargement des suggestions...</p>
                        </div>
                    </div>
                </div>
                
                <!-- Groupes populaires -->
                <div class="content-section" id="popular-groups-section" style="display: none;">
                    <h2>Groupes populaires</h2>
                    <div class="groups-grid" id="popular-groups-grid">
                        <div class="loading-groups">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Chargement des groupes populaires...</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal de création de groupe -->
<div class="modal" id="create-group-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Créer un nouveau groupe</h3>
            <button class="modal-close" id="close-create-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="create-group-form" class="modal-body">
            <div class="form-group">
                <label for="group-name">Nom du groupe *</label>
                <input type="text" id="group-name" name="name" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label for="group-description">Description</label>
                <textarea id="group-description" name="description" rows="4" maxlength="500"></textarea>
            </div>
            
            <div class="form-group">
                <label for="group-category">Catégorie</label>
                <select id="group-category" name="category">
                    <option value="">Sélectionner une catégorie</option>
                    <option value="technology">Technologie</option>
                    <option value="sports">Sports</option>
                    <option value="music">Musique</option>
                    <option value="art">Art & Créativité</option>
                    <option value="education">Éducation</option>
                    <option value="travel">Voyages</option>
                    <option value="food">Cuisine</option>
                    <option value="gaming">Jeux vidéo</option>
                    <option value="books">Livres</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Confidentialité</label>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="privacy" value="public" checked>
                        <span class="radio-label">
                            <i class="fas fa-globe"></i>
                            Public - Tout le monde peut voir et rejoindre
                        </span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="privacy" value="private">
                        <span class="radio-label">
                            <i class="fas fa-lock"></i>
                            Privé - Invitation requise
                        </span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="group-photo">Photo du groupe</label>
                <div class="file-upload">
                    <input type="file" id="group-photo" name="photo" accept="image/*">
                    <div class="upload-area">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Cliquez ou glissez une image ici</p>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancel-create-group">
                Annuler
            </button>
            <button type="submit" form="create-group-form" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Créer le groupe
            </button>
        </div>
    </div>
</div>

<style>
/* Styles pour la page groupes */
.groups-page .main-content {
    padding: var(--spacing-lg);
}

.groups-container {
    max-width: 1200px;
    margin: 0 auto;
}

.groups-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: var(--spacing-xl);
}

.groups-sidebar {
    position: sticky;
    top: 100px;
    height: fit-content;
}

.groups-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-xl);
    background: var(--bg-primary);
    padding: var(--spacing-xl);
    border-radius: var(--radius-lg);
}

.header-info h1 {
    font-size: var(--text-3xl);
    margin-bottom: var(--spacing-sm);
}

.header-info p {
    color: var(--text-secondary);
}

.header-stats {
    display: flex;
    gap: var(--spacing-xl);
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: var(--text-2xl);
    font-weight: 600;
    color: var(--primary-color);
}

.stat-label {
    font-size: var(--text-sm);
    color: var(--text-secondary);
}

.groups-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--spacing-lg);
}

.group-card {
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: transform var(--transition-fast);
}

.group-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.group-cover {
    height: 150px;
    background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.group-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.group-cover .group-icon {
    font-size: var(--text-4xl);
    color: white;
}

.group-info {
    padding: var(--spacing-lg);
}

.group-name {
    font-size: var(--text-lg);
    font-weight: 600;
    margin-bottom: var(--spacing-sm);
}

.group-description {
    color: var(--text-secondary);
    font-size: var(--text-sm);
    margin-bottom: var(--spacing-md);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.group-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-md);
    font-size: var(--text-sm);
    color: var(--text-secondary);
}

.group-actions {
    display: flex;
    gap: var(--spacing-sm);
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: var(--z-modal);
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-lg);
    border-bottom: 1px solid var(--border-color);
}

.modal-close {
    background: none;
    border: none;
    font-size: var(--text-lg);
    cursor: pointer;
    color: var(--text-secondary);
}

.modal-body {
    padding: var(--spacing-lg);
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-sm);
    padding: var(--spacing-lg);
    border-top: 1px solid var(--border-color);
}

.radio-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}

.radio-option {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    padding: var(--spacing-sm);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    cursor: pointer;
}

.radio-option:hover {
    background: var(--bg-secondary);
}

.radio-option input[type="radio"] {
    margin: 0;
}

.radio-label {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.file-upload {
    position: relative;
}

.file-upload input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.upload-area {
    border: 2px dashed var(--border-color);
    border-radius: var(--radius-md);
    padding: var(--spacing-xl);
    text-align: center;
    transition: border-color var(--transition-fast);
}

.upload-area:hover {
    border-color: var(--primary-color);
}

.upload-area i {
    font-size: var(--text-3xl);
    color: var(--text-secondary);
    margin-bottom: var(--spacing-sm);
}

@media (max-width: 768px) {
    .groups-layout {
        grid-template-columns: 1fr;
    }
    
    .groups-header {
        flex-direction: column;
        gap: var(--spacing-lg);
        text-align: center;
    }
    
    .groups-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Gestion de la page groupes
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des filtres
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            loadGroups(filter);
        });
    });
    
    // Modal de création de groupe
    const createGroupBtn = document.getElementById('create-group-btn');
    const createGroupModal = document.getElementById('create-group-modal');
    const closeModalBtn = document.getElementById('close-create-modal');
    const cancelBtn = document.getElementById('cancel-create-group');
    
    createGroupBtn.addEventListener('click', () => {
        createGroupModal.classList.add('active');
    });
    
    closeModalBtn.addEventListener('click', () => {
        createGroupModal.classList.remove('active');
    });
    
    cancelBtn.addEventListener('click', () => {
        createGroupModal.classList.remove('active');
    });
    
    // Formulaire de création
    const createGroupForm = document.getElementById('create-group-form');
    createGroupForm.addEventListener('submit', function(e) {
        e.preventDefault();
        createGroup();
    });
    
    // Recherche de groupes
    const searchInput = document.getElementById('groups-search');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchGroups(this.value);
        }, 300);
    });
    
    // Charger les groupes par défaut
    loadGroups('my-groups');
});

function loadGroups(filter) {
    const sections = document.querySelectorAll('.content-section');
    sections.forEach(section => section.style.display = 'none');
    
    switch(filter) {
        case 'suggestions':
            document.getElementById('suggested-groups-section').style.display = 'block';
            // TODO: Charger les suggestions
            break;
        case 'popular':
            document.getElementById('popular-groups-section').style.display = 'block';
            // TODO: Charger les groupes populaires
            break;
        default:
            document.getElementById('my-groups-section').style.display = 'block';
            // TODO: Charger mes groupes
            break;
    }
}

function createGroup() {
    // TODO: Implémenter la création de groupe
    console.log('Création de groupe');
    document.getElementById('create-group-modal').classList.remove('active');
}

function searchGroups(query) {
    // TODO: Implémenter la recherche de groupes
    console.log('Recherche:', query);
}
</script>

<?php include 'includes/footer2.php'; ?>