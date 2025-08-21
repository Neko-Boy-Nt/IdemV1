
/**
 * main.js - Point d'entrée principal de l'application IDEM
 * Initialise tous les modules et gère les interactions globales
 */

// Configuration globale de l'application
window.app = {
    version: '1.0.0',
    debug: false,
    userId: null,
    csrfToken: null,
    apiUrl: 'api',
    websocketUrl: "ws://localhost:8080",
    breakpoints: {
        mobile: 768,
        tablet: 1024
    },
    settings: {
        theme: 'light',
        language: 'fr',
        notifications: true,
        sounds: true
    }
};

// Classe principale de l'application
class IDEMApp {
    constructor() {
        this.initialized = false;
        this.modules = new Map();
        this.eventListeners = new Map();
        this.loadingStates = new Map();
        
        this.init();
    }

    async init() {
        if (this.initialized) return;

        try {
            // Vérifier les dépendances requises
            this.checkDependencies();
            
            // Configurer l'application
            await this.configure();
            
            // Initialiser les modules de base
            this.initializeCore();
            
            // Configurer les événements globaux
            this.setupGlobalEvents();
            
            // Initialiser les modules spécifiques aux pages
            this.initializePageModules();
            
            // Finaliser l'initialisation
            this.finalize();
            
            this.initialized = true;
            console.log('🚀 IDEM Application initialisée avec succès');
            
        } catch (error) {
            console.error('❌ Erreur lors de l\'initialisation:', error);
            this.showErrorMessage('Erreur lors du chargement de l\'application');
        }
    }

    checkDependencies() {
        const required = ['fetch', 'Promise', 'localStorage'];
        const missing = required.filter(dep => !(dep in window));
        
        if (missing.length > 0) {
            throw new Error(`Dépendances manquantes: ${missing.join(', ')}`);
        }
        
        // Vérifier jQuery si utilisé
        if (typeof $ === 'undefined' && document.querySelector('[data-requires="jquery"]')) {
            console.warn('jQuery non trouvé mais requis par certains modules');
        }
    }

    async configure() {
        // Récupérer la configuration utilisateur depuis les meta tags
        const userMeta = document.querySelector('meta[name="user-id"]');
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        
        if (userMeta) {
            window.app.userId = userMeta.content;
            window.userId = userMeta.content; // Rétrocompatibilité
        }
        
        if (csrfMeta) {
            window.app.csrfToken = csrfMeta.content;
            window.csrfToken = csrfMeta.content; // Rétrocompatibilité
        }
        
        // Configurer l'URL WebSocket
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        window.app.websocketUrl = `${protocol}//${window.location.hostname}:8080`;
        
        // Charger les préférences utilisateur
        await this.loadUserPreferences();
        
        // Appliquer le thème
        this.applyTheme(window.app.settings.theme);
    }

    async loadUserPreferences() {
        try {
            const saved = localStorage.getItem('idem-preferences');
            if (saved) {
                const preferences = JSON.parse(saved);
                window.app.settings = { ...window.app.settings, ...preferences };
            }
        } catch (error) {
            console.warn('Impossible de charger les préférences utilisateur:', error);
        }
    }

    saveUserPreferences() {
        try {
            localStorage.setItem('idem-preferences', JSON.stringify(window.app.settings));
        } catch (error) {
            console.warn('Impossible de sauvegarder les préférences:', error);
        }
    }

    initializeCore() {
        // Initialiser le système de toast/notifications visuelles
        this.initializeToastSystem();
        
        // Initialiser les modales globales
        this.initializeModalSystem();
        
        // Initialiser le système de navigation
        this.initializeNavigation();
        
        // Initialiser les composants de base
        this.initializeBaseComponents();
    }

    initializeToastSystem() {
        // Créer le conteneur pour les toasts
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        }

