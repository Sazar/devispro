<?php
require_once __DIR__ . '/includes/config.php';

session_destroy();
setFlash('info', 'Vous etes deconnecte.');
redirect('login.php');
