<?php
/**
 * API de gestion des posts IDEM
 */
// Simule un post sans image

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
            handlePostRequest();
//            throw new Exception('Méthode non autorisée');
    }
} catch (Exception $e) {
    error_log("Erreur API posts: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetRequest() {
    $action = $_GET['action'] ?? 'feed';

    switch ($action) {
        case 'feed':
            getFeed();
            break;
        case 'single':
            getSinglePost();
            break;
        case 'user_posts':
            getUserPosts();
            break;
        case 'check_new':  // <-- Ajoutez ce cas
            checkNewPosts();
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}

function checkNewPosts() {
    global $db, $userId;

    $sinceId = intval($_GET['since_id'] ?? 0);

    $sql = "SELECT p.*, 
            u.id as author_id, u.username, u.first_name, u.last_name, u.avatar,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) as likes_count,
            (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comments_count,
            (SELECT COUNT(*) FROM post_shares ps WHERE ps.post_id = p.id) as shares_count,
            EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) as user_liked,
            EXISTS(SELECT 1 FROM post_saves ps WHERE ps.post_id = p.id AND ps.user_id = ?) as user_saved
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.is_deleted = 0 AND p.id > ?
            ORDER BY p.created_at DESC";

    $params = [$userId, $userId, $sinceId];
    $posts = fetchAll($sql, $params);

    foreach ($posts as &$post) {
        $post['author'] = [
            'id' => $post['author_id'],
            'username' => $post['username'],
            'name' => $post['first_name'] . ' ' . $post['last_name'],
            'avatar' => $post['avatar']
        ];
        $post['images'] = fetchAll(
            "SELECT url, alt_text FROM post_images WHERE post_id = ? ORDER BY id",
            [$post['id']]
        );
    }

    echo json_encode([
        'success' => true,
        'new_posts' => $posts,
        'new_posts_count' => count($posts),
        'last_id' => $posts[0]['id'] ?? $sinceId
    ]);
}


function getFeed() {
    global $db, $userId;

    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(50, intval($_GET['limit'] ?? 10));
    $offset = ($page - 1) * $limit;

    // Utilisation de ? pour les paramètres positionnels au lieu de :param
    $sql = "SELECT p.*, 
            u.id as author_id, u.username, u.first_name, u.last_name, u.avatar,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) as likes_count,
            (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comments_count,
            (SELECT COUNT(*) FROM post_shares ps WHERE ps.post_id = p.id) as shares_count,
            EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) as user_liked,
            EXISTS(SELECT 1 FROM post_saves ps WHERE ps.post_id = p.id AND ps.user_id = ?) as user_saved
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.is_deleted = 0
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";

    // Tableau de paramètres dans l'ordre exact des ?
    $params = [$userId, $userId, $limit, $offset];

    $posts = fetchAll($sql, $params);

    foreach ($posts as &$post) {
        $post['author'] = [
            'id' => $post['author_id'],
            'username' => $post['username'],
            'name' => $post['first_name'] . ' ' . $post['last_name'],
            'avatar' => $post['avatar']
        ];
        $post['images'] = fetchAll(
            "SELECT url, alt_text FROM post_images WHERE post_id = ? ORDER BY id",
            [$post['id']]
        );
        $post['recent_comments'] = fetchAll(
            "SELECT pc.id, pc.content, pc.created_at,
                    u.id as author_id, u.username, u.first_name, u.last_name, u.avatar
             FROM post_comments pc
             JOIN users u ON pc.user_id = u.id
             WHERE pc.post_id = ?
             ORDER BY pc.created_at DESC
             LIMIT 3",
            [$post['id']]
        );
    }

    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'has_more' => count($posts) === $limit,
        'page' => $page
    ]);
}

function handlePostRequest() {
    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? '';


    // Gestion spécifique pour les uploads d'images
    if (isset($_FILES['images'])) {
        createPostWithImages();
        return;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Si JSON invalide, essaie de lire comme form-data
        $input = $_POST;
    }
    $action = $input['action'] ?? 'create';

    switch ($action) {
        case 'create':
            createPost($input);
            break;
        case 'like':
            likePost($input);
            break;
        case 'unlike':
            unlikePost($input);
            break;
        case 'comment':
            commentPost($input);
            break;
        case 'share':
            sharePost($input);
            break;
        case 'save':
            savePost($input);
            break;
        case 'unsave':
            unsavePost($input);
            break;
        default:
            throw new Exception('Action non reconnue');
    }
}


