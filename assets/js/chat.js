/**
 * chat.js - Gestion de la messagerie pour IDEM
 * Gère les conversations, l'envoi de messages, les fichiers, et les interactions temps réel
 */

class MessagingSystem {
    constructor() {
        this.websocket = null;
        this.currentConversationId = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.messageInput = document.getElementById('message-input');
        this.sendButton = document.getElementById('send-message-btn');
        this.messagesContainer = document.getElementById('messages-container-main');
        this.conversationsList = document.getElementById('conversations-list');
        this.typingTimeout = null;
        this.init();
    }

    init() {
        this.connectWebSocket();
        this.setupEventListeners();
        this.loadConversations();
    }

    connectWebSocket() {
        if (!window.app.websocketUrl) {
            console.error('WebSocket URL non définie');
            return;
        }

        try {
            this.websocket = new WebSocket(window.app.websocketUrl);

            this.websocket.onopen = () => {
                console.log('✅ WebSocket connecté');
                this.reconnectAttempts = 0;
                const sessionId = document.querySelector('meta[name="session-id"]')?.content;
                if (!sessionId) {
                    console.error('Session ID non trouvé');
                    this.websocket.close();
                    return;
                }
                this.websocket.send(JSON.stringify({
                    type: 'auth',
                    user_id: window.app.userId,
                    session_id: sessionId
                }));
            };

            this.websocket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    switch (data.type) {
                        case 'auth_success':
                            console.log('WebSocket authentifié');
                            break;
                        case 'message':
                            this.handleNewMessage(data.message);
                            break;
                        case 'typing':
                            this.showTypingIndicator(data.user_id, data.conversation_id);
                            break;
                        case 'typing_stop':
                            this.hideTypingIndicator(data.conversation_id);
                            break;
                        case 'read':
                            this.updateMessageStatus(data.message_id, 'read');
                            break;
                        case 'delete':
                            this.removeMessage(data.message_id);
                            break;
                        case 'error':
                            console.error('Erreur WebSocket:', data.message);
                            showToast(data.message, 'danger');
                            break;
                        default:
                            console.log('Message WebSocket non géré:', data);
                    }
                } catch (err) {
                    console.error('Erreur message WebSocket:', err);
                    showToast('Erreur de communication temps réel', 'danger');
                }
            };

            this.websocket.onclose = () => {
                console.log('🔌 WebSocket fermé');
                if (this.reconnectAttempts < this.maxReconnectAttempts) {
                    this.reconnectAttempts++;
                    setTimeout(() => this.connectWebSocket(), 5000);
                } else {
                    showToast('Impossible de maintenir la connexion temps réel', 'danger');
                }
            };

