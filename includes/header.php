<?php
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
    
    if (!$current_user) {
        session_destroy();
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar usuário: " . $e->getMessage());
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetch()['unread_count'];
} catch (PDOException $e) {
    $unread_notifications = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Orya - Plataforma para Desenvolvedoras</title>
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/assets/images/logo_oficial.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="shortcut icon" href="<?php echo SITE_URL; ?>/assets/images/logo_oficial.png" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/chatbot/chatbot.css">
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/<?php echo $css; ?>.css">
        <?php endforeach; ?>
    <?php endif; ?>
    <meta name="description" content="Orya - Plataforma dedicada ao crescimento e desenvolvimento de mulheres na tecnologia">
    <meta name="keywords" content="tecnologia, mulheres, programação, desenvolvimento, mentoria, comunidade">
    <meta name="author" content="Orya Platform">
    
    <meta property="og:title" content="<?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Orya">
    <meta property="og:description" content="Plataforma dedicada ao crescimento de mulheres na tecnologia">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/assets/images/orya-logo.png">
    <meta property="og:url" content="<?php echo SITE_URL . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:type" content="website">

    <?php if (isset($page_css)): ?>
        <style><?php echo $page_css; ?></style>
    <?php endif; ?>
    <script>window.BASE_URL = '<?php echo SITE_URL; ?>';</script>
</head>
<body data-page="<?php echo isset($page_name) ? $page_name : ''; ?>">
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="<?php echo SITE_URL; ?>/assets/images/logo_oficial.png" alt="Orya Logo" class="logo-image" style="width: 50px; height: 50px;border-radius: 50%;">
                    <span class="sidebar-text">Orya</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/pages/dashboard.php" class="nav-link">
                        <i class="fas fa-home nav-icon"></i>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/pages/meetings.php" class="nav-link">
                        <span style="position:relative; display:inline-block;">
                            <i class="fas fa-video nav-icon"></i>
                            <span class="badge badge-new" style="position:absolute; top:-7px; right:-10px; background:#e83e8c; color:#fff; font-size:10px; padding:1px 6px; border-radius:8px; z-index:2; box-shadow:0 1px 4px rgba(0,0,0,0.08);">Novo</span>
                        </span>
                        <span class="sidebar-text">Reuniões</span>
                    </a>
                </div> 
                
               <!-- <div class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/pages/calendar.php" class="nav-link">
                        <i class="fas fa-calendar nav-icon"></i>
                        <span class="sidebar-text">Calendário</span>
                    </a>
                </div>
                !-->
                <div class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/pages/kanban.php" class="nav-link">
                        <i class="fas fa-project-diagram nav-icon"></i>
                        <span class="sidebar-text">Projetos</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/pages/repositories.php" class="nav-link">
                        <i class="fas fa-code-branch nav-icon"></i>
                        <span class="sidebar-text">Repositórios</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/pages/community.php" class="nav-link">
                        <i class="fas fa-users nav-icon"></i>
                        <span class="sidebar-text">Comunidade</span>
                    </a>
                </div>
                
                <!-- <div class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/pages/chat.php" class="nav-link">
                        <i class="fas fa-comments nav-icon"></i>
                        <span class="sidebar-text">Chat</span>
                    </a>
                </div> -->
                
                <!-- <div class="nav-item">
                    <a href="<?php echo SITE_URL; ?>/pages/profile.php" class="nav-link">
                        <i class="fas fa-user nav-icon"></i>
                        <span class="sidebar-text">Perfil</span>
                    </a>
                </div> -->
            </nav>
            <button class="sidebar-mobile-toggle" style="display:none;position:absolute;top:18px;right:-48px;z-index:1000;background:#e83e8c;color:#fff;border:none;border-radius:50%;width:38px;height:38px;box-shadow:0 2px 8px rgba(0,0,0,0.08);font-size:20px;align-items:center;justify-content:center;cursor:pointer;">
                <i class="fas fa-bars"></i>
            </button>
        </aside>
        <div class="sidebar-backdrop" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.18);z-index:998;"></div>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
                </div>
                
                <div class="topbar-right">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Pesquisar...">
                    </div>

                    <div class="dropdown">
                        <button class="notification-btn" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_notifications > 0): ?>
                                <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu notifications-dropdown">
                            <div class="dropdown-header">
                                <span>Notificações</span>
                            </div>
                            <div class="notifications-list">
                            </div>
                            <div class="dropdown-footer">
                                <a href="<?php echo SITE_URL; ?>/pages/notifications.php">Ver todas</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dropdown">
                        <button class="profile-btn">
                            <img src="<?php echo $current_user['avatar'] ?: SITE_URL . '/assets/images/default-avatar.svg'; ?>" 
                                 alt="<?php echo escape($current_user['name']); ?>" 
                                 class="user-avatar">
                        </button>
                        <div class="dropdown-menu">
                            <div class="dropdown-header">
                                <span class="user-name"><?php echo escape($current_user['name']); ?></span>
                                <span class="user-email"><?php echo escape($current_user['email']); ?></span>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo SITE_URL; ?>/pages/profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i> Perfil
                            </a>
                            <!--
                            <a href="<?php echo SITE_URL; ?>/pages/settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Configurações
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/help.php" class="dropdown-item">
                                <i class="fas fa-question-circle"></i> Ajuda
                            </a>
                            -->
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo SITE_URL; ?>/logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Sair
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible">
                        <span><?php echo escape($_SESSION['flash_message']); ?></span>
                        <button type="button" class="alert-close">&times;</button>
                    </div>
                    <?php 
                    unset($_SESSION['flash_message'], $_SESSION['flash_type']); 
                    ?>
                <?php endif; ?>
