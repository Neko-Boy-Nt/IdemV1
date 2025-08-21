<?php
/**
 * API de recherche globale IDEM
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
    if ($method !== 'GET') {
        throw new Exception('Seul GET est autorisé');
    }

    // Accepter 'q' ou 'query' comme paramètre de recherche
    $query = trim($_GET['q'] ?? $_GET['query'] ?? '');
    $type = $_GET['type'] ?? 'all';
    $limit = min(intval($_GET['limit'] ?? 10), 50);
    $sort = $_GET['sort'] ?? 'relevance'; // Paramètre de tri
    $date = $_GET['date'] ?? 'all'; // Paramètre de filtre de date

    if (strlen($query) < 2) {
        throw new Exception('Requête de recherche trop courte (minimum 2 caractères)');
    }

    error_log("Requête API: type=$type, query=$query, limit=$limit, sort=$sort, date=$date, user_id=$userId");

    switch ($type) {
        case 'users':
            error_log("Exécution de searchUsers");
            searchUsers($query, $limit, $sort, $date);
            break;
        case 'posts':
            error_log("Exécution de searchPosts");
            searchPosts($query, $limit, $sort, $date);
            break;
        case 'groups':
            error_log("Exécution de searchGroups");
            searchGroups($query, $limit, $sort, $date);
            break;
        case 'hashtags':
            error_log("Exécution de searchHashtags");
            searchHashtags($query, $limit, $sort, $date);
            break;
        case 'all':
        default:
            error_log("Exécution de searchAll");
            searchAll($query, $limit, $sort, $date);
            break;
    }

} catch (Exception $e) {
    error_log("Erreur API search: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function searchUsers($query, $limit, $sort, $date) {
    global $db, $userId;

    $searchTerm = "%{$query}%";

    $sql = "SELECT u.id, u.username, u.first_name, u.last_name, u.avatar, u.bio, u.location,
                   u.is_verified, u.last_seen,
                   -- Vérifier le statut d'amitié
                   CASE 
                       WHEN EXISTS(
                           SELECT 1 FROM friendships f 
                           WHERE ((f.requester_id = u.id AND f.addressee_id = :user_id1) 
                                  OR (f.requester_id = :user_id2 AND f.addressee_id = u.id))
                           AND f.status = 'accepted'
                       ) THEN 'friends'
                       WHEN EXISTS(
                           SELECT 1 FROM friendships f 
                           WHERE f.requester_id = :user_id3 AND f.addressee_id = u.id
                           AND f.status = 'pending'
                       ) THEN 'request_sent'
                       WHEN EXISTS(
                           SELECT 1 FROM friendships f 
                           WHERE f.requester_id = u.id AND f.addressee_id = :user_id4
                           AND f.status = 'pending'
                       ) THEN 'request_received'
                       ELSE 'none'
                   END as friendship_status,
                   -- Nombre d'amis en commun
                   (SELECT COUNT(DISTINCT f2.requester_id, f2.addressee_id)
                    FROM friendships f1, friendships f2 
                    WHERE f1.status = 'accepted' AND f2.status = 'accepted'
                    AND ((f1.requester_id = :user_id5 AND f1.addressee_id = f2.requester_id AND f2.addressee_id = u.id)
                         OR (f1.requester_id = :user_id6 AND f1.addressee_id = f2.addressee_id AND f2.requester_id = u.id)
                         OR (f1.addressee_id = :user_id7 AND f1.requester_id = f2.requester_id AND f2.addressee_id = u.id)
                         OR (f1.addressee_id = :user_id8 AND f1.requester_id = f2.addressee_id AND f2.requester_id = u.id))
                   ) as mutual_friends_count
            FROM users u 
            WHERE u.id != :user_id 
            AND u.is_active = 1
            AND (u.username LIKE :search1 OR u.first_name LIKE :search2 
                 OR u.last_name LIKE :search3 OR CONCAT(u.first_name, ' ', u.last_name) LIKE :search4)";

    // Ajouter un filtre de date si nécessaire
    if ($date !== 'all') {
        if ($date === 'week') {
            $sql .= " AND u.last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($date === 'month') {
            $sql .= " AND u.last_seen >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
    }

    // Ajouter le tri
    if ($sort === 'relevance') {
        $sql .= " ORDER BY 
                    CASE 
                        WHEN LOWER(u.username) = LOWER(:query) THEN 1
                        WHEN LOWER(CONCAT(u.first_name, ' ', u.last_name)) = LOWER(:query) THEN 2
                        WHEN LOWER(u.first_name) LIKE LOWER(:exact_query) OR LOWER(u.last_name) LIKE LOWER(:exact_query) THEN 3
                        WHEN LOWER(u.username) LIKE LOWER(:exact_query) THEN 4
                        ELSE 5
                    END,
                    mutual_friends_count DESC,
                    u.last_seen DESC";
    } elseif ($sort === 'recent') {
        $sql .= " ORDER BY u.last_seen DESC";
    } else {
        $sql .= " ORDER BY mutual_friends_count DESC";
    }

    $sql .= " LIMIT :limit";

    $params = [
        'user_id' => $userId,
        'user_id1' => $userId,
        'user_id2' => $userId,
        'user_id3' => $userId,
        'user_id4' => $userId,
        'user_id5' => $userId,
        'user_id6' => $userId,
        'user_id7' => $userId,
        'user_id8' => $userId,
        'search1' => $searchTerm,
        'search2' => $searchTerm,
        'search3' => $searchTerm,
        'search4' => $searchTerm,
        'query' => $query,
        'exact_query' => "{$query}%",
        'limit' => $limit
    ];

    error_log("Requête searchUsers: $sql");
    error_log("Paramètres searchUsers: " . print_r($params, true));

    try {
        $users = fetchAll($sql, $params);
    } catch (Exception $e) {
        error_log("Erreur dans searchUsers: " . $e->getMessage());
        throw $e;
    }

    echo json_encode([
        'success' => true,
        'results' => $users,
        'type' => 'users',
        'query' => $query
    ]);
}

function searchPosts($query, $limit, $sort, $date) {
    global $db, $userId;

    $searchTerm = "%{$query}%";

    $sql = "SELECT p.id, p.content, p.image, p.video, p.privacy, p.likes_count, 
                   p.comments_count, p.shares_count, p.created_at,
                   u.id as user_id, u.username, u.first_name, u.last_name, u.avatar,
                   -- Vérifier si l'utilisateur peut voir ce post
                   CASE 
                       WHEN p.user_id = :user_id THEN 1
                       WHEN p.privacy = 'public' THEN 1
                       WHEN p.privacy = 'friends' AND EXISTS(
                           SELECT 1 FROM friendships f 
                           WHERE ((f.requester_id = p.user_id AND f.addressee_id = :user_id1) 
                                  OR (f.requester_id = :user_id2 AND f.addressee_id = p.user_id))
                           AND f.status = 'accepted'
                       ) THEN 1
                       ELSE 0
                   END as can_view,
                   -- Vérifier si l'utilisateur a liké
                   EXISTS(
                       SELECT 1 FROM post_likes pl 
                       WHERE pl.post_id = p.id AND pl.user_id = :user_id3
                   ) as user_liked
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.is_deleted = 0 
            AND p.content LIKE :search";

    // Ajouter un filtre de date si nécessaire
    if ($date !== 'all') {
        if ($date === 'week') {
            $sql .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($date === 'month') {
            $sql .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
    }

    $sql .= " HAVING can_view = 1";

    // Ajouter le tri
    if ($sort === 'relevance') {
        $sql .= " ORDER BY 
                    CASE 
                        WHEN p.content LIKE :exact_query THEN 1
                        ELSE 2
                    END,
                    p.likes_count DESC,
                    p.created_at DESC";
    } elseif ($sort === 'recent') {
        $sql .= " ORDER BY p.created_at DESC";
    } else {
        $sql .= " ORDER BY p.likes_count DESC";
    }

    $sql .= " LIMIT :limit";

    $params = [
        'user_id' => $userId,
        'user_id1' => $userId,
        'user_id2' => $userId,
        'user_id3' => $userId,
        'search' => $searchTerm,
        'exact_query' => "{$query}%",
        'limit' => $limit
    ];

    error_log("Requête searchPosts: $sql");
    error_log("Paramètres searchPosts: " . print_r($params, true));

    try {
        $posts = fetchAll($sql, $params);
    } catch (Exception $e) {
        error_log("Erreur dans searchPosts: " . $e->getMessage());
        throw $e;
    }

    echo json_encode([
        'success' => true,
        'results' => $posts,
        'type' => 'posts',
        'query' => $query
    ]);
}

function searchGroups($query, $limit, $sort, $date) {
    global $db, $userId;

    $searchTerm = "%{$query}%";

    $sql = "SELECT g.id, g.name, g.description, g.category, g.privacy,
                   NULL as cover_image,
                   g.members_count, g.posts_count, g.created_at,
                   u.first_name as creator_name,
                   -- Vérifier si l'utilisateur est membre
                   EXISTS(
                       SELECT 1 FROM group_members gm 
                       WHERE gm.group_id = g.id AND gm.user_id = :user_id AND gm.status = 'active'
                   ) as is_member,
                   -- Compter les amis dans le groupe
                   (SELECT COUNT(*) FROM group_members gm
                    JOIN friendships f ON (
                        (f.requester_id = gm.user_id AND f.addressee_id = :user_id1) OR
                        (f.addressee_id = gm.user_id AND f.requester_id = :user_id2)
                    )
                    WHERE gm.group_id = g.id AND gm.status = 'active' AND f.status = 'accepted'
                   ) as friends_in_group
            FROM groups g
            JOIN users u ON g.created_by = u.id
            WHERE g.privacy != 'secret'
            AND (g.name LIKE :search1 OR g.description LIKE :search2)";

    // Ajouter un filtre de date si nécessaire
    if ($date !== 'all') {
        if ($date === 'week') {
            $sql .= " AND g.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($date === 'month') {
            $sql .= " AND g.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
    }

    // Ajouter le tri
    if ($sort === 'relevance') {
        $sql .= " ORDER BY 
                    CASE 
                        WHEN LOWER(g.name) = LOWER(:query) THEN 1
                        WHEN LOWER(g.name) LIKE LOWER(:exact_query) THEN 2
                        ELSE 3
                    END,
                    friends_in_group DESC,
                    g.members_count DESC";
    } elseif ($sort === 'recent') {
        $sql .= " ORDER BY g.created_at DESC";
    } else {
        $sql .= " ORDER BY g.members_count DESC";
    }

    $sql .= " LIMIT :limit";

    $params = [
        'user_id' => $userId,
        'user_id1' => $userId,
        'user_id2' => $userId,
        'search1' => $searchTerm,
        'search2' => $searchTerm,
        'query' => $query,
        'exact_query' => "{$query}%",
        'limit' => $limit
    ];

    error_log("Requête searchGroups: $sql");
    error_log("Paramètres searchGroups: " . print_r($params, true));

    try {
        $groups = fetchAll($sql, $params);
    } catch (Exception $e) {
        error_log("Erreur dans searchGroups: " . $e->getMessage());
        throw $e;
    }

    echo json_encode([
        'success' => true,
        'results' => $groups,
        'type' => 'groups',
        'query' => $query
    ]);
}

function searchHashtags($query, $limit, $sort, $date) {
    global $db, $userId;

    $searchTerm = "%{$query}%";

    $sql = "SELECT h.id, h.tag, h.usage_count, h.created_at,
                   -- Nombre de posts récents (7 derniers jours)
                   (SELECT COUNT(*) FROM post_hashtags ph
                    JOIN posts p ON ph.post_id = p.id
                    WHERE ph.hashtag_id = h.id 
                    AND p.is_deleted = 0
                    AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                   ) as recent_posts_count
            FROM hashtags h
            WHERE h.tag LIKE :search";

    // Ajouter un filtre de date si nécessaire
    if ($date !== 'all') {
        if ($date === 'week') {
            $sql .= " AND h.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($date === 'month') {
            $sql .= " AND h.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
    }

    // Ajouter le tri
    if ($sort === 'relevance') {
        $sql .= " ORDER BY 
                    CASE 
                        WHEN LOWER(h.tag) = LOWER(:query) THEN 1
                        WHEN LOWER(h.tag) LIKE LOWER(:exact_query) THEN 2
                        ELSE 3
                    END,
                    recent_posts_count DESC,
                    h.usage_count DESC";
    } elseif ($sort === 'recent') {
        $sql .= " ORDER BY h.created_at DESC";
    } else {
        $sql .= " ORDER BY h.usage_count DESC";
    }

    $sql .= " LIMIT :limit";

    $params = [
        'search' => $searchTerm,
        'query' => $query,
        'exact_query' => "{$query}%",
        'limit' => $limit
    ];

    error_log("Requête searchHashtags: $sql");
    error_log("Paramètres searchHashtags: " . print_r($params, true));

    try {
        $hashtags = fetchAll($sql, $params);
    } catch (Exception $e) {
        error_log("Erreur dans searchHashtags: " . $e->getMessage());
        throw $e;
    }

    echo json_encode([
        'success' => true,
        'results' => $hashtags,
        'type' => 'hashtags',
        'query' => $query
    ]);
}

function searchAll($query, $limit, $sort, $date) {
    global $db, $userId;

    $limitPerType = max(1, intval($limit / 4));
    $searchTerm = "%{$query}%";

    // Utilisateurs
    $sqlUsers = "SELECT 'user' as result_type, u.id, u.username as title, 
                        CONCAT(u.first_name, ' ', u.last_name) as subtitle,
                        u.avatar as image, u.bio as description
                 FROM users u 
                 WHERE u.id != :user_id 
                 AND u.is_active = 1
                 AND (u.username LIKE :search1 OR u.first_name LIKE :search2 
                      OR u.last_name LIKE :search3)";

    if ($date !== 'all') {
        if ($date === 'week') {
            $sqlUsers .= " AND u.last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($date === 'month') {
            $sqlUsers .= " AND u.last_seen >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
    }

    $sqlUsers .= " ORDER BY u.last_seen DESC LIMIT :limit";

    $paramsUsers = [
        'user_id' => $userId,
        'search1' => $searchTerm,
        'search2' => $searchTerm,
        'search3' => $searchTerm,
        'limit' => $limitPerType
    ];

    error_log("Requête searchAll (users): $sqlUsers");
    error_log("Paramètres searchAll (users): " . print_r($paramsUsers, true));

    try {
        $users = fetchAll($sqlUsers, $paramsUsers);
    } catch (Exception $e) {
        error_log("Erreur dans searchAll (users): " . $e->getMessage());
        throw $e;
    }

    // Posts
    $sqlPosts = "SELECT 'post' as result_type, p.id, 
                        CONCAT(u.first_name, ' ', u.last_name) as title,
                        SUBSTRING(p.content, 1, 100) as subtitle,
                        COALESCE(p.image, u.avatar) as image,
                        p.content as description
                 FROM posts p
                 JOIN users u ON p.user_id = u.id
                 WHERE p.is_deleted = 0 
                 AND p.content LIKE :search
                 AND (p.privacy = 'public' OR p.user_id = :user_id OR
                      (p.privacy = 'friends' AND EXISTS(
                          SELECT 1 FROM friendships f 
                          WHERE ((f.requester_id = p.user_id AND f.addressee_id = :user_id1) 
                                 OR (f.requester_id = :user_id2 AND f.addressee_id = p.user_id))
                          AND f.status = 'accepted'
                      )))";

    if ($date !== 'all') {
        if ($date === 'week') {
            $sqlPosts .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($date === 'month') {
            $sqlPosts .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
    }

    $sqlPosts .= " ORDER BY p.created_at DESC LIMIT :limit";

    $paramsPosts = [
        'user_id' => $userId,
        'user_id1' => $userId,
        'user_id2' => $userId,
        'search' => $searchTerm,
        'limit' => $limitPerType
    ];

    error_log("Requête searchAll (posts): $sqlPosts");
    error_log("Paramètres searchAll (posts): " . print_r($paramsPosts, true));

    try {
        $posts = fetchAll($sqlPosts, $paramsPosts);
    } catch (Exception $e) {
        error_log("Erreur dans searchAll (posts): " . $e->getMessage());
        throw $e;
    }

    // Groupes
    $sqlGroups = "SELECT 'group' as result_type, g.id, g.name as title,
                         CONCAT(g.members_count, ' membres') as subtitle,
                         NULL as image,
                         g.description
                  FROM groups g
                  WHERE g.privacy != 'secret'
                  AND (g.name LIKE :search1 OR g.description LIKE :search2)";

    if ($date !== 'all') {
        if ($date === 'week') {
            $sqlGroups .= " AND g.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($date === 'month') {
            $sqlGroups .= " AND g.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
    }

    $sqlGroups .= " ORDER BY g.members_count DESC LIMIT :limit";

    $paramsGroups = [
        'search1' => $searchTerm,
        'search2' => $searchTerm,
        'limit' => $limitPerType
    ];

    error_log("Requête searchAll (groups): $sqlGroups");
    error_log("Paramètres searchAll (groups): " . print_r($paramsGroups, true));

    try {
        $groups = fetchAll($sqlGroups, $paramsGroups);
    } catch (Exception $e) {
        error_log("Erreur dans searchAll (groups): " . $e->getMessage());
        throw $e;
    }

    // Hashtags
    $sqlHashtags = "SELECT 'hashtag' as result_type, h.id, 
                           CONCAT('#', h.tag) as title,
                           CONCAT(h.usage_count, ' utilisations') as subtitle,
                           NULL as image, h.tag as description
                    FROM hashtags h
                    WHERE h.tag LIKE :search";

    if ($date !== 'all') {
        if ($date === 'week') {
            $sqlHashtags .= " AND h.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($date === 'month') {
            $sqlHashtags .= " AND h.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
    }

    $sqlHashtags .= " ORDER BY h.usage_count DESC LIMIT :limit";

    $paramsHashtags = [
        'search' => $searchTerm,
        'limit' => $limitPerType
    ];

    error_log("Requête searchAll (hashtags): $sqlHashtags");
    error_log("Paramètres searchAll (hashtags): " . print_r($paramsHashtags, true));

    try {
        $hashtags = fetchAll($sqlHashtags, $paramsHashtags);
    } catch (Exception $e) {
        error_log("Erreur dans searchAll (hashtags): " . $e->getMessage());
        throw $e;
    }

    // Combiner tous les résultats
    $allResults = array_merge($users, $posts, $groups, $hashtags);

    // Trier par pertinence si sort=relevance
    if ($sort === 'relevance') {
        usort($allResults, function($a, $b) use ($query) {
            $aScore = calculateRelevanceScore($a, $query);
            $bScore = calculateRelevanceScore($b, $query);
            return $bScore - $aScore;
        });
    } else {
        // Tri par date récente
        usort($allResults, function($a, $b) {
            $timeField = $a['result_type'] === 'user' ? 'last_seen' : 'created_at';
            $aTime = isset($a[$timeField]) ? strtotime($a[$timeField]) : 0;
            $bTime = isset($b[$timeField]) ? strtotime($b[$timeField]) : 0;
            return $bTime - $aTime;
        });
    }

    $results = array_slice($allResults, 0, $limit);

    echo json_encode([
        'success' => true,
        'results' => $results,
        'type' => 'all',
        'query' => $query,
        'counts' => [
            'users' => count($users),
            'posts' => count($posts),
            'groups' => count($groups),
            'hashtags' => count($hashtags)
        ]
    ]);
}

function calculateRelevanceScore($result, $query) {
    $score = 0;
    $queryLower = strtolower($query);

    // Score basé sur le titre
    $titleLower = strtolower($result['title']);
    if ($titleLower === $queryLower) {
        $score += 100;
    } elseif (strpos($titleLower, $queryLower) === 0) {
        $score += 75;
    } elseif (strpos($titleLower, $queryLower) !== false) {
        $score += 50;
    }

    // Score basé sur le sous-titre
    if (isset($result['subtitle'])) {
        $subtitleLower = strtolower($result['subtitle']);
        if (strpos($subtitleLower, $queryLower) !== false) {
            $score += 25;
        }
    }

    // Bonus par type de résultat
    switch ($result['result_type']) {
        case 'user':
            $score += 20;
            break;
        case 'group':
            $score += 15;
            break;
        case 'post':
            $score += 10;
            break;
        case 'hashtag':
            $score += 5;
            break;
    }

    return $score;
}
?>