<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) redirect('dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $company = trim($_POST['company_name'] ?? '');

    if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        setFlash('error', 'Tous les champs obligatoires doivent etre remplis.');
        redirect('register.php');
    }

    if (strlen($password) < 6) {
        setFlash('error', 'Le mot de passe doit contenir au moins 6 caracteres.');
        redirect('register.php');
    }

    // Verifier si email existe deja
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        setFlash('error', 'Cet email est deja utilise.');
        redirect('register.php');
    }

    // Creer l'utilisateur
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, company_name) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$email, $passwordHash, $firstName, $lastName, $company]);

    $_SESSION['user_id'] = $pdo->lastInsertId();
    setFlash('success', 'Bienvenue sur DevisPro ! Vous avez 3 devis gratuits.');
    redirect('dashboard.php');
}

$pageTitle = 'Inscription';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — DevisPro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1>📄 DevisPro</h1>
            <p>Creez votre compte artisan en 30 secondes</p>
        </div>
        <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
        <?php endif; ?>
        <form method="POST" action="register.php">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Prenom *</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Entreprise</label>
                <input type="text" name="company_name" class="form-control" placeholder="Dupont Batiment">
            </div>
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Mot de passe * (min. 6 caracteres)</label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px;">Creer mon compte</button>
        </form>
        <div class="auth-footer">
            Deja un compte ? <a href="login.php">Se connecter</a>
        </div>
    </div>
</div>
</body>
</html>