            this.websocket.onerror = (err) => {
                console.error('Erreur WebSocket:', err);
                showToast('Erreur de connexion temps réel', 'danger');
            };
        } catch (err) {
            console.error('Erreur initialisation WebSocket:', err);
            showToast('Erreur lors de la connexion temps réel', 'danger');
        }
    }

    setupEventListeners() {
        // Nouvelle conversation
        const newConversationBtn = document.getElementById('new-conversation-btn');
        const startConversationBtn = document.getElementById('start-conversation-btn');
        if (newConversationBtn) {
            newConversationBtn.addEventListener('click', () => this.openNewConversationModal());
        }
        if (startConversationBtn) {
            startConversationBtn.addEventListener('click', () => this.openNewConversationModal());
        }

        // Recherche conversations
        const searchInput = document.getElementById('conversations-search');
        if (searchInput) {
            searchInput.addEventListener('input', utils.debounce((e) => this.searchConversations(e.target.value), 300));
        }

        // Envoi message
        if (this.messageInput) {
            this.messageInput.addEventListener('input', () => {
                this.sendButton.disabled = !this.messageInput.value.trim();
                this.sendTyping();
            });
            this.messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }
        if (this.sendButton) {
            this.sendButton.addEventListener('click', () => this.sendMessage());
        }

        // Upload fichiers
        const attachFilesBtn = document.getElementById('attach-files-btn');
        if (attachFilesBtn) {
            attachFilesBtn.addEventListener('click', () => {
                const input = document.createElement('input');
                input.type = 'file';
                input.multiple = true;
                input.accept = 'image/*,video/*,audio/*,.pdf';
                input.onchange = (e) => this.handleFiles(e.target.files);
                input.click();
            });
        }

        // Actions conversation
        const audioCallBtn = document.getElementById('audio-call-btn');
        const videoCallBtn = document.getElementById('video-call-btn');
        const infoBtn = document.getElementById('conversation-info-btn');
        const closeInfoBtn = document.getElementById('close-info-panel');
        const muteBtn = document.getElementById('mute-conversation');
        const archiveBtn = document.getElementById('archive-conversation');
        const deleteBtn = document.getElementById('delete-conversation');

        if (audioCallBtn) audioCallBtn.addEventListener('click', () => this.startCall('audio'));
        if (videoCallBtn) videoCallBtn.addEventListener('click', () => this.startCall('video'));
        if (infoBtn) infoBtn.addEventListener('click', () => this.toggleInfoPanel());
        if (closeInfoBtn) closeInfoBtn.addEventListener('click', () => this.toggleInfoPanel());
        if (muteBtn) muteBtn.addEventListener('click', () => this.muteConversation());
        if (archiveBtn) archiveBtn.addEventListener('click', () => this.archiveConversation());
        if (deleteBtn) deleteBtn.addEventListener('click', () => this.deleteConversation());

        // Création groupe
        const createGroupBtn = document.getElementById('create-group-btn');
        if (createGroupBtn) {
            createGroupBtn.addEventListener('click', () => this.createGroup());
        }

        // Actions sur les messages
        this.messagesContainer?.addEventListener('click', (e) => {
            const option = e.target.closest('.message-options-toggle');
            const action = e.target.closest('.option-item');
            if (option) {
                const menu = option.nextElementSibling;
                menu.classList.toggle('active');
            } else if (action) {
                const messageId = action.closest('.message').dataset.messageId;
                this.handleMessageAction(action.dataset.action, messageId);
            }
        });
    }

    async loadConversations() {
        try {
            const response = await window.utils.apiRequest('api/conversations.php?action=list');
            if (response.success) {
                this.conversationsList.innerHTML = '';
                if (response.conversations.length === 0) {
                    this.conversationsList.innerHTML = `
                    <div class="no-conversations">
                        <p>Aucune conversation pour le moment. Commencez une nouvelle conversation !</p>
                    </div>
                `;
                    return;
                }
                response.conversations.forEach(conv => {
                    const item = document.createElement('div');
                    item.className = `conversation-item ${this.currentConversationId == conv.id ? 'active' : ''}`;
                    item.dataset.conversationId = conv.id;
                    item.innerHTML = `
                    <img src="${window.app.UploadsUrl}avatars/${conv.other_user_avatar || 'default.jpg'}" class="conversation-avatar">
                    <div class="conversation-info">
                        <h4 class="conversation-name">${window.utils.escapeHtml(conv.name || conv.other_user_name)}</h4>
                        <p class="conversation-preview">${window.utils.truncateText(window.utils.escapeHtml(conv.last_message || ''), 50)}</p>
                        <span class="conversation-time">${window.utils.formatTimeAgo(conv.last_message_time)}</span>
                        ${conv.unread_count > 0 ? `<span class="unread-count">${conv.unread_count}</span>` : ''}
                    </div>
                `;
                    item.addEventListener('click', () => this.loadConversationMessages(conv.id));
                    this.conversationsList.appendChild(item);
                });
            } else {
                throw new Error(response.message || 'Erreur lors du chargement des conversations');
            }
        } catch (err) {
            console.error('Erreur chargement conversations:', err);
            showToast('Erreur lors du chargement des conversations', 'danger');
        }
    }

    async loadConversationMessages(conversationId) {
        try {
            const response = await window.utils.apiRequest(`api/messages.php?conversation_id=${conversationId}`);
            if (response.success) {
                this.messagesContainer.innerHTML = '';
                response.messages.forEach(msg => this.renderMessage(msg));
                this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            } else {
                throw new Error(response.message || 'Erreur chargement messages');
            }
        } catch (err) {
            console.error('Erreur chargement messages:', err);
            showToast('Erreur lors du chargement des messages', 'danger');
        }
    }

    renderMessage(message) {
        const isSent = message.sender_id == window.app.userId;
        const messageElement = document.createElement('div');
        messageElement.className = `message ${isSent ? 'sent' : 'received'}`;
        messageElement.dataset.messageId = message.id;
        let content = '';
        switch (message.message_type) {
            case 'text':
                content = `<p class="message-content">${window.utils.escapeHtml(message.content)}</p>`;
                break;
            case 'image':
                content = `<img src="${window.app.uploadsUrl}images/${message.file_url}" class="message-image" alt="Image envoyée">`;
                break;
            case 'video':
                content = `<video src="${window.app.uploadsUrl}videos/${message.file_url}" controls class="message-video"></video>`;
                break;
            case 'audio':
                content = `<audio src="${window.app.uploadsUrl}audio/${message.file_url}" controls class="audio-player"></audio>`;
                break;
            case 'file':
                content = `
                    <div class="file-message">
                        <i class="fas fa-file file-icon"></i>
                        <div class="file-info">
                            <a href="${window.app.uploadsUrl}files/${message.file_url}" download>${window.utils.escapeHtml(message.original_filename)}</a>
                            <span class="file-size">${window.utils.formatFileSize(message.file_size || 0)}</span>
                        </div>
                    </div>`;
                break;
        }
        messageElement.innerHTML = `
            <div class="message-header">
                ${!isSent ? `<span class="sender-name">${window.utils.escapeHtml(message.first_name)} ${window.utils.escapeHtml(message.last_name)}</span>` : ''}
                <span class="message-time">${window.utils.formatTimeAgo(message.created_at)}</span>
                <button class="message-options-toggle"><i class="fas fa-ellipsis-v"></i></button>
            </div>
            ${message.reply_to ? `<div class="message-reply">En réponse à un message</div>` : ''}
            ${content}
            <div class="message-status ${message.is_read ? 'read' : ''}">
                ${message.is_read ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>'}
            </div>
            <div class="message-options-menu">
                <button class="option-item" data-action="reply"><i class="fas fa-reply"></i> Répondre</button>
                <button class="option-item" data-action="react"><i class="fas fa-heart"></i> Réagir</button>
                <button class="option-item delete-option" data-action="delete"><i class="fas fa-trash"></i> Supprimer</button>
            </div>
        `;
        this.messagesContainer.appendChild(messageElement);
    }

    async sendMessage() {
        if (!this.messageInput.value.trim() || !this.currentConversationId) {
            showToast('Veuillez sélectionner une conversation et entrer un message', 'danger');
            return;
        }

        try {
            const response = await window.utils.apiRequest('api/messages.php', {
                method: 'POST',
                body: JSON.stringify({
                    conversation_id: this.currentConversationId,
                    content: this.messageInput.value,
                    message_type: 'text'
                })
            });
            if (response.success) {
                const message = {
                    id: response.message_id,
                    conversation_id: this.currentConversationId,
                    sender_id: window.app.userId,
                    content: this.messageInput.value,
                    message_type: 'text',
                    created_at: new Date().toISOString(),
                    is_read: false,
                    first_name: currentUser.first_name,
                    last_name: currentUser.last_name
                };
                this.renderMessage(message);
                this.messageInput.value = '';
                this.sendButton.disabled = true;
                this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
                if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                    this.websocket.send(JSON.stringify({
                        type: 'message',
                        message: message
                    }));
                }
                this.loadConversations(); // Mettre à jour la liste avec le dernier message
            } else {
                throw new Error(response.message || 'Erreur envoi message');
            }
        } catch (err) {
            console.error('Erreur envoi message:', err);
            showToast('Erreur lors de l\'envoi du message', 'danger');
        }
    }

    async handleFiles(files) {
        const attachmentsContainer = document.getElementById('message-attachments');
        if (!attachmentsContainer) return;
        attachmentsContainer.style.display = 'block';

        for (const file of files) {
            try {
                const response = await window.utils.uploadFile(file, file.type.split('/')[0]);
                if (response.success) {
                    const attachment = document.createElement('div');
                    attachment.className = 'attachment-item';
                    attachment.innerHTML = `
                        <span>${window.utils.escapeHtml(file.name)}</span>
                        <button onclick="window.removeAttachment(this)"><i class="fas fa-times"></i></button>
                    `;
                    attachmentsContainer.appendChild(attachment);

                    const messageResponse = await window.utils.apiRequest('api/messages.php', {
                        method: 'POST',
                        body: JSON.stringify({
                            conversation_id: this.currentConversationId,
                            message_type: file.type.split('/')[0],
                            file_url: response.file_url,
                            original_filename: file.name
                        })
                    });
                    if (messageResponse.success) {
                        const message = {
                            id: messageResponse.message_id,
                            conversation_id: this.currentConversationId,
                            sender_id: window.app.userId,
                            message_type: file.type.split('/')[0],
                            file_url: response.file_url,
                            original_filename: file.name,
                            created_at: new Date().toISOString(),
                            is_read: false,
                            first_name: currentUser.first_name,
                            last_name: currentUser.last_name
                        };
                        this.renderMessage(message);
                        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                            this.websocket.send(JSON.stringify({
                                type: 'message',
                                message: message
                            }));
                        }
                        this.loadConversations();
                    }
                }
            } catch (err) {
                console.error('Erreur upload fichier:', err);
                showToast(`Erreur lors de l'upload de ${file.name}`, 'danger');
            }
        }
    }

    async openNewConversationModal() {
        const modal = document.getElementById('new-conversation-modal');
        if (!modal) {
            console.error('Modal new-conversation-modal non trouvé');
            showToast('Erreur interface: modal non trouvé', 'danger');
            return;
        }
        modal.style.display = 'block';
        this.loadFriendsForNewConversation();
    }

    async loadFriendsForNewConversation() {
        const list = document.getElementById('friends-to-message');
        if (!list) {
            console.error('Conteneur friends-to-message non trouvé');
            return;
        }
        try {
            const response = await window.utils.apiRequest('api/friends.php?action=list');
            if (response.success) {
                list.innerHTML = '';
                if (response.friends.length === 0) {
                    list.innerHTML = '<p>Aucun ami disponible pour démarrer une conversation.</p>';
                    return;
                }
                response.friends.forEach(friend => {
                    const div = document.createElement('div');
                    div.className = 'friend-item';
                    div.innerHTML = `
                    <img src="${window.app.uploadsUrl}avatars/${friend.avatar || 'default.jpg'}" alt="Avatar">
                    <span>${window.utils.escapeHtml(friend.first_name)} ${window.utils.escapeHtml(friend.last_name)}</span>
                `;
                    div.addEventListener('click', () => this.startConversation(friend.id));
                    list.appendChild(div);
                });
            } else {
                throw new Error(response.message || 'Erreur chargement amis');
            }
        } catch (err) {
            console.error('Erreur chargement amis:', err);
            showToast('Erreur lors du chargement des amis', 'danger');
        }
    }

    async startConversation(userId) {
        try {
            const response = await window.utils.apiRequest('api/conversations.php', {
                method: 'POST',
                body: JSON.stringify({
                    type: 'private',
                    participants: [userId]
                })
            });
            if (response.success) {
                window.closeModal('new-conversation-modal');
                this.loadConversations();
                this.loadConversation(response.conversation_id);
                showToast('Conversation démarrée', 'success');
            } else {
                throw new Error(response.message || 'Erreur création conversation');
            }
        } catch (err) {
            console.error('Erreur démarrage conversation:', err);
            showToast('Erreur lors du démarrage de la conversation', 'danger');
        }
    }

    async createGroup() {
        const nameInput = document.getElementById('group-name-input');
        const membersList = document.querySelectorAll('#group-members-list .friend-item input:checked');
        if (!nameInput || !membersList) return;

        const name = nameInput.value.trim();
        const selectedUsers = Array.from(membersList).map(input => parseInt(input.value));
        if (!name || selectedUsers.length === 0) {
            showToast('Nom du groupe et participants requis', 'danger');
            return;
        }

        try {
            const response = await window.utils.apiRequest('api/conversations.php', {
                method: 'POST',
                body: JSON.stringify({
                    type: 'group',
                    name: name,
                    participants: selectedUsers
                })
            });
            if (response.success) {
                window.closeModal('new-group-modal');
                this.loadConversations();
                this.loadConversation(response.conversation_id);
                showToast('Groupe créé', 'success');
            } else {
                throw new Error(response.message || 'Erreur création groupe');
            }
        } catch (err) {
            console.error('Erreur création groupe:', err);
            showToast('Erreur lors de la création du groupe', 'danger');
        }
    }

    async handleMessageAction(action, messageId) {
        try {
            switch (action) {
                case 'reply':
                    const message = this.messageInput.value;
                    this.messageInput.value = `En réponse à #${messageId}: `;
                    this.messageInput.focus();
                    break;
                case 'react':
                    const response = await window.utils.apiRequest('api/messages.php', {
                        method: 'PUT',
                        body: JSON.stringify({
                            action: 'react',
                            message_id: messageId,
                            reaction_type: 'like'
                        })
                    });
                    if (response.success) {
                        showToast('Réaction ajoutée', 'success');
                    }
                    break;
                case 'delete':
                    const deleteResponse = await window.utils.apiRequest(`api/messages.php?id=${messageId}`, {
                        method: 'DELETE'
                    });
                    if (deleteResponse.success) {
                        this.removeMessage(messageId);
                        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                            this.websocket.send(JSON.stringify({
                                type: 'delete',
                                message_id: messageId,
                                conversation_id: this.currentConversationId
                            }));
                        }
                        showToast('Message supprimé', 'success');
                    }
                    break;
            }
        } catch (err) {
            console.error(`Erreur action ${action}:`, err);
            showToast(`Erreur lors de l'action ${action}`, 'danger');
        }
    }

    sendTyping() {
        if (!this.currentConversationId || !this.websocket || this.websocket.readyState !== WebSocket.OPEN) return;

        this.websocket.send(JSON.stringify({
            type: 'typing',
            conversation_id: this.currentConversationId,
            user_id: window.app.userId
        }));

        if (this.typingTimeout) {
            clearTimeout(this.typingTimeout);
        }
        this.typingTimeout = setTimeout(() => {
            this.websocket.send(JSON.stringify({
                type: 'typing_stop',
                conversation_id: this.currentConversationId,
                user_id: window.app.userId
            }));
        }, 3000);
    }

    showTypingIndicator(userId, conversationId) {
        if (conversationId != this.currentConversationId) return;
        const indicator = document.getElementById('typing-indicator');
        if (indicator) {
            indicator.style.display = 'flex';
            indicator.innerHTML = `<span>${userId == window.app.userId ? 'Vous' : 'Quelqu’un'} est en train d'écrire...</span>`;
        }
    }

    hideTypingIndicator(conversationId) {
        if (conversationId != this.currentConversationId) return;
        const indicator = document.getElementById('typing-indicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }

    updateMessageStatus(messageId, status) {
        const messageElement = document.querySelector(`[data-message-id="${messageId}"] .message-status`);
        if (messageElement) {
            messageElement.className = `message-status ${status}`;
            messageElement.innerHTML = status === 'read' ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>';
        }
    }

    removeMessage(messageId) {
        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
        if (messageElement) {
            messageElement.classList.add('deleted');
            const content = messageElement.querySelector('.message-content');
            if (content) content.textContent = 'Message supprimé';
        }
    }

    async startCall(type) {
        showToast(`${type === 'audio' ? 'Appel audio' : 'Appel vidéo'} non implémenté`, 'info');
        // TODO: Implémenter WebRTC pour appels audio/vidéo via api/calls.php
    }

    async toggleInfoPanel() {
        const panel = document.getElementById('conversation-info-panel');
        if (panel) {
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }
    }

    async muteConversation() {
        if (!this.currentConversationId) return;
        try {
            const response = await window.utils.apiRequest('api/conversations.php', {
                method: 'PUT',
                body: JSON.stringify({
                    action: 'mute',
                    conversation_id: this.currentConversationId
                })
            });
            if (response.success) {
                showToast('Notifications désactivées', 'success');
            }
        } catch (err) {
            console.error('Erreur mute conversation:', err);
            showToast('Erreur lors de la désactivation des notifications', 'danger');
        }
    }

    async archiveConversation() {
        if (!this.currentConversationId) return;
        try {
            const response = await window.utils.apiRequest('api/conversations.php', {
                method: 'PUT',
                body: JSON.stringify({
                    action: 'archive',
                    conversation_id: this.currentConversationId
                })
            });
            if (response.success) {
                showToast('Conversation archivée', 'success');
                this.loadConversations();
                document.getElementById('conversation-active').style.display = 'none';
                document.getElementById('conversation-empty').style.display = 'block';
                this.currentConversationId = null;
            }
        } catch (err) {
            console.error('Erreur archive conversation:', err);
            showToast('Erreur lors de l\'archivage', 'danger');
        }
    }

    async deleteConversation() {
        if (!this.currentConversationId) return;
        try {
            const response = await window.utils.apiRequest(`api/conversations.php?id=${this.currentConversationId}`, {
                method: 'DELETE'
            });
            if (response.success) {
                showToast('Conversation supprimée', 'success');
                this.loadConversations();
                document.getElementById('conversation-active').style.display = 'none';
                document.getElementById('conversation-empty').style.display = 'block';
                this.currentConversationId = null;
            }
        } catch (err) {
            console.error('Erreur suppression conversation:', err);
            showToast('Erreur lors de la suppression', 'danger');
        }
    }

    async searchConversations(query) {
        try {
            const response = await window.utils.apiRequest(`api/conversations.php?action=list&search=${encodeURIComponent(query)}`);
            if (response.success) {
                this.loadConversations(); // Recharge avec les résultats filtrés
            }
        } catch (err) {
            console.error('Erreur recherche conversations:', err);
            showToast('Erreur lors de la recherche', 'danger');
        }
    }

    updateConversationListUI() {
        const items = this.conversationsList.querySelectorAll('.conversation-item');
        items.forEach(item => {
            item.classList.toggle('active', parseInt(item.dataset.conversationId) === this.currentConversationId);
        });
    }

    handleNewMessage(message) {
        if (message.conversation_id == this.currentConversationId) {
            this.renderMessage(message);
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            if (message.sender_id != window.app.userId) {
                window.utils.apiRequest('api/messages.php', {
                    method: 'PUT',
                    body: JSON.stringify({
                        action: 'read',
                        message_id: message.id
                    })
                });
            }
        }
        this.loadConversations();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Définir currentUser pour compatibilité
    window.currentUser = {
        id: window.app.userId,
        first_name: document.querySelector('meta[name="user-first-name"]')?.content || 'Utilisateur',
        last_name: document.querySelector('meta[name="user-last-name"]')?.content || ''
    };
    window.messagingSystem = new MessagingSystem();
});