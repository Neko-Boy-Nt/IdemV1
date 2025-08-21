<?php
/**
 * Page de notifications IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

SessionManager::requireLogin();

$pageTitle = "Notifications";
$pageDescription = "Consultez toutes vos notifications sur IDEM";
$bodyClass = "notifications-page";

$db = initDatabase();
$currentUser = SessionManager::getCurrentUser();

include 'includes/header.php';
?>

<div class="notifications-container">
    <div class="notifications-layout">
        <!-- Header avec actions -->
        <div class="notifications-header">
            <div class="header-left">
                <h1>Notifications</h1>
                <p>Restez au courant de l'activité sur votre profil</p>
            </div>
            
            <div class="header-actions">
                <button class="btn btn-secondary" id="mark-all-read-btn">
                    <i class="fas fa-check-double"></i>
                    Tout marquer lu
                </button>
                <button class="btn btn-icon" id="notification-settings-btn" title="Paramètres">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="notifications-filters">
            <button class="filter-btn active" data-filter="all">
                Toutes
                <span class="badge" id="all-count">0</span>
            </button>
            <button class="filter-btn" data-filter="unread">
                Non lues
                <span class="badge" id="unread-count">0</span>
            </button>
            <button class="filter-btn" data-filter="likes">
                <i class="fas fa-heart"></i>
                J'aime
            </button>
            <button class="filter-btn" data-filter="comments">
                <i class="fas fa-comment"></i>
                Commentaires
            </button>
            <button class="filter-btn" data-filter="friends">
                <i class="fas fa-user-plus"></i>
                Amis
            </button>
            <button class="filter-btn" data-filter="mentions">
                <i class="fas fa-at"></i>
                Mentions
            </button>
        </div>
        
        <!-- Liste des notifications -->
        <div class="notifications-content">
            <div class="notifications-list" id="notifications-list">
                <div class="loading-notifications">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Chargement des notifications...</p>
                </div>
            </div>
            
            <!-- État vide -->
            <div class="empty-state" id="empty-notifications" style="display: none;">
                <i class="fas fa-bell-slash"></i>
                <h3>Aucune notification</h3>
                <p>Vous n'avez pas encore de notifications. Interagissez avec vos amis pour en recevoir !</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal des paramètres de notification -->
<div class="modal" id="notification-settings-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Paramètres de notification</h3>
            <button class="modal-close" id="close-settings-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="settings-section">
                <h4>Notifications push</h4>
                <div class="setting-item">
                    <label class="setting-label">
                        <input type="checkbox" id="push-likes" checked>
                        <span class="checkmark"></span>
                        J'aime et réactions
                    </label>
                </div>
                <div class="setting-item">
                    <label class="setting-label">
                        <input type="checkbox" id="push-comments" checked>
                        <span class="checkmark"></span>
                        Commentaires
                    </label>
                </div>
                <div class="setting-item">
                    <label class="setting-label">
                        <input type="checkbox" id="push-friends" checked>
                        <span class="checkmark"></span>
                        Demandes d'amitié
                    </label>
                </div>
                <div class="setting-item">
                    <label class="setting-label">
                        <input type="checkbox" id="push-mentions" checked>
                        <span class="checkmark"></span>
                        Mentions
                    </label>
                </div>
            </div>
            
            <div class="settings-section">
                <h4>Notifications par email</h4>
                <div class="setting-item">
                    <label class="setting-label">
                        <input type="checkbox" id="email-digest">
                        <span class="checkmark"></span>
                        Résumé quotidien
                    </label>
                </div>
                <div class="setting-item">
                    <label class="setting-label">
                        <input type="checkbox" id="email-important">
                        <span class="checkmark"></span>
                        Notifications importantes uniquement
                    </label>
                </div>
            </div>
            
            <div class="settings-section">
                <h4>Mode silencieux</h4>
                <div class="setting-item">
                    <label class="setting-label">
                        <input type="checkbox" id="quiet-hours">
                        <span class="checkmark"></span>
                        Activer les heures silencieuses
                    </label>
                </div>
                <div class="time-range" id="quiet-time-range" style="display: none;">
                    <div class="time-input">
                        <label>De</label>
                        <input type="time" id="quiet-start" value="22:00">
                    </div>
                    <div class="time-input">
                        <label>À</label>
                        <input type="time" id="quiet-end" value="08:00">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancel-settings">
                Annuler
            </button>
            <button type="button" class="btn btn-primary" id="save-settings">
                <i class="fas fa-save"></i>
                Sauvegarder
            </button>
        </div>
    </div>
</div>

<style>
/* Styles pour la page notifications */
.notifications-page .main-content {
    padding: var(--spacing-lg);
}

