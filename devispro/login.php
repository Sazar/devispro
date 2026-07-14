<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) redirect('dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        redirect('dashboard.php');
    } else {
        setFlash('error', 'Email ou mot de passe incorrect.');
        redirect('login.php');
    }
}

$pageTitle = 'Connexion';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — DevisPro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1>📄 DevisPro</h1>
            <p>Connectez-vous a votre espace artisan</p>
        </div>
        <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px;">Se connecter</button>
        </form>
        <div class="auth-footer">
            Pas encore de compte ? <a href="register.php">Creer un compte</a>
        </div>
    </div>
</div>
</body>
</html>
