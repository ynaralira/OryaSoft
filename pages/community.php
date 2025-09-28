<?php
$page_title = 'Comunidade';
$page_name = 'community';
$additional_css = ['community'];

require_once '../includes/header.php';

if (!function_exists('db_table_exists')) {
    function db_table_exists($pdo, $table){
        try { $st = $pdo->prepare("SHOW TABLES LIKE ?"); $st->execute([$table]); return (bool)$st->fetchColumn(); } catch (Throwable $e){ return false; }
    }
}
if (!function_exists('db_column_exists')) {
    function db_column_exists($pdo, $table, $column){
        try { $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?"); $st->execute([$column]); return (bool)$st->fetchColumn(); } catch (Throwable $e){ return false; }
    }
}

if (!function_exists('avatar_url')) {
    function avatar_url($raw) {
        $default = SITE_URL . '/assets/images/default-avatar.svg';
        if (!$raw || trim((string)$raw) === '') return $default;
        if (filter_var($raw, FILTER_VALIDATE_URL)) return $raw;
        if ($raw[0] === '/') return rtrim(SITE_URL, '/') . $raw;
        if (strpos($raw, 'uploads/') === 0) return rtrim(SITE_URL, '/') . '/' . $raw; 
        return rtrim(SITE_URL, '/') . '/uploads/avatars/' . $raw;
    }
}

$likes_table = db_table_exists($pdo, 'community_post_likes') ? 'community_post_likes' : (db_table_exists($pdo, 'post_likes') ? 'post_likes' : 'community_post_likes');
$comments_table = db_table_exists($pdo, 'community_post_comments') ? 'community_post_comments' : (db_table_exists($pdo, 'post_comments') ? 'post_comments' : 'community_post_comments');
$has_posts_is_active = db_column_exists($pdo, 'community_posts', 'is_active');
$has_comments_is_active = db_column_exists($pdo, $comments_table, 'is_active');
$type_col = db_column_exists($pdo, 'community_posts', 'type') ? 'type' : (db_column_exists($pdo, 'community_posts', 'post_type') ? 'post_type' : null);
$select_type = $type_col ? ", cp.$type_col AS type" : '';

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 10;

    if ($has_posts_is_active) {
        $count_sql = "SELECT COUNT(*) FROM community_posts WHERE is_active = 1";
    } else {
        $count_sql = "SELECT COUNT(*) FROM community_posts";
    }
    $total_posts = (int)$pdo->query($count_sql)->fetchColumn();
    $total_pages = max(1, (int)ceil($total_posts / $limit));

    if ($page > $total_pages) { $page = $total_pages; }
    $offset = ($page - 1) * $limit;
    if ($offset < 0) { $offset = 0; }

    $where = [];
    if ($has_posts_is_active) { $where[] = 'cp.is_active = 1'; }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $commentsActiveCond = $has_comments_is_active ? "AND c.is_active = 1" : '';
    $viewerId = isset($current_user['id']) ? (int)$current_user['id'] : 0;

    $likesCountSql = "(SELECT COUNT(*) FROM $likes_table l WHERE l.post_id = cp.id) AS like_count";
    $commentsCountSql = "(SELECT COUNT(*) FROM $comments_table c WHERE c.post_id = cp.id $commentsActiveCond) AS comment_count";
    $userLikedSql = "CASE WHEN EXISTS (SELECT 1 FROM $likes_table ul WHERE ul.post_id = cp.id AND ul.user_id = ?) THEN 1 ELSE 0 END AS user_liked";
    
    $sql = "
        SELECT cp.* $select_type,
               u.name as author_name,
               u.avatar as author_avatar,
               u.bio as author_bio,
               $likesCountSql,
               $commentsCountSql,
               $userLikedSql
        FROM community_posts cp
        JOIN users u ON cp.user_id = u.id
        $whereSql
        ORDER BY cp.created_at DESC
        LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$viewerId]);
    $posts = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Erro ao buscar posts: " . $e->getMessage());
    $posts = [];
    $total_pages = 1;
}

