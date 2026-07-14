<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $company = trim($_POST['company_name'] ?? '');
    $siret = trim($_POST['siret'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Gestion du logo
    $logoPath = $currentUser['logo_path'] ?? null;

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/logos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Supprimer l'ancien logo si existe
        if ($logoPath && file_exists(__DIR__ . '/' . $logoPath)) {
            unlink(__DIR__ . '/' . $logoPath);
        }

        $fileName = uniqid('logo_') . '_' . basename($_FILES['logo']['name']);
        $targetPath = $uploadDir . $fileName;

        // Verifier le type de fichier
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['logo']['tmp_name']);

        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                $logoPath = 'uploads/logos/' . $fileName;
            } else {
                setFlash('error', 'Erreur lors de l'upload du logo.');
            }
        } else {
            setFlash('error', 'Format d'image non valide. Utilisez JPG, PNG, GIF ou WEBP.');
        }
    }

    // Supprimer le logo si demande
    if (isset($_POST['delete_logo']) && $_POST['delete_logo'] === '1') {
        if ($logoPath && file_exists(__DIR__ . '/' . $logoPath)) {
            unlink(__DIR__ . '/' . $logoPath);
        }
        $logoPath = null;
    }

    $stmt = $pdo->prepare('
        UPDATE users SET first_name = ?, last_name = ?, company_name = ?, siret = ?, phone = ?, address = ?, logo_path = ?
        WHERE id = ?
    ');
    $stmt->execute([$firstName, $lastName, $company, $siret, $phone, $address, $logoPath, $currentUser['id']]);

    setFlash('success', 'Profil mis a jour.');
    redirect('profile.php');
}

$pageTitle = 'Mon profil';
$activePage = 'profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="main-header">
    <h1>Mon profil</h1>
    <a href="dashboard.php" class="btn btn-outline">⬅️ Retour</a>
</div>

<div class="card" style="max-width: 700px;">
    <form method="POST" action="profile.php" enctype="multipart/form-data">

        <!-- Logo -->
        <div class="form-group">
            <label class="form-label">Logo de l'entreprise</label>
            <?php if (!empty($currentUser['logo_path']) && file_exists(__DIR__ . '/' . $currentUser['logo_path'])): ?>
                <div style="margin-bottom: 12px;">
                    <img src="<?php echo htmlspecialchars($currentUser['logo_path']); ?>" alt="Logo" style="max-height: 100px; max-width: 200px; border-radius: 8px; border: 1px solid var(--gray-300);">
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="file" name="logo" class="form-control" accept="image/*" style="flex: 1;">
                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.9rem; cursor: pointer;">
                        <input type="checkbox" name="delete_logo" value="1"> Supprimer
                    </label>
                </div>
            <?php else: ?>
                <input type="file" name="logo" class="form-control" accept="image/*">
                <p style="font-size: 0.8rem; color: var(--gray-500); margin-top: 6px;">Formats acceptes : JPG, PNG, GIF, WEBP. Max 2Mo.</p>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Prenom</label>
                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['first_name']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Nom</label>
                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['last_name']); ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Entreprise</label>
            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($currentUser['company_name'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">SIRET</label>
            <input type="text" name="siret" class="form-control" value="<?php echo htmlspecialchars($currentUser['siret'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Telephone</label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Adresse</label>
            <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($currentUser['address'] ?? ''); ?>">
        </div>
        <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
