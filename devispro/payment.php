<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();

$currentUser = getCurrentUser($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulation d'achat (dans la vraie version, rediriger vers Stripe Checkout)
    $stmt = $pdo->prepare('UPDATE users SET paid_quotes_remaining = paid_quotes_remaining + ? WHERE id = ?');
    $stmt->execute([$pack_quotes, $currentUser['id']]);

    $stmt = $pdo->prepare('INSERT INTO payments (user_id, amount, quotes_added, status) VALUES (?, ?, ?, ?)');
    $stmt->execute([$currentUser['id'], $pack_price, $pack_quotes, 'completed']);

    setFlash('success', 'Paiement reussi ! ' . $pack_quotes . ' devis ajoutes a votre compte.');
    redirect('dashboard.php');
}

redirect('dashboard.php');
