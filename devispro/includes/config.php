<?php
session_start();

// Configuration MySQL (Laragon)
$host = 'localhost';
$dbname = 'devispro';
$username = 'root';
$password = 'root';

// Connexion PDO MySQL
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Creer la base si elle n'existe pas
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $dbname");

} catch (PDOException $e) {
    die('Erreur de connexion MySQL : ' . $e->getMessage());
}

// Creer les tables si elles n'existent pas
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(120) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        company_name VARCHAR(100),
        siret VARCHAR(20),
        phone VARCHAR(20),
        address VARCHAR(200),
        logo_path VARCHAR(255),
        free_quotes_used INT DEFAULT 0,
        paid_quotes_remaining INT DEFAULT 0,
        total_quotes_created INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS devis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        public_id VARCHAR(20) UNIQUE NOT NULL,
        client_name VARCHAR(100) NOT NULL,
        client_address VARCHAR(200),
        logo_path VARCHAR(255),
        client_email VARCHAR(120),
        client_phone VARCHAR(20),
        title VARCHAR(200) DEFAULT 'Devis',
        description TEXT,
        tva_rate DECIMAL(5,2) DEFAULT 20.00,
        validity_days INT DEFAULT 30,
        conditions TEXT,
        status VARCHAR(20) DEFAULT 'draft',
        total_ht DECIMAL(10,2) DEFAULT 0.00,
        total_tva DECIMAL(10,2) DEFAULT 0.00,
        total_ttc DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL,
        date_emission DATE,
        date_expiration DATE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS devis_lines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        devis_id INT NOT NULL,
        description VARCHAR(500) NOT NULL,
        quantity DECIMAL(10,2) DEFAULT 1.00,
        unit_price DECIMAL(10,2) DEFAULT 0.00,
        FOREIGN KEY (devis_id) REFERENCES devis(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        stripe_payment_id VARCHAR(100) UNIQUE,
        amount INT DEFAULT 0,
        quotes_added INT DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB
");

// Configuration Stripe
$stripe_secret_key = getenv('STRIPE_SECRET_KEY') ?: '';
$stripe_publishable_key = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';

// Parametres de l'application
$free_quotes_limit = 3;
$pack_price = 500;
$pack_quotes = 10;

// Fonctions utilitaires
function generatePublicId() {
    return 'DEV-' . strtoupper(substr(uniqid(), -6));
}

function formatMoney($amount) {
    return number_format($amount, 2, ',', ' ') . ' €';
}

function getStatusLabel($status) {
    $labels = [
        'draft' => 'Brouillon',
        'sent' => 'Envoye',
        'accepted' => 'Accepte',
        'rejected' => 'Refuse',
        'expired' => 'Expire'
    ];
    return $labels[$status] ?? $status;
}

function getStatusColor($status) {
    $colors = [
        'draft' => 'gray',
        'sent' => 'blue',
        'accepted' => 'green',
        'rejected' => 'red',
        'expired' => 'orange'
    ];
    return $colors[$status] ?? 'gray';
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function canCreateQuote($user) {
    if ($user['free_quotes_used'] < 3) return ['can' => true, 'type' => 'free'];
    if ($user['paid_quotes_remaining'] > 0) return ['can' => true, 'type' => 'paid'];
    return ['can' => false, 'type' => 'none'];
}

function requireAuth() {
    if (!isLoggedIn()) {
        setFlash('error', 'Veuillez vous connecter pour acceder a cette page.');
        redirect('login.php');
    }
}
?>
