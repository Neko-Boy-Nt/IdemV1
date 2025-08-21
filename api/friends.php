<?php
/**
 * API pour la gestion des amis IDEM
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

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
    error_log("Erreur API friends: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetRequest() {
    global $db, $userId;

    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'list':
            getFriendsList();
            break;
        case 'online':
            $onlineFriends = getOnlineFriends($userId);
            echo json_encode([
                'success' => true,
                'friends' => $onlineFriends,
                'count' => count($onlineFriends)
            ]);
            break;
        case 'requests':
            getFriendRequests();
            break;
        case 'suggestions':
            getFriendSuggestions();
            break;
        case 'status':
            getFriendshipStatus();
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function getOnlineFriends($userId) {
    global $db;

    $query = "SELECT u.id, u.username, u.first_name, u.last_name, u.avatar 
              FROM users u
              INNER JOIN friendships f ON 
                  (f.requester_id = :user_id AND f.addressee_id = u.id) OR 
                  (f.addressee_id = :user_id AND f.requester_id = u.id)
              WHERE u.is_online = 1
              AND u.last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
              AND f.status = 'accepted'";

    return fetchAll($query, ['user_id' => $userId]);
}

function getFriendsList() {
    global $db, $userId;

    $sql = "SELECT u.id, u.username, u.first_name, u.last_name, u.avatar, u.bio, u.last_seen, f.created_at as friends_since 
        FROM friendships f 
        JOIN users u ON (CASE WHEN f.requester_id = :userId1 THEN u.id = f.addressee_id ELSE u.id = f.requester_id END) 
        WHERE (f.requester_id = :userId2 OR f.addressee_id = :userId3) AND f.status = 'accepted' AND u.is_active = true 
        ORDER BY u.first_name, u.last_name";
    $friends = fetchAll($sql, ['userId1' => $userId, 'userId2' => $userId, 'userId3' => $userId]);

    echo json_encode([
        'success' => true,
        'friends' => $friends,
        'count' => count($friends)
    ]);
}

function getFriendRequests() {
    global $db, $userId;

    // Requêtes reçues (en attente)
    $received = fetchAll("
        SELECT 
            u.id, u.username, u.first_name, u.last_name, u.avatar, u.bio,
            f.id as request_id, f.created_at as request_date
        FROM friendships f
        JOIN users u ON u.id = f.requester_id
        WHERE f.addressee_id = :user_id
        AND f.status = 'pending'
        AND u.is_active = true
        ORDER BY f.created_at DESC
    ", ['user_id' => $userId]);

    // Requêtes envoyées (en attente)
    $sent = fetchAll("
        SELECT 
            u.id, u.username, u.first_name, u.last_name, u.avatar, u.bio,
            f.id as request_id, f.created_at as request_date
        FROM friendships f
        JOIN users u ON u.id = f.addressee_id
        WHERE f.requester_id = :user_id
        AND f.status = 'pending'
        AND u.is_active = true
        ORDER BY f.created_at DESC
    ", ['user_id' => $userId]);

    echo json_encode([
        'success' => true,
        'received' => $received,
        'sent' => $sent,
        'received_count' => count($received),
        'sent_count' => count($sent)
    ]);
}

function getFriendSuggestions() {
    global $db, $userId;

    // Suggestions basées sur amis communs (exemple simple)
    $sql = "
        SELECT u.id, u.username, u.first_name, u.last_name, u.avatar, u.bio,
               COUNT(mutual.id) as mutual_friends
        FROM users u
        LEFT JOIN friendships f1 ON f1.addressee_id = u.id AND f1.status = 'accepted'
        LEFT JOIN friendships f2 ON f2.requester_id = u.id AND f2.status = 'accepted'
        LEFT JOIN friendships mutual ON 
            (mutual.requester_id = f1.requester_id OR mutual.requester_id = f2.addressee_id)
            AND mutual.addressee_id = :user_id AND mutual.status = 'accepted'
        WHERE u.id != :user_id
        AND u.is_active = true
        AND NOT EXISTS (
            SELECT 1 FROM friendships existing
            WHERE (existing.requester_id = u.id AND existing.addressee_id = :user_id)
               OR (existing.requester_id = :user_id AND existing.addressee_id = u.id)
        )
        GROUP BY u.id
        HAVING mutual_friends > 0
        ORDER BY mutual_friends DESC
        LIMIT 10
    ";

    $suggestions = fetchAll($sql, ['user_id' => $userId]);

    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions
    ]);
}

function getFriendshipStatus() {
    global $db, $userId;

    $targetUserId = intval($_GET['user_id'] ?? 0);

    if (!$targetUserId || $targetUserId === $userId) {
        throw new Exception('ID utilisateur invalide');
    }

    $status = fetchOne(
        "SELECT status FROM friendships
         WHERE (requester_id = :user1 AND addressee_id = :user2)
            OR (requester_id = :user2 AND addressee_id = :user1)",
        ['user1' => $userId, 'user2' => $targetUserId]
    );

    echo json_encode([
        'success' => true,
        'status' => $status ? $status['status'] : 'none'
    ]);
}

function handlePostRequest() {
    global $db, $userId;

    $action = $_GET['action'] ?? 'request';
    $input = json_decode(file_get_contents('php://input'), true);
    $targetUserId = intval($input['user_id'] ?? 0);

    if (!$targetUserId || $targetUserId === $userId) {
        throw new Exception('ID utilisateur invalide');
    }

    // Vérifier si l'utilisateur cible existe
    $targetUser = fetchOne(
        "SELECT id FROM users WHERE id = :id AND is_active = 1",
        ['id' => $targetUserId]
    );

    if (!$targetUser) {
        throw new Exception('Utilisateur non trouvé');
    }

    switch ($action) {
        case 'request':
            sendFriendRequest($targetUserId);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function sendFriendRequest($targetUserId) {
    global $db, $userId;

    // Vérifier si une relation existe déjà
    $existing = fetchOne(
        "SELECT status FROM friendships
         WHERE (requester_id = :user1 AND addressee_id = :user2)
            OR (requester_id = :user2 AND addressee_id = :user1)",
        ['user1' => $userId, 'user2' => $targetUserId]
    );

    if ($existing) {
        $status = $existing['status'];
        if ($status === 'accepted') {
            throw new Exception('Vous êtes déjà amis');
        } elseif ($status === 'pending') {
            throw new Exception('Demande d\'ami déjà envoyée');
        } elseif ($status === 'blocked') {
            throw new Exception('Utilisateur bloqué');
        }
    }

    beginTransaction();

    try {
        // Créer la demande
        $friendshipId = insert('friendships', [
            'requester_id' => $userId,
            'addressee_id' => $targetUserId,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Créer une notification
        createNotification(
            $targetUserId,
            'friend_request',
            'vous a envoyé une demande d\'ami',
            null,
            $userId
        );

        commit();

        echo json_encode([
            'success' => true,
            'message' => 'Demande d\'ami envoyée',
            'friendship_id' => $friendshipId
        ]);

    } catch (Exception $e) {
        rollback();
        error_log("Erreur envoi demande ami: " . $e->getMessage());
        throw new Exception('Échec de l\'envoi de la demande');
    }
}

function acceptFriendRequest($input) {
    global $db, $userId;

    $requestId = intval($input['request_id'] ?? 0);

    if (!$requestId) {
        throw new Exception('ID de demande requis');
    }

    // Vérifier que la demande existe et nous concerne
    $request = fetchOne(
        "SELECT id, requester_id FROM friendships 
         WHERE id = :id AND addressee_id = :user_id AND status = 'pending'",
        ['id' => $requestId, 'user_id' => $userId]
    );

    if (!$request) {
        throw new Exception('Demande non trouvée');
    }

    beginTransaction();

    try {
        // Accepter la demande
        update('friendships', [
            'status' => 'accepted',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $requestId]);

        // Créer une notification pour celui qui a envoyé la demande
        createNotification(
            $request['requester_id'],
            'friend_request',
            'a accepté votre demande d\'ami',
            null,
            $userId
        );

        commit();

        echo json_encode([
            'success' => true,
            'message' => 'Demande d\'ami acceptée'
        ]);

    } catch (Exception $e) {
        rollback();
        throw $e;
    }
}

function declineFriendRequest($input) {
    global $db, $userId;

    $requestId = intval($input['request_id'] ?? 0);

    if (!$requestId) {
        throw new Exception('ID de demande requis');
    }

    // Vérifier que la demande existe et nous concerne
    $request = fetchOne(
        "SELECT id FROM friendships 
         WHERE id = :id AND addressee_id = :user_id AND status = 'pending'",
        ['id' => $requestId, 'user_id' => $userId]
    );

    if (!$request) {
        throw new Exception('Demande non trouvée');
    }

    // Supprimer la demande
    delete('friendships', 'id = :id', ['id' => $requestId]);

    echo json_encode([
        'success' => true,
        'message' => 'Demande d\'ami refusée'
    ]);
}

function blockUser($input) {
    global $db, $userId;

    $targetUserId = intval($input['user_id'] ?? 0);

    if (!$targetUserId || $targetUserId === $userId) {
        throw new Exception('ID utilisateur invalide');
    }

    beginTransaction();

    try {
        // Supprimer toute relation existante
        delete('friendships',
            '(requester_id = :user_id AND addressee_id = :target_id) 
             OR (requester_id = :target_id AND addressee_id = :user_id)',
            ['user_id' => $userId, 'target_id' => $targetUserId]
        );

        // Créer un blocage
        insert('friendships', [
            'requester_id' => $userId,
            'addressee_id' => $targetUserId,
            'status' => 'blocked'
        ]);

        commit();

        echo json_encode([
            'success' => true,
            'message' => 'Utilisateur bloqué'
        ]);

    } catch (Exception $e) {
        rollback();
        throw $e;
    }
}

function handlePutRequest() {
    // Actuellement pas d'actions PUT pour les amis
    throw new Exception('Action non disponible');
}

function handleDeleteRequest() {
    global $db, $userId;

    $targetUserId = intval($_GET['user_id'] ?? 0);

    if (!$targetUserId) {
        throw new Exception('ID utilisateur requis');
    }

    // Supprimer l'amitié
    $deleted = delete('friendships',
        '(requester_id = :user_id AND addressee_id = :target_id) 
         OR (requester_id = :target_id AND addressee_id = :user_id)
         AND status = \'accepted\'',
        ['user_id' => $userId, 'target_id' => $targetUserId]
    );

    if ($deleted->rowCount() === 0) {
        throw new Exception('Amitié non trouvée');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ami supprimé'
    ]);
}

function createNotification($targetUserId, $type, $message, $link = null, $senderId = null) {
    global $db;

    insert('notifications', [
        'user_id' => $targetUserId,
        'type' => $type,
        'message' => $message,
        'link' => $link,
        'sender_id' => $senderId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}
?>