        // Fonction globale pour afficher des toasts
        window.showToast = (message, type = 'info', duration = 5000) => {
            const toast = this.createToast(message, type, duration);
            toastContainer.appendChild(toast);
            
            // Animation d'entrée
            requestAnimationFrame(() => {
                toast.classList.add('show');
            });
            
            // Auto-suppression
            setTimeout(() => {
                this.removeToast(toast);
            }, duration);
        };
    }

    createToast(message, type, duration) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas ${this.getToastIcon(type)}"></i>
            </div>
            <div class="toast-content">
                <p class="toast-message">${utils.escapeHtml(message)}</p>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Auto-fermeture au clic
        toast.addEventListener('click', () => {
            this.removeToast(toast);
        });
        
        return toast;
    }

    removeToast(toast) {
        toast.classList.add('fade-out');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 300);
    }

    getToastIcon(type) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    initializeModalSystem() {
        // Gestionnaire global des modales
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-modal]')) {
                const modalId = e.target.getAttribute('data-modal');
                this.openModal(modalId);
            }
            
            if (e.target.matches('.modal-close') || e.target.matches('.modal-backdrop')) {
                this.closeModal(e.target.closest('.modal'));
            }
        });
        
        // Fermeture avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    this.closeModal(openModal);
                }
            }
        });
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.classList.add('modal-open');
            
            // Focus sur le premier élément focalisable
            const focusable = modal.querySelector('input, textarea, button, [tabindex]');
            if (focusable) {
                focusable.focus();
            }
            
            // Émettre un événement
            modal.dispatchEvent(new CustomEvent('modal:opened'));
        }
    }

    closeModal(modal) {
        if (modal) {
            modal.classList.remove('show');
            document.body.classList.remove('modal-open');
            
            // Émettre un événement
            modal.dispatchEvent(new CustomEvent('modal:closed'));
        }
    }

    initializeNavigation() {
        // Gestion du menu mobile
        const navToggle = document.querySelector('.nav-toggle');
        const navMenu = document.querySelector('.nav-menu');
        
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
                navToggle.classList.toggle('active');
                document.body.classList.toggle('nav-open');
            });
        }
        
        // Fermer le menu lors du clic extérieur
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.nav-menu') && !e.target.closest('.nav-toggle')) {
                if (navMenu) navMenu.classList.remove('active');
                if (navToggle) navToggle.classList.remove('active');
                document.body.classList.remove('nav-open');
            }
        });
        
        // Gestion des liens actifs
        this.updateActiveNavLinks();
    }

    updateActiveNavLinks() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            const linkPath = new URL(link.href).pathname;
            if (linkPath === currentPath) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    initializeBaseComponents() {
        // Composants de base réutilisables
        this.initializeDropdowns();
        this.initializeTooltips();
        this.initializeInfiniteScroll();
        this.initializeLazyLoading();
        this.initializeFormValidation();
    }

    initializeDropdowns() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-dropdown-toggle]')) {
                const targetId = e.target.getAttribute('data-dropdown-toggle');
                const dropdown = document.getElementById(targetId);
                
                // Fermer les autres dropdowns
                document.querySelectorAll('.dropdown.show').forEach(d => {
                    if (d !== dropdown) {
                        d.classList.remove('show');
                    }
                });
                
                if (dropdown) {
                    dropdown.classList.toggle('show');
                }
            } else if (!e.target.closest('.dropdown')) {
                // Fermer tous les dropdowns au clic extérieur
                document.querySelectorAll('.dropdown.show').forEach(d => {
                    d.classList.remove('show');
                });
            }
        });
    }

    initializeTooltips() {
        // Tooltips simples
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.getAttribute('data-tooltip'));
            });
            
            element.addEventListener('mouseleave', (e) => {
                this.hideTooltip();
            });
        });
    }

    showTooltip(element, text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        
        setTimeout(() => tooltip.classList.add('show'), 10);
    }

    hideTooltip() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }

    initializeInfiniteScroll() {
        // Détecter les conteneurs avec scroll infini
        const containers = document.querySelectorAll('[data-infinite-scroll]');
        
        containers.forEach(container => {
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const callback = window[container.getAttribute('data-infinite-scroll')];
                            if (typeof callback === 'function') {
                                callback();
                            }
                        }
                    });
                },
                { threshold: 0.1 }
            );
            
            // Observer le dernier élément
            const trigger = container.querySelector('.infinite-scroll-trigger');
            if (trigger) {
                observer.observe(trigger);
            }
        });
    }

    initializeLazyLoading() {
        // Lazy loading des images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.getAttribute('data-src');
                        
                        if (src) {
                            img.src = src;
                            img.removeAttribute('data-src');
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    initializeFormValidation() {
        // Validation de base des formulaires
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!form.matches('form[data-validate]')) return;
            
            const isValid = this.validateForm(form);
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        // Validation en temps réel
        document.addEventListener('blur', (e) => {
            if (e.target.matches('input, textarea, select')) {
                this.validateField(e.target);
            }
        }, true);
    }

    validateForm(form) {
        let isValid = true;
        const fields = form.querySelectorAll('input, textarea, select');
        
        fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    validateField(field) {
        const value = field.value.trim();
        const rules = {
            required: field.hasAttribute('required'),
            email: field.type === 'email',
            minLength: field.getAttribute('minlength'),
            maxLength: field.getAttribute('maxlength'),
            pattern: field.getAttribute('pattern')
        };
        
        let isValid = true;
        let errorMessage = '';
        
        // Validation requis
        if (rules.required && !value) {
            isValid = false;
            errorMessage = 'Ce champ est requis';
        }
        
        // Validation email
        if (rules.email && value && !utils.validateEmail(value)) {
            isValid = false;
            errorMessage = 'Format email invalide';
        }
        
        // Validation longueur minimale
        if (rules.minLength && value.length < parseInt(rules.minLength)) {
            isValid = false;
            errorMessage = `Minimum ${rules.minLength} caractères`;
        }
        
        // Validation longueur maximale
        if (rules.maxLength && value.length > parseInt(rules.maxLength)) {
            isValid = false;
            errorMessage = `Maximum ${rules.maxLength} caractères`;
        }
        
        // Validation pattern
        if (rules.pattern && value && !new RegExp(rules.pattern).test(value)) {
            isValid = false;
            errorMessage = 'Format invalide';
        }
        
        // Afficher/masquer l'erreur
        this.showFieldError(field, isValid ? null : errorMessage);
        
        return isValid;
    }

    showFieldError(field, message) {
        const container = field.closest('.form-group') || field.parentElement;
        let errorElement = container.querySelector('.field-error');
        
        if (message) {
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'field-error';
                container.appendChild(errorElement);
            }
            errorElement.textContent = message;
            field.classList.add('error');
        } else {
            if (errorElement) {
                errorElement.remove();
            }
            field.classList.remove('error');
        }
    }

    initializePageModules() {
        const body = document.body;
        
        // Initialiser selon la page actuelle
        if (body.classList.contains('feed-page')) {
            this.initializeFeedPage();
        }
        
        if (body.classList.contains('messages-page')) {
            this.initializeMessagesPage();
        }
        
        if (body.classList.contains('profile-page')) {
            this.initializeProfilePage();
        }
        
        if (body.classList.contains('friends-page')) {
            this.initializeFriendsPage();
        }
        
        if (body.classList.contains('groups-page')) {
            this.initializeGroupsPage();
        }
        
        if (body.classList.contains('notifications-page')) {
            this.initializeNotificationsPage();
        }
    }

    initializeFeedPage() {
        console.log('📄 Initialisation de la page Feed');
        
        // Le système de feed est déjà initialisé via feeds.js
        // Ajouter des interactions spécifiques si nécessaire
        
        // Gestion du formulaire de nouveau post
        const newPostForm = document.getElementById('new-post-form');
        if (newPostForm) {
            newPostForm.addEventListener('submit', (e) => {
                e.preventDefault();
                // Laisser feedManager gérer la soumission
            });
        }
        
        // Auto-refresh du feed
        if (window.app.userId) {
            setInterval(() => {
                if (window.feedSystem && !document.hidden) {
                    window.feedSystem.checkForUpdates();
                }
            }, 30000);
        }
    }


    initializeMessagesPage() {
        console.log('💬 Initialisation de la page Messages');
        // Le chat est géré par chat.js
    }

    initializeProfilePage() {
        console.log('👤 Initialisation de la page Profil');
        
        // Gestion de l'upload d'avatar
        const avatarInput = document.getElementById('avatar-upload');
        if (avatarInput) {
            avatarInput.addEventListener('change', (e) => {
                this.handleAvatarUpload(e.target.files[0]);
            });
        }
        
        // Édition du profil
        const editProfileBtn = document.getElementById('edit-profile-btn');
        if (editProfileBtn) {
            editProfileBtn.addEventListener('click', () => {
                this.toggleProfileEdit();
            });
        }
    }

    async handleAvatarUpload(file) {
        if (!file) return;
        
        if (file.size > 5 * 1024 * 1024) { // 5MB
            showToast('L\'image est trop volumineuse (max 5MB)', 'error');
            return;
        }
        
        if (!file.type.startsWith('image/')) {
            showToast('Seules les images sont autorisées', 'error');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('avatar', file);
            
            const response = await this.apiRequest('upload.php', {
                method: 'POST',
                body: formData
            });

            if (response.success) {
                // Mettre à jour l'avatar dans l'interface
                document.querySelectorAll('.user-avatar').forEach(img => {
                    img.src = response.avatar_url + '?t=' + Date.now();
                });

                showToast('Avatar mis à jour!', 'success');
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            console.error('Erreur upload avatar:', error);
            showToast(error.message || 'Erreur lors de l\'upload', 'error');
        }
    }

    toggleProfileEdit() {
        const profileInfo = document.querySelector('.profile-info');
        const editForm = document.querySelector('.profile-edit-form');

        if (profileInfo && editForm) {
            profileInfo.style.display = profileInfo.style.display === 'none' ? 'block' : 'none';
            editForm.style.display = editForm.style.display === 'none' ? 'none' : 'block';
        }
    }

    initializeFriendsPage() {
        console.log('👥 Initialisation de la page Amis');
        // Gestion spécifique aux amis si nécessaire
    }

    initializeGroupsPage() {
        console.log('🏢 Initialisation de la page Groupes');
        // Gestion spécifique aux groupes si nécessaire
    }

    initializeNotificationsPage() {
        console.log('🔔 Initialisation de la page Notifications');

        // Marquer les notifications comme lues
        if (window.app.userId) {
            this.markNotificationsAsRead();
        }
    }

    async markNotificationsAsRead() {
        try {
            await this.apiRequest('/notifications.php', {
                method: 'PUT',
                body: JSON.stringify({ action: 'mark_all_read' })
            });
        } catch (error) {
            console.warn('Erreur marquage notifications lues:', error);
        }
    }

    setupGlobalEvents() {
        // Gestion de la visibilité de la page
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Page redevient visible
                this.onPageVisible();
            } else {
                // Page cachée
                this.onPageHidden();
            }
        });

        // Gestion du redimensionnement
        window.addEventListener('resize', utils.debounce(() => {
            this.onResize();
        }, 250));

        // Gestion de la connexion réseau
        window.addEventListener('online', () => {
            showToast('Connexion rétablie', 'success');
            this.onConnectionRestored();
        });

        window.addEventListener('offline', () => {
            showToast('Connexion perdue', 'warning');
            this.onConnectionLost();
        });

        // Gestion des erreurs globales
        window.addEventListener('error', (e) => {
            console.error('Erreur JavaScript:', e.error);
            if (window.app.debug) {
                showToast('Erreur JavaScript: ' + e.message, 'error');
            }
        });

        // Gestion des promesses rejetées
        window.addEventListener('unhandledrejection', (e) => {
            console.error('Promesse rejetée:', e.reason);
            if (window.app.debug) {
                showToast('Erreur asynchrone: ' + e.reason, 'error');
            }
        });
    }

    onPageVisible() {
        // Rafraîchir les données si nécessaire
        if (window.notificationSystem) {
            window.notificationSystem.loadInitialNotificationCount();
        }
    }

    onPageHidden() {
        // Sauvegarder les données si nécessaire
        this.saveUserPreferences();
    }

    onResize() {
        // Réajuster l'interface si nécessaire
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            // Recentrer les modales
            this.centerModal(modal);
        });
    }

    centerModal(modal) {
        const dialog = modal.querySelector('.modal-dialog');
        if (dialog) {
            // Logique de centrage si nécessaire
        }
    }

    onConnectionRestored() {
        // Reconnecter les WebSockets si nécessaire
        if (window.notificationSystem && !window.notificationSystem.isConnected) {
            window.notificationSystem.connectWebSocket();
        }

        if (window.chatManager && !window.chatManager.isConnected) {
            window.chatManager.connectWebSocket();
        }
    }

    onConnectionLost() {
        // Passer en mode offline si nécessaire
        console.log('Mode hors ligne activé');
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        window.app.settings.theme = theme;
        this.saveUserPreferences();
    }

    // Méthode utilitaire pour les requêtes API
    async apiRequest(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.app.csrfToken || ''
            }
        };

        // Fusionner les options
        const finalOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };

        try {
            const response = await fetch(window.app.apiUrl + url, finalOptions);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Erreur API:', error);
            throw error;
        }
    }

    finalize() {
        // Nettoyer les éléments de chargement
        const loaders = document.querySelectorAll('.app-loader, .page-loader');
        loaders.forEach(loader => {
            loader.classList.add('fade-out');
            setTimeout(() => {
                if (loader.parentElement) {
                    loader.parentElement.removeChild(loader);
                }
            }, 500);
        });

        // Afficher l'application
        const appContainer = document.querySelector('.app-container');
        if (appContainer) {
            appContainer.classList.add('loaded');
        }

        // Émettre un événement d'initialisation complète
        document.dispatchEvent(new CustomEvent('app:initialized'));
    }

    showErrorMessage(message) {
        const errorContainer = document.createElement('div');
        errorContainer.className = 'app-error';
        errorContainer.innerHTML = `
            <div class="error-content">
                <h3>Erreur de l'application</h3>
                <p>${message}</p>
                <button onclick="location.reload()" class="btn btn-primary">
                    Recharger la page
                </button>
            </div>
        `;
        document.body.appendChild(errorContainer);
    }

    // Méthodes publiques pour interagir avec l'application
    updateTheme(theme) {
        this.applyTheme(theme);
    }

    toggleTheme() {
        const currentTheme = window.app.settings.theme;
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(newTheme);
    }
}

