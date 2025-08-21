<?php
// Désactiver les avertissements pour éviter les sorties non-JSON
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Définir l'en-tête JSON
header('Content-Type: application/json; charset=UTF-8');

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
    error_log("Erreur API conversations: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function handleGetRequest() {
    global $db, $userId;
    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'list':
            $conversations = fetchAll(
                "SELECT c.id, c.type, c.name, 
                        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.is_read = 0 AND m.sender_id != :user_id1) as unread_count,
                        (SELECT m2.content FROM messages m2 WHERE m2.conversation_id = c.id ORDER BY m2.created_at DESC LIMIT 1) as last_message,
                        (SELECT m2.created_at FROM messages m2 WHERE m2.conversation_id = c.id ORDER BY m2.created_at DESC LIMIT 1) as last_message_time,
                        (SELECT u.first_name FROM users u JOIN conversation_participants cp2 ON u.id = cp2.user_id WHERE cp2.conversation_id = c.id AND cp2.user_id != :user_id2 LIMIT 1) as other_user_name,
                        (SELECT u.avatar FROM users u JOIN conversation_participants cp3 ON u.id = cp3.user_id WHERE cp3.conversation_id = c.id AND cp3.user_id != :user_id3 LIMIT 1) as other_user_avatar
                 FROM conversations c
                 JOIN conversation_participants cp ON c.id = cp.conversation_id
                 WHERE cp.user_id = :user_id4 AND cp.is_deleted = 0
                 ORDER BY last_message_time DESC",
                [
                    'user_id1' => $userId,
                    'user_id2' => $userId,
                    'user_id3' => $userId,
                    'user_id4' => $userId
                ]
            );
            echo json_encode(['success' => true, 'conversations' => $conversations]);
            break;

        case 'details':
            $conversationId = intval($_GET['id'] ?? 0);
            if (!$conversationId) throw new Exception('ID de conversation requis');
            $details = fetchOne(
                "SELECT c.id, c.type, c.name,
                        GROUP_CONCAT(u.id) as participant_ids,
                        GROUP_CONCAT(u.first_name) as participant_names
                 FROM conversations c
                 JOIN conversation_participants cp ON c.id = cp.conversation_id
                 JOIN users u ON cp.user_id = u.id
                 WHERE c.id = :id AND cp.user_id = :user_id AND cp.is_deleted = 0",
                ['id' => $conversationId, 'user_id' => $userId]
            );
            if (!$details) throw new Exception('Conversation non trouvée');
            echo json_encode(['success' => true, 'conversation' => $details]);
            break;

        default:
            throw new Exception('Action non reconnue');
    }
    exit;
}

function handlePostRequest() {
    global $db, $userId;
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? 'private';
    $participants = $input['participants'] ?? [];
    $name = $input['name'] ?? null;

    if ($type === 'private' && count($participants) !== 1) {
        throw new Exception('Une conversation privée doit avoir un seul participant');
    }

    beginTransaction();

    try {
        // Vérifier si conversation privée existe déjà
        if ($type === 'private') {
            $targetUserId = intval($participants[0]);
            $existing = fetchOne(
                "SELECT c.id 
                 FROM conversations c
                 JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
                 JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
                 WHERE c.type = 'private'
                 AND cp1.user_id = :user_id
                 AND cp2.user_id = :target_id
                 AND cp1.is_deleted = 0
                 AND cp2.is_deleted = 0",
                ['user_id' => $userId, 'target_id' => $targetUserId]
            );
            if ($existing) {
                echo json_encode(['success' => true, 'conversation_id' => $existing['id']]);
                commit();
                exit;
            }
        }

        // Créer nouvelle conversation
        $conversationId = insert('conversations', [
            'type' => $type,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Ajouter participants
        $participants[] = $userId; // Ajouter l'utilisateur courant
        foreach ($participants as $pid) {
            insert('conversation_participants', [
                'conversation_id' => $conversationId,
                'user_id' => intval($pid),
                'is_admin' => ($pid == $userId && $type == 'group') ? 1 : 0,
                'joined_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Notification pour les participants (sauf l'utilisateur courant)
        foreach ($participants as $pid) {
            if ($pid != $userId) {
                createNotification(
                    $pid,
                    'new_conversation',
                    $type === 'group' ? "Vous avez été ajouté au groupe $name" : "Nouvelle conversation avec vous",
                    "/messages.php?conversation=$conversationId",
                    $userId
                );
            }
        }

        commit();
        echo json_encode(['success' => true, 'conversation_id' => $conversationId]);
    } catch (Exception $e) {
        rollback();
        throw $e;
    }
    exit;
}

function handlePutRequest() {
    global $db, $userId;
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $conversationId = intval($input['conversation_id'] ?? 0);

    if (!$conversationId) throw new Exception('ID de conversation requis');

    switch ($action) {
        case 'archive':
            update('conversation_participants',
                ['is_archived' => 1],
                'conversation_id = :cid AND user_id = :uid',
                ['cid' => $conversationId, 'uid' => $userId]
            );
            echo json_encode(['success' => true, 'message' => 'Conversation archivée']);
            break;

        case 'mute':
            update('conversation_participants',
                ['is_muted' => 1],
                'conversation_id = :cid AND user_id = :uid',
                ['cid' => $conversationId, 'uid' => $userId]
            );
            echo json_encode(['success' => true, 'message' => 'Notifications désactivées']);
            break;

        default:
            throw new Exception('Action non reconnue');
    }
    exit;
}

function handleDeleteRequest() {
    global $db, $userId;
    $conversationId = intval($_GET['id'] ?? 0);
    if (!$conversationId) throw new Exception('ID de conversation requis');

    update('conversation_participants',
        ['is_deleted' => 1],
        'conversation_id = :cid AND user_id = :uid',
        ['cid' => $conversationId, 'uid' => $userId]
    );
    echo json_encode(['success' => true, 'message' => 'Conversation supprimée']);
    exit;
}