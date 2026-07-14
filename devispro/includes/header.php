<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' — DevisPro' : 'DevisPro'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<?php if (isLoggedIn()): ?>
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">📄 DevisPro</div>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php" class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>"><span>📊</span> Tableau de bord</a></li>
            <li><a href="new-devis.php" class="<?php echo $activePage === 'new_devis' ? 'active' : ''; ?>"><span>➕</span> Nouveau devis</a></li>
            <li><a href="profile.php" class="<?php echo $activePage === 'profile' ? 'active' : ''; ?>"><span>⚙️</span> Mon profil</a></li>
        </ul>
        <div class="sidebar-footer">
            <div style="margin-bottom:8px;">👤 <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
            <a href="logout.php" style="color:rgba(255,255,255,0.7);">🚪 Déconnexion</a>
        </div>
    </aside>
    <main class="main-content">
        <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
        <?php endif; ?>
<?php endif; ?>
