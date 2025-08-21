<?php
/**
 * Configuration générale de l'application IDEM
 */

// Mode développement
define('DEVELOPMENT', true);

// Configuration du site
define('SITE_NAME', 'IDEM');
define('SITE_URL', 'http://localhost/idem');
define('SITE_DESCRIPTION', 'Plateforme sociale moderne et sécurisée');

// Configuration des chemins
define('ROOT_PATH', dirname(__DIR__));
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('UPLOADS_URL', '/uploads');

// Configuration des uploads
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'webm', 'ogg']);
define('ALLOWED_AUDIO_TYPES', ['mp3', 'wav', 'ogg']);

// Configuration de la sécurité
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Configuration de l'email
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@idem.local');
define('SMTP_FROM_NAME', 'IDEM');

// Configuration de la pagination
define('POSTS_PER_PAGE', 20);
define('NOTIFICATIONS_PER_PAGE', 50);
define('MESSAGES_PER_PAGE', 30);

// Configuration des cookies
define('COOKIE_DOMAIN', '');
define('COOKIE_PATH', '/');
define('COOKIE_SECURE', false); // Mettre à true en HTTPS
define('COOKIE_HTTPONLY', true);

// Configuration du cache
define('CACHE_ENABLED', false);
define('CACHE_LIFETIME', 3600); // 1 heure

// Configuration des API externes
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('FACEBOOK_APP_ID', '');
define('FACEBOOK_APP_SECRET', '');

// Configuration des notifications push
define('PUSH_NOTIFICATIONS_ENABLED', false);
define('VAPID_PUBLIC_KEY', '');
define('VAPID_PRIVATE_KEY', '');

// Configuration du WebSocket
define('WEBSOCKET_ENABLED', false);
define('WEBSOCKET_HOST', 'localhost');
define('WEBSOCKET_PORT', 8080);

// Timezone
date_default_timezone_set('Afrique/Bénin');

// Configuration des erreurs
if (DEVELOPMENT) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/logs/error.log');
}

// Fonctions d'autoload
spl_autoload_register(function ($class) {
    $file = ROOT_PATH . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Headers de sécurité
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if (!DEVELOPMENT) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}