try {
    $whereUser = $has_posts_is_active ? 'WHERE u.is_active = 1' : 'WHERE 1=1';
    $andPosts = $has_posts_is_active ? 'AND cp.is_active = 1' : '';
    $sql = "
        SELECT u.id, u.name, u.avatar, u.bio,
               COUNT(DISTINCT cp.id) as post_count,
               COUNT(DISTINCT cpl.id) as total_likes
        FROM users u
        LEFT JOIN community_posts cp ON u.id = cp.user_id $andPosts
        LEFT JOIN $likes_table cpl ON cp.id = cpl.post_id
        $whereUser
        GROUP BY u.id
        HAVING post_count > 0
        ORDER BY total_likes DESC, post_count DESC
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $featured_users = $stmt->fetchAll();
    
    $eventos = [];
    try {
        $sqlEventos = "SELECT * FROM calendar_events WHERE end_date >= CURDATE() ORDER BY start_date ASC, start_time ASC";
        $stmtEventos = $pdo->prepare($sqlEventos);
        $stmtEventos->execute();
        $eventos = $stmtEventos->fetchAll();
    } catch (PDOException $e) {
        error_log('Erro ao buscar eventos: ' . $e->getMessage());
        $eventos = [];
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar usuários em destaque: " . $e->getMessage());
    $featured_users = [];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/community.css">
    <title>Comunidade - Orya</title>
</head>
<body>
    <div class="community-container">
        <div class="header-content mb-4">
            <div class="header-text">
                <h1>Comunidade</h1>
                <p>Conecte-se, compartilhe e aprenda com outras desenvolvedoras</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline" onclick="openEventModal()">
                    <i class="fas fa-calendar-plus"></i>
                    Criar Evento
                </button>
            </div>
        </div>

        <!-- Compositor de post -->
        <section class="post-composer" id="post-composer">
            <div class="composer-header">
                <img class="composer-avatar" alt="Seu avatar" src="<?php echo avatar_url($current_user['avatar'] ?? null); ?>">
                <div class="composer-input" style="width:100%">
                    <textarea id="composer-text" class="composer-textarea" placeholder="No que você está pensando?"></textarea>
                    <input id="composer-title" type="text" class="form-control" placeholder="Título (opcional)" style="margin-top:8px; display:none;">
                </div>
            </div>
            <div id="composer-preview" style="display:none; margin-top:10px;"></div>
            <div class="composer-actions">
                <div class="composer-tools">
                    <button type="button" class="composer-tool" id="composer-photo-btn"><i class="fas fa-image"></i> Foto</button>
                    <button type="button" class="composer-tool" id="composer-tags-btn"><i class="fas fa-hashtag"></i> Tags</button>
                    <input type="file" id="composer-image" accept="image/*" style="display:none;">
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input id="composer-tags" type="text" class="form-control" placeholder="tags (ex: javascript, react)" style="max-width:260px; display:none;">
                    <button type="button" id="composer-submit" class="btn btn-primary" disabled>
                        <i class="fas fa-paper-plane"></i>
                        Publicar
                    </button>
                </div>
            </div>
            <div id="composer-dropzone" style="display:none;"></div>
        </section>

        <div class="community-layout">
            <!-- Sidebar esquerda -->
            <aside class="community-sidebar left">
                <div class="sidebar-section">
                    <h3>Filtros</h3>
                    <div class="filter-options">
                        <?php $filters = [
                            'all' => 'Todas as publicações',
                            'questions' => 'Perguntas',
                            'tutorials' => 'Tutoriais',
                            'projects' => 'Projetos',
                            'discussions' => 'Discussões',
                            'tips' => 'Dicas'
                        ]; ?>
                        <?php foreach ($filters as $value => $label): ?>
                            <label class="filter-option">
                                <input type="radio" name="post-filter" value="<?php echo $value; ?>" <?php echo $value === 'all' ? 'checked' : ''; ?> >
                                <span class="filter-label"><?php echo $label; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sidebar-section">
                    <h3>Tags Populares</h3>
                    <div class="popular-tags">
                        <?php $tags = ['javascript','react','python','css','nodejs','frontend','backend','carreira']; ?>
                        <?php foreach ($tags as $tag): ?>
                            <span class="tag-pill" onclick="filterByTag('<?php echo $tag; ?>')"><?php echo ucfirst($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <main class="community-main">
                <div class="posts-feed" id="posts-feed">
                    <?php if (empty($posts)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <h3>Nenhuma publicação encontrada</h3>
                            <p>Seja a primeira a compartilhar algo com a comunidade!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <article class="post-card" data-post-id="<?php echo $post['id']; ?>">
                                <div class="post-header">
                                    <div class="post-author">
                                        <img src="<?php echo avatar_url($post['author_avatar'] ?? null); ?>" alt="<?php echo escape($post['author_name']); ?>" class="author-avatar">
                                        <div class="author-info">
                                            <h4 class="author-name"><?php echo escape($post['author_name']); ?></h4>
                                            <span class="post-date"><i class="fas fa-clock"></i> <?php echo timeAgo($post['created_at']); ?> <small style="color:#888;">(<?php echo date('d/m/Y H:i', strtotime($post['created_at'])); ?>)</small></span>
                                        </div>
                                    </div>
                                    <?php if ($post['user_id'] == $current_user['id']): ?>
                                        <div class="post-actions">
                                            <button class="post-action-btn" onclick="editPost(<?php echo $post['id']; ?>)"><i class="fas fa-edit"></i></button>
                                            <button class="post-action-btn" onclick="deletePost(<?php echo $post['id']; ?>)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="post-content">
                                    <div class="post-text"><?php echo formatPostContent($post['content']); ?></div>
                                    <?php if ($post['image_url']): ?>
                                        <div class="post-image">
                                            <img src="<?php echo SITE_URL . '/uploads/posts/' . $post['image_url']; ?>" alt="Imagem do post" onclick="openImageModal('<?php echo SITE_URL . '/uploads/posts/' . $post['image_url']; ?>')">
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($post['tags']): ?>
                                        <div class="post-tags">
                                            <?php $tags = json_decode($post['tags'], true) ?: []; ?>
                                            <?php foreach ($tags as $tag): ?>
                                                <span class="post-tag" onclick="filterByTag('<?php echo escape($tag); ?>')">#<?php echo escape($tag); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="post-footer">
                                    <div class="post-stats">
                                        <button class="stat-btn like-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>" onclick="togglePostLike(<?php echo $post['id']; ?>)"><i class="fas fa-heart"></i> <span class="like-count"><?php echo $post['like_count']; ?></span></button>
                                        <button class="stat-btn comment-btn" onclick="toggleComments(<?php echo $post['id']; ?>)"><i class="fas fa-comment"></i> <span class="comment-count"><?php echo $post['comment_count']; ?></span></button>
                                        <button class="stat-btn share-btn" onclick="sharePost(<?php echo $post['id']; ?>)"><i class="fas fa-share"></i> Compartilhar</button>
                                    </div>
                                </div>
                                <div class="post-comments" id="comments-<?php echo $post['id']; ?>" style="display: none;">
                                    <div class="comments-list"></div>
                                    <form class="comment-form" onsubmit="submitComment(event, <?php echo $post['id']; ?>)">
                                        <div class="comment-input">
                                            <img src="<?php echo avatar_url($current_user['avatar'] ?? null); ?>" alt="Seu avatar" class="comment-avatar">
                                            <textarea placeholder="Escreva um comentário..." required rows="2"></textarea>
                                            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane"></i></button>
                                        </div>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>

            <aside class="community-sidebar right">
                <div class="sidebar-section">
                    <h3>Devs em Destaque</h3>
                    <div class="featured-users">
                        <?php foreach ($featured_users as $user): ?>
                            <div class="featured-user">
                                <img src="<?php echo avatar_url($user['avatar'] ?? null); ?>" alt="<?php echo escape($user['name']); ?>" class="featured-avatar">
                                <div class="featured-info">
                                    <h4><?php echo escape($user['name']); ?></h4>
                                    <p><?php echo escape(substr($user['bio'] ?? '', 0, 60)) . (strlen($user['bio'] ?? '') > 60 ? '...' : ''); ?></p>
                                    <span class="featured-stats"><?php echo $user['post_count']; ?> posts • <?php echo $user['total_likes']; ?> curtidas</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sidebar-section">
                    <h3>Próximos Eventos</h3>
                    <div class="upcoming-events">
                        <?php if (!empty($eventos)): ?>
                            <?php foreach ($eventos as $evento): ?>
                                <div class="event-item">
                                    <div class="event-date">
                                        <span class="event-day"><?php echo date('d', strtotime($evento['start_date'])); ?></span>
                                        <span class="event-month"><?php echo date('M', strtotime($evento['start_date'])); ?></span>
                                    </div>
                                    <div class="event-info">
                                        <h4><?php echo escape($evento['title']); ?></h4>
                                        <p><?php echo escape($evento['description']); ?></p>
                                        <?php if (!empty($evento['start_time'])): ?>
                                            <span class="event-time"><?php echo date('H:i', strtotime($evento['start_time'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="event-item">
                                <div class="event-info">
                                    <span style="color:#888;">Nenhum evento futuro encontrado.</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-sm btn-outline w-full" onclick="openEventModal()"><i class="fas fa-calendar-plus"></i> Criar Evento</button>
                </div>
            </aside>
        </div>
</body>
</html>
 <script src="../assets/js/community.js" defer></script>
<?php 
require_once '../includes/footer.php'; 

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'agora';
    if ($time < 3600) return floor($time/60) . 'm';
    if ($time < 86400) return floor($time/3600) . 'h';
    if ($time < 2592000) return floor($time/86400) . 'd';
    if ($time < 31536000) return floor($time/2592000) . ' meses';
    
    return floor($time/31536000) . ' anos';
}

function getPostTypeLabel($type) {
    $labels = [
        'discussion' => 'Discussão',
        'question' => 'Pergunta',
        'tutorial' => 'Tutorial',
        'project' => 'Projeto',
        'tip' => 'Dica',
        'news' => 'Notícia'
    ];
    
    return $labels[$type] ?? ucfirst($type);
}

function formatPostContent($content) {
    $content = escape($content);
    $content = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1" target="_blank" rel="noopener">$1<\/a>', $content);
    $content = preg_replace('/(^|\s)#([\p{L}\p{N}_-]+)/u', '$1<a class="hashtag" href="?tag=$2">#$2<\/a>', $content);
    $content = preg_replace('/(^|\s)@([\p{L}\p{N}\._-]+)/u', '$1<a class="mention" href="?search=%40$2">@$2<\/a>', $content);
 
    $content = nl2br($content);
    
    return $content;
}
?>
