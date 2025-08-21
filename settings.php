<?php
/**
 * Page de paramètres utilisateur IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

SessionManager::requireLogin();

$pageTitle = "Paramètres";
$pageDescription = "Gérez vos paramètres de compte et de confidentialité sur IDEM";
$bodyClass = "settings-page";

$db = initDatabase();
$currentUser = SessionManager::getCurrentUser();

include 'includes/header.php';
?>

<div class="settings-container">
    <div class="settings-layout">
        <!-- Sidebar des paramètres -->
        <aside class="settings-sidebar">
            <nav class="settings-nav">
                <button class="nav-item active" data-section="profile">
                    <i class="fas fa-user"></i>
                    <span>Profil</span>
                </button>
                <button class="nav-item" data-section="account">
                    <i class="fas fa-cog"></i>
                    <span>Compte</span>
                </button>
                <button class="nav-item" data-section="privacy">
                    <i class="fas fa-shield-alt"></i>
                    <span>Confidentialité</span>
                </button>
                <button class="nav-item" data-section="notifications">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </button>
                <button class="nav-item" data-section="security">
                    <i class="fas fa-lock"></i>
                    <span>Sécurité</span>
                </button>
                <button class="nav-item" data-section="appearance">
                    <i class="fas fa-palette"></i>
                    <span>Apparence</span>
                </button>
                <button class="nav-item" data-section="data">
                    <i class="fas fa-download"></i>
                    <span>Données</span>
                </button>
            </nav>
        </aside>
        
        <!-- Contenu des paramètres -->
        <main class="settings-main">
            <!-- Section Profil -->
            <div class="settings-section active" id="profile-section">
                <div class="section-header">
                    <h2>Informations du profil</h2>
                    <p>Gérez vos informations personnelles et votre présence publique</p>
                </div>
                
                <form class="settings-form" id="profile-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first-name">Prénom</label>
                            <input type="text" id="first-name" name="first_name" 
                                   value="<?= htmlspecialchars($currentUser['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last-name">Nom</label>
                            <input type="text" id="last-name" name="last_name" 
                                   value="<?= htmlspecialchars($currentUser['last_name']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur</label>
                        <input type="text" id="username" name="username" 
                               value="<?= htmlspecialchars($currentUser['username']) ?>" required>
                        <small>Votre nom d'utilisateur unique sur IDEM</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="bio">Biographie</label>
                        <textarea id="bio" name="bio" rows="3" maxlength="160"
                                  placeholder="Parlez un peu de vous..."><?= htmlspecialchars($currentUser['bio'] ?? '') ?></textarea>
                        <small>Maximum 160 caractères</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="location">Localisation</label>
                            <input type="text" id="location" name="location" 
                                   value="<?= htmlspecialchars($currentUser['location'] ?? '') ?>"
                                   placeholder="Ville, Pays">
                        </div>
                        <div class="form-group">
                            <label for="website">Site web</label>
                            <input type="url" id="website" name="website" 
                                   value="<?= htmlspecialchars($currentUser['website'] ?? '') ?>"
                                   placeholder="https://votre-site.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="birth-date">Date de naissance</label>
                        <input type="date" id="birth-date" name="birth_date" 
                               value="<?= htmlspecialchars($currentUser['birth_date'] ?? '') ?>">
                        <small>Utilisé pour calculer votre âge (privé par défaut)</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Sauvegarder
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Section Compte -->
            <div class="settings-section" id="account-section">
                <div class="section-header">
                    <h2>Paramètres du compte</h2>
                    <p>Gérez votre email, mot de passe et autres paramètres de compte</p>
                </div>
                
                <div class="settings-card">
                    <h3>Adresse email</h3>
                    <p>Votre adresse email actuelle : <strong><?= htmlspecialchars($currentUser['email']) ?></strong></p>
                    <button class="btn btn-secondary" id="change-email-btn">
                        Changer l'email
                    </button>
                </div>
                
                <div class="settings-card">
                    <h3>Mot de passe</h3>
                    <p>Dernière modification : il y a 3 mois</p>
                    <button class="btn btn-secondary" id="change-password-btn">
                        Changer le mot de passe
                    </button>
                </div>
                
                <div class="settings-card">
                    <h3>Authentification à deux facteurs</h3>
                    <p>Ajoutez une couche de sécurité supplémentaire à votre compte</p>
                    <div class="toggle-setting">
                        <span>2FA activé</span>
                        <label class="toggle">
                            <input type="checkbox" id="two-factor-toggle">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Section Confidentialité -->
            <div class="settings-section" id="privacy-section">
                <div class="section-header">
                    <h2>Confidentialité</h2>
                    <p>Contrôlez qui peut voir vos informations et interagir avec vous</p>
                </div>
                
                <div class="settings-card">
                    <h3>Visibilité du profil</h3>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="profile-visibility" value="public" checked>
                            <span class="radio-label">
                                <i class="fas fa-globe"></i>
                                Public - Visible par tous
                            </span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="profile-visibility" value="friends">
                            <span class="radio-label">
                                <i class="fas fa-users"></i>
                                Amis uniquement
                            </span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="profile-visibility" value="private">
                            <span class="radio-label">
                                <i class="fas fa-lock"></i>
                                Privé
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="settings-card">
                    <h3>Qui peut vous contacter</h3>
                    <div class="toggle-setting">
                        <span>Messages de non-amis</span>
                        <label class="toggle">
                            <input type="checkbox" id="messages-non-friends" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-setting">
                        <span>Demandes d'amitié</span>
                        <label class="toggle">
                            <input type="checkbox" id="friend-requests" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                
                <div class="settings-card">
                    <h3>Activité en ligne</h3>
                    <div class="toggle-setting">
                        <span>Afficher quand je suis en ligne</span>
                        <label class="toggle">
                            <input type="checkbox" id="online-status" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-setting">
                        <span>Afficher la dernière connexion</span>
                        <label class="toggle">
                            <input type="checkbox" id="last-seen">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Section Notifications -->
            <div class="settings-section" id="notifications-section">
                <div class="section-header">
                    <h2>Notifications</h2>
                    <p>Choisissez comment et quand vous souhaitez être notifié</p>
                </div>
                
                <div class="settings-card">
                    <h3>Notifications push</h3>
                    <div class="toggle-setting">
                        <span>J'aime et réactions</span>
                        <label class="toggle">
                            <input type="checkbox" id="notif-likes" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-setting">
                        <span>Commentaires</span>
                        <label class="toggle">
                            <input type="checkbox" id="notif-comments" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-setting">
                        <span>Demandes d'amitié</span>
                        <label class="toggle">
                            <input type="checkbox" id="notif-friends" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-setting">
                        <span>Messages</span>
                        <label class="toggle">
                            <input type="checkbox" id="notif-messages" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                
                <div class="settings-card">
                    <h3>Email</h3>
                    <div class="toggle-setting">
                        <span>Résumé hebdomadaire</span>
                        <label class="toggle">
                            <input type="checkbox" id="email-weekly">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-setting">
                        <span>Notifications importantes</span>
                        <label class="toggle">
                            <input type="checkbox" id="email-important" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Section Sécurité -->
            <div class="settings-section" id="security-section">
                <div class="section-header">
                    <h2>Sécurité</h2>
                    <p>Protégez votre compte avec ces paramètres de sécurité</p>
                </div>
                
                <div class="settings-card">
                    <h3>Sessions actives</h3>
                    <p>Gérez les appareils connectés à votre compte</p>
                    <div class="session-list">
                        <div class="session-item">
                            <div class="session-info">
                                <strong>Session actuelle</strong>
                                <span>Chrome sur Windows • France</span>
                                <small>Actif maintenant</small>
                            </div>
                            <span class="session-badge current">Actuel</span>
                        </div>
                    </div>
                    <button class="btn btn-secondary">
                        Déconnecter tous les autres appareils
                    </button>
                </div>
                
                <div class="settings-card">
                    <h3>Connexions tierces</h3>
                    <p>Applications connectées à votre compte IDEM</p>
                    <div class="connected-apps">
                        <p class="text-muted">Aucune application connectée</p>
                    </div>
                </div>
            </div>
            
            <!-- Section Apparence -->
            <div class="settings-section" id="appearance-section">
                <div class="section-header">
                    <h2>Apparence</h2>
                    <p>Personnalisez l'apparence de IDEM selon vos préférences</p>
                </div>
                
                <div class="settings-card">
                    <h3>Thème</h3>
                    <div class="theme-options">
                        <label class="theme-option">
                            <input type="radio" name="theme" value="light" checked>
                            <div class="theme-preview light">
                                <div class="theme-header"></div>
                                <div class="theme-content"></div>
                            </div>
                            <span>Clair</span>
                        </label>
                        <label class="theme-option">
                            <input type="radio" name="theme" value="dark">
                            <div class="theme-preview dark">
                                <div class="theme-header"></div>
                                <div class="theme-content"></div>
                            </div>
                            <span>Sombre</span>
                        </label>
                        <label class="theme-option">
                            <input type="radio" name="theme" value="auto">
                            <div class="theme-preview auto">
                                <div class="theme-header"></div>
                                <div class="theme-content"></div>
                            </div>
                            <span>Automatique</span>
                        </label>
                    </div>
                </div>
                
                <div class="settings-card">
                    <h3>Langue</h3>
                    <select class="form-select" id="language-select">
                        <option value="fr" selected>Français</option>
                        <option value="en">English</option>
                        <option value="es">Español</option>
                        <option value="de">Deutsch</option>
                    </select>
                </div>
            </div>
            
            <!-- Section Données -->
            <div class="settings-section" id="data-section">
                <div class="section-header">
                    <h2>Vos données</h2>
                    <p>Téléchargez ou supprimez vos données personnelles</p>
                </div>
                
                <div class="settings-card">
                    <h3>Télécharger vos données</h3>
                    <p>Obtenez une copie de vos informations personnelles, posts, messages et plus</p>
                    <button class="btn btn-primary" id="download-data-btn">
                        <i class="fas fa-download"></i>
                        Télécharger mes données
                    </button>
                </div>
                
                <div class="settings-card danger">
                    <h3>Supprimer le compte</h3>
                    <p>Cette action est irréversible. Toutes vos données seront définitivement supprimées.</p>
                    <button class="btn btn-danger" id="delete-account-btn">
                        <i class="fas fa-trash"></i>
                        Supprimer mon compte
                    </button>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
/* Styles pour la page paramètres */
.settings-page .main-content {
    padding: var(--spacing-lg);
}

