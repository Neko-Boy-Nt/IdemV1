<?php
/**
 * Fonctions utilitaires pour IDEM
 */

/**
 * Sécurisation et validation des données
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    // Au moins 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
}

function validateUsername($username) {
    // 3-30 caractères, alphanumériques et underscore uniquement
    return preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username);
}

/**
 * Gestion des mots de passe
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Upload de fichiers
 */
function uploadImage($file, $destination = 'uploads/images/') {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception("Type de fichier non autorisé");
    }

    if ($file['size'] > $maxSize) {
        throw new Exception("Fichier trop volumineux (max 5MB)");
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $destination . $filename;

    // Créer le dossier s'il n'existe pas
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Redimensionner l'image si nécessaire
        resizeImageFile($filepath, 800, 600);
        return $filename;
    }

    throw new Exception("Erreur lors de l'upload");
}

function resizeImageFile($filepath, $maxWidth = 800, $maxHeight = 600) {
    $imageInfo = getimagesize($filepath);
    if (!$imageInfo) return false;

    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $type = $imageInfo[2];

    // Calculer les nouvelles dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);

    // Créer l'image source
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($filepath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($filepath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($filepath);
            break;
        default:
            return false;
    }

    // Créer la nouvelle image
    $destination = imagecreatetruecolor($newWidth, $newHeight);

    // Préserver la transparence pour PNG et GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Sauvegarder la nouvelle image
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($destination, $filepath, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($destination, $filepath);
            break;
        case IMAGETYPE_GIF:
            imagegif($destination, $filepath);
            break;
    }

    imagedestroy($source);
    imagedestroy($destination);

    return true;
}

/**
 * Formatage des dates et temps
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);

    if ($time < 60) return 'il y a quelques secondes';
    if ($time < 3600) return 'il y a ' . floor($time/60) . ' minutes';
    if ($time < 86400) return 'il y a ' . floor($time/3600) . ' heures';
    if ($time < 2592000) return 'il y a ' . floor($time/86400) . ' jours';
    if ($time < 31104000) return 'il y a ' . floor($time/2592000) . ' mois';

    return 'il y a ' . floor($time/31104000) . ' ans';
}

function formatDate($date, $format = 'd/m/Y à H:i') {
    return date($format, strtotime($date));
}

/**
 * Gestion des notifications
 */
function createNotification($userId, $type, $message, $relatedId = null, $relatedUserId = null) {
    try {
        $result = executeQuery(
            "INSERT INTO notifications (user_id, type, message, related_id, related_user_id, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$userId, $type, $message, $relatedId, $relatedUserId]
        );
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

function getUnreadNotificationsCount($userId) {
    try {
        $result = fetchOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Traitement du contenu
 */
function processPostContent($content) {
    // Échapper le HTML
    $content = htmlspecialchars(trim($content), ENT_QUOTES, 'UTF-8');

    // Traiter les hashtags
    $content = preg_replace('/#(\w+)/', '<a href="search.php?hashtag=$1" class="hashtag">#$1</a>', $content);

    // Traiter les mentions
    $content = preg_replace('/@(\w+)/', '<a href="profile.php?user=$1" class="mention">@$1</a>', $content);

    // Traiter les liens
    $content = preg_replace(
        '/(https?:\/\/[^\s]+)/',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
        $content
    );

    // Convertir les retours à la ligne
    $content = nl2br($content);

    return $content;
}

function extractHashtags($content) {
    preg_match_all('/#(\w+)/', $content, $matches);
    return $matches[1] ?? [];
}

function extractMentions($content) {
    preg_match_all('/@(\w+)/', $content, $matches);
    return $matches[1] ?? [];
}

/**
 * Vérification des permissions
 */
function canViewProfile($viewerUserId, $profileUserId) {
    if ($viewerUserId == $profileUserId) return true;

    try {
        // Récupérer les paramètres de confidentialité
        $profile = fetchOne(
            "SELECT privacy_level FROM users WHERE id = ?",
            [$profileUserId]
        );

        if (!$profile) return false;

        switch ($profile['privacy_level']) {
            case 'public':
                return true;
            case 'friends':
                return areFriends($viewerUserId, $profileUserId);
            case 'private':
                return false;
            default:
                return false;
        }
    } catch (Exception $e) {
        return false;
    }
}

function areFriends($userId1, $userId2) {
    try {
        $result = fetchOne(
            "SELECT id FROM friendships 
             WHERE ((requester_id = ? AND addressee_id = ?) 
                    OR (requester_id = ? AND addressee_id = ?))
             AND status = 'accepted'",
            [$userId1, $userId2, $userId2, $userId1]
        );
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Pagination
 */
function paginate($page, $totalItems, $itemsPerPage = 10) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $itemsPerPage;

    return [
        'page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'offset' => $offset,
        'has_previous' => $page > 1,
        'has_next' => $page < $totalPages
    ];
}

/**
 * Génération de tokens sécurisés
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function generateEmailToken($userId, $type = 'verification') {
    try {
        $token = generateToken();

        // Supprimer les anciens tokens
        executeQuery(
            "DELETE FROM email_tokens WHERE user_id = ? AND type = ?",
            [$userId, $type]
        );

        // Créer le nouveau token
        executeQuery(
            "INSERT INTO email_tokens (user_id, token, type, expires_at, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [$userId, $token, $type, date('Y-m-d H:i:s', strtotime('+24 hours'))]
        );

        return $token;
    } catch (Exception $e) {
        error_log("Erreur génération token: " . $e->getMessage());
        return false;
    }
}

/**
 * Redirection sécurisée
 */
function redirect($url, $code = 302) {
    // Vérifier que l'URL est sûre (relative ou même domaine)
    if (filter_var($url, FILTER_VALIDATE_URL) === false && !str_starts_with($url, '')) {
        $url = '';
    }

    header("Location: $url", true, $code);
    exit();
}

?>