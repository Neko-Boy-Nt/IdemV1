<?php
/**
 * Serveur de test pour IDEM
 * Simulation d'un serveur web simple pour les tests locaux
 */

// Démarrer la session et inclure les dépendances
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

$pageTitle = "Serveur IDEM";
$pageDescription = "Interface de gestion du serveur IDEM";
$bodyClass = "server-page";

// Informations du serveur
$serverInfo = [
    'status' => 'running',
    'uptime' => time() - filemtime(__FILE__),
    'version' => '1.0.0',
    'environment' => DEVELOPMENT ? 'development' : 'production',
    'database' => false,
    'sessions' => session_status() === PHP_SESSION_ACTIVE,
    'memory_usage' => memory_get_usage(true),
    'memory_peak' => memory_get_peak_usage(true)
];
// Dans server.php
$wsStatus = @fsockopen('localhost', 8080)
        ? ['status' => 'running', 'clients' => file_get_contents('http://localhost:8080/clients')]
        : ['status' => 'stopped'];

$serverInfo['websocket'] = $wsStatus;
// Test de la connexion base de données
try {
    $db = initDatabase();
    $serverInfo['database'] = true;
} catch (Exception $e) {
    $serverInfo['database'] = false;
    $serverInfo['db_error'] = $e->getMessage();
}

// En-têtes pour API JSON si demandé
if (isset($_GET['api']) && $_GET['api'] === 'status') {
    header('Content-Type: application/json');
    echo json_encode($serverInfo);
    exit;
}

include 'includes/header.php';
?>

<div class="server-container">
    <div class="server-header">
        <h1>🖥️ Serveur IDEM</h1>
        <p>Interface de monitoring et gestion du serveur</p>
    </div>

    <div class="server-grid">
        <!-- Statut général -->
        <div class="server-card">
            <div class="card-header">
                <h3>Statut du système</h3>
                <span class="status-badge <?= $serverInfo['status'] ?>">
                    <?= ucfirst($serverInfo['status']) ?>
                </span>
            </div>
            <div class="card-content">
                <div class="info-row">
                    <span class="label">Version :</span>
                    <span class="value"><?= $serverInfo['version'] ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Environnement :</span>
                    <span class="value"><?= $serverInfo['environment'] ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Uptime :</span>
                    <span class="value"><?= formatDuration($serverInfo['uptime']) ?></span>
                </div>
            </div>
        </div>

        <!-- Base de données -->
        <div class="server-card">
            <div class="card-header">
                <h3>Base de données</h3>
                <span class="status-badge <?= $serverInfo['database'] ? 'running' : 'error' ?>">
                    <?= $serverInfo['database'] ? 'Connectée' : 'Erreur' ?>
                </span>
            </div>
            <div class="card-content">
                <?php if ($serverInfo['database']): ?>
                <div class="info-row">
                    <span class="label">Host :</span>
                    <span class="value"><?= DB_HOST ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Base :</span>
                    <span class="value"><?= DB_NAME ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Charset :</span>
                    <span class="value"><?= DB_CHARSET ?></span>
                </div>
                <?php else: ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= $serverInfo['db_error'] ?? 'Connexion impossible' ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sessions -->
        <div class="server-card">
            <div class="card-header">
                <h3>Sessions</h3>
                <span class="status-badge <?= $serverInfo['sessions'] ? 'running' : 'error' ?>">
                    <?= $serverInfo['sessions'] ? 'Actives' : 'Inactives' ?>
                </span>
            </div>
            <div class="card-content">
                <div class="info-row">
                    <span class="label">Support :</span>
                    <span class="value"><?= function_exists('session_start') ? 'Oui' : 'Non' ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Statut :</span>
                    <span class="value">
                        <?php
                        switch (session_status()) {
                            case PHP_SESSION_DISABLED: echo 'Désactivées'; break;
                            case PHP_SESSION_NONE: echo 'Aucune'; break;
                            case PHP_SESSION_ACTIVE: echo 'Active'; break;
                            default: echo 'Inconnu';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Mémoire -->
        <div class="server-card">
            <div class="card-header">
                <h3>Mémoire</h3>
                <span class="status-badge running">Normal</span>
            </div>
            <div class="card-content">
                <div class="info-row">
                    <span class="label">Utilisée :</span>
                    <span class="value"><?= formatBytes($serverInfo['memory_usage']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Pic :</span>
                    <span class="value"><?= formatBytes($serverInfo['memory_peak']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Limite :</span>
                    <span class="value"><?= ini_get('memory_limit') ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="server-actions">
        <button onclick="location.reload()" class="btn btn-primary">
            <i class="fas fa-sync"></i> Actualiser
        </button>
        <a href="start.php" class="btn btn-secondary">
            <i class="fas fa-check-circle"></i> Vérifications système
        </a>
        <a href="?api=status" class="btn btn-info" target="_blank">
            <i class="fas fa-code"></i> API JSON
        </a>
    </div>
</div>

<style>
.server-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.server-header {
    text-align: center;
    margin-bottom: 2rem;
}

.server-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.server-card {
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border-color);
}

.card-header h3 {
    margin: 0;
    font-size: 1.1rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.running {
    background: #d4edda;
    color: #155724;
}

.status-badge.error {
    background: #f8d7da;
    color: #721c24;
}

.card-content {
    padding: 1rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.info-row:last-child {
    margin-bottom: 0;
}

.label {
    color: var(--text-secondary);
    font-weight: 500;
}

.value {
    color: var(--text-primary);
    font-family: var(--font-mono);
    font-size: 0.9rem;
}

.error-message {
    color: var(--danger-color);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.server-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-secondary {
    background: var(--gray-500);
    color: white;
}

.btn-info {
    background: var(--info-color);
    color: white;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

@media (max-width: 768px) {
    .server-grid {
        grid-template-columns: 1fr;
    }
    
    .server-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .btn {
        width: 100%;
        max-width: 200px;
        justify-content: center;
    }
}
</style>

<?php include 'includes/footer.php'; ?>

<?php
/**
 * Fonctions utilitaires pour le serveur
 */
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf('%dh %02dm %02ds', $hours, $minutes, $seconds);
    } elseif ($minutes > 0) {
        return sprintf('%dm %02ds', $minutes, $seconds);
    } else {
        return sprintf('%ds', $seconds);
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>