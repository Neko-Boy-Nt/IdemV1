// Gestion de la page amis
class FriendsManager {
    constructor() {
        this.currentFilter = sessionStorage.getItem('friendsFilter') || 'all';
        this.currentView = 'grid';
        this.friendsData = {
            all: [],
            online: [],
            requests: [],
            suggestions: []
        };
        this.init();
    }
    initOnlineStatusChecker() {
        // Vérification initiale
        this.checkOnlineStatus();

        // Vérification périodique
        setInterval(() => this.checkOnlineStatus(), 30000);
    }
    async checkOnlineStatus() {
        try {
            const response = await fetch('api/online_status.php');
            const data = await response.json();

            if (data?.success) {
                this.onlineUsers = data.online_users.reduce((acc, id) => {
                    acc[id] = true;
                    return acc;
                }, {});

                this.updateOnlineIndicators();

                // Mettre à jour friendsData.online
                this.friendsData.online = this.friendsData.all.filter(
                    friend => this.onlineUsers[friend.id]
                );
            }
        } catch (error) {
            console.error('Erreur statut en ligne:', error);
        }
    }
    updateOnlineIndicators() {
        document.querySelectorAll('.friend-card').forEach(card => {
            const userId = card.dataset.userId;
            const isOnline = this.onlineUsers[userId];

            const statusElement = card.querySelector('.friend-status');
            const avatarElement = card.querySelector('.friend-avatar');

            if (isOnline) {
                statusElement.innerHTML = '<span class="online-dot"></span> En ligne';
                avatarElement.classList.add('online');
            } else {
                statusElement.textContent = 'Hors ligne';
                avatarElement.classList.remove('online');
            }
        });
    }
    initEventListeners() {
        // Gestion persistante des clics sur filtres
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('filter-btn')) {
                this.handleFilterClick(e.target);
            }
        });
    }
    handleFilterClick(btn) {
        this.currentFilter = btn.dataset.filter;
        this.applyCurrentFilter();

        // Stocker le filtre actif
        sessionStorage.setItem('friendsFilter', this.currentFilter);
    }

    applyCurrentFilter() {
        let filteredFriends = [];

        switch(this.currentFilter) {
            case 'online':
                filteredFriends = this.friendsData.all.filter(f => f.is_online);
                break;
            case 'all':
                filteredFriends = this.friendsData.all;
            default:
                filteredFriends = this.friendsData.all;
        }

        this.renderFriends(filteredFriends);
    }
    init() {
        this.setupEventListeners();
        this.loadInitialData();
    }

    setupEventListeners() {
        // Gestion des filtres
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const filter = btn.dataset.filter;
                this.setActiveFilter(filter);
            });
        });

        // Gestion des vues
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const view = btn.dataset.view;
                this.setActiveView(view);
            });
        });

        // Recherche d'amis
        const searchInput = document.getElementById('friends-search');
        searchInput.addEventListener('input', debounce(() => {
            this.searchFriends(searchInput.value);
        }, 300));

        // Bouton "Trouver des amis"
        document.getElementById('find-friends-btn').addEventListener('click', () => {
            this.showFriendSuggestions();
        });

        // Bouton "Inviter par email"
        document.getElementById('invite-friends-btn').addEventListener('click', () => {
            this.showInviteModal();
        });
    }
    // Dans FriendsManager.js


    async loadInitialData() {
        try {
            // Charger les données avec gestion des erreurs pour chaque requête
            const [allFriends, onlineData] = await Promise.all([
                this.safeApiRequest('/friends.php?action=list'),
                this.safeApiRequest('/friends.php?action=online')
            ]);

            if (allFriends?.success) {
                this.friendsData.all = allFriends.friends || [];
            }

            if (onlineData?.success) {
                this.friendsData.online = onlineData.friends || [];
                this.updateOnlineStatus();
            }

        } catch (error) {
            console.error('Erreur chargement données:', error);
            showToast('Erreur lors du chargement des données', 'danger');
        }
    }
    showOnlineFriends() {
        if (this.friendsData.online.length === 0) {
            this.loadOnlineFriends();
        } else {
            this.renderFriends(this.friendsData.online);
        }

        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.filter === 'online');
        });
    }
    saveState() {
        sessionStorage.setItem('friendsState', JSON.stringify({
            filter: this.currentFilter,
            view: this.currentView,
            data: this.friendsData
        }));
    }
    renderCurrentView() {
        switch(this.currentFilter) {
            case 'online':
                this.showOnlineFriends();
                break;
            case 'requests':
                this.renderFriendRequests(this.friendsData.requests);
                break;
            case 'suggestions':
                this.renderFriendSuggestions(this.friendsData.suggestions);
                break;
            default:
                this.renderFriends(this.friendsData.all);
        }
    }
    loadState() {
        const savedState = sessionStorage.getItem('friendsState');
        if (savedState) {
            const state = JSON.parse(savedState);
            this.currentFilter = state.filter;
            this.currentView = state.view;
            this.friendsData = state.data;
            this.renderCurrentView();
        }
    }
