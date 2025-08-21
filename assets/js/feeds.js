/**
 * Gestionnaire du fil d'actualité pour IDEM
 */
/**
 * Fonction utilitaire pour échapper le HTML
 * @param {string} str
 * @returns {string}
 */

const privacySelect = document.getElementById('post-privacy');
const postForm = document.getElementById('new-post-form');

if (privacySelect){
    privacySelect.addEventListener('change', function() {
        postForm.dataset.privacy = this.value;
    });
}
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Formatte une date en "il y a X temps"
 * @param {string} timestamp
 * @returns {string}
 */
function formatTimeAgo(timestamp) {
    const seconds = Math.floor((new Date() - new Date(timestamp)) / 1000);

    const intervals = {
        année: 31536000,
        mois: 2592000,
        semaine: 604800,
        jour: 86400,
        heure: 3600,
        minute: 60,
        seconde: 1
    };

    for (const [unit, secondsInUnit] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / secondsInUnit);
        if (interval >= 1) {
            return interval === 1 ? `il y a ${interval} ${unit}` : `il y a ${interval} ${unit}s`;
        }
    }

    return 'à l\'instant';
}
class FeedManager {
    constructor() {
        this.feedContainer = null;
        this.postForm = null;
        this.posts = new Map();
        this.isLoading = false;
        this.hasMorePosts = true;
        this.currentPage = 1;
        this.lastPostId = 0;
        this.refreshInterval = null;
        this.newPostsBuffer = [];

        this.init();
    }

    init() {
        this.setupElements();
        this.setupEventListeners();
        this.loadInitialPosts();
        this.setupInfiniteScroll();
        this.setupPostForm();
        this.startAutoRefresh();
    }

    setupElements() {
        this.feedContainer = document.getElementById('feed-container');
        this.postForm = document.getElementById('new-post-form');

        if (!this.feedContainer) {
            console.log('Feed container not found');
            return;
        }
    }

    showPostOptions(postElement, button) {
        // Fermer tous les autres menus ouverts
        document.querySelectorAll('.post-options-menu').forEach(menu => {
            if (menu !== postElement.querySelector('.post-options-menu')) {
                menu.classList.remove('active');
            }
        });

        // Trouver le menu spécifique à ce post
        const optionsMenu = postElement.querySelector('.post-options-menu');
        optionsMenu.classList.toggle('active');

        // Positionner le menu près du bouton
        const buttonRect = button.getBoundingClientRect();
        optionsMenu.style.top = `${buttonRect.bottom + window.scrollY}px`;
        optionsMenu.style.left = `${buttonRect.left + window.scrollX - optionsMenu.offsetWidth + buttonRect.width}px`;

        // Fermer le menu si on clique ailleurs
        const clickOutsideHandler = (e) => {
            if (!optionsMenu.contains(e.target) && e.target !== button) {
                optionsMenu.classList.remove('active');
                document.removeEventListener('click', clickOutsideHandler);
            }
        };

        document.addEventListener('click', clickOutsideHandler);
    }

