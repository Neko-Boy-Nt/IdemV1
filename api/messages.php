<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::start();
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Connexion requise']);
    exit;
}

$db = initDatabase();
$userId = SessionManager::getUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        case 'PUT':
            handlePutRequest();
            break;
        case 'DELETE':
            handleDeleteRequest();
            break;
        default:
            throw new Exception('Méthode non autorisée');
    }
} catch (Exception $e) {
    error_log("Erreur API messages: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGetRequest() {
    global $db, $userId;
    $conversationId = intval($_GET['conversation_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);

    if (!$conversationId) throw new Exception('ID de conversation requis');

    // Vérifier si l'utilisateur est participant
    $participant = fetchOne(
        "SELECT id FROM conversation_participants WHERE conversation_id = :cid AND user_id = :uid AND is_deleted = 0",
        ['cid' => $conversationId, 'uid' => $userId]
    );
    if (!$participant) throw new Exception('Accès non autorisé');

    $messages = fetchAll(
        "SELECT m.id, m.content, m.message_type, m.file_url, m.original_filename, m.is_read, m.expires_at, m.reply_to,
                m.created_at, u.first_name, u.last_name, u.avatar,
                (SELECT COUNT(*) FROM message_reactions mr WHERE mr.message_id = m.id) as reaction_count
         FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE m.conversation_id = :cid
         ORDER BY m.created_at DESC
         LIMIT :limit OFFSET :offset",
        ['cid' => $conversationId, 'limit' => $limit, 'offset' => $offset]
    );

    // Marquer les messages comme lus
    update('messages',
        ['is_read' => 1],
        'conversation_id = :cid AND sender_id != :uid AND is_read = 0',
        ['cid' => $conversationId, 'uid' => $userId]
    );

    echo json_encode(['success' => true, 'messages' => array_reverse($messages)]);
}

function handlePostRequest() {
    global $db, $userId;
    $input = json_decode(file_get_contents('php://input'), true);
    $conversationId = intval($input['conversation_id'] ?? 0);
    $content = trim($input['content'] ?? '');
    $messageType = $input['message_type'] ?? 'text';
    $replyTo = isset($input['reply_to']) ? intval($input['reply_to']) : null;
    $expiresAt = $input['expires_at'] ?? null;

    if (!$conversationId) throw new Exception('ID de conversation requis');
    if ($messageType === 'text' && empty($content)) throw new Exception('Contenu requis');

    // Vérifier si utilisateur est participant
    $participant = fetchOne(
        "SELECT id FROM conversation_participants WHERE conversation_id = :cid AND user_id = :uid AND is_deleted = 0",
        ['cid' => $conversationId, 'uid' => $userId]
    );
    if (!$participant) throw new Exception('Accès non autorisé');

    beginTransaction();

    try {
        $data = [
            'conversation_id' => $conversationId,
            'sender_id' => $userId,
            'message_type' => $messageType,
            'content' => $content,
            'file_url' => $input['file_url'] ?? null,
            'original_filename' => $input['original_filename'] ?? null,
            'is_read' => 0,
            'expires_at' => $expiresAt,
            'reply_to' => $replyTo,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $messageId = insert('messages', $data);

        // Notifier les autres participants
        $participants = fetchAll(
            "SELECT user_id FROM conversation_participants WHERE conversation_id = :cid AND user_id != :uid AND is_deleted = 0",
            ['cid' => $conversationId, 'uid' => $userId]
        );
        foreach ($participants as $p) {
            createNotification(
                $p['user_id'],
                'new_message',
                "Nouveau message de " . fetchOne("SELECT first_name FROM users WHERE id = :id", ['id' => $userId])['first_name'],
                "/messages.php?conversation=$conversationId",
                $userId
            );
        }

        commit();
        echo json_encode(['success' => true, 'message_id' => $messageId]);
    } catch (Exception $e) {
        rollback();
        throw $e;
    }
}

function handlePutRequest() {
    global $db, $userId;
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $messageId = intval($input['message_id'] ?? 0);

    if (!$messageId) throw new Exception('ID de message requis');

    switch ($action) {
        case 'react':
            $reactionType = $input['reaction_type'] ?? 'like';
            $existing = fetchOne(
                "SELECT id FROM message_reactions WHERE message_id = :mid AND user_id = :uid",
                ['mid' => $messageId, 'uid' => $userId]
            );
            if ($existing) {
                update('message_reactions',
                    ['reaction_type' => $reactionType],
                    'id = :id',
                    ['id' => $existing['id']]
                );
            } else {
                insert('message_reactions', [
                    'message_id' => $messageId,
                    'user_id' => $userId,
                    'reaction_type' => $reactionType,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'Réaction ajoutée']);
            break;

        case 'read':
            insert('message_reads', [
                'message_id' => $messageId,
                'user_id' => $userId,
                'read_at' => date('Y-m-d H:i:s')
            ]);
            update('messages',
                ['is_read' => 1],
                'id = :mid AND sender_id != :uid',
                ['mid' => $messageId, 'uid' => $userId]
            );
            echo json_encode(['success' => true, 'message' => 'Message marqué comme lu']);
            break;

        default:
            throw new Exception('Action non reconnue');
    }
}

function handleDeleteRequest() {
    global $db, $userId;
    $messageId = intval($_GET['id'] ?? 0);
    if (!$messageId) throw new Exception('ID de message requis');

    $message = fetchOne(
        "SELECT conversation_id FROM messages WHERE id = :id",
        ['id' => $messageId]
    );
    if (!$message) throw new Exception('Message non trouvé');

    $participant = fetchOne(
        "SELECT id FROM conversation_participants WHERE conversation_id = :cid AND user_id = :uid AND is_deleted = 0",
        ['cid' => $message['conversation_id'], 'uid' => $userId]
    );
    if (!$participant) throw new Exception('Accès non autorisé');

    insert('message_deletions', [
        'message_id' => $messageId,
        'user_id' => $userId,
        'deleted_at' => date('Y-m-d H:i:s')
    ]);

    echo json_encode(['success' => true, 'message' => 'Message supprimé']);
}