// Nouvelle méthode helper
    async safeApiRequest(endpoint) {
        try {
            const response = await apiRequest(endpoint);
            return response || { success: false };
        } catch (error) {
            console.error(`Erreur API (${endpoint}):`, error);
            return { success: false };
        }
    }

    setActiveFilter(filter) {
        this.currentFilter = filter;
        sessionStorage.setItem('friendsFilter', filter);

        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.filter === filter);
        });

        // Afficher la section appropriée
        document.querySelectorAll('.content-section').forEach(section => {
            section.style.display = 'none';
        });

        switch(filter) {
            case 'requests':
                document.getElementById('friend-requests-section').style.display = 'block';
                break;
            case 'online':
                this.showOnlineFriends();
                break;
            case 'all':
                this.renderFriends(this.friendsData.all);
                break;
            case 'suggestions':
                document.getElementById('friend-suggestions-section').style.display = 'block';
                break;
            default:
                document.getElementById('friends-list-section').style.display = 'block';
                this.renderFriends(this.getFilteredFriends(filter));
                break;
        }
        if (filter === 'online' && this.friendsData.online.length === 0) {
            this.loadOnlineFriends();
        }
    }
    async loadOnlineFriends() {
        try {
            const response = await this.safeApiRequest('/friends.php?action=online');
            if (response?.success) {
                this.friendsData.online = response.friends || [];
                this.updateStats();
                this.renderFriends(this.friendsData.online);
            }
        } catch (error) {
            console.error('Erreur chargement amis en ligne:', error);
        }
    }

    getFilteredFriends(filter) {
        switch(filter) {
            case 'online': return this.friendsData.online;
            case 'recent': return [...this.friendsData.all].sort((a, b) =>
                new Date(b.last_interaction) - new Date(a.last_interaction));
            default: return this.friendsData.all;
        }
    }

    setActiveView(view) {
        this.currentView = view;
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });

        const friendsGrid = document.getElementById('friends-grid');
        friendsGrid.classList.toggle('list-view', view === 'list');
    }

    updateStats() {
        document.getElementById('total-friends').textContent =
            this.friendsData.all?.length || 0;
        document.getElementById('online-friends').textContent =
            this.friendsData.online?.length || 0;
        document.getElementById('pending-requests').textContent =
            this.friendsData.received_requests?.length || 0;
    }

    renderFriends(friends = []) {
        const container = document.getElementById('friends-grid');
        container.innerHTML = friends.length
            ? friends.map(friend => this.createFriendCard(friend)).join('')
            : this.createEmptyState('Aucun ami trouvé', 'fa-user-friends');

        this.setupFriendCardEvents();
    }

    createFriendCard(friend) {
        const isOnline = friend.is_online && !friend.hidden_status;

        return `
        <div class="friend-card" data-user-id="${friend.id}">
            <img src="uploads/avatars/${friend.avatar || 'default-avatar.png'}" 
                 alt="${friend.username}" 
                 class="friend-avatar ${isOnline ? 'online' : ''}">
            
            <h3 class="friend-name">${friend.first_name} ${friend.last_name}</h3>
            
            <p class="friend-status">
                ${isOnline
            ? '<span class="online-dot"></span> En ligne'
            : `Dernière connexion: ${this.formatLastSeen(friend.last_seen)}`
        }
            </p>
            
            <div class="friend-actions">
                ${isOnline
            ? '<button class="btn btn-chat">Chat</button>'
            : '<button class="btn btn-message">Message</button>'
        }
            </div>
        </div>
    `;
    }
// Formatage de la date de dernière connexion
    formatLastSeen(lastSeen) {
        if (!lastSeen) return 'inconnue';

        const now = new Date();
        const last = new Date(lastSeen);
        const diffHours = Math.floor((now - last) / (1000 * 60 * 60));

        if (diffHours < 24) return `aujourd'hui à ${last.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`;
        if (diffHours < 48) return 'hier';
        return `le ${last.toLocaleDateString()}`;
    }

