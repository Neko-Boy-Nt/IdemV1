
<?php
/**
 * API de gestion des groupes IDEM
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
    error_log("Erreur API groupes: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetRequest() {
    global $db, $userId;
    
    $action = $_GET['action'] ?? 'my_groups';
    
    switch ($action) {
        case 'my_groups':
            getMyGroups();
            break;
        case 'discover':
            getDiscoverGroups();
            break;
        case 'invitations':
            getGroupInvitations();
            break;
        case 'managed':
            getManagedGroups();
            break;
        case 'counts':
            getGroupCounts();
            break;
        case 'search':
            searchGroups();
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function getMyGroups() {
    global $db, $userId;
    
    $groups = $db->fetchAll(
        "SELECT g.id, g.name, g.description, g.category, g.privacy, g.cover_image,
                g.created_at, g.members_count, g.posts_count,
                u.first_name as creator_name
         FROM groups g
         JOIN group_members gm ON g.id = gm.group_id
         JOIN users u ON g.created_by = u.id
         WHERE gm.user_id = :user_id AND gm.status = 'active'
         ORDER BY g.last_activity DESC",
        ['user_id' => $userId]
    );
    
    echo json_encode([
        'success' => true,
        'data' => $groups
    ]);
}

function getDiscoverGroups() {
    global $db, $userId;
    
    $category = $_GET['category'] ?? '';
    $limit = min(intval($_GET['limit'] ?? 20), 50);
    
    $whereClause = "g.privacy != 'secret'";
    $params = ['user_id' => $userId];
    
    if ($category) {
        $whereClause .= " AND g.category = :category";
        $params['category'] = $category;
    }
    
    // Groupes populaires
    $popularGroups = $db->fetchAll(
        "SELECT g.id, g.name, g.description, g.category, g.privacy, g.cover_image,
                g.members_count, g.posts_count
         FROM groups g
         WHERE {$whereClause}
         AND g.id NOT IN (
             SELECT gm.group_id FROM group_members gm 
             WHERE gm.user_id = :user_id
         )
         ORDER BY g.members_count DESC, g.posts_count DESC
         LIMIT :limit",
        array_merge($params, ['limit' => $limit])
    );
    
    // Groupes suggérés (basés sur les amis)
    $suggestedGroups = $db->fetchAll(
        "SELECT DISTINCT g.id, g.name, g.description, g.category, g.privacy, 
                g.cover_image, g.members_count, g.posts_count,
                COUNT(friend_groups.group_id) as friend_count
         FROM groups g
         LEFT JOIN (
             SELECT DISTINCT gm.group_id
             FROM group_members gm
             JOIN friendships f ON (
                 (f.requester_id = :user_id AND f.addressee_id = gm.user_id) OR
                 (f.addressee_id = :user_id AND f.requester_id = gm.user_id)
             )
             WHERE f.status = 'accepted'
         ) friend_groups ON g.id = friend_groups.group_id
         WHERE {$whereClause}
         AND g.id NOT IN (
             SELECT gm2.group_id FROM group_members gm2 
             WHERE gm2.user_id = :user_id
         )
         GROUP BY g.id, g.name, g.description, g.category, g.privacy, 
                  g.cover_image, g.members_count, g.posts_count
         ORDER BY friend_count DESC, g.members_count DESC
         LIMIT :limit",
        array_merge($params, ['limit' => $limit])
    );
    
    echo json_encode([
        'success' => true,
        'data' => [
            'popular' => $popularGroups,
            'suggested' => $suggestedGroups
        ]
    ]);
}

function getGroupInvitations() {
    global $db, $userId;
    
    $invitations = $db->fetchAll(
        "SELECT gi.id, gi.created_at,
                g.id as group_id, g.name as group_name, g.description,
                g.category, g.cover_image, g.members_count,
                u.first_name as inviter_name, u.username as inviter_username
         FROM group_invitations gi
         JOIN groups g ON gi.group_id = g.id
         JOIN users u ON gi.invited_by = u.id
         WHERE gi.user_id = :user_id AND gi.status = 'pending'
         ORDER BY gi.created_at DESC",
        ['user_id' => $userId]
    );
    
    echo json_encode([
        'success' => true,
        'data' => $invitations
    ]);
}

function getManagedGroups() {
    global $db, $userId;
    
    $groups = $db->fetchAll(
        "SELECT g.id, g.name, g.description, g.category, g.privacy, g.cover_image,
                g.created_at, g.members_count, g.posts_count,
                (SELECT COUNT(*) FROM group_join_requests gjr 
                 WHERE gjr.group_id = g.id AND gjr.status = 'pending') as pending_requests
         FROM groups g
         JOIN group_members gm ON g.id = gm.group_id
         WHERE gm.user_id = :user_id AND gm.role IN ('admin', 'moderator')
         ORDER BY g.created_at DESC",
        ['user_id' => $userId]
    );
    
    echo json_encode([
        'success' => true,
        'data' => $groups
    ]);
}

function getGroupCounts() {
    global $db, $userId;
    
    $myGroupsCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM group_members 
         WHERE user_id = :user_id AND status = 'active'",
        ['user_id' => $userId]
    )['count'];
    
    $invitationsCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM group_invitations 
         WHERE user_id = :user_id AND status = 'pending'",
        ['user_id' => $userId]
    )['count'];
    
    echo json_encode([
        'success' => true,
        'counts' => [
            'my_groups' => intval($myGroupsCount),
            'invitations' => intval($invitationsCount)
        ]
    ]);
}

function searchGroups() {
    global $db, $userId;
    
    $query = trim($_GET['query'] ?? '');
    $category = $_GET['category'] ?? '';
    $limit = min(intval($_GET['limit'] ?? 20), 50);
    
    if (strlen($query) < 2) {
        throw new Exception('Requête de recherche trop courte');
    }
    
    $whereClause = "g.privacy != 'secret' AND (g.name LIKE :query OR g.description LIKE :query)";
    $params = ['query' => "%{$query}%"];
    
    if ($category) {
        $whereClause .= " AND g.category = :category";
        $params['category'] = $category;
    }
    
    $groups = $db->fetchAll(
        "SELECT g.id, g.name, g.description, g.category, g.privacy, g.cover_image,
                g.members_count, g.posts_count
         FROM groups g
         WHERE {$whereClause}
         ORDER BY g.members_count DESC
         LIMIT :limit",
        array_merge($params, ['limit' => $limit])
    );
    
    echo json_encode([
        'success' => true,
        'data' => $groups,
        'query' => $query
    ]);
}

function handlePostRequest() {
    global $db, $userId;
    
    // Vérifier CSRF
    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? '';
    
    if (!SessionManager::validateCsrfToken($csrfToken)) {
        throw new Exception('Token CSRF invalide');
    }
    
    if (isset($_FILES['cover']) && !empty($_POST['name'])) {
        // Création de groupe avec upload
        createGroupWithUpload();
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'create';
        
        switch ($action) {
            case 'create':
                createGroup($input);
                break;
            case 'join':
                joinGroup($input);
                break;
            case 'accept_invitation':
                acceptGroupInvitation($input);
                break;
            case 'invite_user':
                inviteToGroup($input);
                break;
            default:
                throw new Exception('Action non reconnue');
        }
    }
}

function createGroupWithUpload() {
    global $db, $userId;
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $privacy = $_POST['privacy'] ?? 'public';
    
    if (empty($name)) {
        throw new Exception('Nom du groupe requis');
    }
    
    $coverImage = null;
    
    // Traiter l'upload de l'image de couverture
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/groups/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileInfo = pathinfo($_FILES['cover']['name']);
        $extension = strtolower($fileInfo['extension']);
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('Format d\'image non supporté');
        }
        
        if ($_FILES['cover']['size'] > 5 * 1024 * 1024) {
            throw new Exception('Fichier trop volumineux (max 5MB)');
        }
        
        $fileName = uniqid('group_') . '.' . $extension;
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['cover']['tmp_name'], $targetPath)) {
            $coverImage = $fileName;
        }
    }
    
    $db->beginTransaction();
    
    try {
        // Créer le groupe
        $groupId = $db->insert('groups', [
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'privacy' => $privacy,
            'cover_image' => $coverImage,
            'created_by' => $userId
        ]);
        
        // Ajouter le créateur comme admin
        $db->insert('group_members', [
            'group_id' => $groupId,
            'user_id' => $userId,
            'role' => 'admin',
            'status' => 'active'
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'group_id' => $groupId,
            'message' => 'Groupe créé avec succès'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        if ($coverImage && file_exists($uploadDir . $coverImage)) {
            unlink($uploadDir . $coverImage);
        }
        throw $e;
    }
}

function joinGroup($input) {
    global $db, $userId;
    
    $groupId = intval($input['group_id'] ?? 0);
    
    if (!$groupId) {
        throw new Exception('ID groupe requis');
    }
    
    // Vérifier que le groupe existe
    $group = $db->fetchOne(
        "SELECT privacy FROM groups WHERE id = :id",
        ['id' => $groupId]
    );
    
    if (!$group) {
        throw new Exception('Groupe non trouvé');
    }
    
    // Vérifier si l'utilisateur n'est pas déjà membre
    $existingMember = $db->fetchOne(
        "SELECT id FROM group_members 
         WHERE group_id = :group_id AND user_id = :user_id",
        ['group_id' => $groupId, 'user_id' => $userId]
    );
    
    if ($existingMember) {
        throw new Exception('Vous êtes déjà membre de ce groupe');
    }
    
    if ($group['privacy'] === 'public') {
        // Rejoindre directement
        $db->insert('group_members', [
            'group_id' => $groupId,
            'user_id' => $userId,
            'role' => 'member',
            'status' => 'active'
        ]);
        
        $message = 'Vous avez rejoint le groupe';
    } else {
        // Créer une demande
        $db->insert('group_join_requests', [
            'group_id' => $groupId,
            'user_id' => $userId,
            'status' => 'pending'
        ]);
        
        $message = 'Demande envoyée aux administrateurs';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
}

function acceptGroupInvitation($input) {
    global $db, $userId;
    
    $invitationId = intval($input['invitation_id'] ?? 0);
    
    if (!$invitationId) {
        throw new Exception('ID invitation requis');
    }
    
    // Vérifier l'invitation
    $invitation = $db->fetchOne(
        "SELECT group_id FROM group_invitations 
         WHERE id = :id AND user_id = :user_id AND status = 'pending'",
        ['id' => $invitationId, 'user_id' => $userId]
    );
    
    if (!$invitation) {
        throw new Exception('Invitation non trouvée');
    }
    
    $db->beginTransaction();
    
    try {
        // Accepter l'invitation
        $db->update('group_invitations', [
            'status' => 'accepted',
            'responded_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $invitationId]);
        
        // Ajouter comme membre
        $db->insert('group_members', [
            'group_id' => $invitation['group_id'],
            'user_id' => $userId,
            'role' => 'member',
            'status' => 'active'
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Invitation acceptée'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
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
    
    switch ($action) {
        case 'update':
            updateGroup($input);
            break;
        case 'leave':
            leaveGroup($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function updateGroup($input) {
    global $db, $userId;
    
    $groupId = intval($input['group_id'] ?? 0);
    
    if (!$groupId) {
        throw new Exception('ID groupe requis');
    }
    
    // Vérifier les permissions
    $member = $db->fetchOne(
        "SELECT role FROM group_members 
         WHERE group_id = :group_id AND user_id = :user_id AND status = 'active'",
        ['group_id' => $groupId, 'user_id' => $userId]
    );
    
    if (!$member || !in_array($member['role'], ['admin', 'moderator'])) {
        throw new Exception('Permissions insuffisantes');
    }
    
    $allowedFields = ['name', 'description', 'category', 'privacy'];
    $updateData = [];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateData[$field] = sanitize($input[$field]);
        }
    }
    
    if (empty($updateData)) {
        throw new Exception('Aucune donnée à mettre à jour');
    }
    
    $db->update('groups', $updateData, 'id = :id', ['id' => $groupId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Groupe mis à jour'
    ]);
}

function leaveGroup($input) {
    global $db, $userId;
    
    $groupId = intval($input['group_id'] ?? 0);
    
    if (!$groupId) {
        throw new Exception('ID groupe requis');
    }
    
    // Vérifier que l'utilisateur est membre
    $member = $db->fetchOne(
        "SELECT role FROM group_members 
         WHERE group_id = :group_id AND user_id = :user_id AND status = 'active'",
        ['group_id' => $groupId, 'user_id' => $userId]
    );
    
    if (!$member) {
        throw new Exception('Vous n\'êtes pas membre de ce groupe');
    }
    
    // Si c'est le seul admin, empêcher de quitter
    if ($member['role'] === 'admin') {
        $adminCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM group_members 
             WHERE group_id = :group_id AND role = 'admin' AND status = 'active'",
            ['group_id' => $groupId]
        )['count'];
        
        if ($adminCount <= 1) {
            throw new Exception('Vous ne pouvez pas quitter le groupe en tant que seul administrateur');
        }
    }
    
    // Quitter le groupe
    $db->update('group_members', [
        'status' => 'left',
        'left_at' => date('Y-m-d H:i:s')
    ], 'group_id = :group_id AND user_id = :user_id', [
        'group_id' => $groupId,
        'user_id' => $userId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Vous avez quitté le groupe'
    ]);
}

function handleDeleteRequest() {
    global $db, $userId;
    
    $groupId = intval($_GET['id'] ?? 0);
    
    if (!$groupId) {
        throw new Exception('ID groupe requis');
    }
    
    // Vérifier que l'utilisateur est admin du groupe
    $member = $db->fetchOne(
        "SELECT role FROM group_members 
         WHERE group_id = :group_id AND user_id = :user_id AND status = 'active'",
        ['group_id' => $groupId, 'user_id' => $userId]
    );
    
    if (!$member || $member['role'] !== 'admin') {
        throw new Exception('Seuls les administrateurs peuvent supprimer le groupe');
    }
    
    $db->beginTransaction();
    
    try {
        // Supprimer toutes les données liées
        $db->delete('group_posts', 'group_id = :group_id', ['group_id' => $groupId]);
        $db->delete('group_members', 'group_id = :group_id', ['group_id' => $groupId]);
        $db->delete('group_invitations', 'group_id = :group_id', ['group_id' => $groupId]);
        $db->delete('group_join_requests', 'group_id = :group_id', ['group_id' => $groupId]);
        $db->delete('groups', 'id = :id', ['id' => $groupId]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Groupe supprimé'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
?>