    reportPost(postElement) {
        const postId = postElement.dataset.postId;
        const modal = this.createModal(
            'Signaler la publication',
            'Pourquoi souhaitez-vous signaler cette publication ?',
            [
                { type: 'radio', name: 'reason', value: 'spam', label: 'Spam ou publicité' },
                { type: 'radio', name: 'reason', value: 'inappropriate', label: 'Contenu inapproprié' },
                { type: 'radio', name: 'reason', value: 'harassment', label: 'Harcèlement' },
                { type: 'radio', name: 'reason', value: 'other', label: 'Autre' },
                { type: 'textarea', name: 'details', placeholder: 'Détails (optionnel)' }
            ],
            async (formData) => {
                try {
                    const response = await apiRequest('/posts.php', {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'report',
                            post_id: postId,
                            reason: formData.reason,
                            details: formData.details || ''
                        })
                    });

                    if (response.success) {
                        showToast('Publication signalée avec succès', 'success');
                    } else {
                        throw new Error(response.message || 'Erreur lors du signalement');
                    }
                } catch (error) {
                    showToast(error.message, 'error');
                }
            }
        );
        modal.open();
    }

    editPost(postElement) {
        const postId = postElement.dataset.postId;
        const postContent = postElement.querySelector('.post-text').textContent;

        const modal = this.createModal(
            'Modifier la publication',
            '',
            [
                { type: 'textarea', name: 'content', value: postContent, placeholder: 'Modifiez votre publication...' }
            ],
            async (formData) => {
                try {
                    const response = await apiRequest('/posts.php', {
                        method: 'POST',
                        body: JSON.stringify({
                            action: 'edit',
                            post_id: postId,
                            content: formData.content
                        })
                    });

                    if (response.success) {
                        postElement.querySelector('.post-text').innerHTML =
                            this.formatPostContent(formData.content);
                        showToast('Publication modifiée avec succès', 'success');
                    } else {
                        throw new Error(response.message || 'Erreur lors de la modification');
                    }
                } catch (error) {
                    showToast(error.message, 'error');
                }
            }
        );
        modal.open();
    }

    async deletePost(postElement) {
        const postId = postElement.dataset.postId;

        const confirm = await this.showConfirmationDialog(
            'Supprimer la publication',
            'Êtes-vous sûr de vouloir supprimer cette publication ? Cette action est irréversible.',
            'Supprimer',
            'danger'
        );

        if (confirm) {
            try {
                const response = await apiRequest('/posts.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        action: 'delete',
                        post_id: postId
                    })
                });

                if (response.success) {
                    postElement.style.opacity = '0';
                    postElement.style.height = `${postElement.offsetHeight}px`;

                    setTimeout(() => {
                        postElement.style.height = '0';
                        postElement.style.margin = '0';
                        postElement.style.padding = '0';
                        postElement.style.border = 'none';

                        setTimeout(() => {
                            postElement.remove();
                            showToast('Publication supprimée avec succès', 'success');
                        }, 300);
                    }, 50);
                } else {
                    throw new Error(response.message || 'Erreur lors de la suppression');
                }
            } catch (error) {
                showToast(error.message, 'error');
            }
        }
    }

    // Méthodes utilitaires
    createModal(title, description, fields, onSubmit) {
        const modal = document.createElement('div');
        modal.className = 'custom-modal';

        // ... (implémentation complète de la création modale)
        // Retourne un objet avec une méthode open()
        return {
            open: () => document.body.appendChild(modal)
        };
    }

    showConfirmationDialog(title, message, confirmText, confirmType = 'primary') {
        return new Promise((resolve) => {
            const dialog = document.createElement('div');
            dialog.className = 'confirmation-dialog';

            // ... (implémentation complète du dialogue de confirmation)
            // Retourne une Promise qui se résout avec true/false
        });
    }
    setupEventListeners() {
        // Actions sur les posts
        document.addEventListener('click', (e) => {
            const postElement = e.target.closest('.post-item');

            if (e.target.matches('.like-btn')) {
                this.toggleLike(postElement);
            } else if (e.target.matches('.comment-btn')) {
                this.toggleComments(postElement);
            } else if (e.target.matches('.share-btn')) {
                this.sharePost(postElement);
            } else if (e.target.matches('.save-btn')) {
                this.savePost(postElement);
            } else if (e.target.matches('.more-options-btn')) {
                this.showPostOptions(postElement, e.target);
            } else if (e.target.matches('.report-btn')) {
                this.reportPost(postElement);
            } else if (e.target.matches('.edit-post-btn')) {
                this.editPost(postElement);
            } else if (e.target.matches('.delete-post-btn')) {
                this.deletePost(postElement);
            } else if (e.target.matches('.load-more-comments')) {
                this.loadMoreComments(postElement);
            }
        });

        // Soumission de commentaires
        document.addEventListener('submit', (e) => {
            if (e.target.matches('.comment-form')) {
                e.preventDefault();
                this.submitComment(e.target);
            }
        });

        // Double-clic pour liker (comme Instagram)
        document.addEventListener('dblclick', (e) => {
            const postContent = e.target.closest('.post-content');
            if (postContent) {
                const postElement = postContent.closest('.post-item');
                this.quickLike(postElement);
            }
        });

        // Notification de nouveaux posts
        // document.addEventListener('click', (e) => {
        //     if (e.target.matches('.new-posts-notification')) {
        //         this.showNewPosts();
        //     }
        // });

        // Scroll pour masquer/afficher la barre de navigation
        let lastScrollTop = 0;
        window.addEventListener('scroll', utils.throttle(() => {
            const scrollTop = window.pageYOffset;
            const navbar = document.querySelector('.navbar');

            if (navbar) {
                if (scrollTop > lastScrollTop && scrollTop > 100) {
                    navbar.classList.add('hidden');
                } else {
                    navbar.classList.remove('hidden');
                }
            }
            lastScrollTop = scrollTop;
        }, 100));
    }

    setupPostForm() {
        if (!this.postForm) return;

        const textarea = this.postForm.querySelector('.post-textarea');
        const submitBtn = this.postForm.querySelector('.post-submit-btn');
        // Sélectionnez soit par ID soit par classe
        const imageInput = document.getElementById('media-file-input') ||
            this.postForm.querySelector('.post-image-input');
        const imagePreview = this.postForm.querySelector('.image-preview');

        // Vérification de l'existence des éléments
        if (!textarea || !submitBtn || !imageInput) {
            console.error('Éléments du formulaire introuvables');
            return;
        }

        // Connectez le bouton photo
        const photoBtn = this.postForm.querySelector('#photo-btn');


        if (photoBtn && imageInput) {
            photoBtn.addEventListener('click', () => {
                imageInput.click();
            });
        }

        // Auto-resize du textarea
        if (textarea) {
            textarea.addEventListener('input', () => {
                this.autoResizeTextarea(textarea);
                this.updateSubmitButton();
            });

            // Placeholder dynamique
            textarea.addEventListener('focus', () => {
                textarea.placeholder = 'Exprimez-vous...';
            });

            textarea.addEventListener('blur', () => {
                if (!textarea.value.trim()) {
                    textarea.placeholder = 'Que voulez-vous partager ?';
                }
            });
        }

        // Upload d'images
        if (imageInput) {
            imageInput.addEventListener('change', (e) => {
                this.handleImageUpload(e.target.files, imagePreview);
            });
        }



        // Drag & Drop pour les images
        if (textarea) {
            textarea.addEventListener('dragover', (e) => {
                e.preventDefault();
                textarea.classList.add('drag-over');
            });

            textarea.addEventListener('dragleave', () => {
                textarea.classList.remove('drag-over');
            });

            textarea.addEventListener('drop', (e) => {
                e.preventDefault();
                textarea.classList.remove('drag-over');
                this.handleImageUpload(e.dataTransfer.files, imagePreview);
            });
        }
        // Gestion des émojis
        const emojiBtn = this.postForm.querySelector('#emoji-btn');
        const emojiModal = document.getElementById('emoji-modal');
        if (emojiBtn && emojiModal && textarea) {
            // Ouvrir le modal
            emojiBtn.addEventListener('click', () => {
                emojiModal.style.display = 'block';
                this.loadEmojis('smileys'); // Charger par défaut les smileys
            });

            // Fermer le modal
            emojiModal.querySelector('.close-emoji-modal').addEventListener('click', () => {
                emojiModal.style.display = 'none';
            });

            // Changer de catégorie
            emojiModal.querySelectorAll('.emoji-categories button').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.loadEmojis(btn.dataset.category);
                });
            });
        }

        // Gestion de la localisation
        const locationBtn = this.postForm.querySelector('#location-btn');
        const locationModal = document.getElementById('location-modal');

        if (locationBtn && locationModal) {
            // Ouvrir le modal
            locationBtn.addEventListener('click', () => {
                locationModal.style.display = 'block';
            });

            // Fermer le modal
            locationModal.querySelector('.close-location-modal').addEventListener('click', () => {
                locationModal.style.display = 'none';
            });

            // Recherche de lieux
            const locationSearch = locationModal.querySelector('#location-search');
            locationSearch.addEventListener('input', utils.debounce(() => {
                this.searchLocations(locationSearch.value);
            }, 300));
        }
        // Soumission du formulaire
        this.postForm.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitNewPost();
        });
    }

    searchLocations(query) {
        const locationResults = document.getElementById('location-results');
        if (!locationResults || !query.trim()) {
            locationResults.innerHTML = '';
            return;
        }

        // Exemple avec l'API Nominatim (OpenStreetMap)
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(locations => {
                locationResults.innerHTML = locations.slice(0, 5).map(location => `
                <div class="location-item" data-lat="${location.lat}" data-lon="${location.lon}">
                    <strong>${location.display_name}</strong>
                </div>
            `).join('');

                // Sélection d'un lieu
                locationResults.querySelectorAll('.location-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const textarea = this.postForm.querySelector('.post-textarea');
                        if (textarea) {
                            textarea.value += `\n[Localisation: ${item.textContent}]`;
                            this.autoResizeTextarea(textarea);
                        }
                        document.getElementById('location-modal').style.display = 'none';
                    });
                });
            })
            .catch(error => {
                console.error('Erreur recherche lieu:', error);
                locationResults.innerHTML = '<p>Erreur lors de la recherche</p>';
            });
    }
    loadEmojis(category) {
        const emojiList = document.getElementById('emoji-list');
        if (!emojiList) return;

        // Exemple simplifié - en pratique, utiliser une librairie ou une API d'émojis
        const emojis = {
            smileys: [
                '😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','😘','😗','😙','😚','🤗',
                '🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','😴','😌','😛','😜','😝',
                '🤤','😒','😓','😔','😕','🙃','🤑','😲','☹','🙁','😖','😞','😟','😤','😢','😭','😦','😧','😨','😩',
                '🤯','😬','😰','😱','🥵','🥶','😳','🤪','😵','😠','😡','🤬','😷','🤒','🤕','🤢','🤮','🤧','😇','🥳',
                '🥴','🥺','🤠','🤡','🤥','🤫','🤭','🧐','😈','👿','👹','👺','💀','☠','👻','👽','👾','🤖','😺',
                '😸','😹','😻','😼','😽','🙀','😿','😾'
            ],

            animals: [
                '🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🦍','🦧','🐔','🐧','🐦',
                '🐤','🐣','🐥','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🐛','🦋','🐌','🐞','🐜','🦟','🦠','🐢',
                '🐍','🦎','🦂','🦞','🦀','🐙','🐡','🐠','🐟','🐬','🐳','🐋','🦈','🐊','🐅','🐆','🦓','🦍','🦧','🦣',
                '🦛','🦏','🐘','🦒','🦘','🐐','🐏','🐑','🐎','🐖','🐀','🐁','🐓','🦃','🕊','🦤','🦚','🦜','🦢','🦩',
                '🐇','🦝','🦨','🦡','🦦','🦥','🐾','🪰','🪱','🪲','🪳','🪴','🦫','🪶','🪹','🪺','🐉','🐲','🕷','🕸'
            ],

            food: [
                '🍏','🍎','🍐','🍊','🍋','🍌','🍉','🍇','🍓','🫐','🍈','🍒','🍑','🥭','🍍','🥥','🥝','🍅','🍆','🥑',
                '🥦','🥬','🥒','🌶','🫑','🌽','🥕','🫒','🧄','🧅','🥔','🍠','🥐','🥯','🍞','🥖','🥨','🧀','🥚','🍳',
                '🧈','🥞','🧇','🥓','🥩','🍗','🍖','🦴','🌭','🍔','🍟','🍕','🫓','🥪','🥙','🧆','🌮','🌯','🫔','🥗',
                '🍝','🍜','🍲','🍛','🍣','🍱','🥟','🦪','🍤','🍙','🍚','🍘','🍥','🥠','🥮','🍢','🍡','🍧','🍨','🍦',
                '🥧','🧁','🍰','🎂','🍮','🍭','🍬','🍫','🍿','🧃','🥤','🧋','🧉','🧊','🍵','☕','🫖','🍶','🍺','🍻'
            ],

            travel: [
                '🚗','🚕','🚙','🚌','🚎','🏎','🚓','🚑','🚒','🚐','🛻','🚚','🚛','🚜','🛴','🚲','🛵','🏍','🛺','🚔',
                '🚍','🚘','🚖','🚡','🚠','🚟','🚃','🚋','🚞','🚝','🚄','🚅','🚈','🚂','🚆','🚇','🚊','🚉','✈','🛫',
                '🛬','🛩','💺','🛰','🚀','🛸','🚁','🛶','⛵','🚤','🛥','🛳','⛴','🚢','⚓','🪝','⛽','🚧','🚦','🚥',
                '🏁','🚏','🗺','🗿','🗽','🗼','🏰','🏯','🏟','🎡','🎢','🎠','⛲','⛱','🏖','🏝','🌋','⛰','🏔','🗻','🏕',
                '⛺','🏠','🏡','🏘','🏚','🏗','🏢','🏬','🏣','🏤','🏥','🏦','🏨','🏪','🏫','🏛','⛪','🕌','🛕','🕍'
            ]
        };


        emojiList.innerHTML = emojis[category].map(emoji => `
        <span class="emoji-item">${emoji}</span>
    `).join('');

        // Insérer émoji dans le textarea
        emojiList.querySelectorAll('.emoji-item').forEach(emoji => {
            emoji.addEventListener('click', () => {
                const textarea = this.postForm.querySelector('.post-textarea');
                if (textarea) {
                    textarea.value += emoji.textContent;
                    textarea.focus();
                    this.autoResizeTextarea(textarea);
                    this.updateSubmitButton();
                }
            });
        });
    }

    async loadInitialPosts() {
        if (this.isLoading) return;

        this.isLoading = true;
        this.showFeedLoading();

        try {
            const response = await apiRequest('/posts.php?action=feed&page=1&limit=10');
            if (!response || !response.success) {
                throw new Error(response?.message || 'Erreur inconnue');
            }
            if (response.success) {
                this.renderPosts(response.posts);
                this.hasMorePosts = response.has_more;
                this.lastPostId = response.last_id || 0;
            } else {
                this.showFeedError('Erreur lors du chargement du fil');
            }
        } catch (error) {
            console.error('Erreur chargement feed:', error);
            this.showFeedError('Erreur de connexion');
            this.showFeedError(error.message);
        } finally {
            this.isLoading = false;
        }
    }

    renderPosts(posts) {
        if (!this.feedContainer) return;

        if (posts.length === 0 && this.currentPage === 1) {
            this.showEmptyFeed();
            return;
        }

        const fragment = document.createDocumentFragment();

        posts.forEach(post => {
            if (!this.posts.has(post.id)) {
                const postElement = this.createPostElement(post);
                fragment.appendChild(postElement);
                this.posts.set(post.id, post);
            }
        });

        if (this.currentPage === 1) {
            this.feedContainer.innerHTML = '';
        }

        this.feedContainer.appendChild(fragment);
        this.setupPostInteractions();
    }

    createPostElement(post) {
        const postDiv = document.createElement('div');
        postDiv.className = 'post-item';
        postDiv.dataset.postId = post.id;

        const isLiked = post.user_liked || false;
        const isSaved = post.user_saved || false;

        postDiv.innerHTML = `
    <div class="post-header">
        <div class="post-author">
            <img src="uploads/avatars/${post.author.avatar || 'default-avatar.svg'}" 
                 alt="${post.author.name}" class="post-author-avatar">
            <div class="post-author-info">
                <h4 class="post-author-name">
                    <a href="profile.php?id=${post.author.id}">${escapeHtml(post.author.name)}</a>
                </h4>
                <div class="post-meta">
                    <span class="post-time" data-time="${post.created_at}">
                        ${formatTimeAgo(post.created_at)}
                    </span>
                    ${post.location ? `
                        <span class="post-location">
                            <i class="fas fa-map-marker-alt"></i>
                            ${escapeHtml(post.location)}
                        </span>
                    ` : ''}
                </div>
            </div>
        </div>
        <div class="post-actions">
        <button class="more-options-btn" title="Plus d'options" data-post-id="${post.id}">
            <i class="fas fa-ellipsis-h"></i>
        </button>
        <div class="post-options-menu" id="options-menu-${post.id}">
            ${post.author.id === window.currentUser?.id ? `
                <button class="post-option-item edit-post-btn" data-post-id="${post.id}">
                    <i class="fas fa-edit"></i> Modifier
                </button>
                <button class="post-option-item delete-post-btn" data-post-id="${post.id}">
                    <i class="fas fa-trash"></i> Supprimer
                </button>
            ` : ''}
            <button class="post-option-item report-btn" data-post-id="${post.id}">
                <i class="fas fa-flag"></i> Signaler
            </button>
            <button class="post-option-item copy-link-btn" data-post-id="${post.id}">
                <i class="fas fa-link"></i> Copier le lien
            </button>
        </div>
    </div>
    </div>
    
    <div class="post-content">
        ${post.content ? `
            <div class="post-text">
                ${this.formatPostContent(post.content)}
            </div>
        ` : ''}
        
        ${post.images && post.images.length > 0 ? `
            <div class="post-images ${post.images.length > 1 ? 'multiple-images' : ''}">
                ${post.images.map((image, index) => `
                    <img src="${image.url}" alt="Image ${index + 1}" 
                         class="post-image" 
                         data-expandable
                         onclick="feedManager.openImageModal('${image.url}', '${image.alt || ''}')">
                `).join('')}
            </div>
        ` : ''}
        
        ${post.video ? `
            <div class="post-video">
                <video controls preload="metadata">
                    <source src="${post.video.url}" type="${post.video.type}">
                    Votre navigateur ne supporte pas les vidéos HTML5.
                </video>
            </div>
        ` : ''}
        
        ${post.shared_post ? `
            <div class="shared-post">
                <div class="shared-post-header">
                    <i class="fas fa-share"></i>
                    <span>Publication partagée</span>
                </div>
                ${this.createPostElement(post.shared_post).innerHTML}
            </div>
        ` : ''}
    </div>
    
    <div class="post-stats">
        <div class="post-reactions">
            ${post.likes_count > 0 ? `
                <span class="reaction-count">
                    <i class="fas fa-heart"></i>
                    ${post.likes_count} ${post.likes_count === 1 ? 'j\'aime' : 'j\'aime'}
                </span>
            ` : ''}
            ${post.comments_count > 0 ? `
                <span class="comment-count">
                    ${post.comments_count} ${post.comments_count === 1 ? 'commentaire' : 'commentaires'}
                </span>
            ` : ''}
            ${post.shares_count > 0 ? `
                <span class="share-count">
                    ${post.shares_count} ${post.shares_count === 1 ? 'partage' : 'partages'}
                </span>
            ` : ''}
        </div>
    </div>
    
    <div class="post-buttons">
        <button class="post-btn like-btn ${isLiked ? 'liked' : ''}" data-action="like">
            <i class="fas fa-heart"></i>
            <span>J'aime</span>
        </button>
        <button class="post-btn comment-btn" data-action="comment">
            <i class="fas fa-comment"></i>
            <span>Commenter</span>
        </button>
        <button class="post-btn share-btn" data-action="share">
            <i class="fas fa-share"></i>
            <span>Partager</span>
        </button>
        <button class="post-btn save-btn ${isSaved ? 'saved' : ''}" data-action="save">
            <i class="fas fa-bookmark"></i>
            <span>${isSaved ? 'Sauvegardé' : 'Sauvegarder'}</span>
        </button>
    </div>
    
    <div class="post-comments" style="display: none;">
        <div class="comments-container">
            ${post.recent_comments ? post.recent_comments.map(comment =>
            this.createCommentElement(comment)
        ).join('') : ''}
            ${post.comments_count > 3 ? `
                <button class="load-more-comments">
                    Voir les ${post.comments_count - 3} autres commentaires
                </button>
            ` : ''}
        </div>
        
        <form class="comment-form">
            <img src="uploads/avatars/${window.currentUser?.avatar || 'default-avatar.svg'}" 
                 alt="Votre avatar" class="comment-avatar">
            <div class="comment-input-container">
                <input type="text" name="content" placeholder="Ajoutez un commentaire..." 
                       class="comment-input" autocomplete="off">
                <button type="submit" class="comment-submit">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>
    </div>
`;

        return postDiv;
    }

    createCommentElement(comment) {
        // Vérification de l'existence de l'auteur
        const author = comment.author || {
            id: 0,
            name: 'Utilisateur inconnu',
            avatar: 'default-avatar.svg'
        };

        return `
        <div class="comment-item" data-comment-id="${comment.id}">
            <img src="uploads/avatars/${author.avatar}" 
                 alt="${author.name}" class="comment-avatar">
            <div class="comment-content">
                <div class="comment-bubble">
                    <h5 class="comment-author">
                        <a href="profile.php?id=${author.id}">${escapeHtml(author.name)}</a>
                    </h5>
                    <p class="comment-text">${this.formatPostContent(comment.content)}</p>
                </div>
                <div class="comment-actions">
                    <button class="comment-like-btn ${comment.user_liked ? 'liked' : ''}" 
                            data-comment-id="${comment.id}">
                        J'aime
                    </button>
                    <button class="comment-reply-btn" data-comment-id="${comment.id}">
                        Répondre
                    </button>
                    <span class="comment-time">${formatTimeAgo(comment.created_at)}</span>
                    ${comment.likes_count > 0 ? `
                        <span class="comment-likes">${comment.likes_count} j'aime</span>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
    }

    formatPostContent(content) {
        return escapeHtml(content)
            .replace(/\n/g, '<br>')
            .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')
            .replace(/@(\w+)/g, '<span class="mention">@$1</span>')
            .replace(/#(\w+)/g, '<a href="/search.php?q=%23$1" class="hashtag">#$1</a>');
    }

    async toggleLike(postElement) {
        const postId = postElement.dataset.postId;
        const likeBtn = postElement.querySelector('.like-btn');
        const isLiked = likeBtn.classList.contains('liked');

        // Animation optimiste
        likeBtn.classList.toggle('liked');
        this.animateLike(likeBtn, !isLiked);

        try {
            const response = await apiRequest('/posts.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: isLiked ? 'unlike' : 'like',
                    post_id: postId
                })
            });

            if (response.success) {
                this.updatePostStats(postElement, 'likes', response.likes_count);
            } else {
                // Annuler l'animation optimiste
                likeBtn.classList.toggle('liked');
                showToast('Erreur lors du like', 'error');
            }
        } catch (error) {
            likeBtn.classList.toggle('liked');
            showToast('Erreur de connexion', 'error');
        }
    }

    quickLike(postElement) {
        const likeBtn = postElement.querySelector('.like-btn');
        if (!likeBtn.classList.contains('liked')) {
            this.toggleLike(postElement);
            this.showLikeAnimation(postElement);
        }
    }

    showLikeAnimation(postElement) {
        const heart = document.createElement('div');
        heart.className = 'like-animation';
        heart.innerHTML = '<i class="fas fa-heart"></i>';

        const content = postElement.querySelector('.post-content');
        content.style.position = 'relative';
        content.appendChild(heart);

        setTimeout(() => {
            heart.remove();
        }, 1000);
    }

    animateLike(button, isLiked) {
        const icon = button.querySelector('i');

        icon.style.transform = 'scale(1.3)';
        icon.style.color = isLiked ? '#e91e63' : '';

        setTimeout(() => {
            icon.style.transform = 'scale(1)';
        }, 150);
    }

    toggleComments(postElement) {
        const commentsSection = postElement.querySelector('.post-comments');
        const isVisible = commentsSection.style.display !== 'none';

        if (isVisible) {
            commentsSection.style.display = 'none';
        } else {
            commentsSection.style.display = 'block';
            const commentInput = commentsSection.querySelector('.comment-input');
            commentInput.focus();
        }
    }

    async submitComment(form) {
        const postElement = form.closest('.post-item');
        const postId = postElement.dataset.postId;
        const input = form.querySelector('.comment-input');
        const content = input.value.trim();

        if (!content) return;

        const submitBtn = form.querySelector('.comment-submit');
        submitBtn.disabled = true;

        try {
            const response = await apiRequest('/posts.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'comment',
                    post_id: postId,
                    content: content
                })
            });

            if (response.success) {
                input.value = '';
                this.addCommentToPost(postElement, response.comment);
                this.updatePostStats(postElement, 'comments', response.comments_count);
            } else {
                showToast(response.message || 'Erreur lors du commentaire', 'error');
            }
        } catch (error) {
            showToast('Erreur de connexion', 'error');
        } finally {
            submitBtn.disabled = false;
        }
    }

    addCommentToPost(postElement, comment) {
        const commentsContainer = postElement.querySelector('.comments-container');
        const commentElement = document.createElement('div');
        commentElement.innerHTML = this.createCommentElement(comment);

        commentsContainer.appendChild(commentElement.firstElementChild);
    }

    async sharePost(postElement) {
        const postId = postElement.dataset.postId;

        try {
            const response = await apiRequest('/posts.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'share',
                    post_id: postId
                })
            });

            if (response.success) {
                this.updatePostStats(postElement, 'shares', response.shares_count);
                showToast('Publication partagée', 'success');
            } else {
                showToast('Erreur lors du partage', 'error');
            }
        } catch (error) {
            showToast('Erreur de connexion', 'error');
        }
    }

    async savePost(postElement) {
        const postId = postElement.dataset.postId;
        const saveBtn = postElement.querySelector('.save-btn');
        const isSaved = saveBtn.classList.contains('saved');

        try {
            const response = await apiRequest('/posts.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: isSaved ? 'unsave' : 'save',
                    post_id: postId
                })
            });

            if (response.success) {
                saveBtn.classList.toggle('saved');
                const span = saveBtn.querySelector('span');
                span.textContent = isSaved ? 'Sauvegarder' : 'Sauvegardé';
                showToast(isSaved ? 'Supprimé des favoris' : 'Ajouté aux favoris', 'success');
            }
        } catch (error) {
            showToast('Erreur de connexion', 'error');
        }
    }

    updatePostStats(postElement, type, count) {
        const selector = type === 'likes' ? '.reaction-count' :
            type === 'comments' ? '.comment-count' : '.share-count';

        const statElement = postElement.querySelector(selector);

        if (count > 0) {
            if (!statElement) {
                const statsContainer = postElement.querySelector('.post-reactions');
                const newStat = document.createElement('span');
                newStat.className = type === 'likes' ? 'reaction-count' :
                    type === 'comments' ? 'comment-count' : 'share-count';

                if (type === 'likes') {
                    newStat.innerHTML = `<i class="fas fa-heart"></i> ${count} j'aime`;
                } else if (type === 'comments') {
                    newStat.textContent = `${count} ${count === 1 ? 'commentaire' : 'commentaires'}`;
                } else {
                    newStat.textContent = `${count} ${count === 1 ? 'partage' : 'partages'}`;
                }

                statsContainer.appendChild(newStat);
            } else {
                if (type === 'likes') {
                    statElement.innerHTML = `<i class="fas fa-heart"></i> ${count} j'aime`;
                } else if (type === 'comments') {
                    statElement.textContent = `${count} ${count === 1 ? 'commentaire' : 'commentaires'}`;
                } else {
                    statElement.textContent = `${count} ${count === 1 ? 'partage' : 'partages'}`;
                }
            }
        } else if (statElement) {
            statElement.remove();
        }
    }

    async submitNewPost() {


        const privacy = postForm.dataset.privacy;

        const textarea = this.postForm.querySelector('.post-textarea');
        const imageInput = document.getElementById('media-file-input');
        const submitBtn = this.postForm.querySelector('.post-submit-btn');

        const content = textarea.value.trim();
        const encodedContent = encodeURIComponent(content);
        const images = imageInput.files;

        if (!content && images.length === 0) {
            showToast('Veuillez ajouter du texte ou un média', 'warning');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Publication...';

        try {
            const formData = new FormData();
            formData.append('action', 'create'); // Important pour le routeur PHP
            formData.append('content', encodedContent);
            formData.append('privacy', privacy);

            // Ajouter chaque image individuellement
            for (let i = 0; i < images.length; i++) {
                formData.append(`images[${i}]`, images[i]);
            }

            const response = await fetch('api/posts.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': window.app.csrfToken
                    // NE PAS mettre Content-Type pour FormData, le navigateur le fera automatiquement
                },
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                textarea.value = '';
                imageInput.value = '';
                this.clearImagePreview();
                this.prependNewPost(result.post);
                showToast('Publication créée!', 'success');
            } else {
                throw new Error(result.message || 'Erreur lors de la publication');
            }
        } catch (error) {
            console.error('Erreur:', error);
            showToast(error.message || 'Échec de la publication', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Publier';
        }
    }

    prependNewPost(post) {
        if (!this.feedContainer) return;

        const postElement = this.createPostElement(post);
        this.feedContainer.insertBefore(postElement, this.feedContainer.firstChild);
        this.posts.set(post.id, post);

        // Animation d'apparition
        postElement.style.opacity = '0';
        postElement.style.transform = 'translateY(-20px)';

        setTimeout(() => {
            postElement.style.transition = 'all 0.3s ease';
            postElement.style.opacity = '1';
            postElement.style.transform = 'translateY(0)';
        }, 10);
    }

    setupInfiniteScroll() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && this.hasMorePosts && !this.isLoading) {
                    this.loadMorePosts();
                }
            });
        });

        // Observer le dernier post
        const sentinel = document.createElement('div');
        sentinel.className = 'scroll-sentinel';
        sentinel.style.height = '1px';

        if (this.feedContainer) {
            this.feedContainer.appendChild(sentinel);
            observer.observe(sentinel);
        }
    }

    async loadMorePosts() {
        if (this.isLoading || !this.hasMorePosts) return;

        this.isLoading = true;
        this.currentPage++;

        try {
            const response = await apiRequest(`/posts.php?action=feed&page=${this.currentPage}&last_id=${this.lastPostId}&limit=10`);

            if (response.success) {
                this.renderPosts(response.posts);
                this.hasMorePosts = response.has_more;
                this.lastPostId = response.last_id || this.lastPostId;
            }
        } catch (error) {
            console.error('Erreur chargement posts:', error);
            this.currentPage--; // Revenir à la page précédente
        } finally {
            this.isLoading = false;
        }
    }

    startAutoRefresh() {
        // Vérifier les nouveaux posts toutes les 30 secondes
        this.refreshInterval = setInterval(async () => {
            if (!document.hidden) {
                await this.checkForNewPosts();
            }
        }, 30000);
    }

    async checkForNewPosts() {
        try {
            const response = await apiRequest(`/posts.php?action=check_new&since_id=${this.lastPostId}`);

            if (response.success && response.new_posts.length > 0) {
                this.newPostsBuffer = response.new_posts;
                this.showNewPostsNotification(response.new_posts.length);
            }
        } catch (error) {
            // Silencieux
        }
    }

    showNewPostsNotification(count) {
        let notification = document.querySelector('.new-posts-notification');

        if (!notification) {
            notification = document.createElement('div');
            notification.className = 'new-posts-notification';
            notification.onclick = () => this.showNewPosts();
            document.body.appendChild(notification);
        }

        notification.innerHTML = `
            <i class="fas fa-arrow-up"></i>
            ${count} nouvelle${count > 1 ? 's' : ''} publication${count > 1 ? 's' : ''}
        `;
        notification.style.display = 'block';
    }

    showNewPosts() {
        if (this.newPostsBuffer.length === 0) return;

        this.newPostsBuffer.forEach(post => {
            this.prependNewPost(post);
        });

        this.newPostsBuffer = [];

        const notification = document.querySelector('.new-posts-notification');
        if (notification) {
            notification.style.display = 'none';
        }

        // Scroll vers le haut
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Méthodes utilitaires
    autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
    }

    updateSubmitButton() {
        const textarea = this.postForm.querySelector('.post-textarea');
        const submitBtn = this.postForm.querySelector('.post-submit-btn');
        const hasContent = textarea.value.trim().length > 0;

        submitBtn.disabled = !hasContent;
        submitBtn.classList.toggle('active', hasContent);
    }

    handleImageUpload(files, previewContainer) {
        if (!files.length) return;

        Array.from(files).forEach(file => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const preview = document.createElement('div');
                    preview.className = 'image-preview-item';
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Aperçu">
                        <button class="remove-image" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    previewContainer.appendChild(preview);
                };
                reader.readAsDataURL(file);
            }
        });

        previewContainer.style.display = 'block';
    }

    clearImagePreview() {
        const preview = this.postForm.querySelector('.image-preview');
        if (preview) {
            preview.innerHTML = '';
            preview.style.display = 'none';
        }
    }

    showFeedLoading() {
        if (!this.feedContainer) return;

        this.feedContainer.innerHTML = `
            <div class="feed-loading">
                <div class="loading-spinner"></div>
                <p>Chargement du fil d'actualité...</p>
            </div>
        `;
    }

    showFeedError(message) {
        if (!this.feedContainer) return;

        this.feedContainer.innerHTML = `
            <div class="feed-error">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Erreur de chargement</h3>
                <p>${escapeHtml(message)}</p>
                <button class="btn btn-primary" onclick="feedManager.loadInitialPosts()">
                    Réessayer
                </button>
            </div>
        `;
    }

    showEmptyFeed() {
        if (!this.feedContainer) return;

        this.feedContainer.innerHTML = `
            <div class="empty-feed">
                <i class="fas fa-stream"></i>
                <h3>Votre fil est vide</h3>
                <p>Commencez à suivre des utilisateurs pour voir leurs publications ici.</p>
                <a href=friends.php" class="btn btn-primary">Trouver des amis</a>
            </div>
        `;
    }

    setupPostInteractions() {
        // Réinitialiser les observers d'images
        if (window.imageManager) {
            window.imageManager.setupLazyLoading();
        }

        // Réinitialiser les timestamps
        document.querySelectorAll('[data-time]').forEach(element => {
            const timestamp = element.dataset.time;
            element.textContent = formatTimeAgo(timestamp);
        });
    }

    openImageModal(src, alt) {
        if (window.imageManager) {
            window.imageManager.openImageModal(src, alt);
        }
    }

    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}

