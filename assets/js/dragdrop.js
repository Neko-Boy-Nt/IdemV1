/**
 * Système de glissé-déposer avancé pour IDEM
 */

class DragDropManager {
    constructor() {
        this.draggedElement = null;
        this.draggedData = null;
        this.dropZones = new Map();
        this.init();
    }

    init() {
        this.setupGlobalDragEvents();
        this.setupFileDropZones();
        this.setupPostDragDrop();
        this.setupConversationDragDrop();
    }

    setupGlobalDragEvents() {
        document.addEventListener('dragstart', (e) => {
            this.draggedElement = e.target;
            this.draggedData = this.extractDragData(e.target);
            e.target.classList.add('dragging');
            
            // Créer un aperçu personnalisé
            if (this.draggedData.type === 'post') {
                this.createPostDragPreview(e);
            } else if (this.draggedData.type === 'conversation') {
                this.createConversationDragPreview(e);
            }
        });

        document.addEventListener('dragend', (e) => {
            e.target.classList.remove('dragging');
            this.clearDragPreview();
            this.draggedElement = null;
            this.draggedData = null;
        });

        document.addEventListener('dragover', (e) => {
            e.preventDefault();
        });

        document.addEventListener('drop', (e) => {
            e.preventDefault();
            this.handleGlobalDrop(e);
        });
    }

    setupFileDropZones() {
        // Zone de drop pour les fichiers dans les posts
        const postTextarea = document.getElementById('post-content');
        if (postTextarea) {
            const postCard = postTextarea.closest('.create-post-card');
            this.makeFileDropZone(postCard, this.handleFileUpload.bind(this));
        }

        // Zone de drop pour les fichiers dans les messages
        const messageInputs = document.querySelectorAll('.message-input');
        messageInputs.forEach(input => {
            const container = input.closest('.message-compose');
            this.makeFileDropZone(container, this.handleFileUpload.bind(this));
        });
    }