// Fonction globale pour l'API (rétrocompatibilité)
window.apiRequest = async (url, options = {}) => {
    if (window.idemApp) {
        return await window.idemApp.apiRequest(url, options);
    }

    // Fallback si l'app n'est pas encore initialisée
    const response = await fetch('api' + url, options);
    return await response.json();
};

// Styles CSS intégrés pour les composants de base
const baseStyles = `
<style>
/* Toast System */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    pointer-events: none;
}

.toast {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    padding: 16px;
    margin-bottom: 12px;
    max-width: 400px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
    pointer-events: auto;
    cursor: pointer;
}

.toast.show {
    opacity: 1;
    transform: translateX(0);
}

.toast.fade-out {
    opacity: 0;
    transform: translateX(100%);
}

.toast-success { border-left: 4px solid var(--success-color); }
.toast-error { border-left: 4px solid var(--error-color); }
.toast-warning { border-left: 4px solid var(--warning-color); }
.toast-info { border-left: 4px solid var(--info-color); }

.toast-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
}

.toast-content {
    flex: 1;
    min-width: 0;
}

.toast-message {
    margin: 0;
    font-size: var(--text-sm);
    color: var(--text-primary);
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px;
    border-radius: var(--radius-sm);
    transition: all var(--transition-fast);
}

.toast-close:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

/* Tooltip */
.tooltip {
    position: absolute;
    background: var(--bg-dark);
    color: white;
    padding: 8px 12px;
    border-radius: var(--radius-sm);
    font-size: var(--text-xs);
    white-space: nowrap;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.2s ease;
    pointer-events: none;
}

.tooltip.show {
    opacity: 1;
}

.tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: var(--bg-dark) transparent transparent transparent;
}

/* Form Validation */
.field-error {
    color: var(--error-color);
    font-size: var(--text-xs);
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.field-error::before {
    content: '⚠';
    font-size: 12px;
}

input.error,
textarea.error,
select.error {
    border-color: var(--error-color) !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}

/* Loading States */
.app-loader,
.page-loader {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--bg-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    transition: opacity 0.5s ease;
}

.app-loader.fade-out,
.page-loader.fade-out {
    opacity: 0;
    pointer-events: none;
}

.loader-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid var(--border-color);
    border-top-color: var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* App Container */
.app-container {
    opacity: 0;
    transition: opacity 0.5s ease;
}

.app-container.loaded {
    opacity: 1;
}

/* Error States */
.app-error {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--bg-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10001;
}

.error-content {
    text-align: center;
    max-width: 400px;
    padding: 32px;
}

.error-content h3 {
    color: var(--error-color);
    margin-bottom: 16px;
}

.error-content p {
    color: var(--text-secondary);
    margin-bottom: 24px;
}

/* Responsive */
@media (max-width: 768px) {
    .toast-container {
        left: 20px;
        right: 20px;
        top: 80px;
    }
    
    .toast {
        max-width: none;
    }
}
</style>
`;

