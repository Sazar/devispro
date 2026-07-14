<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);
$quoteInfo = canCreateQuote($currentUser);

if (!$quoteInfo['can']) {
    setFlash('warning', 'Vous avez utilise vos 3 devis gratuits. Achetez un pack pour continuer.');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Creer le devis
    $publicId = generatePublicId();
    $clientName = trim($_POST['client_name'] ?? '');
    $clientAddress = trim($_POST['client_address'] ?? '');
    $clientEmail = trim($_POST['client_email'] ?? '');
    $clientPhone = trim($_POST['client_phone'] ?? '');
    $title = trim($_POST['title'] ?? 'Devis');
    $tvaRate = floatval($_POST['tva_rate'] ?? 20);
    $validityDays = intval($_POST['validity_days'] ?? 30);
    $conditions = trim($_POST['conditions'] ?? '');
    $dateEmission = $_POST['date_emission'] ?? date('Y-m-d');
    $dateExpiration = $_POST['date_expiration'] ?? null;

    // Calculer date expiration si non fournie
    if (empty($dateExpiration) && !empty($dateEmission)) {
        $dateExpiration = date('Y-m-d', strtotime($dateEmission . ' + ' . $validityDays . ' days'));
    }

    $stmt = $pdo->prepare('
        INSERT INTO devis (user_id, public_id, client_name, client_address, client_email, client_phone, title, tva_rate, validity_days, conditions, date_emission, date_expiration)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$currentUser['id'], $publicId, $clientName, $clientAddress, $clientEmail, $clientPhone, $title, $tvaRate, $validityDays, $conditions, $dateEmission, $dateExpiration]);
    $devisId = $pdo->lastInsertId();

    // Lignes
    $descriptions = $_POST['line_description'] ?? [];
    $quantities = $_POST['line_quantity'] ?? [];
    $prices = $_POST['line_price'] ?? [];

    $totalHT = 0;
    for ($i = 0; $i < count($descriptions); $i++) {
        if (!empty(trim($descriptions[$i]))) {
            $qty = floatval($quantities[$i] ?? 1);
            $price = floatval($prices[$i] ?? 0);
            $lineTotal = $qty * $price;
            $totalHT += $lineTotal;

            $stmt = $pdo->prepare('INSERT INTO devis_lines (devis_id, description, quantity, unit_price) VALUES (?, ?, ?, ?)');
            $stmt->execute([$devisId, trim($descriptions[$i]), $qty, $price]);
        }
    }

    // Totaux
    $totalTVA = $totalHT * ($tvaRate / 100);
    $totalTTC = $totalHT + $totalTVA;

    $stmt = $pdo->prepare('UPDATE devis SET total_ht = ?, total_tva = ?, total_ttc = ? WHERE id = ?');
    $stmt->execute([$totalHT, $totalTVA, $totalTTC, $devisId]);

    // Decrementer compteur
    if ($quoteInfo['type'] === 'free') {
        $stmt = $pdo->prepare('UPDATE users SET free_quotes_used = free_quotes_used + 1, total_quotes_created = total_quotes_created + 1 WHERE id = ?');
    } else {
        $stmt = $pdo->prepare('UPDATE users SET paid_quotes_remaining = paid_quotes_remaining - 1, total_quotes_created = total_quotes_created + 1 WHERE id = ?');
    }
    $stmt->execute([$currentUser['id']]);

    setFlash('success', 'Devis cree avec succes !');
    redirect('view-devis.php?id=' . $devisId);
}

$pageTitle = 'Nouveau devis';
$activePage = 'new_devis';
require_once __DIR__ . '/includes/header.php';
?>

<div class="main-header">
    <h1>Nouveau devis</h1>
    <a href="dashboard.php" class="btn btn-outline">⬅️ Retour</a>
</div>

