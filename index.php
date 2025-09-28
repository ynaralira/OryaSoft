<?php
require_once 'includes/config.php';

// Se estiver logado, redirecionar para dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/pages/dashboard.php');
    exit();
}

// Se não estiver logado, redirecionar para login
header('Location: ' . SITE_URL . '/login.php');
exit();
?>