// Injecter les styles de base
document.head.insertAdjacentHTML('beforeend', baseStyles);

// Variables globales
window.idemApp = null;

// Initialisation automatique
document.addEventListener('DOMContentLoaded', () => {
    // Petit délai pour s'assurer que tous les scripts sont chargés
    setTimeout(() => {
        window.idemApp = new IDEMApp();
    }, 100);
});

// Fonction principale pour démarrer les mises à jour temps réel
function startRealTimeUpdates() {
    console.log('🔄 Démarrage des mises à jour temps réel...');

    if (!window.app.userId || !window.app.csrfToken) {
        console.warn('Utilisateur non connecté, arrêt des mises à jour temps réel');
        // return;
    }

    // Initialiser le gestionnaire de notifications en temps réel
    if (typeof NotificationManager !== 'undefined') {
        if (!window.notificationManager) {
            window.notificationManager = new NotificationManager();
        }
        console.log('✅ Gestionnaire de notifications initialisé');
    }

    // Vérification périodique des nouveaux messages
    setInterval(async () => {
        try {
            if (!document.hidden && window.app.userId) {
                await checkForNewMessages();
            }
        } catch (error) {
            console.error('Erreur vérification messages:', error);
        }
    }, 15000); // Toutes les 15 secondes

    // Vérification des mises à jour du feed
    setInterval(async () => {
        try {
            if (!document.hidden && window.app.userId && document.body.classList.contains('feed-page')) {
                await checkForFeedUpdates();
            }
        } catch (error) {
            console.error('Erreur vérification feed:', error);
        }
    }, 30000); // Toutes les 30 secondes

    // Vérification du statut en ligne des amis
    setInterval(async () => {
        try {
            if (!document.hidden && window.app.userId) {
                await updateFriendsOnlineStatus();
            }
        } catch (error) {
            console.error('Erreur statut amis:', error);
        }
    }, 60000); // Toutes les minutes

    // Heartbeat pour maintenir la session active
    setInterval(async () => {
        try {
            if (window.app.userId) {
                await sendHeartbeat();
            }
        } catch (error) {
            console.error('Erreur heartbeat:', error);
        }
    }, 300000); // Toutes les 5 minutes

    console.log('✅ Mises à jour temps réel démarrées avec succès');
}

