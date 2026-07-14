<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$devisId = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM devis WHERE id = ? AND user_id = ?');
$stmt->execute([$devisId, $currentUser['id']]);
$devis = $stmt->fetch();

if (!$devis) {
    setFlash('error', 'Devis introuvable.');
    redirect('dashboard.php');
}

// Lignes
$stmt = $pdo->prepare('SELECT * FROM devis_lines WHERE devis_id = ?');
$stmt->execute([$devisId]);
$lines = $stmt->fetchAll();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM devis WHERE id = ? AND user_id = ?');
            $stmt->execute([$devisId, $currentUser['id']]);
            setFlash('info', 'Devis supprime.');
            redirect('dashboard.php');
        }
        if ($_POST['action'] === 'status') {
            $newStatus = $_POST['status'] ?? 'draft';
            $sentAt = $newStatus === 'sent' ? date('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare('UPDATE devis SET status = ?, sent_at = ? WHERE id = ?');
            $stmt->execute([$newStatus, $sentAt, $devisId]);
            setFlash('success', 'Statut mis a jour.');
            redirect('view-devis.php?id=' . $devisId);
        }
        if ($_POST['action'] === 'duplicate') {
            $quoteInfo = canCreateQuote($currentUser);
            if (!$quoteInfo['can']) {
                setFlash('warning', 'Credits insuffisants pour dupliquer.');
                redirect('view-devis.php?id=' . $devisId);
            }

            $newPublicId = generatePublicId();
            $stmt = $pdo->prepare('
                INSERT INTO devis (user_id, public_id, client_name, client_address, client_email, client_phone, title, description, tva_rate, validity_days, conditions, total_ht, total_tva, total_ttc, date_emission, date_expiration)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $currentUser['id'], $newPublicId, $devis['client_name'], $devis['client_address'],
                $devis['client_email'], $devis['client_phone'], $devis['title'] . ' (copie)',
                $devis['description'], $devis['tva_rate'], $devis['validity_days'],
                $devis['conditions'], $devis['total_ht'], $devis['total_tva'], $devis['total_ttc'],
                $devis['date_emission'], $devis['date_expiration']
            ]);
            $newId = $pdo->lastInsertId();

            // Copier lignes
            foreach ($lines as $line) {
                $stmt = $pdo->prepare('INSERT INTO devis_lines (devis_id, description, quantity, unit_price) VALUES (?, ?, ?, ?)');
                $stmt->execute([$newId, $line['description'], $line['quantity'], $line['unit_price']]);
            }

            // Decrementer
            if ($quoteInfo['type'] === 'free') {
                $stmt = $pdo->prepare('UPDATE users SET free_quotes_used = free_quotes_used + 1 WHERE id = ?');
            } else {
                $stmt = $pdo->prepare('UPDATE users SET paid_quotes_remaining = paid_quotes_remaining - 1 WHERE id = ?');
            }
            $stmt->execute([$currentUser['id']]);

            setFlash('success', 'Devis duplique.');
            redirect('view-devis.php?id=' . $newId);
        }
    }
}

$pageTitle = 'Devis ' . $devis['public_id'];
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="main-header">
    <div>
        <h1>Devis <?php echo htmlspecialchars($devis['public_id']); ?></h1>
        <p style="color:var(--gray-500); font-size:0.85rem;">
            Emis le <?php echo $devis['date_emission'] ? date('d/m/Y', strtotime($devis['date_emission'])) : date('d/m/Y', strtotime($devis['created_at'])); ?> 
            <?php if ($devis['date_expiration']): ?>
            — Expire le <?php echo date('d/m/Y', strtotime($devis['date_expiration'])); ?>
            <?php endif; ?>
            — <span class="badge badge-<?php echo getStatusColor($devis['status']); ?>"><?php echo getStatusLabel($devis['status']); ?></span>
        </p>
    </div>
    <a href="dashboard.php" class="btn btn-outline">⬅️ Retour</a>
