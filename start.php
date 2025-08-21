<?php
/**
 * Script de démarrage pour vérifier la configuration IDEM
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

$pageTitle = "Vérification système - IDEM";
$pageDescription = "Vérification de la configuration du système IDEM";
$bodyClass = "start-page";

// Vérifications système
$checks = [
    'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'session_support' => function_exists('session_start'),
    'database_config' => defined('DB_HOST') && defined('DB_NAME'),
    'upload_dirs' => is_dir('uploads') && is_writable('uploads'),
    'config_files' => file_exists('config/database.php') && file_exists('config/session.php')
];

$allGood = array_reduce($checks, function($carry, $check) { return $carry && $check; }, true);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .start-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .check-item.success {
            background: #d4edda;
            color: #155724;
        }
        .check-item.error {
            background: #f8d7da;
            color: #721c24;
        }
        .check-icon {
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        .actions {
            margin-top: 2rem;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 0 0.5rem;
        }
        .btn:hover {
            background: #5a67d8;
        }
        .btn.secondary {
            background: #6c757d;
        }
    </style>
</head>
<body class="<?= $bodyClass ?>">
    <div class="start-container">
        <h1>🚀 Configuration IDEM</h1>
        <p>Vérification de la configuration du système avant le démarrage.</p>
        
        <div class="checks">
            <div class="check-item <?= $checks['php_version'] ? 'success' : 'error' ?>">
                <span class="check-icon"><?= $checks['php_version'] ? '✅' : '❌' ?></span>
                <div>
                    <strong>Version PHP</strong><br>
                    Actuelle: <?= PHP_VERSION ?> (Requis: 7.4.0+)
                </div>
            </div>
            
            <div class="check-item <?= $checks['session_support'] ? 'success' : 'error' ?>">
                <span class="check-icon"><?= $checks['session_support'] ? '✅' : '❌' ?></span>
                <div>
                    <strong>Support des sessions</strong><br>
                    <?= $checks['session_support'] ? 'Disponible' : 'Non disponible' ?>
                </div>
            </div>
            
            <div class="check-item <?= $checks['database_config'] ? 'success' : 'error' ?>">
                <span class="check-icon"><?= $checks['database_config'] ? '✅' : '❌' ?></span>
                <div>
                    <strong>Configuration base de données</strong><br>
                    <?= $checks['database_config'] ? 'Configurée' : 'Non configurée' ?>
                </div>
            </div>
            
            <div class="check-item <?= $checks['upload_dirs'] ? 'success' : 'error' ?>">
                <span class="check-icon"><?= $checks['upload_dirs'] ? '✅' : '❌' ?></span>
                <div>
                    <strong>Dossiers d'upload</strong><br>
                    <?= $checks['upload_dirs'] ? 'Configurés et accessibles' : 'Non configurés ou inaccessibles' ?>
                </div>
            </div>
            
            <div class="check-item <?= $checks['config_files'] ? 'success' : 'error' ?>">
                <span class="check-icon"><?= $checks['config_files'] ? '✅' : '❌' ?></span>
                <div>
                    <strong>Fichiers de configuration</strong><br>
                    <?= $checks['config_files'] ? 'Présents' : 'Manquants' ?>
                </div>
            </div>
        </div>
        
        <?php if ($allGood): ?>
        <div class="check-item success">
            <span class="check-icon">🎉</span>
            <div>
                <strong>Système prêt !</strong><br>
                Toutes les vérifications sont passées avec succès.
            </div>
        </div>
        
        <div class="actions">
            <a href="index.php" class="btn">🏠 Aller à l'accueil</a>
            <a href="register.php" class="btn secondary">📝 S'inscrire</a>
        </div>
        
        <?php else: ?>
        <div class="check-item error">
            <span class="check-icon">⚠️</span>
            <div>
                <strong>Configuration incomplète</strong><br>
                Veuillez corriger les erreurs ci-dessus avant de continuer.
            </div>
        </div>
        
        <div class="actions">
            <button onclick="location.reload()" class="btn">🔄 Vérifier à nouveau</button>
        </div>
        <?php endif; ?>
        
        <hr style="margin: 2rem 0; border: none; border-top: 1px solid #dee2e6;">
        
        <h3>ℹ️ Informations système</h3>
        <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px; font-family: monospace; font-size: 0.9rem;">
            <strong>PHP:</strong> <?= PHP_VERSION ?><br>
            <strong>Serveur:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu' ?><br>
            <strong>Système:</strong> <?= PHP_OS ?><br>
            <strong>Mémoire:</strong> <?= ini_get('memory_limit') ?><br>
            <strong>Upload max:</strong> <?= ini_get('upload_max_filesize') ?><br>
            <strong>Timezone:</strong> <?= date_default_timezone_get() ?>
        </div>
    </div>
</body>
</html>