// Vérifier les nouveaux messages non lus
async function checkForNewMessages() {
    try {
        const response = await apiRequest('messages.php?action=unread_count');
        if (response.success && response.count > 0) {
            updateMessagesBadge(response.count);

            // Si on est sur la page des messages, mettre à jour la liste
            if (document.body.classList.contains('messages-page') && window.chatManager) {
                window.chatManager.refreshConversations();
            }
        }
    } catch (error) {
        console.error('Erreur vérification nouveaux messages:', error);
    }
}

// Vérifier les mises à jour du feed
async function checkForFeedUpdates() {
    try {
        const lastPostTime = localStorage.getItem('lastFeedUpdate') || new Date().toISOString();
        const response = await apiRequest(`posts.php?action=count_new&since=${encodeURIComponent(lastPostTime)}`);

        if (response.success && response.count > 0) {
            showFeedUpdateNotification(response.count);
        }
    } catch (error) {
        console.error('Erreur vérification feed:', error);
    }
}

// Mettre à jour le statut en ligne des amis
async function updateFriendsOnlineStatus() {
    try {
        const response = await apiRequest('friends.php?action=online_status');
        if (response.success && response.friends) {
            updateFriendsStatusUI(response.friends);
        }
    } catch (error) {
        console.error('Erreur statut amis:', error);
    }
}

