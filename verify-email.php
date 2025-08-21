<?php
/**
 * verify-email.php - Page pour vérifier l'email d'un utilisateur
 * Met à jour is_verified dans la table users et affiche un message de succès ou d'erreur
 */

require_once 'config/database.php';
require_once 'config/session.php';

// Initialiser la session et la base de données
SessionManager::start();
$db = initDatabase();

// Récupérer le token depuis l'URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    $message = "Erreur : Token manquant";
    $isSuccess = false;
} else {
    try {
        // Vérifier le token dans la table email_tokens
        $tokenData = $db->fetchOne(
            "SELECT user_id, expires_at FROM email_tokens 
             WHERE token = :token AND type = 'verification'",
            ['token' => $token]
        );

        if (!$tokenData) {
            throw new Exception('Token invalide ou expiré');
        }

        // Vérifier si le token a expiré
        if (strtotime($tokenData['expires_at']) < time()) {
            $db->delete('email_tokens', 'token = :token', ['token' => $token]);
            throw new Exception('Le lien de vérification a expiré');
        }

        // Démarrer une transaction pour assurer l'intégrité
        $db->beginTransaction();

        // Mettre à jour is_verified dans la table users
        $updated = $db->update(
            'users',
            ['is_verified' => true],
            'id = :id',
            ['id' => $tokenData['user_id']]
        );

        if (!$updated) {
            throw new Exception('Échec de la mise à jour de l\'utilisateur');
        }

        // Supprimer le token utilisé
        $db->delete('email_tokens', 'token = :token', ['token' => $token]);

        // Valider la transaction
        $db->commit();

        $message = "Votre email a été vérifié avec succès ! Vous pouvez maintenant vous connecter.";
        $isSuccess = true;

        // Journaliser le succès
        error_log("Vérification email réussie pour user_id: " . $tokenData['user_id']);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        $message = "Erreur : " . $e->getMessage();
        $isSuccess = false;
        error_log("Erreur vérification email: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification d'email - IDEM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
        }
        .container {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 400px;
        }
        .success {
            color: #28a745;
            font-size: 1.2em;
            margin-bottom: 20px;
        }
        .error {
            color: #dc3545;
            font-size: 1.2em;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 1em;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<div class="container">
    <?php if ($isSuccess): ?>
        <div class="success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <a href="index.php" class="btn">Retour à la connexion</a>
    <?php else: ?>
        <div class="error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <a href="index.php" class="btn">Retour à l'accueil</a>
    <?php endif; ?>
</div>
</body>
</html>