    makeFileDropZone(element, callback) {
        if (!element) return;

        element.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            element.classList.add('drag-over');
        });

        element.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (!element.contains(e.relatedTarget)) {
                element.classList.remove('drag-over');
            }
        });

        element.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            element.classList.remove('drag-over');
            
            const files = Array.from(e.dataTransfer.files);
            if (files.length > 0) {
                callback(files, element);
            }
        });
    }

    setupPostDragDrop() {
        // Rendre les posts déplaçables
        document.addEventListener('DOMContentLoaded', () => {
            this.makePostsDraggable();
        });

        // Observer pour les nouveaux posts
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1 && node.classList.contains('post-card')) {
                        this.makePostDraggable(node);
                    }
                });
            });
        });

        const postsContainer = document.getElementById('posts-container');
        if (postsContainer) {
            observer.observe(postsContainer, { childList: true });
        }
    }

    setupConversationDragDrop() {
        // Rendre les conversations déplaçables
        document.addEventListener('DOMContentLoaded', () => {
            this.makeConversationsDraggable();
        });
    }

    makePostsDraggable() {
        const posts = document.querySelectorAll('.post-card');
        posts.forEach(post => this.makePostDraggable(post));
    }

    makePostDraggable(post) {
        post.draggable = true;
        post.dataset.dragType = 'post';
        post.dataset.postId = post.dataset.postId || post.querySelector('[data-post-id]')?.dataset.postId;
    }

    makeConversationsDraggable() {
        const conversations = document.querySelectorAll('.conversation-item');
        conversations.forEach(conv => this.makeConversationDraggable(conv));
    }

    makeConversationDraggable(conversation) {
        conversation.draggable = true;
        conversation.dataset.dragType = 'conversation';
    }

    extractDragData(element) {
        const type = element.dataset.dragType;
        const data = { type };

        switch (type) {
            case 'post':
                data.postId = element.dataset.postId;
                data.content = element.querySelector('.post-content')?.textContent;
                data.author = element.querySelector('.post-author')?.textContent;
                break;
            case 'conversation':
                data.conversationId = element.dataset.conversationId;
                data.username = element.querySelector('.conversation-name')?.textContent;
                data.lastMessage = element.querySelector('.conversation-preview')?.textContent;
                break;
        }

        return data;
    }

    createPostDragPreview(e) {
        const preview = document.createElement('div');
        preview.className = 'drag-preview post-preview';
        preview.innerHTML = `
            <div class="drag-preview-content">
                <i class="fas fa-newspaper"></i>
                <span>Publication de ${this.draggedData.author}</span>
                <small>${this.draggedData.content?.substring(0, 50)}...</small>
            </div>
        `;
        this.setDragPreview(e, preview);
    }

    createConversationDragPreview(e) {
        const preview = document.createElement('div');
        preview.className = 'drag-preview conversation-preview';
        preview.innerHTML = `
            <div class="drag-preview-content">
                <i class="fas fa-comments"></i>
                <span>Conversation avec ${this.draggedData.username}</span>
                <small>${this.draggedData.lastMessage?.substring(0, 50)}...</small>
            </div>
        `;
        this.setDragPreview(e, preview);
    }

    setDragPreview(e, preview) {
        document.body.appendChild(preview);
        e.dataTransfer.setDragImage(preview, 0, 0);
        
        // Supprimer l'aperçu après un délai
        setTimeout(() => {
            if (document.body.contains(preview)) {
                document.body.removeChild(preview);
            }
        }, 0);
    }

    clearDragPreview() {
        const previews = document.querySelectorAll('.drag-preview');
        previews.forEach(preview => {
            if (document.body.contains(preview)) {
                document.body.removeChild(preview);
            }
        });
    }

    async handleFileUpload(files, dropZone) {
        const validFiles = this.validateFiles(files);
        if (validFiles.length === 0) {
            showToast('Aucun fichier valide sélectionné', 'warning');
            return;
        }

        // Afficher l'indicateur de chargement
        this.showUploadIndicator(dropZone, validFiles.length);

        try {
            const uploadPromises = validFiles.map(file => this.uploadFile(file));
            const results = await Promise.all(uploadPromises);
            
            // Traiter les résultats
            this.handleUploadResults(results, dropZone);
            
        } catch (error) {
            console.error('Erreur upload:', error);
            showToast('Erreur lors du téléchargement', 'danger');
        } finally {
            this.hideUploadIndicator(dropZone);
        }
    }

    validateFiles(files) {
        const maxSize = 10 * 1024 * 1024; // 10MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm'];
        
        return files.filter(file => {
            if (file.size > maxSize) {
                showToast(`${file.name} est trop volumineux (max 10MB)`, 'warning');
                return false;
            }
            
            if (!allowedTypes.includes(file.type)) {
                showToast(`${file.name} n'est pas un type de fichier supporté`, 'warning');
                return false;
            }
            
            return true;
        });
    }

    async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', file.type.startsWith('image/') ? 'image' : 'video');

        const response = await fetch('/api/upload.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': getCSRFToken()
            },
            body: formData
        });

        if (!response.ok) {
            throw new Error(`Upload failed: ${response.statusText}`);
        }

        return await response.json();
    }

    showUploadIndicator(dropZone, fileCount) {
        const indicator = document.createElement('div');
        indicator.className = 'upload-indicator';
        indicator.innerHTML = `
            <div class="upload-progress">
                <i class="fas fa-cloud-upload-alt fa-spin"></i>
                <span>Téléchargement de ${fileCount} fichier(s)...</span>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
            </div>
        `;
        
        dropZone.appendChild(indicator);
    }

    hideUploadIndicator(dropZone) {
        const indicator = dropZone.querySelector('.upload-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    handleUploadResults(results, dropZone) {
        const successCount = results.filter(r => r.success).length;
        const failCount = results.length - successCount;

        if (successCount > 0) {
            showToast(`${successCount} fichier(s) téléchargé(s) avec succès`, 'success');
            
            // Ajouter les fichiers à l'interface
            results.forEach(result => {
                if (result.success) {
                    this.addFileToInterface(result, dropZone);
                }
            });
        }

        if (failCount > 0) {
            showToast(`${failCount} fichier(s) ont échoué`, 'warning');
        }
    }

    addFileToInterface(fileResult, dropZone) {
        // Détecter le contexte (post ou message)
        if (dropZone.closest('.create-post-card')) {
            this.addFileToPost(fileResult);
        } else if (dropZone.closest('.message-compose')) {
            this.addFileToMessage(fileResult, dropZone);
        }
    }

    addFileToPost(fileResult) {
        const attachmentsContainer = this.getOrCreateAttachmentsContainer('.create-post-card');
        const fileElement = this.createFileElement(fileResult);
        attachmentsContainer.appendChild(fileElement);
    }

    addFileToMessage(fileResult, dropZone) {
        const attachmentsContainer = this.getOrCreateAttachmentsContainer('.message-compose', dropZone);
        const fileElement = this.createFileElement(fileResult);
        attachmentsContainer.appendChild(fileElement);
    }

    getOrCreateAttachmentsContainer(selector, context = document) {
        const container = (context.closest ? context.closest(selector) : context.querySelector(selector));
        let attachments = container.querySelector('.attachments-preview');
        
        if (!attachments) {
            attachments = document.createElement('div');
            attachments.className = 'attachments-preview';
            container.appendChild(attachments);
        }
        
        return attachments;
    }

    createFileElement(fileResult) {
        const fileDiv = document.createElement('div');
        fileDiv.className = 'attachment-item';
        fileDiv.dataset.fileId = fileResult.file_id;

        if (fileResult.type === 'image') {
            fileDiv.innerHTML = `
                <div class="attachment-preview">
                    <img src="${fileResult.url}" alt="Image" class="attachment-image">
                    <button type="button" class="attachment-remove" onclick="removeAttachment(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        } else {
            fileDiv.innerHTML = `
                <div class="attachment-preview">
                    <div class="attachment-video">
                        <video src="${fileResult.url}" controls class="attachment-video-player"></video>
                        <button type="button" class="attachment-remove" onclick="removeAttachment(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        }

        return fileDiv;
    }

    handleGlobalDrop(e) {
        if (!this.draggedData) return;

        const dropTarget = e.target.closest('[data-drop-zone]');
        if (!dropTarget) return;

        const dropZoneType = dropTarget.dataset.dropZone;
        
        switch (dropZoneType) {
            case 'favorites':
                this.handleFavoritesDrop(dropTarget);
                break;
            case 'archive':
                this.handleArchiveDrop(dropTarget);
                break;
            case 'share':
                this.handleShareDrop(dropTarget);
                break;
        }
    }

    async handleFavoritesDrop(dropZone) {
        if (this.draggedData.type === 'post') {
            try {
                const response = await apiRequest('/api/favorites.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        action: 'add',
                        post_id: this.draggedData.postId
                    })
                });

                if (response.success) {
                    showToast('Publication ajoutée aux favoris', 'success');
                    this.animateDropSuccess(dropZone);
                }
            } catch (error) {
                showToast('Erreur lors de l\'ajout aux favoris', 'danger');
            }
        }
    }

    async handleArchiveDrop(dropZone) {
        if (this.draggedData.type === 'conversation') {
            try {
                const response = await apiRequest('/api/conversations.php', {
                    method: 'PUT',
                    body: JSON.stringify({
                        action: 'archive',
                        conversation_id: this.draggedData.conversationId
                    })
                });

                if (response.success) {
                    showToast('Conversation archivée', 'success');
                    this.animateDropSuccess(dropZone);
                    this.draggedElement.style.display = 'none';
                }
            } catch (error) {
                showToast('Erreur lors de l\'archivage', 'danger');
            }
        }
    }

    async handleShareDrop(dropZone) {
        if (this.draggedData.type === 'post') {
            // Ouvrir le modal de partage
            this.openShareModal(this.draggedData.postId);
        }
    }

    animateDropSuccess(dropZone) {
        dropZone.classList.add('drop-success');
        setTimeout(() => {
            dropZone.classList.remove('drop-success');
        }, 1000);
    }

    openShareModal(postId) {
        // Implémenter le modal de partage
        const modal = document.getElementById('shareModal') || this.createShareModal();
        modal.dataset.postId = postId;
        modal.style.display = 'block';
    }

    createShareModal() {
        const modal = document.createElement('div');
        modal.id = 'shareModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Partager la publication</h3>
                    <button type="button" class="modal-close" onclick="closeModal('shareModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <textarea placeholder="Ajouter un commentaire..." class="share-comment"></textarea>
                    <div class="share-options">
                        <button type="button" class="btn btn-primary" onclick="sharePost()">
                            Partager
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('shareModal')">
                            Annuler
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        return modal;
    }
}

// Fonctions globales pour l'interface
function removeAttachment(button) {
    const attachment = button.closest('.attachment-item');
    attachment.remove();
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

async function sharePost() {
    const modal = document.getElementById('shareModal');
    const postId = modal.dataset.postId;
    const comment = modal.querySelector('.share-comment').value;
    
    try {
        const response = await apiRequest('/api/posts.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'share',
                post_id: postId,
                comment: comment
            })
        });

        if (response.success) {
            showToast('Publication partagée', 'success');
            closeModal('shareModal');
        }
    } catch (error) {
        showToast('Erreur lors du partage', 'danger');
    }
}

// Initialiser le gestionnaire de drag & drop
document.addEventListener('DOMContentLoaded', () => {
    window.dragDropManager = new DragDropManager();
});