// Envoyer un heartbeat pour maintenir la session
async function sendHeartbeat() {
    try {
        await apiRequest('auth.php?action=heartbeat', { method: 'POST' });
    } catch (error) {
        // Si le heartbeat échoue, l'utilisateur est peut-être déconnecté
        if (error.message.includes('401') || error.message.includes('Unauthorized')) {
            console.warn('Session expirée, redirection vers la connexion');
            window.location.href = 'index.php?message=session_expired';
        }
    }
}

// Mettre à jour le badge des messages
function updateMessagesBadge(count) {
    const badge = document.querySelector('.messages-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
}

// Afficher une notification de mise à jour du feed
function showFeedUpdateNotification(count) {
    const notification = document.createElement('div');
    notification.className = 'feed-update-notification';
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-arrow-up"></i>
            <span>${count} nouvelle${count > 1 ? 's' : ''} publication${count > 1 ? 's' : ''}</span>
        </div>
        <button class="btn-refresh" onclick="refreshFeed()">
            <i class="fas fa-sync"></i>
            Actualiser
        </button>
    `;
    
    // Insérer en haut du feed
    const feedContainer = document.querySelector('.feed-container');
    if (feedContainer && !document.querySelector('.feed-update-notification')) {
        feedContainer.insertBefore(notification, feedContainer.firstChild);
        
        // Auto-suppression après 30 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 30000);
    }
}

// Rafraîchir le feed
function refreshFeed() {
    if (window.feedSystem) {
        window.feedSystem.refreshFeed();
    } else {
        location.reload();
    }
    
    // Supprimer la notification
    const notification = document.querySelector('.feed-update-notification');
    if (notification) {
        notification.remove();
    }
    
    // Mettre à jour le timestamp
    localStorage.setItem('lastFeedUpdate', new Date().toISOString());
}

// Mettre à jour l'interface du statut des amis
function updateFriendsStatusUI(friends) {
    friends.forEach(friend => {
        const statusElement = document.querySelector(`[data-friend-id="${friend.id}"] .status-indicator`);
        if (statusElement) {
            statusElement.className = `status-indicator ${friend.is_online ? 'online' : 'offline'}`;
            statusElement.title = friend.is_online ? 'En ligne' : `Vu pour la dernière fois ${utils.formatTimeAgo(friend.last_seen)}`;
        }
    });
}

// Gestionnaire d'événements globaux pour les WebSockets (si disponible)
// main.js (extrait modifié)
function initWebSocketConnection() {
    if (typeof WebSocket === 'undefined') {
        console.warn('WebSocket non supporté par ce navigateur');
        return;
    }

    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${protocol}//${window.location.hostname}:8080`;

    try {
        const ws = new WebSocket(wsUrl);

        ws.onopen = () => {
            console.log('✅ Connexion WebSocket établie');
            const sessionId = document.querySelector('meta[name="session-id"]')?.content;
            if (!sessionId) {
                console.error('Session ID non trouvé');
                ws.close();
                return;
            }
            ws.send(JSON.stringify({
                type: 'auth',
                user_id: window.app.userId,
                session_id: sessionId
            }));
        };

        ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                handleRealtimeMessage(data);
            } catch (error) {
                console.error('Erreur message WebSocket:', error);
            }
        };

        ws.onclose = () => {
            console.log('🔌 Connexion WebSocket fermée');
            setTimeout(initWebSocketConnection, 5000);
        };

        ws.onerror = (error) => {
            console.error('Erreur WebSocket:', error);
        };

        window.websocket = ws;
    } catch (error) {
        console.error('Impossible de se connecter au WebSocket:', error);
    }
}