function createPost($input) {
    global $db, $userId;

    $content = urldecode(trim($input['content'] ?? ''));
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $hasImages = isset($_FILES['images']) && count($_FILES['images']['name']) > 0;

    // Validation plus permissive
//    if (empty($content) && !$hasImages) {
//        http_response_code(400);
//        echo json_encode([
//            'success' => false,
//            'message' => 'Ajoutez du texte ou une image'
//        ]);
//        exit;
//    }
    $privacy = $input['privacy'] ?? 'friends';
    if (!in_array($privacy, ['public', 'friends', 'private'])) {
        $privacy = 'friends';
    }
    $location = $input['location'] ?? null;

    if (empty($content) && empty($input['images'])) {
        throw new Exception('Le contenu ne peut pas être vide');
    }

    if (strlen($content) > 2000) {
        throw new Exception('Contenu trop long (max 2000 caractères)');
    }

    beginTransaction();

    try {
        $postData = [
            'user_id' => $userId,
            'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            'privacy' => $privacy
        ];

        if ($location && is_array($location)) {
            $postData['location_name'] = substr($location['name'] ?? 'Lieu inconnu', 0, 100);
            $postData['location_lat'] = floatval($location['lat'] ?? 0);
            $postData['location_lon'] = floatval($location['lon'] ?? 0);
        }

        $postId = insert('posts', $postData);

        // Traitement des hashtags et mentions
        processPostMetadata($postId, $content);

        commit();

        echo json_encode([
            'success' => true,
            'post' => getCompletePost($postId),
            'message' => 'Publication créée avec succès'
        ]);

    } catch (Exception $e) {
        rollback();
        throw $e;
    }
}

function getCompletePost($postId) {
    $post = fetchOne(
        "SELECT p.*, 
                u.id as author_id, u.username, u.first_name, u.last_name, u.avatar,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) as likes_count,
                (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) as comments_count,
                (SELECT COUNT(*) FROM post_shares ps WHERE ps.post_id = p.id) as shares_count
         FROM posts p
         JOIN users u ON p.user_id = u.id
         WHERE p.id = :id",
        ['id' => $postId]
    );

    if (!$post) return null;

    $post['author'] = [
        'id' => $post['author_id'],
        'username' => $post['username'],
        'name' => $post['first_name'] . ' ' . $post['last_name'],
        'avatar' => $post['avatar']
    ];

    $post['user_liked'] = (bool)fetchOne(
        "SELECT 1 FROM post_likes WHERE post_id = :post_id AND user_id = :user_id",
        ['post_id' => $postId, 'user_id' => $GLOBALS['userId']]
    );

    $post['user_saved'] = (bool)fetchOne(
        "SELECT 1 FROM post_saves WHERE post_id = :post_id AND user_id = :user_id",
        ['post_id' => $postId, 'user_id' => $GLOBALS['userId']]
    );

    return $post;
}
function likePost($input)
{
    global $db, $userId;

    $postId = intval($input['post_id'] ?? 0);

    if (!$postId) {
        throw new Exception('ID du post requis');
    }

    // Vérifier si déjà liké
    $existing = fetchOne(
        "SELECT id FROM post_likes WHERE post_id = :post_id AND user_id = :user_id",
        ['post_id' => $postId, 'user_id' => $userId]
    );

    if ($existing) {
        throw new Exception('Post déjà liké');
    }

    insert('post_likes', [
        'post_id' => $postId,
        'user_id' => $userId
    ]);

    // Compter les likes
    $likesCount = fetchOne(
        "SELECT COUNT(*) as count FROM post_likes WHERE post_id = :post_id",
        ['post_id' => $postId]
    )['count'];

    // Notification au propriétaire du post
    $post = fetchOne(
        "SELECT user_id FROM posts WHERE id = :id",
        ['id' => $postId]
    );

    if ($post && $post['user_id'] != $userId) {
        createNotification(
            $post['user_id'],
            'like',
            'a aimé votre publication',
            $postId,
            $userId
        );
    }

    echo json_encode([
        'success' => true,
        'likes_count' => intval($likesCount)
    ]);
}

