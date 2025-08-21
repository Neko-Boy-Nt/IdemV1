
/**
 * Utilitaires JavaScript pour IDEM
 */

// Configuration globale
window.app = {
    userId: window.userId || null,
    csrfToken: window.csrfToken || '',
    isLoggedIn: !!window.userId,
    currentPage: window.location.pathname,
    breakpoints: {
        mobile: 768,
        tablet: 1024,
        desktop: 1200
    },
    apiBaseUrl: '../../api/',
    uploadsUrl: 'uploads/'
};

// Utilities globales
window.utils = {
    // Débounce pour optimiser les performances
    debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    },

    // Throttle pour les événements fréquents
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    // Détection de la taille d'écran
    isMobile() {
        return window.innerWidth <= window.app.breakpoints.mobile;
    },

    isTablet() {
        return window.innerWidth > window.app.breakpoints.mobile && window.innerWidth <= window.app.breakpoints.tablet;
    },

    isDesktop() {
        return window.innerWidth > window.app.breakpoints.tablet;
    },

    // Formatage des dates
    formatTimeAgo(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return 'À l\'instant';
        if (diffInSeconds < 3600) return `Il y a ${Math.floor(diffInSeconds / 60)} min`;
        if (diffInSeconds < 86400) return `Il y a ${Math.floor(diffInSeconds / 3600)}h`;
        if (diffInSeconds < 2592000) return `Il y a ${Math.floor(diffInSeconds / 86400)}j`;
        if (diffInSeconds < 31536000) return `Il y a ${Math.floor(diffInSeconds / 2592000)} mois`;
        return `Il y a ${Math.floor(diffInSeconds / 31536000)} ans`;
    },

    formatDate(dateString, options = {}) {
        const date = new Date(dateString);
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return date.toLocaleDateString('fr-FR', { ...defaultOptions, ...options });
    },

    // Gestion des requêtes AJAX
    async apiRequest(endpoint, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin' // Important pour les cookies de session
        };

        // Ajout du CSRF Token si nécessaire
        if (window.app.csrfToken && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method)) {
            defaultOptions.headers['X-CSRF-Token'] = window.app.csrfToken;
        }

        const config = { ...defaultOptions, ...options };

        try {
            const response = await fetch(endpoint, config);

            // Vérifier d'abord le Content-Type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(`Réponse non-JSON: ${text.substring(0, 100)}...`);
            }

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `Erreur HTTP ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('Erreur API:', {
                endpoint,
                error: error.message,
                stack: error.stack
            });

            // Vous pourriez afficher une notification à l'utilisateur ici
            window.showToast?.('Erreur de connexion au serveur', 'error');

            throw error;
        }
    },
    // Validation des données
    validateEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    },

    validatePassword(password) {
        return password.length >= 8 && 
               /[A-Z]/.test(password) && 
               /[a-z]/.test(password) && 
               /\d/.test(password);
    },

    validateUsername(username) {
        return /^[a-zA-Z0-9_]{3,30}$/.test(username);
    },

    // Gestion des notifications
    showNotification(message, type = 'info', duration = 5000) {
        // Créer l'élément de notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;

        // Ajouter au container des notifications
        let container = document.getElementById('notifications-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifications-container';
            container.className = 'notifications-container';
            document.body.appendChild(container);
        }

        container.appendChild(notification);

        // Animation d'entrée
        setTimeout(() => notification.classList.add('show'), 10);

        // Fermeture automatique
        const closeNotification = () => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        };

        // Événement de fermeture
        notification.querySelector('.notification-close').addEventListener('click', closeNotification);

        // Fermeture automatique
        if (duration > 0) {
            setTimeout(closeNotification, duration);
        }
    },

    // Gestion des modales
    showModal(title, content, actions = []) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3>${title}</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
                <div class="modal-footer">
                    ${actions.map(action => `
                        <button class="btn ${action.class || 'btn-secondary'}" data-action="${action.action}">
                            ${action.text}
                        </button>
                    `).join('')}
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        document.body.classList.add('modal-open');

        // Gestion des événements
        const closeModal = () => {
            document.body.classList.remove('modal-open');
            modal.remove();
        };

        modal.querySelector('.modal-close').addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        // Gestion des actions
        modal.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                const actionHandler = actions.find(a => a.action === action);
                if (actionHandler && actionHandler.handler) {
                    actionHandler.handler();
                }
                if (action === 'close' || action === 'cancel') {
                    closeModal();
                }
            });
        });

        return modal;
    },

    // Gestion des formulaires
    serializeForm(form) {
        const formData = new FormData(form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        return data;
    },

    // Upload de fichiers
    async uploadFile(file, type = 'image') {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', type);

        try {
            const response = await fetch(window.app.apiBaseUrl + 'upload.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': window.app.csrfToken
                }
            });

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message);
            }

            return result;
        } catch (error) {
            console.error('Erreur upload:', error);
            throw error;
        }
    },

    // Gestion du localStorage
    storage: {
        set(key, value) {
            try {
                localStorage.setItem(key, JSON.stringify(value));
            } catch (e) {
                console.warn('Erreur localStorage:', e);
            }
        },

        get(key, defaultValue = null) {
            try {
                const item = localStorage.getItem(key);
                return item ? JSON.parse(item) : defaultValue;
            } catch (e) {
                console.warn('Erreur localStorage:', e);
                return defaultValue;
            }
        },

        remove(key) {
            try {
                localStorage.removeItem(key);
            } catch (e) {
                console.warn('Erreur localStorage:', e);
            }
        }
    },

    // Utilitaires de formatage
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    truncateText(text, maxLength = 100) {
        if (text.length <= maxLength) return text;
        return text.substr(0, maxLength) + '...';
    },

    // Échappement HTML
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    // Copier dans le presse-papiers
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showNotification('Copié dans le presse-papiers', 'success');
        } catch (err) {
            console.error('Erreur copie:', err);
            this.showNotification('Erreur lors de la copie', 'error');
        }
    },
    showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">✕</button>
        `;
        toastContainer.appendChild(toast);

        // Animation d'apparition
        setTimeout(() => toast.classList.add('show'), 100);
        // Disparition après 5 secondes
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
};
// Créer le conteneur de toasts si absent
function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// Initialisation globale
document.addEventListener('DOMContentLoaded', function() {
    // Gérer la navigation responsive
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    // Fermer les dropdowns en cliquant ailleurs
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown.active').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });

    // Gestion des liens AJAX
    document.addEventListener('click', (e) => {
        if (e.target.matches('[data-ajax]')) {
            e.preventDefault();
            // Implémenter la navigation AJAX si nécessaire
        }
    });
});

// Export pour les modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = window.utils;
}