// Styles CSS pour le feed
const feedStyles = `
<style>
.post-item {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    margin-bottom: var(--spacing-lg);
    overflow: hidden;
    transition: box-shadow var(--transition-fast);
}

.post-item:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.post-header {
    padding: var(--spacing-md);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.post-author {
    display: flex;
    gap: var(--spacing-sm);
    align-items: center;
}

.post-author-avatar {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-full);
    object-fit: cover;
}

.post-author-name {
    margin: 0;
    font-size: var(--text-base);
}

.post-author-name a {
    color: var(--text-primary);
    text-decoration: none;
    font-weight: 600;
}

.post-meta {
    display: flex;
    gap: var(--spacing-sm);
    font-size: var(--text-xs);
    color: var(--text-muted);
    margin-top: var(--spacing-xs);
}

.post-content {
    position: relative;
}

.post-text {
    padding: 0 var(--spacing-md);
    margin-bottom: var(--spacing-md);
    line-height: 1.6;
}

.post-images {
    margin-bottom: var(--spacing-md);
}

.post-images.multiple-images {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-xs);
    padding: 0 var(--spacing-md);
}

.post-image {
    width: 100%;
    max-height: 400px;
    object-fit: cover;
    cursor: pointer;
    border-radius: var(--radius-md);
}

.post-video video {
    width: 100%;
    max-height: 400px;
    border-radius: var(--radius-md);
}

.post-stats {
    padding: var(--spacing-sm) var(--spacing-md);
    border-bottom: 1px solid var(--border-light);
}

.post-reactions {
    display: flex;
    gap: var(--spacing-md);
    font-size: var(--text-sm);
    color: var(--text-muted);
}

.post-buttons {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    border-bottom: 1px solid var(--border-light);
}

.post-btn {
    padding: var(--spacing-md);
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all var(--transition-fast);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-xs);
    font-size: var(--text-sm);
}

.post-btn:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.post-btn.liked {
    color: var(--danger-color);
}

.post-btn.saved {
    color: var(--primary-color);
}

.post-comments {
    padding: var(--spacing-md);
}

.comment-item {
    display: flex;
    gap: var(--spacing-sm);
    margin-bottom: var(--spacing-md);
}

.comment-avatar {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-full);
    flex-shrink: 0;
}

.comment-content {
    flex: 1;
}

.comment-bubble {
    background: var(--bg-secondary);
    padding: var(--spacing-sm);
    border-radius: var(--radius-md);
}

.comment-author {
    margin: 0 0 var(--spacing-xs) 0;
    font-size: var(--text-sm);
    font-weight: 600;
}

.comment-text {
    margin: 0;
    font-size: var(--text-sm);
}

.comment-actions {
    margin-top: var(--spacing-xs);
    display: flex;
    gap: var(--spacing-md);
    font-size: var(--text-xs);
}

.comment-actions button {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: var(--text-xs);
}

.comment-form {
    display: flex;
    gap: var(--spacing-sm);
    align-items: flex-end;
    margin-top: var(--spacing-md);
}

.comment-input-container {
    flex: 1;
    display: flex;
    align-items: center;
    background: var(--bg-secondary);
    border-radius: var(--radius-full);
    padding: var(--spacing-xs) var(--spacing-md);
}

.comment-input {
    flex: 1;
    background: none;
    border: none;
    outline: none;
    padding: var(--spacing-xs) 0;
    font-size: var(--text-sm);
    color: var(--text-primary);
}

.comment-submit {
    background: none;
    border: none;
    color: var(--primary-color);
    cursor: pointer;
    padding: var(--spacing-xs);
    border-radius: var(--radius-full);
}

.like-animation {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 4rem;
    color: var(--danger-color);
    pointer-events: none;
    animation: likeHeart 1s ease-out forwards;
}

@keyframes likeHeart {
    0% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.5);
    }
    50% {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1.2);
    }
    100% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(1.5);
    }
}

.new-posts-notification {
    position: fixed;
    top: 80px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--primary-color);
    color: white;
    padding: var(--spacing-sm) var(--spacing-lg);
    border-radius: var(--radius-full);
    cursor: pointer;
    z-index: 1000;
    display: none;
    animation: slideDown 0.3s ease;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

@keyframes slideDown {
    from {
        transform: translateX(-50%) translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }
}

.feed-loading,
.feed-error,
.empty-feed {
    text-align: center;
    padding: var(--spacing-xl);
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--border-color);
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto var(--spacing-md);
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.mention {
    color: var(--primary-color);
    font-weight: 600;
}

.hashtag {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
}

.hashtag:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .post-buttons {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .post-btn span {
        display: none;
    }
    
    .post-images.multiple-images {
        grid-template-columns: 1fr;
    }
}
</style>
`;

// Injecter les styles
document.head.insertAdjacentHTML('beforeend', feedStyles);

// Initialisation
let feedManager;
document.addEventListener('DOMContentLoaded', () => {
    if (document.body.classList.contains('feed-page')) {
        feedManager = new FeedManager();
        window.feedManager = feedManager;
    }
});

// Nettoyage lors du départ de la page
window.addEventListener('beforeunload', () => {
    if (feedManager) {
        feedManager.destroy();
    }
});