function unlikePost($input)
{
    global $db, $userId;

    $postId = intval($input['post_id'] ?? 0);

    if (!$postId) {
        throw new Exception('ID du post requis');
    }

    delete('post_likes', 'post_id = :post_id AND user_id = :user_id', [
        'post_id' => $postId,
        'user_id' => $userId
    ]);

    $likesCount = fetchOne(
        "SELECT COUNT(*) as count FROM post_likes WHERE post_id = :post_id",
        ['post_id' => $postId]
    )['count'];

    echo json_encode([
        'success' => true,
        'likes_count' => intval($likesCount)
    ]);
}

function commentPost($input)
{
    global $db, $userId;

    $postId = intval($input['post_id'] ?? 0);
    $content = trim($input['content'] ?? '');

    if (!$postId) {
        throw new Exception('ID du post requis');
    }

    if (empty($content)) {
        throw new Exception('Le commentaire ne peut pas être vide');
    }

    if (strlen($content) > 500) {
        throw new Exception('Commentaire trop long (max 500 caractères)');
    }

    $commentId = insert('post_comments', [
        'post_id' => $postId,
        'user_id' => $userId,
        'content' => $content
    ]);

    // Récupérer le commentaire créé
    $comment = fetchOne(
        "SELECT pc.id, pc.content, pc.created_at,
                u.id as author_id, u.username, u.first_name, u.last_name, u.avatar
         FROM post_comments pc
         JOIN users u ON pc.user_id = u.id
         WHERE pc.id = :id",
        ['id' => $commentId]
    );

    $comment['author'] = [
        'id' => $comment['author_id'],
        'username' => $comment['username'],
        'name' => $comment['first_name'] . ' ' . $comment['last_name'],
        'avatar' => $comment['avatar']
    ];

    // Compter les commentaires
    $commentsCount = fetchOne(
        "SELECT COUNT(*) as count FROM post_comments WHERE post_id = :post_id",
        ['post_id' => $postId]
    )['count'];

    // Notification au propriétaire du post
    $post = fetchOne(
        "SELECT user_id FROM posts WHERE id = :id",
        ['id' => $postId]
    );

    if ($post && $post['user_id'] != $userId) {
        createNotification(
            $post['user_id'],
            'comment',
            'a commenté votre publication',
            $postId,
            $userId
        );
    }

    echo json_encode([
        'success' => true,
        'comment' => $comment,
        'comments_count' => intval($commentsCount)
    ]);
}

function sharePost($input)
{
    global $db, $userId;

    $postId = intval($input['post_id'] ?? 0);

    if (!$postId) {
        throw new Exception('ID du post requis');
    }

    // Vérifier si déjà partagé
    $existing = fetchOne(
        "SELECT id FROM post_shares WHERE post_id = :post_id AND user_id = :user_id",
        ['post_id' => $postId, 'user_id' => $userId]
    );

    if ($existing) {
        throw new Exception('Post déjà partagé');
    }

    insert('post_shares', [
        'post_id' => $postId,
        'user_id' => $userId
    ]);

    $sharesCount = fetchOne(
        "SELECT COUNT(*) as count FROM post_shares WHERE post_id = :post_id",
        ['post_id' => $postId]
    )['count'];

    echo json_encode([
        'success' => true,
        'shares_count' => intval($sharesCount)
    ]);
}

function savePost($input)
{
    global $db, $userId;

    $postId = intval($input['post_id'] ?? 0);

    if (!$postId) {
        throw new Exception('ID du post requis');
    }

    // Vérifier si déjà sauvegardé
    $existing = fetchOne(
        "SELECT id FROM post_saves WHERE post_id = :post_id AND user_id = :user_id",
        ['post_id' => $postId, 'user_id' => $userId]
    );

    if ($existing) {
        throw new Exception('Post déjà sauvegardé');
    }

    insert('post_saves', [
        'post_id' => $postId,
        'user_id' => $userId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Post sauvegardé'
    ]);
}

function unsavePost($input)
{
    global $db, $userId;

    $postId = intval($input['post_id'] ?? 0);

    if (!$postId) {
        throw new Exception('ID du post requis');
    }

    delete('post_saves', 'post_id = :post_id AND user_id = :user_id', [
        'post_id' => $postId,
        'user_id' => $userId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Post retiré des favoris'
    ]);
}