// Mise à jour en temps réel
    startOnlineStatusChecker() {
        setInterval(async () => {
            try {
                const response = await this.safeApiRequest('/friends.php?action=online_status');
                if (response?.success) {
                    this.friendsData.online = response.online_friends || [];
                    if (this.currentFilter === 'online') {
                        this.showOnlineFriends();
                    }
                }
            } catch (error) {
                console.error('Erreur vérification statut:', error);
            }
        }, 30000); // Toutes les 30 secondes
    }
    renderFriendRequests(data) {
        // Mise à jour des compteurs
        document.getElementById('received-count').textContent = data.received_requests?.length || 0;
        document.getElementById('sent-count').textContent = data.sent_requests?.length || 0;

        // Rendu des demandes reçues
        const receivedContainer = document.getElementById('received-requests');
        receivedContainer.innerHTML = data.received_requests?.length
            ? data.received_requests.map(req => this.createRequestCard(req, 'received')).join('')
            : this.createEmptyState('Aucune demande reçue', 'fa-user-clock');

        // Rendu des demandes envoyées
        const sentContainer = document.getElementById('sent-requests');
        sentContainer.innerHTML = data.sent_requests?.length
            ? data.sent_requests.map(req => this.createRequestCard(req, 'sent')).join('')
            : this.createEmptyState('Aucune demande envoyée', 'fa-paper-plane');

        // Initialisation des événements
        this.setupRequestCardEvents();
        this.setupRequestTabs(); // Ajout de l'appel manquant
    }
    setupRequestTabs() {
        document.querySelectorAll('.requests-tabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.dataset.tab;

                // Activer l'onglet cliqué
                document.querySelectorAll('.requests-tabs .tab-btn').forEach(b => {
                    b.classList.toggle('active', b === btn);
                });

                // Afficher le contenu correspondant
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.toggle('active', content.id === `${tabId}-requests`);
                });
            });
        });
    }
    createEmptyState(message, icon) {
        return `
        <div class="empty-state">
            <i class="fas ${icon}"></i>
            <p>${message}</p>
        </div>
    `;
    }
    createRequestCard(request, type) {
        return `
            <div class="friend-request" data-request-id="${request.request_id}">
                <div class="request-info">
                    <img src="uploads/avatars/${request.avatar || 'default-avatar.svg'}" 
                         alt="${request.username}" class="request-avatar">
                    <div>
                        <h4>${escapeHtml(request.first_name)} ${escapeHtml(request.last_name)}</h4>
                        <p>@${escapeHtml(request.username)}</p>
                        <small>${type === 'received' ? 'Reçu' : 'Envoyé'} ${formatTimeAgo(request.request_date)}</small>
                    </div>
                </div>
                <div class="request-actions">
                    ${type === 'received' ? `
                        <button class="btn btn-sm btn-success accept-btn" data-request-id="${request.request_id}">
                            <i class="fas fa-check"></i> Accepter
                        </button>
                        <button class="btn btn-sm btn-danger decline-btn" data-request-id="${request.request_id}">
                            <i class="fas fa-times"></i> Refuser
                        </button>
                    ` : `
                        <button class="btn btn-sm btn-warning cancel-btn" data-request-id="${request.request_id}">
                            <i class="fas fa-trash-alt"></i> Annuler
                        </button>
                    `}
                </div>
            </div>
        `;
    }
    async cancelRequest(requestId) {
        const btn = document.querySelector(`.cancel-btn[data-request-id="${requestId}"]`);
        if (!btn) return;

        btn.disabled = true;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const response = await apiRequest('/friends.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'cancel_request',
                    request_id: requestId
                })
            });

            if (response?.success) {
                const card = btn.closest('.friend-request');
                card.classList.add('fade-out');
                setTimeout(() => {
                    card.remove();
                    this.updateRequestCounts();
                    showToast('Demande annulée', 'success');
                }, 300);
            } else {
                throw new Error(response?.message || 'Échec de l\'annulation');
            }
        } catch (error) {
            console.error('Erreur annulation demande:', error);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            showToast(`Échec: ${error.message}`, 'danger');
        }
    }

    updateRequestCounts() {
        const receivedCount = document.querySelectorAll('#received-requests .friend-request').length;
        const sentCount = document.querySelectorAll('#sent-requests .friend-request').length;

        document.getElementById('received-count').textContent = receivedCount;
        document.getElementById('sent-count').textContent = sentCount;

        if (receivedCount === 0) {
            document.getElementById('received-requests').innerHTML = `
            <div class="empty-state">
                <i class="fas fa-user-clock"></i>
                <p>Aucune demande reçue</p>
            </div>
        `;
        }

        if (sentCount === 0) {
            document.getElementById('sent-requests').innerHTML = `
            <div class="empty-state">
                <i class="fas fa-paper-plane"></i>
                <p>Aucune demande envoyée</p>
            </div>
        `;
        }
    }
    renderFriendSuggestions(suggestions = []) {
        const container = document.getElementById('friend-suggestions');
        container.innerHTML = suggestions.length
            ? suggestions.map(suggestion => this.createSuggestionCard(suggestion)).join('')
            : this.createEmptyState('Aucune suggestion disponible', 'fa-user-plus');

        // Appel correct avec this
        this.setupSuggestionCardEvents();
    }
    setupSuggestionCardEvents() {
        // Bouton "Ajouter"
        document.querySelectorAll('.add-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const userId = e.currentTarget.dataset.userId;
                await this.sendFriendRequest(userId);
            });
        });

        // Bouton "Ignorer"
        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const userId = e.currentTarget.dataset.userId;
                await this.ignoreSuggestion(userId);
            });
        });
    }
    createSuggestionCard(suggestion) {
        return `
            <div class="friend-suggestion" data-user-id="${suggestion.id}">
                <div class="suggestion-info">
                    <img src="uploads/avatars/${suggestion.avatar || 'default-avatar.svg'}" 
                         alt="${suggestion.username}" class="suggestion-avatar">
                    <div>
                        <h4>${escapeHtml(suggestion.first_name)} ${escapeHtml(suggestion.last_name)}</h4>
                        <p>${suggestion.mutual_friends} amis en commun</p>
                        <div class="mutual-friends">
                            ${this.getMutualFriendsPreview(suggestion.mutual_friends_list)}
                        </div>
                    </div>
                </div>
                <div class="suggestion-actions">
                    <button class="btn btn-sm btn-primary add-btn" data-user-id="${suggestion.id}">
                        <i class="fas fa-user-plus"></i> Ajouter
                    </button>
                    <button class="btn btn-sm btn-secondary remove-btn" data-user-id="${suggestion.id}">
                        <i class="fas fa-times"></i> Ignorer
                    </button>
                </div>
            </div>
        `;
    }

    getMutualFriendsPreview(mutualFriends) {
        if (!mutualFriends || mutualFriends.length === 0) return '';

        const preview = mutualFriends.slice(0, 3).map(friend =>
            `<img src="uploads/avatars/${friend.avatar || 'default-avatar.svg'}" 
                  alt="${friend.username}" title="${friend.first_name}">`
        ).join('');

        if (mutualFriends.length > 3) {
            return preview + `<span>+${mutualFriends.length - 3}</span>`;
        }
        return preview;
    }

    setupFriendCardEvents() {
        // Bouton message
        document.querySelectorAll('.message-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const userId = e.currentTarget.dataset.userId;
                await messagingSystem.startConversation(userId);
            });
        });

        // Bouton plus d'options
        document.querySelectorAll('.more-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const userId = e.currentTarget.dataset.userId;
                this.showFriendOptionsMenu(userId, e.currentTarget);
            });
        });
    }

    setupRequestCardEvents() {
        // Demandes reçues
        document.querySelectorAll('.accept-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleRequestAction(e, 'accept'));
        });

        document.querySelectorAll('.decline-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleRequestAction(e, 'decline'));
        });

        // Demandes envoyées
        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleRequestAction(e, 'cancel'));
        });
    }

    async handleRequestAction(e, action) {
        e.stopPropagation();
        const requestId = e.currentTarget.dataset.requestId;
        const card = e.currentTarget.closest('.friend-request');

        // Feedback visuel
        const originalHTML = e.currentTarget.innerHTML;
        e.currentTarget.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        e.currentTarget.disabled = true;

        try {
            let endpoint, body;
            if (action === 'cancel') {
                endpoint = '/friends.php?action=cancel_request';
                body = { request_id: requestId };
            } else {
                endpoint = '/friends.php?action=respond_request';
                body = {
                    request_id: requestId,
                    response: action
                };
            }

            const response = await apiRequest(endpoint, {
                method: 'POST',
                body: JSON.stringify(body)
            });

            if (response.success) {
                // Animation de disparition
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    this.updateRequestCounts();
                    showToast(`Demande ${action === 'accept' ? 'acceptée' : action === 'decline' ? 'refusée' : 'annulée'}`, 'success');
                }, 300);
            } else {
                throw new Error(response.message || 'Action échouée');
            }
        } catch (error) {
            e.currentTarget.innerHTML = originalHTML;
            e.currentTarget.disabled = false;
            showToast(error.message, 'danger');
        }
    }

    updateSuggestionsCount() {
        const remaining = document.querySelectorAll('.friend-suggestion').length;
        document.getElementById('suggestions-count').textContent = remaining;

        if (remaining === 0) {
            document.getElementById('friend-suggestions').innerHTML = `
            <div class="empty-state">
                <i class="fas fa-user-plus"></i>
                <p>Aucune suggestion disponible</p>
                <button class="btn btn-primary" id="refresh-suggestions">
                    Rafraîchir les suggestions
                </button>
            </div>
        `;

            document.getElementById('refresh-suggestions')?.addEventListener('click', () => {
                this.loadSuggestions();
            });
        }
    }

    async loadSuggestions() {
        try {
            const response = await apiRequest('/friends.php?action=suggestions');
            if (response.success) {
                this.friendsData.suggestions = response.suggestions;
                this.renderFriendSuggestions(this.friendsData.suggestions);
                this.updateSuggestionsCount();
            }
        } catch (error) {
            console.error("Erreur chargement suggestions:", error);
        }
    }
    async respondToRequest(requestId, action) {
        const btn = document.querySelector(`.${action}-btn[data-request-id="${requestId}"]`);
        if (!btn) return;

        btn.disabled = true;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const response = await apiRequest('/friends.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action === 'accept' ? 'accept_request' : 'decline_request',
                    request_id: requestId
                })
            });

            if (response?.success) {
                // Animation de disparition
                const card = btn.closest('.friend-request');
                card.style.transition = 'opacity 0.3s';
                card.style.opacity = '0';

                setTimeout(() => {
                    card.remove();
                    this.updateRequestCounts();

                    if (action === 'accept') {
                        // Ajouter le nouvel ami à la liste
                        this.loadInitialData(); // Recharger les données
                        showToast('Demande acceptée', 'success');
                    }
                }, 300);
            } else {
                throw new Error(response?.message || 'Action échouée');
            }
        } catch (error) {
            console.error(`Erreur ${action} demande:`, error);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            showToast(`Échec: ${error.message}`, 'danger');
        }
    }

    async sendFriendRequest(userId) {
        const btn = document.querySelector(`.add-btn[data-user-id="${userId}"]`);
        if (!btn) return;

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const response = await fetch('../api/friends.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'send_request',
                    user_id: userId
                })
            });

            const data = await response.json();

            if (data.success) {
                // Animation de disparition
                const card = btn.closest('.friend-suggestion');
                card.style.transition = 'opacity 0.3s';
                card.style.opacity = '0';

                setTimeout(() => card.remove(), 300);

                // Mettre à jour le compteur
                this.updateSuggestionsCount();
            } else {
                throw new Error(data.message || 'Erreur inconnue');
            }
        } catch (error) {
            console.error('Erreur:', error);
            btn.innerHTML = originalText;
            btn.disabled = false;
            // alert('Échec de l\'envoi: ' + error.message);
        }
    }

    async ignoreSuggestion(userId) {
        try {
            const response = await apiRequest('/friends.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'ignore_suggestion',
                    user_id: userId
                })
            });

            if (response.success) {
                // Mettre à jour l'interface
                this.friendsData.suggestions = this.friendsData.suggestions.filter(s => s.id != userId);
                document.querySelector(`.friend-suggestion[data-user-id="${userId}"]`).remove();
            }
        } catch (error) {
            console.error('Erreur ignore suggestion:', error);
        }
    }

    searchFriends(query) {
        if (!query) {
            this.renderFriends(this.getFilteredFriends(this.currentFilter));
            return;
        }

        const filtered = this.getFilteredFriends(this.currentFilter).filter(friend => {
            const name = `${friend.first_name} ${friend.last_name}`.toLowerCase();
            const username = friend.username.toLowerCase();
            return name.includes(query.toLowerCase()) || username.includes(query.toLowerCase());
        });

        this.renderFriends(filtered);
    }

    showFriendOptionsMenu(userId, targetElement) {
        // Implémentez un menu contextuel avec des options comme:
        // - Voir le profil
        // - Bloquer
        // - Supprimer de la liste d'amis
        // - etc.
        console.log('Afficher options pour:', userId);
    }

    showFriendSuggestions() {
        this.setActiveFilter('suggestions');
    }

    showInviteModal() {
        // Implémentez une modal pour inviter des amis par email
        console.log('Afficher modal d\'invitation');
    }
}

// Fonction utilitaire pour le debounce
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    const friendsManager = new FriendsManager();
    window.friendsManager = friendsManager; // Pour accès global si nécessaire
});
setInterval(() => {
    fetch('api/track_activity.php');
}, 300000)