.settings-container {
    max-width: 1200px;
    margin: 0 auto;
}

.settings-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: var(--spacing-xl);
}

.settings-sidebar {
    position: sticky;
    top: 100px;
    height: fit-content;
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
}

.settings-nav {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.nav-item {
    background: none;
    border: none;
    padding: var(--spacing-md);
    border-radius: var(--radius-md);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    text-align: left;
    transition: background-color var(--transition-fast);
    width: 100%;
}

.nav-item:hover,
.nav-item.active {
    background: var(--primary-color);
    color: white;
}

.settings-main {
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.settings-section {
    display: none;
    padding: var(--spacing-xl);
}

.settings-section.active {
    display: block;
}

.section-header {
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-lg);
    border-bottom: 1px solid var(--border-color);
}

.section-header h2 {
    font-size: var(--text-2xl);
    margin-bottom: var(--spacing-sm);
}

.section-header p {
    color: var(--text-secondary);
}

.settings-card {
    background: var(--bg-secondary);
    border-radius: var(--radius-lg);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.settings-card.danger {
    border: 1px solid var(--danger-color);
    background: rgba(var(--danger-rgb), 0.05);
}

.settings-card h3 {
    margin-bottom: var(--spacing-md);
}

.settings-form {
    max-width: 600px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-lg);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-lg);
}