.notifications-container {
    max-width: 800px;
    margin: 0 auto;
}

.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-xl);
    background: var(--bg-primary);
    padding: var(--spacing-xl);
    border-radius: var(--radius-lg);
}

.header-left h1 {
    font-size: var(--text-3xl);
    margin-bottom: var(--spacing-sm);
}

.header-left p {
    color: var(--text-secondary);
}

.header-actions {
    display: flex;
    gap: var(--spacing-sm);
}

.notifications-filters {
    display: flex;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-xl);
    background: var(--bg-primary);
    padding: var(--spacing-md);
    border-radius: var(--radius-lg);
    overflow-x: auto;
}

.filter-btn {
    background: none;
    border: none;
    padding: var(--spacing-sm) var(--spacing-md);
    border-radius: var(--radius-md);
    cursor: pointer;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    transition: all var(--transition-fast);
}

.filter-btn:hover,
.filter-btn.active {
    background: var(--primary-color);
    color: white;
}

.notifications-list {
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.notification-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-lg);
    border-bottom: 1px solid var(--border-color);
    transition: background-color var(--transition-fast);
    cursor: pointer;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item:hover {
    background: var(--bg-secondary);
}

.notification-item.unread {
    background: rgba(var(--primary-rgb), 0.05);
    border-left: 3px solid var(--primary-color);
}

.notification-avatar {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-full);
    object-fit: cover;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-text {
    font-size: var(--text-sm);
    line-height: 1.5;
    margin-bottom: var(--spacing-xs);
}

.notification-text strong {
    font-weight: 600;
}

.notification-time {
    font-size: var(--text-xs);
    color: var(--text-secondary);
}

.notification-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-sm);
    flex-shrink: 0;
}

.notification-icon.like {
    background: rgba(var(--danger-rgb), 0.1);
    color: var(--danger-color);
}

.notification-icon.comment {
    background: rgba(var(--info-rgb), 0.1);
    color: var(--info-color);
}

.notification-icon.friend {
    background: rgba(var(--success-rgb), 0.1);
    color: var(--success-color);
}

.notification-icon.mention {
    background: rgba(var(--warning-rgb), 0.1);
    color: var(--warning-color);
}

.notification-actions {
    display: flex;
    gap: var(--spacing-sm);
    opacity: 0;
    transition: opacity var(--transition-fast);
}

.notification-item:hover .notification-actions {
    opacity: 1;
}

.notification-action {
    background: none;
    border: none;
    padding: var(--spacing-xs);
    border-radius: var(--radius-sm);
    cursor: pointer;
    color: var(--text-secondary);
}

.notification-action:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.empty-state {
    text-align: center;
    padding: var(--spacing-3xl);
    color: var(--text-secondary);
}

.empty-state i {
    font-size: var(--text-5xl);
    margin-bottom: var(--spacing-lg);
    opacity: 0.5;
}

.empty-state h3 {
    font-size: var(--text-xl);
    margin-bottom: var(--spacing-sm);
}

/* Paramètres de notification */
.settings-section {
    margin-bottom: var(--spacing-xl);
}

.settings-section h4 {
    margin-bottom: var(--spacing-md);
    color: var(--text-primary);
    font-size: var(--text-lg);
}

.setting-item {
    margin-bottom: var(--spacing-md);
}

.setting-label {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    cursor: pointer;
}

.setting-label input[type="checkbox"] {
    margin: 0;
}

.time-range {
    display: flex;
    gap: var(--spacing-md);
    margin-top: var(--spacing-sm);
    padding-left: var(--spacing-lg);
}

