<?php
/**
 * Page de messagerie temps réel IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

SessionManager::requireLogin();

$pageTitle = "Messagerie";
$pageDescription = "Communiquez avec vos amis en temps réel";
$bodyClass = "messages-page";

$db = initDatabase();
$currentUser = SessionManager::getCurrentUser();

include 'includes/header.php';
?>

    <style>
        /* Ajouts CSS personnalisés pour compléter les fonctionnalités */
        /* Messages temporaires */
        .message.temporary {
            border-left: 4px solid var(--warning-color);
        }

        /* Statut lu/non lu */
        .message-status.read {
            color: var(--primary-color);
        }

        /* Indicateur de frappe */
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--text-muted);
            font-size: 0.8em;
            padding: 4px 12px;
        }

        .typing-dots {
            display: flex;
            gap: 2px;
        }

        .typing-dots span {
            width: 6px;
            height: 6px;
            background: var(--text-muted);
            border-radius: 50%;
            animation: typing 1s infinite ease-in-out;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        /* Messages de groupe : afficher expéditeur */
        .message.received.group .sender-name {
            font-size: 0.8em;
            color: var(--primary-color);
            margin-bottom: 2px;
        }

        /* Messages supprimés */
        .message.deleted {
            color: var(--text-muted);
            font-style: italic;
        }

        /* Aperçu fichiers pour vidéos/audio */
        .file-preview video,
        .file-preview audio {
            max-width: 200px;
            max-height: 100px;
        }

        /* Modal groupe */
        .group-creation {
            padding: 16px;
        }

        .group-members-select {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>

    <div class="messages-container">
        <div class="messages-layout">
            <!-- Liste des conversations -->
            <aside class="conversations-sidebar">
                <div class="sidebar-header">
                    <h2>Messages</h2>
                    <button type="button" class="btn-icon" id="new-conversation-btn" title="Nouvelle conversation">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>

                <div class="search-conversations">
                    <div class="search-input-group">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Rechercher des conversations..." id="conversations-search">
                    </div>
                </div>

                <div class="conversations-list" id="conversations-list">
                    <!-- Conversations chargées dynamiquement via JS -->
                </div>
            </aside>

            <!-- Zone de conversation active -->
            <main class="conversation-main">
                <div class="conversation-empty" id="conversation-empty">
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>Sélectionnez une conversation</h3>
                        <p>Choisissez une conversation existante ou commencez une nouvelle discussion</p>
                        <button type="button" class="btn btn-primary" id="start-conversation-btn">
                            Nouvelle conversation
                        </button>
                    </div>
                </div>

                <div class="conversation-active" id="conversation-active" style="display: none;">
                    <div class="conversation-header">
                        <div class="conversation-info">
                            <img src="" alt="Avatar" class="conversation-avatar" id="active-conversation-avatar">
                            <div class="conversation-details">
                                <h3 class="conversation-name" id="active-conversation-name"></h3>
                                <p class="conversation-status" id="active-conversation-status"></p>
                                <div class="typing-indicator" id="typing-indicator" style="display: none;">
                                <span class="typing-dots">
                                    <span></span><span></span><span></span>
                                </span>
                                    <span class="typing-text">En train d'écrire...</span>
                                </div>
                            </div>
                        </div>

                        <div class="conversation-actions">
                            <button type="button" class="btn-icon" title="Appel audio" id="audio-call-btn">
                                <i class="fas fa-phone"></i>
                            </button>
                            <button type="button" class="btn-icon" title="Appel vidéo" id="video-call-btn">
                                <i class="fas fa-video"></i>
                            </button>
                            <button type="button" class="btn-icon" title="Informations" id="conversation-info-btn">
                                <i class="fas fa-info-circle"></i>
                            </button>
                            <button type="button" class="btn-icon" title="Plus d'options" id="conversation-menu-btn">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                    </div>

                    <div class="messages-container-main" id="messages-container-main">
                        <!-- Messages chargés dynamiquement via JS -->
                    </div>

                    <div class="message-compose" data-drop-zone="files">
                        <div class="message-attachments" id="message-attachments" style="display: none;">
                            <!-- Pièces jointes affichées ici -->
                        </div>

                        <div class="message-input-group">
                            <button type="button" class="message-attachment-btn" id="attach-files-btn" title="Joindre un fichier">
                                <i class="fas fa-paperclip"></i>
                            </button>

                            <div class="message-input-container">
                            <textarea
                                    class="message-input"
                                    id="message-input"
                                    placeholder="Tapez votre message..."
                                    rows="1"></textarea>
                                <button type="button" class="emoji-btn" id="emoji-btn" title="Emoji">
                                    <i class="fas fa-smile"></i>
                                </button>
                            </div>

                            <button type="button" class="message-send-btn" id="send-message-btn" disabled>
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>

                        <div class="message-actions">
                            <button type="button" class="message-action" id="voice-record-btn" title="Message vocal">
                                <i class="fas fa-microphone"></i>
                            </button>
                            <button type="button" class="message-action" id="quick-reaction-btn" title="Réaction rapide">
                                <i class="fas fa-heart"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Panel d'informations -->
            <aside class="conversation-info-panel" id="conversation-info-panel" style="display: none;">
                <div class="info-panel-header">
                    <h3>Informations</h3>
                    <button type="button" class="btn-icon" id="close-info-panel">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="info-panel-content">
                    <div class="participant-info">
                        <img src="" alt="Avatar" class="participant-avatar">
                        <h4 class="participant-name"></h4>
                        <p class="participant-status"></p>
                    </div>

                    <div class="info-section">
                        <h5>Médias partagés</h5>
                        <div class="shared-media" id="shared-media">
                            <!-- Médias chargés dynamiquement -->
                        </div>
                    </div>

                    <div class="info-section">
                        <h5>Actions</h5>
                        <div class="info-actions">
                            <button type="button" class="info-action" id="mute-conversation">
                                <i class="fas fa-bell-slash"></i>
                                <span>Couper les notifications</span>
                            </button>
                            <button type="button" class="info-action" id="archive-conversation">
                                <i class="fas fa-archive"></i>
                                <span>Archiver la conversation</span>
                            </button>
                            <button type="button" class="info-action danger" id="delete-conversation">
                                <i class="fas fa-trash"></i>
                                <span>Supprimer la conversation</span>
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- Modal nouvelle conversation -->
    <div class="modal" id="new-conversation-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nouvelle conversation</h3>
                <button type="button" class="modal-close" onclick="closeModal('new-conversation-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="search-users">
                    <input type="text" placeholder="Rechercher des contacts..." id="search-friends" class="form-input">
                </div>
                <div class="friends-list" id="friends-to-message">
                    <!-- Contacts chargés dynamiquement -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal création groupe (ajouté pour support groupes) -->
    <div class="modal" id="new-group-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Créer un groupe</h3>
                <button type="button" class="modal-close" onclick="closeModal('new-group-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body group-creation">
                <input type="text" placeholder="Nom du groupe" id="group-name-input" class="form-input">
                <div class="group-members-select" id="group-members-list">
                    <!-- Liste des amis à sélectionner pour le groupe -->
                </div>
                <button type="button" class="btn btn-primary" id="create-group-btn">Créer</button>
            </div>
        </div>
    </div>

    <!-- Modal emoji picker -->
    <div class="emoji-picker" id="emoji-picker" style="display: none;">
        <div class="emoji-categories">
            <button type="button" class="emoji-category active" data-category="smileys">😀</button>
            <button type="button" class="emoji-category" data-category="people">👋</button>
            <button type="button" class="emoji-category" data-category="nature">🌟</button>
            <button type="button" class="emoji-category" data-category="food">🍕</button>
            <button type="button" class="emoji-category" data-category="activities">⚽</button>
            <button type="button" class="emoji-category" data-category="travel">✈️</button>
            <button type="button" class="emoji-category" data-category="objects">📱</button>
            <button type="button" class="emoji-category" data-category="symbols">❤️</button>
        </div>
        <div class="emoji-grid" id="emoji-grid">
            <!-- Emojis chargés dynamiquement -->
        </div>
    </div>

    <!-- Lien CSS -->
    <link rel="stylesheet" href="assets/css/message-style.css">

    <!-- Scripts JS -->
    <script src="assets/js/dragdrop.js"></script>
    <script>
        const currentUser = <?php echo json_encode(SessionManager::getJsUserData()); ?>;
    </script>

<?php include 'includes/footer2.php'; ?>