// Traiter les messages temps réel
function handleRealtimeMessage(data) {
    switch (data.type) {
        case 'new_message':
            if (window.chatManager) {
                window.chatManager.handleNewMessage(data.message);
            }
            break;
        case 'new_notification':
            if (window.notificationManager) {
                window.notificationManager.handleNewNotification(data.notification);
            }
            break;
        case 'friend_online':
            updateFriendStatus(data.userId, true);
            break;
        case 'friend_offline':
            updateFriendStatus(data.userId, false);
            break;
        case 'new_post':
            if (document.body.classList.contains('feed-page')) {
                showFeedUpdateNotification(1);
            }
            break;
        default:
            console.log('Message WebSocket non géré:', data);
    }
}

// Mettre à jour le statut d'un ami
function updateFriendStatus(userId, isOnline) {
    const statusElement = document.querySelector(`[data-friend-id="${userId}"] .status-indicator`);
    if (statusElement) {
        statusElement.className = `status-indicator ${isOnline ? 'online' : 'offline'}`;
        statusElement.title = isOnline ? 'En ligne' : 'Hors ligne';
    }
}

// Expose des utilitaires globaux
window.IDEM = {
    showToast: (message, type, duration) => window.showToast?.(message, type, duration),
    apiRequest: (url, options) => window.apiRequest?.(url, options),
    toggleTheme: () => window.idemApp?.toggleTheme(),
    version: window.app.version,
    startRealTimeUpdates: startRealTimeUpdates,
    initWebSocket: initWebSocketConnection
};

// Exposer la fonction globalement pour le footer.php
window.startRealTimeUpdates = startRealTimeUpdates;