.time-input {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.time-input label {
    font-size: var(--text-sm);
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .notifications-header {
        flex-direction: column;
        gap: var(--spacing-lg);
        text-align: center;
    }
    
    .notifications-filters {
        flex-wrap: wrap;
    }
    
    .notification-item {
        padding: var(--spacing-md);
    }
}
</style>

<script>
// Gestion de la page notifications
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des filtres
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            loadNotifications(filter);
        });
    });
    
    // Marquer toutes comme lues
    document.getElementById('mark-all-read-btn').addEventListener('click', markAllAsRead);
    
    // Modal des paramètres
    const settingsBtn = document.getElementById('notification-settings-btn');
    const settingsModal = document.getElementById('notification-settings-modal');
    const closeSettingsBtn = document.getElementById('close-settings-modal');
    const cancelSettingsBtn = document.getElementById('cancel-settings');
    const saveSettingsBtn = document.getElementById('save-settings');
    
    settingsBtn.addEventListener('click', () => {
        settingsModal.classList.add('active');
    });
    
    closeSettingsBtn.addEventListener('click', () => {
        settingsModal.classList.remove('active');
    });
    
    cancelSettingsBtn.addEventListener('click', () => {
        settingsModal.classList.remove('active');
    });
    
    saveSettingsBtn.addEventListener('click', saveNotificationSettings);
    
    // Mode silencieux
    const quietHoursCheckbox = document.getElementById('quiet-hours');
    const quietTimeRange = document.getElementById('quiet-time-range');
    
    quietHoursCheckbox.addEventListener('change', function() {
        quietTimeRange.style.display = this.checked ? 'flex' : 'none';
    });
    
    // Charger les notifications par défaut
    loadNotifications('all');
});

function loadNotifications(filter) {
    const notificationsList = document.getElementById('notifications-list');
    
    // Simuler le chargement
    notificationsList.innerHTML = `
        <div class="loading-notifications">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Chargement des notifications...</p>
        </div>
    `;
    
    // TODO: Implémenter le chargement des notifications réelles
    setTimeout(() => {
        displaySampleNotifications();
    }, 1000);
}

function displaySampleNotifications() {
    const notificationsList = document.getElementById('notifications-list');
    
    const sampleNotifications = [
        {
            id: 1,
            type: 'like',
            avatar: 'uploads/avatars/default-avatar.svg',
            text: '<strong>Marie Dubois</strong> a aimé votre publication',
            time: 'Il y a 2 minutes',
            unread: true
        },
        {
            id: 2,
            type: 'comment',
            avatar: 'uploads/avatars/default-avatar.svg',
            text: '<strong>Pierre Martin</strong> a commenté votre photo',
            time: 'Il y a 15 minutes',
            unread: true
        },
        {
            id: 3,
            type: 'friend',
            avatar: 'uploads/avatars/default-avatar.svg',
            text: '<strong>Sophie Leroy</strong> a accepté votre demande d\'amitié',
            time: 'Il y a 1 heure',
            unread: false
        }
    ];
    
    notificationsList.innerHTML = sampleNotifications.map(notification => `
        <div class="notification-item ${notification.unread ? 'unread' : ''}" data-id="${notification.id}">
            <img src="${notification.avatar}" alt="Avatar" class="notification-avatar">
            <div class="notification-content">
                <div class="notification-text">${notification.text}</div>
                <div class="notification-time">${notification.time}</div>
            </div>
            <div class="notification-icon ${notification.type}">
                <i class="fas fa-${getNotificationIcon(notification.type)}"></i>
            </div>
            <div class="notification-actions">
                <button class="notification-action" onclick="markAsRead(${notification.id})" title="Marquer comme lu">
                    <i class="fas fa-check"></i>
                </button>
                <button class="notification-action" onclick="deleteNotification(${notification.id})" title="Supprimer">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
    
    // Mettre à jour les compteurs
    updateNotificationCounts();
}

function getNotificationIcon(type) {
    const icons = {
        like: 'heart',
        comment: 'comment',
        friend: 'user-plus',
        mention: 'at'
    };
    return icons[type] || 'bell';
}

function markAllAsRead() {
    // TODO: Implémenter le marquage de toutes les notifications
    document.querySelectorAll('.notification-item.unread').forEach(item => {
        item.classList.remove('unread');
    });
    updateNotificationCounts();
}

function markAsRead(notificationId) {
    // TODO: Implémenter le marquage d'une notification
    const notification = document.querySelector(`[data-id="${notificationId}"]`);
    if (notification) {
        notification.classList.remove('unread');
        updateNotificationCounts();
    }
}

function deleteNotification(notificationId) {
    // TODO: Implémenter la suppression d'une notification
    const notification = document.querySelector(`[data-id="${notificationId}"]`);
    if (notification) {
        notification.remove();
        updateNotificationCounts();
    }
}

function updateNotificationCounts() {
    const allCount = document.querySelectorAll('.notification-item').length;
    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
    
    document.getElementById('all-count').textContent = allCount;
    document.getElementById('unread-count').textContent = unreadCount;
}

function saveNotificationSettings() {
    // TODO: Implémenter la sauvegarde des paramètres
    document.getElementById('notification-settings-modal').classList.remove('active');
}
</script>

<?php include 'includes/footer2.php'; ?>