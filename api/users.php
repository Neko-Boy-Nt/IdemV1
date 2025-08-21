
<?php
/**
 * API de gestion des utilisateurs IDEM
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::start();
$db = initDatabase();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetUser();
            break;
        case 'PUT':
            handleUpdateUser();
            break;
        case 'POST':
            handleSearchUsers();
            break;
        default:
            throw new Exception('Méthode non autorisée');
    }
} catch (Exception $e) {
    error_log("Erreur API users: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetUser() {
    global $db;
    
    $userId = $_GET['id'] ?? null;
    $username = $_GET['username'] ?? null;
    $currentUserId = SessionManager::getUserId();
    
    if (!$userId && !$username) {
        // Retourner le profil de l'utilisateur connecté
        if (!$currentUserId) {
            throw new Exception('Connexion requise');
        }
        $userId = $currentUserId;
    }
    
    $whereClause = $userId ? 'u.id = :identifier' : 'u.username = :identifier';
    $identifier = $userId ?: $username;
    
    $user = fetchOne(
        "SELECT u.id, u.username, u.email, u.first_name, u.last_name, 
                u.avatar, u.bio, u.location, u.website, u.privacy_level,
                u.show_online, u.last_seen, u.created_at,
                (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as posts_count,
                (SELECT COUNT(*) FROM friendships WHERE (requester_id = u.id OR addressee_id = u.id) AND status = 'accepted') as friends_count
         FROM users u 
         WHERE $whereClause AND u.is_active = 1",
        ['identifier' => $identifier]
    );
    
    if (!$user) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    // Vérifier les permissions de visualisation
    if ($currentUserId && $currentUserId != $user['id']) {
        if (!canViewProfile($currentUserId, $user['id'])) {
            throw new Exception('Profil privé');
        }
        
        // Vérifier le statut d'amitié
        $friendship = fetchOne(
            "SELECT status FROM friendships 
             WHERE ((requester_id = :user1 AND addressee_id = :user2) 
                    OR (requester_id = :user2 AND addressee_id = :user1))",
            ['user1' => $currentUserId, 'user2' => $user['id']]
        );
        
        $user['friendship_status'] = $friendship['status'] ?? 'none';
    }
    
    // Ne pas exposer l'email pour les autres utilisateurs
    if ($currentUserId != $user['id']) {
        unset($user['email']);
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
}

function handleUpdateUser() {
    global $db;
    
    if (!SessionManager::isLoggedIn()) {
        throw new Exception('Connexion requise');
    }
    
    $userId = SessionManager::getUserId();
    $data = json_decode(file_get_contents('php://input'), true);
    
    $allowedFields = ['first_name', 'last_name', 'bio', 'location', 'website', 'privacy_level', 'show_online', 'allow_messages'];
    $updateData = [];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = sanitize($data[$field]);
        }
    }
    
    if (empty($updateData)) {
        throw new Exception('Aucune donnée à mettre à jour');
    }
    
    // Validation spécifique
    if (isset($updateData['website']) && !empty($updateData['website'])) {
        if (!filter_var($updateData['website'], FILTER_VALIDATE_URL)) {
            throw new Exception('URL du site web invalide');
        }
    }
    
    update('users', $updateData, 'id = :id', ['id' => $userId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profil mis à jour avec succès'
    ]);
}

function handleSearchUsers() {
    global $db;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $query = trim($data['query'] ?? '');
    $limit = min((int)($data['limit'] ?? 20), 50);
    $offset = (int)($data['offset'] ?? 0);
    
    if (strlen($query) < 2) {
        throw new Exception('La recherche doit contenir au moins 2 caractères');
    }
    
    $searchTerm = "%$query%";
    
    $users = fetchAll(
        "SELECT id, username, first_name, last_name, avatar, location
         FROM users 
         WHERE (username LIKE :term OR first_name LIKE :term OR last_name LIKE :term)
         AND is_active = 1
         ORDER BY 
             CASE 
                 WHEN username LIKE :exact THEN 1
                 WHEN first_name LIKE :exact OR last_name LIKE :exact THEN 2
                 ELSE 3
             END,
             username
         LIMIT :limit OFFSET :offset",
        [
            'term' => $searchTerm,
            'exact' => "$query%",
            'limit' => $limit,
            'offset' => $offset
        ]
    );
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => count($users)
    ]);
}
?>
