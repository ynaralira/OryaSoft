<?php
$page_title = 'Dashboard';
$page_name = 'dashboard';
$additional_css = ['dashboard'];

require_once '../includes/header.php';

// Buscar estatísticas do usuário
try {
    // Reuniões próximas
    $stmt = $pdo->prepare("
        SELECT m.*, COUNT(mp.user_id) as participant_count 
        FROM meetings m 
        LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id  
        WHERE m.start_datetime > NOW()  
        AND (m.created_by = ? OR m.id IN (SELECT meeting_id FROM meeting_participants WHERE user_id = ?)) 
        GROUP BY m.id 
        ORDER BY m.start_datetime ASC 
        LIMIT 3
    ");
    $stmt->execute([$current_user['id'], $current_user['id']]);
    $upcoming_meetings = $stmt->fetchAll();
    
    // Projetos em andamento
    $stmt = $pdo->prepare("
        SELECT * FROM projects 
        WHERE (created_by = ? OR assigned_to = ?) 
        AND status IN ('planning', 'in_progress') 
        ORDER BY updated_at DESC 
        LIMIT 4
    ");
    $stmt->execute([$current_user['id'], $current_user['id']]);
    $active_projects = $stmt->fetchAll();
    
    // Posts recentes da comunidade
    $stmt = $pdo->prepare("
        SELECT cp.*, u.name as author_name, u.avatar as author_avatar 
        FROM community_posts cp 
        JOIN users u ON cp.user_id = u.id 
        ORDER BY cp.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_posts = $stmt->fetchAll();
    
    // Estatísticas
    $stats = [];
    
    // Total de projetos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE created_by = ? OR assigned_to = ?");
    $stmt->execute([$current_user['id'], $current_user['id']]);
    $stats['total_projects'] = $stmt->fetchColumn();
    
    // Projetos concluídos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE (created_by = ? OR assigned_to = ?) AND status = 'completed'");
    $stmt->execute([$current_user['id'], $current_user['id']]);
    $stats['completed_projects'] = $stmt->fetchColumn();
    
    // Total de reuniões participadas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM meeting_participants WHERE user_id = ?");
    $stmt->execute([$current_user['id']]);
    $stats['meetings_attended'] = $stmt->fetchColumn();
    
    // Posts na comunidade
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM community_posts WHERE user_id = ?");
    $stmt->execute([$current_user['id']]);
    $stats['community_posts'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Erro no dashboard: " . $e->getMessage());
    $upcoming_meetings = [];
    $active_projects = [];
    $recent_posts = [];
    $stats = ['total_projects' => 0, 'completed_projects' => 0, 'meetings_attended' => 0, 'community_posts' => 0];
}

function getUserBadges(PDO $pdo, int $userId): array {
    $sql = "SELECT b.id, b.nome, b.descricao, b.icone, ub.earned_at 
            FROM user_badges ub
            JOIN badges b ON ub.badge_id = b.id
            WHERE ub.user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<div class="dashboard">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-content">
            <h1>Bem-vinda de volta, <?php echo escape(explode(' ', $current_user['name'])[0]); ?>! 👋</h1>
            <p>Aqui está um resumo das suas atividades na plataforma Orya.</p>
        </div>
        <div class="welcome-actions">
            <!--<a href="../pages/meetings.php" class="btn btn-primary">
                <i class="fas fa-video"></i>
                Nova Reunião
            </a></a>-->
			 <a href="../pages/news.php" class="btn btn-outline">
                <i class="fas fa-view"></i>
               Notícias BR
            </a>
            <a href="../pages/kanban.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Novo Projeto
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon bg-wine">
                <i class="fas fa-project-diagram"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['total_projects']; ?></div>
                <div class="stat-label">Projetos Totais</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['completed_projects']; ?></div>
                <div class="stat-label">Projetos Concluídos</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-success">
                <i class="fas fa-video"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['meetings_attended']; ?></div>
                <div class="stat-label">Reuniões Participadas</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-warning">
                <i class="fas fa-comments"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['community_posts']; ?></div>
                <div class="stat-label">Posts na Comunidade</div>
            </div>
        </div>
    </div>

		<div class="badges-section">
			<h3>Suas Conquistas</h3>
			<?php if (empty($userBadges)): ?>
				<p>Ainda não conquistou badges. Bora começar!</p>
			<?php else: ?>
				<div class="badges-list">
					<?php foreach ($userBadges as $badge): ?>
						<div class="badge-item" title="<?php echo escape($badge['descricao']); ?>">
							<i class="<?php echo escape($badge['icone']); ?>"></i>
							<span><?php echo escape($badge['nome']); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

	<div class='d-flex' style='justify-content: space-between;'>
        <!-- Recent Community Posts -->
        <div class="dashboard-card community-card">
            <div class="card-header">
                <h3>Atividade na Comunidade</h3>
                <a href="../pages/community.php" class="view-all-link">Ver comunidade</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_posts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>Nenhuma atividade recente</p>
                        <a href="../pages/community.php" class="btn btn-sm btn-primary">Visitar Comunidade</a>
                    </div>
                <?php else: ?>
                    <div class="posts-list">
                        <?php foreach ($recent_posts as $post): ?>
                            <div class="post-item">
                                <div class="post-author">
                                    <img src="<?php echo $post['author_avatar'] ?: '../assets/images/default-avatar.svg'; ?>" 
                                         alt="<?php echo escape($post['author_name']); ?>" 
                                         class="author-avatar">
                                    <div class="author-info">
                                        <span class="author-name"><?php echo escape($post['author_name']); ?></span>
                                        <span class="post-time"><?php echo date('d/m H:i', strtotime($post['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="post-content">
                                    <p><?php echo escape(substr($post['content'], 0, 120)) . '...'; ?></p>
                                </div>
                                <div class="post-stats">
                                    <span><i class="fas fa-heart"></i> <?php echo $post['likes_count']; ?></span>
                                    <span><i class="fas fa-comment"></i> <?php echo $post['comments_count']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>


        <!-- Quick Actions -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3>Ações Rápidas</h3>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="../pages/kanban.php" class="quick-action">
                        <div class="action-icon bg-success">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <div class="action-content">
                            <h4>Novo Projeto</h4>
                            <p>Crie e gerencie seus projetos</p>
                        </div>
                    </a>
                    
                    <a href="../pages/community.php" class="quick-action">
                        <div class="action-icon bg-success">
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                        <div class="action-content">
                            <h4>Compartilhar</h4>
                            <p>Faça um post na comunidade</p>
                        </div>
                    </a>
                    
                    <a href="../pages/repositories.php" class="quick-action">
                        <div class="action-icon bg-warning">
                            <i class="fas fa-code-branch"></i>
                        </div>
                        <div class="action-content">
                            <h4>Repositórios</h4>
                            <p>Gerencie seus repositórios de código</p>
                        </div>
                    </a>
                </div>
				
            </div>
        </div>
    </div>
</div>

<?php 
$additional_js = ['dashboard'];
require_once '../includes/footer.php'; 
?>
