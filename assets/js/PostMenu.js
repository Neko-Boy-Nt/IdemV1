class PostMenu {
    constructor() {
        this.initMenu();
        this.setupEventListeners();
    }

    initMenu() {
        // Créer le menu dans le DOM s'il n'existe pas
        if (!document.getElementById('post-options-menu-template')) {
            const menuTemplate = `
                <div id="post-options-menu-template" class="post-options-menu">
                    <button class="post-option-item edit-post-btn">
                        <i class="fas fa-edit"></i> Modifier
                    </button>
                    <button class="post-option-item delete-post-btn">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                    <button class="post-option-item report-btn">
                        <i class="fas fa-flag"></i> Signaler
                    </button>
                    <button class="post-option-item copy-link-btn">
                        <i class="fas fa-link"></i> Copier le lien
                    </button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', menuTemplate);
        }
    }

    setupEventListeners() {
        document.addEventListener('click', (e) => {
            // Gestion du bouton ⋮
            if (e.target.closest('.more-options-btn')) {
                e.preventDefault();
                const button = e.target.closest('.more-options-btn');
                const postId = button.dataset.postId;
                this.toggleMenu(button, postId);
            }
            // Fermer le menu si on clique ailleurs
            else if (!e.target.closest('.post-options-menu')) {
                this.closeAllMenus();
            }
        });
    }

    toggleMenu(button, postId) {
        // Fermer tous les menus ouverts
        this.closeAllMenus();

        // Cloner le menu template
        const menuTemplate = document.getElementById('post-options-menu-template');
        const menuClone = menuTemplate.cloneNode(true);
        menuClone.id = `post-options-menu-${postId}`;
        menuClone.classList.add('active');

        // Positionner le menu près du bouton
        const buttonRect = button.getBoundingClientRect();
        menuClone.style.position = 'absolute';
        menuClone.style.top = `${buttonRect.bottom + window.scrollY}px`;
        menuClone.style.left = `${buttonRect.left + window.scrollX}px`;

        // Ajouter le menu au DOM
        document.body.appendChild(menuClone);

        // Configurer les boutons du menu
        this.setupMenuButtons(menuClone, postId);
    }

    setupMenuButtons(menu, postId) {
        // Gérer chaque option du menu
        menu.querySelectorAll('.post-option-item').forEach(item => {
            item.dataset.postId = postId;

            item.addEventListener('click', (e) => {
                e.stopPropagation();
                this.handleMenuAction(item, postId);
                this.closeAllMenus();
            });
        });
    }

    handleMenuAction(item, postId) {
        const action = item.classList[1]; // edit-post-btn, delete-post-btn, etc.
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);

        switch(action) {
            case 'edit-post-btn':
                this.editPost(postElement);
                break;
            case 'delete-post-btn':
                this.deletePost(postElement);
                break;
            case 'report-btn':
                this.reportPost(postId);
                break;
            case 'copy-link-btn':
                this.copyPostLink(postId);
                break;
        }
    }

    editPost(postElement) {
        console.log('Édition du post', postElement.dataset.postId);
        // Implémentez la logique d'édition ici
    }

    deletePost(postElement) {
        console.log('Suppression du post', postElement.dataset.postId);
        // Implémentez la logique de suppression ici
    }

    reportPost(postId) {
        console.log('Signalement du post', postId);
        // Implémentez la logique de signalement ici
    }

    copyPostLink(postId) {
        const postLink = `${window.location.origin}/post.php?id=${postId}`;
        navigator.clipboard.writeText(postLink)
            .then(() => alert('Lien copié !'))
            .catch(err => console.error('Erreur lors de la copie :', err));
    }

    closeAllMenus() {
        document.querySelectorAll('.post-options-menu').forEach(menu => {
            if (menu.id !== 'post-options-menu-template') {
                menu.remove();
            }
        });
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    new PostMenu();
});