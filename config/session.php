<?php
/**
 * Gestionnaire de sessions IDEM
 */

class SessionManager {

    /**
     * Démarre la session de façon sécurisée
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Vérifie si l'utilisateur est connecté
     * @return bool
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Récupère l'ID de l'utilisateur connecté
     * @return int|null
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    /**
     * Récupère les données utilisateur pour JavaScript
     * @return array
     */
    public static function getUser() {
        if (!self::isLoggedIn()) {
            return null;
        }

        $db = initDatabase();
        try {
            $stmt = $db->prepare("SELECT id, first_name, last_name, avatar FROM users WHERE id = :id");
            $stmt->execute(['id' => self::getUserId()]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (Exception $e) {
            error_log("Erreur récupération utilisateur: " . $e->getMessage());
            return null;
        }
    }
    public static function getJsUserData() {
        $user = self::getCurrentUser();
        return [
            'id' => $user['id'] ?? 0,
            'username' => $user['username'] ?? 'guest',
            'avatar' => $user['avatar'] ?? 'default.jpg'
        ];
    }
    /**
     * Récupère le nom d'utilisateur de l'utilisateur connecté
     * @return string|null
     */
    public static function getUsername() {
        $user = self::getCurrentUser();
        return $user ? $user['username'] : null;
    }

    /**
     * Récupère les informations de l'utilisateur connecté
     * @return array|null
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }

        if (!isset($_SESSION['user_data'])) {
            // Charger les données utilisateur depuis la base
            $user = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
            if ($user) {
                $_SESSION['user_data'] = $user;
            } else {
                // Utilisateur supprimé, déconnecter
                self::logout();
                return null;
            }
        }

        return $_SESSION['user_data'];
    }

    /**
     * Connecte un utilisateur
     * @param array $user
     */
    public static function login($userId) {
        global $db;
        self::start();
        $_SESSION['user_id'] = $userId;
        $sessionId = session_id();
        // Stocker session_id dans la base de données
        try {
            $db->beginTransaction();
            $existing = fetchOne('SELECT id FROM user_sessions WHERE user_id = ?', [$userId]);
            if ($existing) {
                update('user_sessions', [
                    'session_id' => $sessionId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'expires_at' => date('Y-m-d H:i:s', time() + 86400)
                ], 'user_id = ?', [$userId]);
            } else {
                insert('user_sessions', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'expires_at' => date('Y-m-d H:i:s', time() + 86400)
                ]);
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Erreur stockage session: " . $e->getMessage());
        }
        session_regenerate_id(true);
    }

    /**
     * Déconnecte l'utilisateur
     */
    public static function logout() {
        global $db;
        self::start();
        $userId = self::getUserId();
        if ($userId) {
            delete('user_sessions', 'user_id = :user', ['user' => $userId]);
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Redirige vers la page de connexion si non connecté
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            redirect('index.php?message=login_required');
        }
    }

    /**
     * Génère un token CSRF
     * @return string
     */
    public static function generateCsrfToken() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Récupère le token CSRF
     * @return string
     */
    public static function getCsrfToken() {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Vérifie le token CSRF
     * @param string $token
     * @return bool
     */

    public static function validateCsrfToken($token) {
        self::start();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Définit un message flash
     * @param string $message
     * @param string $type (success, error, warning, info)
     */
    public static function setFlash($message, $type = 'info') {
        $_SESSION['flash_messages'][] = [
            'message' => $message,
            'type' => $type
        ];
    }

    /**
     * Récupère et efface les messages flash
     * @return array
     */
    public static function getFlashes() {
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $messages;
    }

    /**
     * Vérifie la validité de la session
     * @return bool
     */
    public static function validateSession($userId, $sessionId) {
        global $db;
        $session = fetchOne(
            'SELECT * FROM user_sessions WHERE user_id = ? AND session_id = ? AND expires_at > NOW()',
            [$userId, $sessionId]
        );
        return $session !== null;
    }

    /**
     * Récupère une donnée de session
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Définit une donnée de session
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    /**
     * Supprime une donnée de session
     * @param string $key
     */
    public static function remove($key) {
        unset($_SESSION[$key]);
    }
}