function handlePutRequest()
{
    // Pour les modifications de posts
    throw new Exception('Fonctionnalité non implémentée');
}

function handleDeleteRequest() {
    global $db, $userId;

    $postId = intval($_GET['id'] ?? 0);

    if (!$postId) {
        throw new Exception('ID du post requis');
    }

    $post = fetchOne(
        "SELECT user_id FROM posts WHERE id = :id",
        ['id' => $postId]
    );

    if (!$post || $post['user_id'] != $userId) {
        throw new Exception('Post non trouvé ou non autorisé');
    }

    update('posts', [
        'is_deleted' => true,
        'deleted_at' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $postId]);

    echo json_encode([
        'success' => true,
        'message' => 'Post supprimé'
    ]);
}
function createPostWithImages() {
    global $userId;

    // Récupération du contenu textuel
    $content = urldecode(trim($_POST['content'] ?? ''));
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $images = $_FILES['images'] ?? [];
    $location = json_decode($_POST['location'] ?? '[]', true);

    beginTransaction();

    try {
        $postData = [
            'user_id' => $userId,
            'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8')
        ];

        if (!empty($location)) {
            $postData['location_name'] = substr($location['name'] ?? '', 0, 100);
            $postData['location_lat'] = floatval($location['lat'] ?? 0);
            $postData['location_lon'] = floatval($location['lon'] ?? 0);
        }


        $postId = insert('posts', $postData);

        // Traitement des images
        $uploadDir = '../uploads/posts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadedImages = [];
        foreach ($images['tmp_name'] as $key => $tmpName) {
            if ($images['error'][$key] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($images['name'][$key], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    continue;
                }

                $filename = uniqid('post_') . '.' . $ext;
                $destPath = $uploadDir . $filename;

                if (move_uploaded_file($tmpName, $destPath)) {
                    insert('post_images', [
                        'post_id' => $postId,
                        'url' => '../uploads/posts/' . $filename,
                        'alt_text' => 'Image postée par l\'utilisateur'
                    ]);
                    $uploadedImages[] = '../uploads/posts/' . $filename;
                }
            }
        }

        // Traitement des hashtags et mentions
        processPostMetadata($postId, $content);

        commit();

        $post = getCompletePost($postId);
        $post['images'] = array_map(function($url) {
            return ['url' => $url];
        }, $uploadedImages);

        echo json_encode([
            'success' => true,
            'post' => $post,
            'message' => 'Publication avec images créée'
        ]);

    } catch (Exception $e) {
        rollback();
        throw $e;
    }
}
function processPostMetadata($postId, $content) {
    // Hashtags
    preg_match_all('/#(\w+)/', $content, $matches);
    foreach ($matches[1] as $hashtag) {
        insert('post_hashtags', [
            'post_id' => $postId,
            'hashtag_id' => $hashtag
        ]);
    }

    // Mentions
    preg_match_all('/@(\w+)/', $content, $matches);
    foreach ($matches[1] as $username) {
        $user = fetchOne("SELECT id FROM users WHERE username = :username", ['username' => $username]);
        if ($user) {
            createNotification(
                $user['id'],
                'mention',
                'vous a mentionné dans une publication',
                $postId,
                $GLOBALS['userId']
            );
        }
    }
}
// Fonction helper
function fetchPostImages($postId) {
    return fetchAll(
        "SELECT url, alt_text FROM post_images WHERE post_id = :post_id ORDER BY id",
        ['post_id' => $postId]
    );
}
function validateEmoji($emoji) {
    // Expression régulière pour valider les émojis
    return preg_match('/[\x{1F600}-\x{1F64F}]/u', $emoji);
}

function validateLocation($location) {
    return isset($location['lat'], $location['lon']) &&
        is_numeric($location['lat']) &&
        is_numeric($location['lon']);
}
function fetchCompletePost($postId) {
    $post = fetchOne("SELECT * FROM posts WHERE id = :id", ['id' => $postId]);

    if (!$post) return null;

    $post['author'] = fetchUserDetails($post['user_id']);
    $post['images'] = fetchPostImages($postId);
    $post['comments'] = fetchPostComments($postId);
    $post['likes_count'] = fetchLikeCount($postId);

    return $post;
}
?>