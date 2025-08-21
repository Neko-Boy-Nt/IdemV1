<?php
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/Exception.php';
require_once '../PHPMailer/src/SMTP.php';
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

SessionManager::start();
$db = initDatabase();

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'idemahdk@gmail.com';
        $mail->Password = 'pbdyppedjhobwznc';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->setFrom('idemahdk@gmail.com', 'IDEM');
        $mail->addAddress($to);
        $mail->isHTML(false);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $body;
        return $mail->send();
    } catch (Exception $e) {
        error_log("Échec de l'envoi de l'email: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST ?: json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'login':
                handleLogin($input);
                break;
            case 'register':
                handleRegister($input);
                break;
            case 'logout':
                handleLogout();
                break;
            default:
                throw new Exception('Action non reconnue');
        }
    } catch (Exception $e) {
        error_log("Erreur API auth: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

function handleLogin($data) {
    global $db;

    $requiredFields = ['login-email', 'password'];
    foreach ($requiredFields as $field) {
        if (empty(trim($data[$field] ?? ''))) {
            throw new Exception('Tous les champs sont requis');
        }
    }

    $email = trim($data['login-email']);
    $password = $data['password'];

    $user = fetchOne(
        'SELECT id, password, is_verified, is_active FROM users WHERE email = ?',
        [$email]
    );

    if (!$user || !verifyPassword($password, $user['password'])) {
        throw new Exception('Email ou mot de passe incorrect');
    }

    if (!$user['is_verified']) {
        throw new Exception('Compte non vérifié. Vérifiez votre email.');
    }

    if (!$user['is_active']) {
        throw new Exception('Compte désactivé');
    }

    SessionManager::login($user['id']);
    echo json_encode([
        'success' => true,
        'redirect' => 'feed.php'
    ]);
}

function handleRegister($data) {
    global $db;

    $requiredFields = ['username', 'email', 'password', 'confirm_password', 'first_name', 'last_name'];
    foreach ($requiredFields as $field) {
        if (empty(trim($data[$field] ?? ''))) {
            throw new Exception('Tous les champs sont requis');
        }
    }

    if ($data['password'] !== $data['confirm_password']) {
        throw new Exception('Les mots de passe ne correspondent pas');
    }

    if (!validateEmail($data['email'])) {
        throw new Exception('Email invalide');
    }

    if (!validateUsername($data['username'])) {
        throw new Exception('Nom d\'utilisateur invalide');
    }

    if (!validatePassword($data['password'])) {
        throw new Exception('Mot de passe trop faible');
    }

    beginTransaction();

    try {
        $userId = insert('users', [
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => hashPassword($data['password']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'is_verified' => 0,
            'is_active' => 1
        ]);

        $verificationToken = generateEmailToken($userId, 'verification');
        $verificationLink = "https://".$_SERVER['HTTP_HOST']."/verify-email.php?token=$verificationToken";

        if (!sendEmail(
            $data['email'],
            "Vérification de votre compte",
            "Cliquez sur ce lien pour vérifier votre compte: $verificationLink"
        )) {
            throw new Exception('Échec de l\'envoi de l\'email');
        }

        commit();

        echo json_encode([
            'success' => true,
            'message' => 'Compte créé - Vérifiez votre email'
        ]);
    } catch (Exception $e) {
        rollback();
        throw $e;
    }
}

function handleLogout() {
    SessionManager::logout();
    echo json_encode([
        'success' => true,
        'message' => 'Déconnexion réussie',
        'redirect' => 'index.php'
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['verify_email'])) {
    $token = $_GET['token'] ?? '';

    try {
        $tokenData = fetchOne(
            "SELECT user_id FROM email_tokens 
             WHERE token = ? AND type = 'verification' AND expires_at > NOW()",
            [$token]
        );

        if (!$tokenData) {
            throw new Exception('Lien invalide ou expiré');
        }

        beginTransaction();

        update('users', ['is_verified' => 1], 'id = ?', [$tokenData['user_id']]);
        delete('email_tokens', 'token = ?', [$token]);

        commit();

        echo json_encode(['success' => true, 'message' => 'Email vérifié']);
    } catch (Exception $e) {
        rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}