.form-group label {
    font-weight: 500;
    color: var(--text-primary);
}

.form-group input,
.form-group textarea,
.form-group select {
    padding: 12px 16px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    font-size: var(--text-base);
    background: var(--bg-primary);
    transition: border-color var(--transition-fast);
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary-color);
}

.form-group small {
    color: var(--text-secondary);
    font-size: var(--text-xs);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    padding-top: var(--spacing-lg);
    border-top: 1px solid var(--border-color);
}

.toggle-setting {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md) 0;
    border-bottom: 1px solid var(--border-color);
}

.toggle-setting:last-child {
    border-bottom: none;
}

.toggle {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 24px;
}

.toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--border-color);
    transition: var(--transition-fast);
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: var(--transition-fast);
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--primary-color);
}

input:checked + .slider:before {
    transform: translateX(24px);
}

.theme-options {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--spacing-md);
}

.theme-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--spacing-sm);
    cursor: pointer;
}

.theme-preview {
    width: 80px;
    height: 60px;
    border-radius: var(--radius-md);
    border: 2px solid var(--border-color);
    padding: var(--spacing-xs);
    transition: border-color var(--transition-fast);
}

.theme-option input[type="radio"]:checked + .theme-preview {
    border-color: var(--primary-color);
}

.theme-preview.light {
    background: white;
}

