<?php
/**
 * Page de recherche IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

SessionManager::start();

$pageTitle = "Recherche";
$pageDescription = "Recherchez des amis, des posts et du contenu sur IDEM";
$bodyClass = "search-page";

$query = sanitize($_GET['q'] ?? '');
$type = sanitize($_GET['type'] ?? 'all');

include 'includes/header.php';
?>

<div class="search-container">
    <div class="search-header">
        <h1>Résultats de recherche</h1>
        <?php if ($query): ?>
        <p>Recherche pour : "<strong><?= htmlspecialchars($query) ?></strong>"</p>
        <?php endif; ?>
    </div>
    
    <div class="search-filters">
        <a href="?q=<?= urlencode($query) ?>&type=all" class="filter-btn <?= $type === 'all' ? 'active' : '' ?>">Tout</a>
        <a href="?q=<?= urlencode($query) ?>&type=users" class="filter-btn <?= $type === 'users' ? 'active' : '' ?>">Utilisateurs</a>
        <a href="?q=<?= urlencode($query) ?>&type=posts" class="filter-btn <?= $type === 'posts' ? 'active' : '' ?>">Posts</a>
        <a href="?q=<?= urlencode($query) ?>&type=groups" class="filter-btn <?= $type === 'groups' ? 'active' : '' ?>">Groupes</a>
    </div>
    
    <div class="search-results" id="search-results">
        <div class="loading">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Recherche en cours...</span>
        </div>
    </div>
</div>

<script>
// Simulation de recherche
document.addEventListener('DOMContentLoaded', function() {
    const resultsContainer = document.getElementById('search-results');
    
    setTimeout(() => {
        resultsContainer.innerHTML = `
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>Aucun résultat trouvé</h3>
                <p>Essayez avec d'autres mots-clés ou vérifiez l'orthographe.</p>
            </div>
        `;
    }, 1000);
});
</script>

<?php include 'includes/footer.php'; ?>