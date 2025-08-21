<?php
/**
 * API pour la gestion des notifications IDEM
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
    error_log("Request Method: $method, User ID: $userId");
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
    error_log("Erreur API notifications: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetRequest() {
    global $userId;

    $action = $_GET['action'] ?? 'list';
    error_log("handleGetRequest Action: $action");

    switch ($action) {
        case 'list':
            getNotificationsList();
            break;
        case 'count':
            getUnreadCount();
            break;
        case 'unread_count':
            getUnreadCount();
        break;
        case 'settings':
            getNotificationSettings();
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function getNotificationsList() {
    global $db, $userId;

    // Vérifier $userId
    if (!is_int($userId) || $userId <= 0) {
        error_log("getNotificationsList Error: Invalid userId: " . var_export($userId, true));
        throw new Exception("Utilisateur non valide");
    }

    $filter = $_GET['filter'] ?? 'all';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $whereConditions = ['n.user_id = :user_id'];

    switch ($filter) {
        case 'unread':
            $whereConditions[] = 'n.is_read IS NULL';
            break;
        case 'mentions':
            $whereConditions[] = 'n.type = "mention"';
            break;
        case 'likes':
            $whereConditions[] = 'n.type = "like"';
            break;
        case 'friends':
            $whereConditions[] = 'n.type IN ("friend_request", "friend_accepted")';
            break;
        case 'messages':
            $whereConditions[] = 'n.type = "message"';
            break;
    }

    $whereClause = implode(' AND ', $whereConditions);

    $sql = "SELECT 
        n.id,
        n.type,
        n.message,
        n.is_read,
        n.created_at,
        n.related_id AS post_id,
        n.related_user_id AS sender_id,
        sender.username AS sender_username,
        CONCAT(sender.first_name, ' ', sender.last_name) AS sender_name,
        sender.avatar AS sender_avatar
    FROM notifications n
    LEFT JOIN users sender ON n.related_user_id = sender.id
    WHERE {$whereClause}
    ORDER BY n.created_at DESC
    LIMIT :limit OFFSET :offset";

    try {
        $params = [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ];
        $stmt = executeQuery($sql, $params);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("getNotificationsList SQL: $sql");
        error_log("getNotificationsList Params: " . json_encode($params));
        error_log("getNotificationsList WhereClause: $whereClause");
    } catch (PDOException $e) {
        error_log("PDO Error in getNotificationsList: " . $e->getMessage() . " | SQL: $sql | Params: " . json_encode($params));
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    $statsQuery = "SELECT 
        COUNT(*) AS total_count,
        COUNT(CASE WHEN is_read IS NULL THEN 1 END) AS unread_count
    FROM notifications 
    WHERE user_id = :user_id";

    try {
        $stats = fetchOne($statsQuery, ['user_id' => $userId]);
        error_log("getNotificationsList Stats SQL: $statsQuery");
        error_log("getNotificationsList Stats Params: user_id=$userId");
    } catch (PDOException $e) {
        error_log("PDO Error in getNotificationsList Stats: " . $e->getMessage() . " | SQL: $statsQuery");
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'stats' => $stats,
        'page' => $page,
        'has_more' => count($notifications) === $limit
    ]);
}

function getUnreadCount() {
    global $db, $userId;

    $sql = "SELECT COUNT(*) AS count FROM notifications 
            WHERE user_id = :user_id AND is_read IS NULL";

    try {
        $count = fetchOne($sql, ['user_id' => $userId]);
        error_log("getUnreadCount SQL: $sql");
        error_log("getUnreadCount Params: user_id=$userId");
    } catch (PDOException $e) {
        error_log("PDO Error in getUnreadCount: " . $e->getMessage() . " | SQL: $sql");
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'unread_count' => intval($count['count'])
    ]);
}

function getTotalCount() {
    global $userId;

    $sql = "SELECT COUNT(*) AS total_count FROM notifications 
            WHERE user_id = ?";

    try {
        $result = fetchOne($sql, [$userId]);

        echo json_encode([
            'success' => true,
            'total_count' => (int)$result['total_count'],
            'message' => 'Nombre total de notifications'
        ]);

    } catch (Exception $e) {
        error_log("Error in getTotalCount: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du comptage'
        ]);
    }
}

function getNotificationSettings() {
    global $db, $userId;

    $sql = "SELECT 
            push_enabled,
            notify_likes,
            notify_comments,
            notify_mentions,
            notify_friends,
            notify_messages,
            email_frequency
         FROM user_notification_settings 
         WHERE user_id = :user_id";

    try {
        $settings = fetchOne($sql, ['user_id' => $userId]);
        error_log("getNotificationSettings SQL: $sql");
        error_log("getNotificationSettings Params: user_id=$userId");
    } catch (PDOException $e) {
        error_log("PDO Error in getNotificationSettings: " . $e->getMessage() . " | SQL: $sql");
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    if (!$settings) {
        try {
            insert('user_notification_settings', [
                'user_id' => $userId,
                'push_enabled' => true,
                'notify_likes' => true,
                'notify_comments' => true,
                'notify_mentions' => true,
                'notify_friends' => true,
                'notify_messages' => true,
                'email_frequency' => 'weekly'
            ]);
            error_log("getNotificationSettings Insert: user_id=$userId");
        } catch (PDOException $e) {
            error_log("PDO Error in getNotificationSettings Insert: " . $e->getMessage());
            throw new Exception("Erreur SQL: " . $e->getMessage());
        }

        $settings = [
            'push_enabled' => true,
            'notify_likes' => true,
            'notify_comments' => true,
            'notify_mentions' => true,
            'notify_friends' => true,
            'notify_messages' => true,
            'email_frequency' => 'weekly'
        ];
    }

    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
}

function handlePostRequest() {
    global $db, $userId;

    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? '';

    if (!SessionManager::validateCsrfToken($csrfToken)) {
        throw new Exception('Token CSRF invalide');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    error_log("handlePostRequest Action: $action");

    switch ($action) {
        case 'create':
            createApiNotification($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function createApiNotification($input) {
    global $db, $userId;

    $targetUserId = intval($input['user_id'] ?? 0);
    $type = $input['type'] ?? '';
    $message = trim($input['message'] ?? '');
    $postId = intval($input['post_id'] ?? 0) ?: null;

    if (!$targetUserId || $targetUserId === $userId) {
        throw new Exception('ID utilisateur cible invalide');
    }

    if (empty($type)) {
        throw new Exception('Type de notification requis');
    }

    $sql = "SELECT id FROM users WHERE id = :id AND is_active = true";
    try {
        $targetUser = fetchOne($sql, ['id' => $targetUserId]);
        error_log("createApiNotification User Check SQL: $sql");
        error_log("createApiNotification User Check Params: id=$targetUserId");
    } catch (PDOException $e) {
        error_log("PDO Error in createApiNotification User Check: " . $e->getMessage() . " | SQL: $sql");
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    if (!$targetUser) {
        throw new Exception('Utilisateur cible non trouvé');
    }

    $sql = "SELECT notify_likes, notify_comments, notify_mentions, notify_friends, notify_messages
            FROM user_notification_settings
            WHERE user_id = :user_id";
    try {
        $settings = fetchOne($sql, ['user_id' => $targetUserId]);
        error_log("createApiNotification Settings SQL: $sql");
        error_log("createApiNotification Settings Params: user_id=$targetUserId");
    } catch (PDOException $e) {
        error_log("PDO Error in createApiNotification Settings: " . $e->getMessage() . " | SQL: $sql");
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    if ($settings) {
        $settingKey = 'notify_' . str_replace(['_request', '_accepted'], '', $type);
        if ($type === 'friend_request' || $type === 'friend_accepted') {
            $settingKey = 'notify_friends';
        } elseif ($type === 'comment') {
            $settingKey = 'notify_comments';
        }

        if (isset($settings[$settingKey]) && !$settings[$settingKey]) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification ignorée (paramètres utilisateur)'
            ]);
            return;
        }
    }

    $sql = "SELECT id FROM notifications
            WHERE user_id = :user_id AND related_user_id = :related_user_id AND type = :type
            AND created_at > NOW() - INTERVAL '5 minutes'";
    try {
        $existingRecent = fetchOne($sql, [
            'user_id' => $targetUserId,
            'related_user_id' => $userId,
            'type' => $type
        ]);
        error_log("createApiNotification Duplicate Check SQL: $sql");
        error_log("createApiNotification Duplicate Check Params: user_id=$targetUserId, related_user_id=$userId, type=$type");
    } catch (PDOException $e) {
        error_log("PDO Error in createApiNotification Duplicate Check: " . $e->getMessage() . " | SQL: $sql");
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    if ($existingRecent) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification récente existante ignorée'
        ]);
        return;
    }

    try {
        $notificationId = insert('notifications', [
            'user_id' => $targetUserId,
            'related_user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'related_id' => $postId
        ]);
        error_log("createApiNotification Insert: user_id=$targetUserId, related_user_id=$userId, type=$type, message=$message, related_id=$postId");
    } catch (PDOException $e) {
        error_log("PDO Error in createApiNotification Insert: " . $e->getMessage());
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'notification_id' => $notificationId,
        'message' => 'Notification créée avec succès'
    ]);
}

function handlePutRequest() {
    global $db, $userId;

    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? '';

    if (!SessionManager::validateCsrfToken($csrfToken)) {
        throw new Exception('Token CSRF invalide');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    error_log("handlePutRequest Action: $action");

    switch ($action) {
        case 'mark_read':
            markNotificationAsRead($input);
            break;
        case 'mark_all_read':
            markAllNotificationsAsRead();
            break;
        case 'update_settings':
            updateNotificationSettings($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function markNotificationAsRead($input) {
    global $db, $userId;

    $notificationId = intval($input['notification_id'] ?? 0);

    if (!$notificationId) {
        throw new Exception('ID de notification requis');
    }

    try {
        $updated = update('notifications', [
            'is_read' => date('Y-m-d H:i:s')
        ], 'id = :id AND user_id = :user_id AND is_read IS NULL', [
            'id' => $notificationId,
            'user_id' => $userId
        ]);
        error_log("markNotificationAsRead Update: id=$notificationId, user_id=$userId");
    } catch (PDOException $e) {
        error_log("PDO Error in markNotificationAsRead: " . $e->getMessage());
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    if ($updated->rowCount() === 0) {
        throw new Exception('Notification non trouvée ou déjà lue');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Notification marquée comme lue'
    ]);
}

function markAllNotificationsAsRead() {
    global $db, $userId;

    try {
        $updated = update('notifications', [
            'is_read' => date('Y-m-d H:i:s')
        ], 'user_id = :user_id AND is_read IS NULL', [
            'user_id' => $userId
        ]);
        error_log("markAllNotificationsAsRead Update: user_id=$userId");
    } catch (PDOException $e) {
        error_log("PDO Error in markAllNotificationsAsRead: " . $e->getMessage());
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'notifications_updated' => $updated->rowCount(),
        'message' => 'Toutes les notifications marquées comme lues'
    ]);
}

function updateNotificationSettings($input) {
    global $db, $userId;

    $settings = $input['settings'] ?? [];

    $allowedSettings = [
        'push_enabled', 'notify_likes', 'notify_comments',
        'notify_mentions', 'notify_friends', 'notify_messages', 'email_frequency'
    ];

    $cleanSettings = [];
    foreach ($allowedSettings as $setting) {
        if (isset($settings[$setting])) {
            if ($setting === 'email_frequency') {
                $allowedFrequencies = ['never', 'daily', 'weekly', 'monthly'];
                if (in_array($settings[$setting], $allowedFrequencies)) {
                    $cleanSettings[$setting] = $settings[$setting];
                }
            } else {
                $cleanSettings[$setting] = (bool)$settings[$setting];
            }
        }
    }

    if (empty($cleanSettings)) {
        throw new Exception('Aucun paramètre valide fourni');
    }

    $sql = "SELECT id FROM user_notification_settings WHERE user_id = :user_id";
    try {
        $existing = fetchOne($sql, ['user_id' => $userId]);
        error_log("updateNotificationSettings Check SQL: $sql");
        error_log("updateNotificationSettings Check Params: user_id=$userId");
    } catch (PDOException $e) {
        error_log("PDO Error in updateNotificationSettings Check: " . $e->getMessage() . " | SQL: $sql");
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    if ($existing) {
        $cleanSettings['updated_at'] = date('Y-m-d H:i:s');
        try {
            update('user_notification_settings',
                $cleanSettings,
                'user_id = :user_id',
                ['user_id' => $userId]
            );
            error_log("updateNotificationSettings Update: user_id=$userId, settings=" . json_encode($cleanSettings));
        } catch (PDOException $e) {
            error_log("PDO Error in updateNotificationSettings Update: " . $e->getMessage());
            throw new Exception("Erreur SQL: " . $e->getMessage());
        }
    } else {
        $cleanSettings['user_id'] = $userId;
        try {
            insert('user_notification_settings', $cleanSettings);
            error_log("updateNotificationSettings Insert: user_id=$userId, settings=" . json_encode($cleanSettings));
        } catch (PDOException $e) {
            error_log("PDO Error in updateNotificationSettings Insert: " . $e->getMessage());
            throw new Exception("Erreur SQL: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Paramètres mis à jour avec succès'
    ]);
}

function handleDeleteRequest() {
    global $db, $userId;

    $notificationId = intval($_GET['id'] ?? 0);

    if (!$notificationId) {
        throw new Exception('ID de notification requis');
    }

    try {
        $deleted = delete('notifications',
            'id = :id AND user_id = :user_id',
            ['id' => $notificationId, 'user_id' => $userId]
        );
        error_log("handleDeleteRequest Delete: id=$notificationId, user_id=$userId");
    } catch (PDOException $e) {
        error_log("PDO Error in handleDeleteRequest: " . $e->getMessage());
        throw new Exception("Erreur SQL: " . $e->getMessage());
    }

    if ($deleted->rowCount() === 0) {
        throw new Exception('Notification non trouvée');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Notification supprimée'
    ]);
}
?>