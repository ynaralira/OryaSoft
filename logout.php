<?php
require_once 'includes/config.php';

// Destruir a sessão
session_destroy();

// Redirecionar para login
header('Location: ' . SITE_URL . '/login.php');
exit();
?>
