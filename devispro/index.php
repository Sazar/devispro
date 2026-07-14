<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}