</div>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px;">
    <div>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <div class="card-title">ℹ️ Informations</div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                <div>
                    <div style="font-size:0.7rem; text-transform:uppercase; color:var(--gray-500); margin-bottom:8px;">Emetteur</div>
                    <div style="font-size:0.9rem;">
                        <?php if (!empty($currentUser['logo_path']) && file_exists(__DIR__ . '/' . $currentUser['logo_path'])): ?>
                        <div style="margin-bottom: 8px;">
                            <img src="<?php echo htmlspecialchars($currentUser['logo_path']); ?>" alt="Logo" style="max-height: 50px; max-width: 120px;">
                        </div>
                        <?php endif; ?>
                        <strong><?php echo htmlspecialchars($currentUser['company_name'] ?: $currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></strong><br>
                        SIRET : <?php echo htmlspecialchars($currentUser['siret'] ?: '—'); ?><br>
                        <?php echo htmlspecialchars($currentUser['address'] ?: '—'); ?><br>
                        <?php echo htmlspecialchars($currentUser['phone'] ?: '—'); ?>
                    </div>
                </div>
                <div>
                    <div style="font-size:0.7rem; text-transform:uppercase; color:var(--gray-500); margin-bottom:8px;">Client</div>
                    <div style="font-size:0.9rem;">
                        <strong><?php echo htmlspecialchars($devis['client_name']); ?></strong><br>
                        <?php echo htmlspecialchars($devis['client_address'] ?: '—'); ?><br>
                        <?php echo htmlspecialchars($devis['client_phone'] ?: '—'); ?><br>
                        <?php echo htmlspecialchars($devis['client_email'] ?: '—'); ?>
                    </div>
                </div>
            </div>
            <?php if ($devis['date_emission'] || $devis['date_expiration']): ?>
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--gray-200); display: flex; gap: 30px; font-size: 0.9rem;">
                <?php if ($devis['date_emission']): ?>
                <div><strong>Date d'emission :</strong> <?php echo date('d/m/Y', strtotime($devis['date_emission'])); ?></div>
                <?php endif; ?>
                <?php if ($devis['date_expiration']): ?>
                <div><strong>Date d'expiration :</strong> <?php echo date('d/m/Y', strtotime($devis['date_expiration'])); ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title mb-4">🔨 Prestations</div>
            <div style="overflow-x:auto;">
                <table class="table">
                    <thead>
                        <tr><th>Description</th><th class="text-right">Qte</th><th class="text-right">Prix U. HT</th><th class="text-right">Total HT</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $line): 
                            $lineTotal = $line['quantity'] * $line['unit_price'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($line['description']); ?></td>
                            <td class="text-right"><?php echo $line['quantity']; ?></td>
                            <td class="text-right"><?php echo formatMoney($line['unit_price']); ?></td>
                            <td class="text-right"><strong><?php echo formatMoney($lineTotal); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:16px; text-align:right; border-top:2px solid var(--primary); padding-top:12px;">
                <div style="display:flex; justify-content:flex-end; gap:16px; margin-bottom:4px;"><span>Total HT :</span><span><?php echo formatMoney($devis['total_ht']); ?></span></div>
                <div style="display:flex; justify-content:flex-end; gap:16px; margin-bottom:4px;"><span>TVA (<?php echo $devis['tva_rate']; ?>%) :</span><span><?php echo formatMoney($devis['total_tva']); ?></span></div>
                <div style="display:flex; justify-content:flex-end; gap:16px; font-size:1.1rem; font-weight:bold; color:var(--primary); margin-top:8px; padding-top:8px; border-top:1px solid var(--gray-200);">
                    <span>TOTAL TTC :</span><span><?php echo formatMoney($devis['total_ttc']); ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title mb-4">📄 Conditions</div>
            <p style="color:var(--gray-600); font-size:0.9rem; white-space:pre-line;"><?php echo nl2br(htmlspecialchars($devis['conditions'] ?: 'Aucune condition particuliere.')); ?></p>
            <div style="margin-top:12px; font-size:0.8rem; color:var(--gray-500);">
                <strong>Validite du devis :</strong> <?php echo $devis['validity_days']; ?> jours a compter de la date d'emission.
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="position:sticky; top:24px;">
            <div class="card-title mb-4">⚡ Actions</div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <a href="pdf.php?id=<?php echo $devis['id']; ?>" class="btn btn-primary" style="width:100%;">📄 Telecharger le PDF</a>

                <?php if ($devis['client_email']): ?>
                <button class="btn btn-success" style="width:100%;" onclick="alert('Dans la version complete, le PDF serait envoye a <?php echo htmlspecialchars($devis['client_email']); ?>')">📧 Envoyer par email</button>
                <?php else: ?>
                <button class="btn btn-success" style="width:100%; opacity:0.5; cursor:not-allowed;" disabled>📧 Envoyer par email</button>
                <p style="font-size:0.75rem; color:var(--gray-500); text-align:center;"><a href="edit-devis.php?id=<?php echo $devis['id']; ?>">Ajoutez un email client</a> pour activer l'envoi.</p>
                <?php endif; ?>

                <hr style="border:none; border-top:1px solid var(--gray-200); margin:8px 0;">

                <form method="POST" style="width:100%;">
                    <input type="hidden" name="action" value="status">
                    <label style="font-size:0.75rem; color:var(--gray-500); margin-bottom:4px; display:block;">Changer le statut</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="draft" <?php echo $devis['status'] === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                        <option value="sent" <?php echo $devis['status'] === 'sent' ? 'selected' : ''; ?>>Envoye</option>
                        <option value="accepted" <?php echo $devis['status'] === 'accepted' ? 'selected' : ''; ?>>Accepte</option>
                        <option value="rejected" <?php echo $devis['status'] === 'rejected' ? 'selected' : ''; ?>>Refuse</option>
                        <option value="expired" <?php echo $devis['status'] === 'expired' ? 'selected' : ''; ?>>Expire</option>
                    </select>
                </form>

                <form method="POST" style="width:100%;">
                    <input type="hidden" name="action" value="duplicate">
                    <button type="submit" class="btn btn-outline" style="width:100%;">📋 Dupliquer</button>
                </form>

                <hr style="border:none; border-top:1px solid var(--gray-200); margin:8px 0;">

                <form method="POST" style="width:100%;" onsubmit="return confirm('Supprimer definitivement ce devis ?');">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-sm" style="width:100%; background:var(--danger); color:white;">🗑️ Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