<form method="POST" id="devisForm">
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <div>
            <div class="card">
                <div class="card-title">🏢 Vos informations</div>
                <div class="form-group">
                    <label class="form-label">Entreprise</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($currentUser['company_name'] ?: $currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>" readonly style="background:var(--gray-50);">
                </div>
                <div class="form-group">
                    <label class="form-label">Telephone</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($currentUser['phone'] ?: 'Non renseigne'); ?>" readonly style="background:var(--gray-50);">
                </div>
                <?php if (!empty($currentUser['logo_path']) && file_exists(__DIR__ . '/' . $currentUser['logo_path'])): ?>
                <div style="margin-top: 10px;">
                    <img src="<?php echo htmlspecialchars($currentUser['logo_path']); ?>" alt="Logo" style="max-height: 60px; max-width: 150px; border-radius: 4px;">
                </div>
                <?php endif; ?>
                <a href="profile.php" class="btn btn-sm btn-outline">✏️ Modifier</a>
            </div>

            <div class="card">
                <div class="card-title">👤 Client</div>
                <div class="form-group">
                    <label class="form-label">Nom du client *</label>
                    <input type="text" name="client_name" class="form-control" placeholder="M. Martin" required oninput="updatePreview()">
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse du chantier</label>
                    <input type="text" name="client_address" class="form-control" placeholder="45 avenue de la Republique" oninput="updatePreview()">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email client</label>
                        <input type="email" name="client_email" class="form-control" placeholder="client@email.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telephone client</label>
                        <input type="text" name="client_phone" class="form-control" placeholder="06 12 34 56 78">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">📋 Details</div>
                <div class="form-group">
                    <label class="form-label">Titre</label>
                    <input type="text" name="title" class="form-control" value="Devis" placeholder="Devis peinture salon">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">TVA (%)</label>
                        <select name="tva_rate" class="form-control" id="tvaSelect" onchange="updatePreview()">
                            <option value="0">0% (auto-entrepreneur)</option>
                            <option value="2.10">2.10%</option>
                            <option value="5.50">5.50%</option>
                            <option value="8.50">8.50%</option>
                            <option value="10">10%</option>
                            <option value="20" selected>20%</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Validite (jours)</label>
                        <select name="validity_days" class="form-control" id="validitySelect" onchange="updatePreview()">
                            <option value="30" selected>30 jours</option>
                            <option value="60">60 jours</option>
                            <option value="90">90 jours</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date d'emission</label>
                        <input type="date" name="date_emission" class="form-control" value="<?php echo date('Y-m-d'); ?>" onchange="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date d'expiration</label>
                        <input type="date" name="date_expiration" class="form-control" id="dateExpiration" onchange="updatePreview()">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Conditions</label>
                    <textarea name="conditions" class="form-control" rows="3" id="conditionsInput" placeholder="Acompte 30%..." oninput="updatePreview()">Acompte de 30% a la signature. Solde a la fin des travaux.</textarea>
                </div>
            </div>

            <div class="card">
                <div class="card-title">🔨 Prestations</div>
                <div id="linesContainer">
                    <div class="devis-line">
                        <div class="form-group mb-0"><input type="text" name="line_description[]" class="form-control" placeholder="Peinture murs et plafond" required oninput="updatePreview()"></div>
                        <div class="form-group mb-0"><input type="number" name="line_quantity[]" class="form-control qty-input" value="1" min="0" step="0.01" required oninput="updatePreview()"></div>
                        <div class="form-group mb-0"><input type="number" name="line_price[]" class="form-control price-input" value="0" min="0" step="0.01" required oninput="updatePreview()"></div>
                        <div class="form-group mb-0"><input type="text" class="form-control line-total" readonly value="0,00 €"></div>
                        <div class="form-group mb-0"><button type="button" class="btn btn-sm" style="background:var(--danger); color:white;" onclick="removeLine(this)">🗑️</button></div>
                    </div>
                </div>
                <button type="button" class="btn btn-outline" style="margin-top:10px;" onclick="addLine()">➕ Ajouter une ligne</button>
            </div>

            <div style="display:flex; gap:12px; margin-bottom:40px;">
                <button type="submit" class="btn btn-primary btn-lg" style="flex:1;">💾 Creer le devis</button>
                <a href="dashboard.php" class="btn btn-outline btn-lg">Annuler</a>
            </div>
        </div>

        <div>
            <div class="card" style="position:sticky; top:24px;">
                <div class="card-title">👁️ Previsualisation</div>
                <div class="preview-box" id="previewBox" style="position:relative;">
                    <div class="watermark" id="watermark" style="display:<?php echo ($currentUser['free_quotes_used'] >= 3 && $currentUser['paid_quotes_remaining'] == 0) ? 'block' : 'none'; ?>;">DEVIS PRO</div>

                    <div style="border-bottom:2px solid var(--primary); padding-bottom:12px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:1.5rem; font-weight:bold; color:var(--primary);">DEVIS</div>
                            <div style="font-size:0.8rem; color:var(--gray-500);">N° <span id="p-num">---</span></div>
                            <div style="font-size:0.75rem; color:var(--gray-500);">Emis le <span id="p-date-emission"><?php echo date('d/m/Y'); ?></span></div>
                            <div style="font-size:0.75rem; color:var(--gray-500);">Expire le <span id="p-date-expiration">---</span></div>
                        </div>
                        <div style="text-align:right;">
                            <?php if (!empty($currentUser['logo_path']) && file_exists(__DIR__ . '/' . $currentUser['logo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($currentUser['logo_path']); ?>" alt="Logo" style="max-height: 50px; max-width: 120px; margin-bottom: 5px;">
                            <?php endif; ?>
                            <div style="font-weight:600; color:var(--primary); font-size:0.95rem;" id="p-company"><?php echo htmlspecialchars($currentUser['company_name'] ?: $currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
                            <div style="font-size:0.75rem; color:var(--gray-500);" id="p-phone"><?php echo htmlspecialchars($currentUser['phone'] ?: ''); ?></div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">
                        <div style="background:var(--gray-50); padding:12px; border-radius:8px;">
                            <div style="font-size:0.65rem; text-transform:uppercase; color:var(--gray-500); margin-bottom:6px;">Emetteur</div>
                            <div style="font-size:0.85rem;">
                                <?php echo htmlspecialchars($currentUser['company_name'] ?: $currentUser['first_name'] . ' ' . $currentUser['last_name']); ?><br>
                                SIRET : <?php echo htmlspecialchars($currentUser['siret'] ?: '—'); ?><br>
                                <?php echo htmlspecialchars($currentUser['address'] ?: '—'); ?>
                            </div>
                        </div>
                        <div style="background:var(--gray-50); padding:12px; border-radius:8px;">
                            <div style="font-size:0.65rem; text-transform:uppercase; color:var(--gray-500); margin-bottom:6px;">Client</div>
                            <div style="font-size:0.85rem;" id="p-client">
                                <span id="p-client-name">---</span><br>
                                <span id="p-client-addr">---</span>
                            </div>
                        </div>
                    </div>

                    <table style="width:100%; font-size:0.8rem; border-collapse:collapse;">
                        <thead>
                            <tr style="background:var(--gray-50);">
                                <th style="text-align:left; padding:8px; border-bottom:2px solid var(--primary);">Description</th>
                                <th style="text-align:center; padding:8px; border-bottom:2px solid var(--primary);">Qte</th>
                                <th style="text-align:right; padding:8px; border-bottom:2px solid var(--primary);">Prix U.</th>
                                <th style="text-align:right; padding:8px; border-bottom:2px solid var(--primary);">Total HT</th>
                            </tr>
                        </thead>
                        <tbody id="p-table"></tbody>
                    </table>

                    <div style="margin-top:16px; text-align:right; border-top:2px solid var(--primary); padding-top:12px;">
                        <div style="display:flex; justify-content:flex-end; gap:16px; margin-bottom:4px; font-size:0.85rem;">
                            <span>Total HT :</span><span id="p-total-ht">0,00 €</span>
                        </div>
                        <div style="display:flex; justify-content:flex-end; gap:16px; margin-bottom:4px; font-size:0.85rem;">
                            <span>TVA (<span id="p-tva">20</span>%) :</span><span id="p-total-tva">0,00 €</span>
                        </div>
                        <div style="display:flex; justify-content:flex-end; gap:16px; font-size:1rem; font-weight:bold; color:var(--primary); margin-top:8px; padding-top:8px; border-top:1px solid var(--gray-200);">
                            <span>TOTAL TTC :</span><span id="p-total-ttc">0,00 €</span>
                        </div>
                    </div>

                    <div style="margin-top:30px; font-size:0.7rem; color:var(--gray-500); border-top:1px dashed var(--gray-300); padding-top:12px;">
                        <strong>Conditions :</strong> <span id="p-conditions">---</span><br><br>
                        <strong>Validite :</strong> <span id="p-validite">30</span> jours
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function formatMoney(n) {
    return n.toFixed(2).replace('.', ',') + ' €';
}

function formatDate(dateStr) {
    if (!dateStr) return '---';
    const parts = dateStr.split('-');
    return parts[2] + '/' + parts[1] + '/' + parts[0];
}

function updatePreview() {
    const clientName = document.querySelector('input[name="client_name"]')?.value || '---';
    const clientAddr = document.querySelector('input[name="client_address"]')?.value || '---';
    const conditions = document.getElementById('conditionsInput')?.value || '---';
    const validite = document.getElementById('validitySelect')?.value || '30';
    const tvaRate = document.getElementById('tvaSelect')?.value || '20';
    const dateEmission = document.querySelector('input[name="date_emission"]')?.value;
    const dateExpiration = document.querySelector('input[name="date_expiration"]')?.value || document.getElementById('dateExpiration')?.value;

    document.getElementById('p-client-name').textContent = clientName;
    document.getElementById('p-client-addr').textContent = clientAddr;
    document.getElementById('p-conditions').textContent = conditions;
    document.getElementById('p-validite').textContent = validite;
    document.getElementById('p-tva').textContent = tvaRate;
    document.getElementById('p-date-emission').textContent = formatDate(dateEmission);
    document.getElementById('p-date-expiration').textContent = formatDate(dateExpiration);

    const lines = document.querySelectorAll('.devis-line');
    let totalHT = 0;
    let html = '';

    lines.forEach(line => {
        const desc = line.querySelector('input[name="line_description[]"]')?.value || '';
        const qty = parseFloat(line.querySelector('.qty-input')?.value) || 0;
        const price = parseFloat(line.querySelector('.price-input')?.value) || 0;
        const total = qty * price;
        totalHT += total;

        line.querySelector('.line-total').value = formatMoney(total);

        html += `<tr style="border-bottom:1px solid var(--gray-100);">
            <td style="padding:8px;">${desc || '—'}</td>
            <td style="padding:8px; text-align:center;">${qty}</td>
            <td style="padding:8px; text-align:right;">${formatMoney(price)}</td>
            <td style="padding:8px; text-align:right;">${formatMoney(total)}</td>
        </tr>`;
    });

    document.getElementById('p-table').innerHTML = html;

    const totalTVA = totalHT * (parseFloat(tvaRate) / 100);
    const totalTTC = totalHT + totalTVA;

    document.getElementById('p-total-ht').textContent = formatMoney(totalHT);
    document.getElementById('p-total-tva').textContent = formatMoney(totalTVA);
    document.getElementById('p-total-ttc').textContent = formatMoney(totalTTC);
}

function addLine() {
    const container = document.getElementById('linesContainer');
    const div = document.createElement('div');
    div.className = 'devis-line';
    div.innerHTML = `
        <div class="form-group mb-0"><input type="text" name="line_description[]" class="form-control" placeholder="Description..." required oninput="updatePreview()"></div>
        <div class="form-group mb-0"><input type="number" name="line_quantity[]" class="form-control qty-input" value="1" min="0" step="0.01" required oninput="updatePreview()"></div>
        <div class="form-group mb-0"><input type="number" name="line_price[]" class="form-control price-input" value="0" min="0" step="0.01" required oninput="updatePreview()"></div>
        <div class="form-group mb-0"><input type="text" class="form-control line-total" readonly value="0,00 €"></div>
        <div class="form-group mb-0"><button type="button" class="btn btn-sm" style="background:var(--danger); color:white;" onclick="removeLine(this)">🗑️</button></div>
    `;
    container.appendChild(div);
    updatePreview();
}

function removeLine(btn) {
    btn.closest('.devis-line').remove();
    updatePreview();
}

// Auto-calculer date expiration
function updateExpirationDate() {
    const emission = document.querySelector('input[name="date_emission"]')?.value;
    const validity = parseInt(document.getElementById('validitySelect')?.value || 30);

    if (emission) {
        const date = new Date(emission);
        date.setDate(date.getDate() + validity);
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        document.getElementById('dateExpiration').value = yyyy + '-' + mm + '-' + dd;
    }
    updatePreview();
}

document.getElementById('validitySelect')?.addEventListener('change', updateExpirationDate);
document.querySelector('input[name="date_emission"]')?.addEventListener('change', updateExpirationDate);

updatePreview();
updateExpirationDate();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
