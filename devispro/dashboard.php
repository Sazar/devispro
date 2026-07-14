<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$quoteInfo = canCreateQuote($currentUser);

// Stats
$stmt = $pdo->prepare('SELECT COUNT(*) FROM devis WHERE user_id = ?');
$stmt->execute([$currentUser['id']]);
$totalDevis = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM devis WHERE user_id = ? AND status = ?');
$stmt->execute([$currentUser['id'], 'sent']);
$devisEnvoyes = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM devis WHERE user_id = ? AND status = ?');
$stmt->execute([$currentUser['id'], 'accepted']);
$devisAcceptes = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(total_ttc), 0) FROM devis WHERE user_id = ?');
$stmt->execute([$currentUser['id']]);
$caPotentiel = $stmt->fetchColumn();

// Derniers devis
$stmt = $pdo->prepare('SELECT * FROM devis WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$currentUser['id']]);
$recentDevis = $stmt->fetchAll();

$pageTitle = 'Tableau de bord';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="main-header">
    <h1>Tableau de bord</h1>
    <a href="new-devis.php" class="btn btn-primary">➕ Nouveau devis</a>
</div>

<div class="credits-bar">
    <div style="display:flex; gap:20px; align-items:center;">
        <div style="font-size:0.9rem;">🎁 Gratuits restants : <strong style="color:var(--primary);"><?php echo max(0, 3 - $currentUser['free_quotes_used']); ?></strong></div>
        <div style="font-size:0.9rem;">👑 Payants restants : <strong style="color:var(--warning);"><?php echo $currentUser['paid_quotes_remaining']; ?></strong></div>
    </div>
    <?php if (!$quoteInfo['can']): ?>
    <form action="payment.php" method="POST">
        <button type="submit" class="btn btn-success">💳 Acheter un pack (10 devis — 5€)</button>
    </form>
    <?php elseif ($currentUser['free_quotes_used'] >= 3): ?>
    <span class="badge badge-green">✅ Pack actif</span>
    <?php endif; ?>
</div>

<?php if (!$quoteInfo['can']): ?>
<div class="alert alert-warning">⚠️ Vous avez utilise vos 3 devis gratuits. Achetez un pack pour continuer.</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Devis crees</div><div class="stat-value"><?php echo $totalDevis; ?></div></div>
    <div class="stat-card"><div class="stat-label">Envoies</div><div class="stat-value" style="color:var(--success);"><?php echo $devisEnvoyes; ?></div></div>
    <div class="stat-card"><div class="stat-label">Acceptes</div><div class="stat-value" style="color:var(--warning);"><?php echo $devisAcceptes; ?></div></div>
    <div class="stat-card"><div class="stat-label">CA potentiel</div><div class="stat-value" style="color:var(--danger);"><?php echo formatMoney($caPotentiel); ?></div></div>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <div class="card-title">📋 Derniers devis</div>
    </div>
    <?php if (empty($recentDevis)): ?>
    <div style="text-align:center; padding:40px; color:var(--gray-500);">
        <div style="font-size:3rem; margin-bottom:16px; opacity:0.3;">📄</div>
        <p>Aucun devis cree.</p>
        <a href="new-devis.php" class="btn btn-primary" style="margin-top:16px;">Creer mon premier devis</a>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr><th>N°</th><th>Client</th><th>Date</th><th class="text-right">Montant</th><th>Statut</th><th class="text-right">Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentDevis as $d): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($d['public_id']); ?></strong></td>
                    <td><?php echo htmlspecialchars($d['client_name']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($d['created_at'])); ?></td>
                    <td class="text-right"><strong><?php echo formatMoney($d['total_ttc']); ?></strong></td>
                    <td><span class="badge badge-<?php echo getStatusColor($d['status']); ?>"><?php echo getStatusLabel($d['status']); ?></span></td>
                    <td class="text-right"><a href="view-devis.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline">👁️ Voir</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
