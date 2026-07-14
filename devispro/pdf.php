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

$stmt = $pdo->prepare('SELECT * FROM devis_lines WHERE devis_id = ?');
$stmt->execute([$devisId]);
$lines = $stmt->fetchAll();

// Watermark si pas de credits payants
$hasWatermark = ($currentUser['free_quotes_used'] >= 3 && $currentUser['paid_quotes_remaining'] == 0);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Devis <?php echo htmlspecialchars($devis['public_id']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #1f2937;
            padding: 40px;
            max-width: 210mm;
            margin: 0 auto;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 72pt;
            color: rgba(200,200,200,0.2);
            font-weight: bold;
            pointer-events: none;
            z-index: 1000;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #1e3a5f;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header-left h1 {
            font-size: 28pt;
            color: #1e3a5f;
            margin-bottom: 5px;
        }
        .header-left .num {
            font-size: 10pt;
            color: #6b7280;
        }
        .header-left .dates {
            font-size: 9pt;
            color: #6b7280;
            margin-top: 5px;
        }
        .header-right {
            text-align: right;
        }
        .header-right .logo {
            max-height: 60px;
            max-width: 150px;
            margin-bottom: 8px;
        }
        .header-right .company {
            font-size: 12pt;
            font-weight: bold;
            color: #1e3a5f;
        }
        .header-right .contact {
            font-size: 9pt;
            color: #6b7280;
        }
        .info-grid {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        .info-block {
            flex: 1;
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
        }
        .info-block .label {
            font-size: 8pt;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        .info-block .content {
            font-size: 10pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background: #f9fafb;
            padding: 10px;
            text-align: left;
            font-size: 9pt;
            color: #374151;
            border-bottom: 2px solid #1e3a5f;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10pt;
        }
        .text-right { text-align: right; }
        .totals {
            margin-top: 20px;
            border-top: 2px solid #1e3a5f;
            padding-top: 15px;
            text-align: right;
        }
        .totals .line {
            margin-bottom: 5px;
            font-size: 10pt;
        }
        .totals .line.total {
            font-size: 14pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
        }
        .conditions {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px dashed #d1d5db;
            font-size: 8pt;
            color: #6b7280;
        }
        .signatures {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .signature-block {
            width: 45%;
        }
        .signature-block .name {
            font-weight: bold;
            margin-bottom: 40px;
        }
        .signature-block .line {
            border-top: 1px solid #000;
            padding-top: 5px;
            font-size: 8pt;
            color: #6b7280;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <?php if ($hasWatermark): ?>
    <div class="watermark">DEVIS PRO</div>
    <?php endif; ?>

    <div class="header">
        <div class="header-left">
            <h1>DEVIS</h1>
            <div class="num">N° <?php echo htmlspecialchars($devis['public_id']); ?></div>
            <div class="dates">
                Emis le <?php echo $devis['date_emission'] ? date('d/m/Y', strtotime($devis['date_emission'])) : date('d/m/Y', strtotime($devis['created_at'])); ?><br>
                <?php if ($devis['date_expiration']): ?>
                Expire le <?php echo date('d/m/Y', strtotime($devis['date_expiration'])); ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-right">
            <?php if (!empty($currentUser['logo_path'])): ?>
            <img src="<?php echo htmlspecialchars($currentUser['logo_path']); ?>" alt="Logo" class="logo">
            <?php endif; ?>
            <div class="company"><?php echo htmlspecialchars($currentUser['company_name'] ?: $currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
            <div class="contact"><?php echo htmlspecialchars($currentUser['phone'] ?: ''); ?></div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-block">
            <div class="label">Emetteur</div>
            <div class="content">
                <strong><?php echo htmlspecialchars($currentUser['company_name'] ?: $currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></strong><br>
                SIRET : <?php echo htmlspecialchars($currentUser['siret'] ?: '—'); ?><br>
                <?php echo htmlspecialchars($currentUser['address'] ?: '—'); ?><br>
                <?php echo htmlspecialchars($currentUser['phone'] ?: '—'); ?>
            </div>
        </div>
        <div class="info-block">
            <div class="label">Client</div>
            <div class="content">
                <strong><?php echo htmlspecialchars($devis['client_name']); ?></strong><br>
                <?php echo htmlspecialchars($devis['client_address'] ?: '—'); ?><br>
                <?php echo htmlspecialchars($devis['client_phone'] ?: '—'); ?><br>
                <?php echo htmlspecialchars($devis['client_email'] ?: '—'); ?>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qte</th>
                <th class="text-right">Prix U. HT</th>
                <th class="text-right">Total HT</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $line): 
                $lineTotal = $line['quantity'] * $line['unit_price'];
            ?>
            <tr>
                <td><?php echo htmlspecialchars($line['description']); ?></td>
                <td class="text-right"><?php echo $line['quantity']; ?></td>
                <td class="text-right"><?php echo number_format($line['unit_price'], 2, ',', ' '); ?> €</td>
                <td class="text-right"><strong><?php echo number_format($lineTotal, 2, ',', ' '); ?> €</strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="line">Total HT : <?php echo number_format($devis['total_ht'], 2, ',', ' '); ?> €</div>
        <div class="line">TVA (<?php echo $devis['tva_rate']; ?>%) : <?php echo number_format($devis['total_tva'], 2, ',', ' '); ?> €</div>
        <div class="line total">TOTAL TTC : <?php echo number_format($devis['total_ttc'], 2, ',', ' '); ?> €</div>
    </div>

    <div class="conditions">
        <strong>Conditions :</strong> <?php echo nl2br(htmlspecialchars($devis['conditions'])); ?><br><br>
        <strong>Validite du devis :</strong> <?php echo $devis['validity_days']; ?> jours a compter de la date d'emission.<br>
        Le devis est ferme et definitif une fois signe et date par le client.
    </div>

    <div class="signatures">
        <div class="signature-block">
            <div class="name"><?php echo htmlspecialchars($currentUser['company_name'] ?: $currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
            <div class="line">Date et signature</div>
        </div>
        <div class="signature-block" style="text-align: right;">
            <div class="name"><?php echo htmlspecialchars($devis['client_name']); ?></div>
            <div class="line">Date et signature</div>
        </div>
    </div>

    <div class="no-print" style="margin-top: 40px; text-align: center;">
        <button onclick="window.print()" style="padding: 12px 24px; background: #1e3a5f; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 12pt;">
            🖨️ Imprimer / Enregistrer en PDF
        </button>
        <p style="margin-top: 10px; font-size: 9pt; color: #6b7280;">
            Astuce : Choisissez "Enregistrer au format PDF" dans les options d'impression.
        </p>
    </div>
</body>
</html>
