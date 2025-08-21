<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - IDEM' : 'IDEM - Réseau Social'; ?></title>
    <meta name="description" content="<?php echo isset($pageDescription) ? $pageDescription : 'IDEM - Un réseau social moderne pour connecter les gens'; ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/logo.png">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=1.2">
    <link rel="stylesheet" href="assets/css/responsive.css">
    
    <!-- Fonts -->
<!--    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">-->
    
    <!-- Icons -->
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    
    <!-- Meta tags pour réseaux sociaux -->
    <meta property="og:title" content="<?php echo isset($pageTitle) ? $pageTitle . ' - IDEM' : 'IDEM - Réseau Social'; ?>">
    <meta property="og:description" content="<?php echo isset($pageDescription) ? $pageDescription : 'Un réseau social moderne pour connecter les gens'; ?>">
    <meta property="og:image" content="assets/images/og-image.jpg">
    <meta property="og:type" content="website">

    <!-- CSRF Token pour JavaScript -->
    <?php if (SessionManager::isLoggedIn()): ?>
    <meta name="session-id" content="<?php echo session_id(); ?>">
    <meta name="csrf-token" content="<?php echo SessionManager::getCsrfToken(); ?>">
        <meta name="user-first-name" content="<?php echo htmlspecialchars(SessionManager::getUser()['first_name'] ?? ''); ?>">
        <meta name="user-last-name" content="<?php echo htmlspecialchars(SessionManager::getUser()['last_name'] ?? ''); ?>">
    <?php endif; ?>
</head>
<body class="<?php echo isset($bodyClass) ? $bodyClass : ''; ?>">
    
    <!-- Messages flash -->
    <?php 
    $flashMessages = SessionManager::getFlashes();
    if (!empty($flashMessages)):
    ?>
    <div class="flash-messages">
        <?php foreach ($flashMessages as $flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>" role="alert">
            <span><?php echo sanitize($flash['message']); ?></span>
            <button type="button" class="close-alert">&times;</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (SessionManager::isLoggedIn()): ?>
    <!-- Navigation principale -->
    <nav class="navbar">
        <div class="nav-container">
            <!-- Logo -->
            <div class="nav-brand">
                <a href="feed.php">
                    <img src="assets/images/logo.png" alt="IDEM" class="logo">
                    <span class="brand-text">IDEM</span>
                </a>
            </div>
            
            <!-- Barre de recherche -->
            <div class="nav-search">
                <form action="search.php" method="GET" class="search-form">
                    <input type="text" name="q" placeholder="Rechercher des amis, des posts..." class="search-input" id="global-search">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
                <div id="search-results" class="search-dropdown"></div>
            </div>
            
            <!-- Menu navigation -->
            <div class="nav-menu">
                <a href="feed.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'feed.php') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Accueil</span>
                </a>
                <a href="friends.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'friends.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-friends"></i>
                    <span>Amis</span>
                </a>
                <a href="messages.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'messages.php') ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i>
                    <span>Messages</span>
                    <?php 
                    // TODO: Compter les messages non lus
                    $unreadMessages = 0; // getUnreadMessagesCount(SessionManager::getUserId());
                    if ($unreadMessages > 0): 
                    ?>
                    <span class="badge"><?php echo $unreadMessages; ?></span>
                    <?php endif; ?>
                </a>
                <a href="groups.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'groups.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Groupes</span>
                </a>
                <a href="notifications.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'notifications.php') ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <span class="badge" id="notifications-badge" style="display: none;">0</span>
                </a>
            </div>
            
            <!-- Menu utilisateur -->
            <div class="nav-user">
                <div class="user-dropdown">
                    <button class="user-btn" id="user-menu-btn">
                        <?php 
                        $currentUser = SessionManager::getCurrentUser();
                        $avatar = $currentUser['avatar'] ?? 'default-avatar.svg';
                        ?>
                        <img src="uploads/avatars/<?php echo $avatar; ?>" alt="Avatar" class="user-avatar">
                        <span class="user-name"><?php echo sanitize($currentUser['first_name'] ?? $currentUser['username']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    
                    <div class="dropdown-menu" id="user-dropdown">
                        <a href="profile.php?id=<?php echo $currentUser['id']; ?>" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            Mon profil
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            Paramètres
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item text-danger">
                            <i class="fas fa-sign-out-alt"></i>
                            Déconnexion
                        </a>
                    </div>
                </div>
                
                <!-- Toggle mode sombre -->
                <button class="theme-toggle" id="theme-toggle">
                    <i class="fas fa-moon"></i>
                </button>
                
                <!-- Menu mobile -->
                <button class="mobile-menu-btn" id="mobile-menu-btn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </nav>
    
    <!-- Overlay mobile -->
    <div class="mobile-overlay" id="mobile-overlay"></div>
    
    <?php endif; ?>
    
    <!-- Contenu principal -->
    <main class="main-content"><?php