.theme-preview.light .theme-header {
    background: #f8f9fa;
    height: 15px;
    border-radius: 2px;
    margin-bottom: 4px;
}

.theme-preview.light .theme-content {
    background: #e9ecef;
    height: 25px;
    border-radius: 2px;
}

.theme-preview.dark {
    background: #212529;
}

.theme-preview.dark .theme-header {
    background: #343a40;
    height: 15px;
    border-radius: 2px;
    margin-bottom: 4px;
}

.theme-preview.dark .theme-content {
    background: #495057;
    height: 25px;
    border-radius: 2px;
}

.theme-preview.auto {
    background: linear-gradient(45deg, white 50%, #212529 50%);
}

.theme-preview.auto .theme-header {
    background: linear-gradient(45deg, #f8f9fa 50%, #343a40 50%);
    height: 15px;
    border-radius: 2px;
    margin-bottom: 4px;
}

.theme-preview.auto .theme-content {
    background: linear-gradient(45deg, #e9ecef 50%, #495057 50%);
    height: 25px;
    border-radius: 2px;
}

.session-list {
    margin: var(--spacing-md) 0;
}

.session-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-md);
    background: var(--bg-primary);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-sm);
}

.session-info {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.session-info small {
    color: var(--text-secondary);
}

.session-badge {
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--radius-sm);
    font-size: var(--text-xs);
    font-weight: 500;
}

.session-badge.current {
    background: var(--success-color);
    color: white;
}

@media (max-width: 768px) {
    .settings-layout {
        grid-template-columns: 1fr;
    }
    
    .settings-sidebar {
        position: static;
    }
    
    .settings-nav {
        flex-direction: row;
        overflow-x: auto;
        gap: var(--spacing-sm);
    }
    
    .nav-item {
        white-space: nowrap;
        min-width: fit-content;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .theme-options {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Gestion de la page paramètres
document.addEventListener('DOMContentLoaded', function() {
    // Navigation entre les sections
    const navItems = document.querySelectorAll('.nav-item');
    const sections = document.querySelectorAll('.settings-section');
    
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            const targetSection = this.dataset.section;
            
            // Retirer les classes actives
            navItems.forEach(nav => nav.classList.remove('active'));
            sections.forEach(section => section.classList.remove('active'));
            
            // Ajouter les classes actives
            this.classList.add('active');
            document.getElementById(targetSection + '-section').classList.add('active');
        });
    });
    
    // Sauvegarde du profil
    document.getElementById('profile-form').addEventListener('submit', function(e) {
        e.preventDefault();
        saveProfileSettings();
    });
    
    // Gestion des thèmes
    const themeInputs = document.querySelectorAll('input[name="theme"]');
    themeInputs.forEach(input => {
        input.addEventListener('change', function() {
            changeTheme(this.value);
        });
    });
});

function saveProfileSettings() {
    // TODO: Implémenter la sauvegarde du profil
    console.log('Sauvegarde du profil');
    
    // Simuler la sauvegarde
    const btn = document.querySelector('#profile-form button[type="submit"]');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde...';
    btn.disabled = true;
    
    setTimeout(() => {
        btn.innerHTML = '<i class="fas fa-check"></i> Sauvegardé';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 2000);
    }, 1000);
}

function changeTheme(theme) {
    // TODO: Implémenter le changement de thème
    console.log('Changement de thème:', theme);
    
    switch(theme) {
        case 'dark':
            document.documentElement.setAttribute('data-theme', 'dark');
            break;
        case 'light':
            document.documentElement.setAttribute('data-theme', 'light');
            break;
        case 'auto':
            // Détecter la préférence système
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
            break;
    }
    
    // Sauvegarder la préférence
    localStorage.setItem('theme', theme);
}

// Charger le thème sauvegardé
function loadSavedTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    const themeInput = document.querySelector(`input[name="theme"][value="${savedTheme}"]`);
    if (themeInput) {
        themeInput.checked = true;
        changeTheme(savedTheme);
    }
}

// Charger le thème au démarrage
loadSavedTheme();
</script>

<?php include 'includes/footer2.php'; ?>