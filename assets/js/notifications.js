
/**
 * Gestionnaire de notifications pour IDEM
 */

class NotificationManager {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.lastCheck = null;
        this.checkInterval = null;
        this.isVisible = document.visibilityState === 'visible';
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.requestPermission();
        this.startPeriodicCheck();
        this.loadUnreadCount();
    }

    setupEventListeners() {
        // Visibilité de la page
        document.addEventListener('visibilitychange', () => {
            this.isVisible = document.visibilityState === 'visible';
            if (this.isVisible) {
                this.checkForNewNotifications();
            }
        });

        // Bouton notifications
        const notificationBtn = document.getElementById('notifications-btn');
        if (notificationBtn) {
            notificationBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleNotificationsPanel();
            });
        }

        // Marquer comme lu au clic
        document.addEventListener('click', (e) => {
            if (e.target.closest('.notification-item')) {
                const notificationId = e.target.closest('.notification-item').dataset.id;
                this.markAsRead(notificationId);
            }
        });
    }

    async requestPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            try {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    utils.showNotification('Notifications activées !', 'success');
                }
            } catch (error) {
                console.error('Erreur permission notifications:', error);
            }
        }
    }

    startPeriodicCheck() {
        // Vérifier toutes les 30 secondes
        this.checkInterval = setInterval(() => {
            if (window.app.isLoggedIn) {
                this.checkForNewNotifications();
            }
        }, 30000);

        // Vérification initiale
        if (window.app.isLoggedIn) {
            this.checkForNewNotifications();
        }
    }

    async checkForNewNotifications() {
        try {
            const params = this.lastCheck ? `?since=${encodeURIComponent(this.lastCheck)}` : '';
            const response = await apiRequest(`/notifications.php${params}`);
            
            if (response.success) {
                this.processNewNotifications(response.notifications);
                this.unreadCount = response.unread_count || 0;
                this.updateUnreadBadge();
                this.lastCheck = response.last_check;
            }
        } catch (error) {
            console.error('Erreur vérification notifications:', error);
        }
    }

    processNewNotifications(newNotifications) {
        newNotifications.forEach(notification => {
            // Ajouter à la liste
            this.notifications.unshift(notification);
            
            // Afficher notification navigateur si pas visible
            if (!this.isVisible && this.shouldShowBrowserNotification(notification)) {
                this.showBrowserNotification(notification);
            }
            
            // Afficher notification dans l'interface
            this.showInAppNotification(notification);
        });

        // Limiter le nombre de notifications en mémoire
        if (this.notifications.length > 50) {
            this.notifications = this.notifications.slice(0, 50);
        }
    }

    shouldShowBrowserNotification(notification) {
        const importantTypes = ['friend_request', 'message', 'mention'];
        return importantTypes.includes(notification.type);
    }

    showBrowserNotification(notification) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const options = {
                body: notification.message,
                icon: '/assets/images/logo.png',
                badge: '/assets/images/badge.png',
                tag: `notification-${notification.id}`,
                data: {
                    id: notification.id,
                    type: notification.type,
                    related_id: notification.related_id
                }
            };

            const browserNotification = new Notification(notification.title, options);
            
            browserNotification.onclick = () => {
                window.focus();
                this.handleNotificationClick(notification);
                browserNotification.close();
            };

            // Fermer automatiquement après 5 secondes
            setTimeout(() => browserNotification.close(), 5000);
        }
    }

    showInAppNotification(notification) {
        // Afficher une notification toast dans l'interface
        const toast = document.createElement('div');
        toast.className = `notification-toast notification-${notification.type}`;
        toast.innerHTML = `
            <div class="notification-icon">
                ${this.getNotificationIcon(notification.type)}
            </div>
            <div class="notification-content">
                <div class="notification-title">${notification.title}</div>
                <div class="notification-message">${notification.message}</div>
            </div>
            <button class="notification-close">&times;</button>
        `;

        // Ajouter au container
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        container.appendChild(toast);

        // Animation et événements
        setTimeout(() => toast.classList.add('show'), 10);
        
        toast.addEventListener('click', () => {
            this.handleNotificationClick(notification);
            this.removeToast(toast);
        });

        toast.querySelector('.notification-close').addEventListener('click', (e) => {
            e.stopPropagation();
            this.removeToast(toast);
        });

        // Retirer automatiquement après 5 secondes
        setTimeout(() => this.removeToast(toast), 5000);
    }

    removeToast(toast) {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    getNotificationIcon(type) {
        const icons = {
            'like': '<i class="fas fa-heart"></i>',
            'comment': '<i class="fas fa-comment"></i>',
            'friend_request': '<i class="fas fa-user-plus"></i>',
            'friend_accept': '<i class="fas fa-user-check"></i>',
            'message': '<i class="fas fa-envelope"></i>',
            'mention': '<i class="fas fa-at"></i>',
            'share': '<i class="fas fa-share"></i>'
        };
        return icons[type] || '<i class="fas fa-bell"></i>';
    }

    async toggleNotificationsPanel() {
        const panel = document.getElementById('notifications-panel');
        if (!panel) {
            await this.createNotificationsPanel();
        } else {
            panel.classList.toggle('active');
        }
    }

    async createNotificationsPanel() {
        try {
            const response = await apiRequest('/notifications.php?action=list&limit=20');
            
            if (response.success) {
                this.renderNotificationsPanel(response.notifications);
            }
        } catch (error) {
            console.error('Erreur chargement notifications:', error);
            utils.showNotification('Erreur lors du chargement des notifications', 'error');
        }
    }

    renderNotificationsPanel(notifications) {
        const panel = document.createElement('div');
        panel.id = 'notifications-panel';
        panel.className = 'notifications-panel active';
        
        panel.innerHTML = `
            <div class="notifications-header">
                <h3>Notifications</h3>
                <div class="notifications-actions">
                    <button class="btn-icon" id="mark-all-read" title="Tout marquer comme lu">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <button class="btn-icon" id="close-notifications" title="Fermer">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="notifications-list">
                ${notifications.length > 0 ? this.renderNotificationsList(notifications) : this.renderEmptyState()}
            </div>
        `;

        // Positionner le panel
        const notificationBtn = document.getElementById('notifications-btn');
        if (notificationBtn) {
            const rect = notificationBtn.getBoundingClientRect();
            panel.style.position = 'fixed';
            panel.style.top = `${rect.bottom + 10}px`;
            panel.style.right = `${window.innerWidth - rect.right}px`;
        }

        document.body.appendChild(panel);

        // Événements
        panel.querySelector('#close-notifications').addEventListener('click', () => {
            panel.remove();
        });

        panel.querySelector('#mark-all-read').addEventListener('click', () => {
            this.markAllAsRead();
        });

        // Fermer en cliquant ailleurs
        setTimeout(() => {
            document.addEventListener('click', (e) => {
                if (!panel.contains(e.target) && !notificationBtn.contains(e.target)) {
                    panel.remove();
                }
            }, { once: true });
        }, 100);
    }

    renderNotificationsList(notifications) {
        return notifications.map(notification => `
            <div class="notification-item ${notification.is_read ? '' : 'unread'}" data-id="${notification.id}">
                <div class="notification-icon">
                    ${this.getNotificationIcon(notification.type)}
                </div>
                <div class="notification-content">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time">${utils.formatTimeAgo(notification.created_at)}</div>
                </div>
                ${!notification.is_read ? '<div class="notification-unread-dot"></div>' : ''}
            </div>
        `).join('');
    }

    renderEmptyState() {
        return `
            <div class="notifications-empty">
                <i class="fas fa-bell-slash"></i>
                <p>Aucune notification</p>
            </div>
        `;
    }

    async markAsRead(notificationId) {
        try {
            const response = await apiRequest('/notifications.php', {
                method: 'PUT',
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: notificationId
                })
            });

            if (response.success) {
                // Mettre à jour l'interface
                const item = document.querySelector(`[data-id="${notificationId}"]`);
                if (item) {
                    item.classList.remove('unread');
                    const dot = item.querySelector('.notification-unread-dot');
                    if (dot) dot.remove();
                }

                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this.updateUnreadBadge();
            }
        } catch (error) {
            console.error('Erreur marquage notification:', error);
        }
    }

    async markAllAsRead() {
        try {
            const response = await apiRequest('/notifications.php', {
                method: 'PUT',
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            });

            if (response.success) {
                // Mettre à jour l'interface
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    const dot = item.querySelector('.notification-unread-dot');
                    if (dot) dot.remove();
                });

                this.unreadCount = 0;
                this.updateUnreadBadge();
                utils.showNotification('Toutes les notifications marquées comme lues', 'success');
            }
        } catch (error) {
            console.error('Erreur marquage notifications:', error);
        }
    }

    handleNotificationClick(notification) {
        // Marquer comme lu
        if (!notification.is_read) {
            this.markAsRead(notification.id);
        }

        // Naviguer vers le contenu approprié
        switch (notification.type) {
            case 'friend_request':
                window.location.href = '/friends.php';
                break;
            case 'message':
                if (notification.related_id) {
                    window.location.href = `/messages.php?conversation=${notification.related_id}`;
                }
                break;
            case 'like':
            case 'comment':
            case 'share':
                if (notification.related_id) {
                    window.location.href = `/feed.php#post-${notification.related_id}`;
                }
                break;
            case 'mention':
                // Implémenter selon le contexte (post, commentaire, etc.)
                break;
            default:
                // Action par défaut
                break;
        }
    }

    updateUnreadBadge() {
        const badge = document.querySelector('.notifications-badge');
        if (badge) {
            if (this.unreadCount > 0) {
                badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }

        // Mettre à jour le titre de la page
        if (this.unreadCount > 0) {
            document.title = `(${this.unreadCount}) IDEM`;
        } else {
            document.title = 'IDEM';
        }
    }

    async loadUnreadCount() {
        try {
            const response = await apiRequest('/notifications.php?action=count');
            if (response.success) {
                this.unreadCount = response.count;
                this.updateUnreadBadge();
            }
        } catch (error) {
            console.error('Erreur chargement compteur:', error);
        }
    }

    destroy() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
    }
}

// Initialisation automatique
let notificationManager;

document.addEventListener('DOMContentLoaded', () => {
    if (window.app.isLoggedIn) {
        notificationManager = new NotificationManager();
        
        // Exposer globalement pour debugging
        window.notificationManager = notificationManager;
    }
});

// Nettoyage avant déchargement de la page
window.addEventListener('beforeunload', () => {
    if (notificationManager) {
        notificationManager.destroy();
    }
});
