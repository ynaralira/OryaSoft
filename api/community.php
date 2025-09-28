<?php
/**
 * API da Comunidade - Plataforma Orya
 * ===================================
 * 
 * Esta API gerencia todas as operações relacionadas à comunidade:
 * - Criar, editar e excluir posts
 * - Listar posts com filtros
 * - Curtir/descurtir posts
 * - Comentários em posts
 * - Sistema de tags
 */

require_once '../includes/config.php';

// Verificar autenticação
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Configurar headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Tratar OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        case 'PUT':
            handlePutRequest();
            break;
        case 'DELETE':
            handleDeleteRequest();
            break;
        default:
            throw new Exception('Método não suportado');
    }
} catch (Exception $e) {
    error_log("Erro na API da comunidade: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}

/**
 * Manipula requisições GET
 */
function handleGetRequest() {
    global $pdo, $user_id;
    
    // Buscar comentários de um post (precisa vir antes do post_id isolado)
    if (isset($_GET['comments']) && isset($_GET['post_id'])) {
        $post_id = (int)$_GET['post_id'];
        $comments = getPostComments($post_id, $user_id);
        echo json_encode(['success' => true, 'comments' => $comments]);
        return;
    }

    // Buscar post específico (com opção de incluir comentários completos)
    if (isset($_GET['post_id'])) {
        $post_id = (int)$_GET['post_id'];
        $post = getPostById($post_id, $user_id);
        
        if ($post) {
            if (!empty($_GET['include_comments'])) {
                $limit = isset($_GET['comments_limit']) ? max(1, (int)$_GET['comments_limit']) : null; // sem limite por padrão
                $post['comments'] = getPostComments($post_id, $user_id, $limit);
            }
            echo json_encode(['success' => true, 'post' => $post]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Post não encontrado']);
        }
        return;
    }
    
    // Listar posts com filtros (com saneamento de paginação)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) { $page = 1; }
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if ($limit < 1) { $limit = 10; }
    $limit = min($limit, 20);

    $filters = [
        'type' => $_GET['type'] ?? 'all',
        'tag' => $_GET['tag'] ?? '',
        'search' => $_GET['search'] ?? '',
        'sort' => $_GET['sort'] ?? 'recent',
        'page' => $page,
        'limit' => $limit
    ];
    
    $posts = getCommunityPosts($user_id, $filters);

    // Opcional: incluir comentários recentes de cada post (ex.: últimos 3)
    if (!empty($_GET['include_comments'])) {
        $cLimit = isset($_GET['comments_limit']) ? max(1, (int)$_GET['comments_limit']) : 3;
        foreach ($posts as &$p) {
            $p['recent_comments'] = getPostComments($p['id'], $user_id, $cLimit);
        }
        unset($p);
    }

    echo json_encode(['success' => true, 'posts' => $posts]);
}

/**
 * Manipula requisições POST
 */
function handlePostRequest() {
    global $pdo, $user_id;
    
    // Verificar se é ação específica
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['action'])) {
        handleSpecialAction($input);
        return;
    }
    
    // Criar ou atualizar post
    $data = validatePostData($_POST, $_FILES);
    
    if (isset($_POST['post_id']) && !empty($_POST['post_id'])) {
        // Atualizar post existente
        $post_id = (int)$_POST['post_id'];
        $result = updatePost($post_id, $data, $user_id);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Post atualizado com sucesso',
                'post_id' => $post_id
            ]);
        } else {
            throw new Exception('Erro ao atualizar post');
        }
    } else {
        // Criar novo post
        $post_id = createPost($data, $user_id);
        
        if ($post_id) {
            echo json_encode([
                'success' => true,
                'message' => 'Post criado com sucesso',
                'post_id' => $post_id
            ]);
        } else {
            throw new Exception('Erro ao criar post');
        }
    }
}

/**
 * Manipula requisições PUT
 */
function handlePutRequest() {
    global $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['post_id'])) {
        throw new Exception('ID do post é obrigatório');
    }
    
    $post_id = (int)$input['post_id'];
    $data = validatePostData($input, []);
    
    $result = updatePost($post_id, $data, $user_id);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Post atualizado com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao atualizar post');
    }
}

/**
 * Manipula requisições DELETE
 */
function handleDeleteRequest() {
    global $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['post_id'])) {
        throw new Exception('ID do post é obrigatório');
    }
    
    $post_id = (int)$input['post_id'];
    $result = deletePost($post_id, $user_id);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Post excluído com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao excluir post');
    }
}

/**
 * Manipula ações especiais
 */
function handleSpecialAction($input) {
    global $user_id;
    
    $action = $input['action'];
    
    switch ($action) {
        case 'like_post':
            if (!isset($input['post_id'])) {
                throw new Exception('ID do post é obrigatório');
            }
            $post_id = (int)$input['post_id'];
            $result = togglePostLike($post_id, $user_id);
            $message = $result ? 'Like atualizado' : 'Erro ao atualizar like';
            break;
            
        case 'add_comment':
            if (!isset($input['post_id']) || !isset($input['content'])) {
                throw new Exception('ID do post e conteúdo são obrigatórios');
            }
            $post_id = (int)$input['post_id'];
            $content = $input['content'];
            $result = addComment($post_id, $content, $user_id);
            $message = $result ? 'Comentário adicionado com sucesso' : 'Erro ao adicionar comentário';
            break;
            
        case 'delete_comment':
            if (!isset($input['comment_id'])) {
                throw new Exception('ID do comentário é obrigatório');
            }
            $comment_id = (int)$input['comment_id'];
            $result = deleteComment($comment_id, $user_id);
            $message = $result ? 'Comentário excluído com sucesso' : 'Erro ao excluir comentário';
            break;
            
        default:
            throw new Exception('Ação não suportada');
    }
    
    echo json_encode([
        'success' => $result,
        'message' => $message
    ]);
}

/**
 * Busca post por ID
 */
function getPostById($post_id, $user_id) {
    global $pdo;

    $likesTbl = db_table_exists('community_post_likes') ? 'community_post_likes' : (db_table_exists('post_likes') ? 'post_likes' : 'community_post_likes');
    $commentsTbl = db_table_exists('community_post_comments') ? 'community_post_comments' : (db_table_exists('post_comments') ? 'post_comments' : 'community_post_comments');
    $hasPostActive = db_column_exists('community_posts', 'is_active');
    $hasCommentActive = db_column_exists($commentsTbl, 'is_active');

    $where = ['cp.id = ?'];
    if ($hasPostActive) { $where[] = 'cp.is_active = 1'; }

    $commentJoinCond = "cp.id = c.post_id" . ($hasCommentActive ? " AND c.is_active = 1" : "");

    $sql = "
        SELECT cp.*, 
               u.name as author_name,
               u.avatar as author_avatar,
               u.bio as author_bio,
               COUNT(DISTINCT l.id) as like_count,
               COUNT(DISTINCT c.id) as comment_count,
               CASE WHEN ul.user_id IS NOT NULL THEN 1 ELSE 0 END as user_liked
        FROM community_posts cp
        JOIN users u ON cp.user_id = u.id
        LEFT JOIN $likesTbl l ON cp.id = l.post_id
        LEFT JOIN $commentsTbl c ON $commentJoinCond
        LEFT JOIN $likesTbl ul ON cp.id = ul.post_id AND ul.user_id = ?
        WHERE " . implode(' AND ', $where) . "
        GROUP BY cp.id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $post_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Busca posts da comunidade com filtros
 */
function getCommunityPosts($user_id, $filters) {
    global $pdo;

    $likesTbl = db_table_exists('community_post_likes') ? 'community_post_likes' : (db_table_exists('post_likes') ? 'post_likes' : 'community_post_likes');
    $commentsTbl = db_table_exists('community_post_comments') ? 'community_post_comments' : (db_table_exists('post_comments') ? 'post_comments' : 'community_post_comments');
    $hasPostActive = db_column_exists('community_posts', 'is_active');
    $hasCommentActive = db_column_exists($commentsTbl, 'is_active');
    $hasTags = db_column_exists('community_posts', 'tags');

    $where_conditions = [];
    if ($hasPostActive) { $where_conditions[] = 'cp.is_active = 1'; }
    $params = [];

    // Filtro de tipo (aplicar somente se vier valor e não for 'all')
    if (!empty($filters['type']) && strtolower($filters['type']) !== 'all') {
        $typeCol = db_column_exists('community_posts','type') ? 'cp.type' : (db_column_exists('community_posts','post_type') ? 'cp.post_type' : 'cp.type');
        $where_conditions[] = "$typeCol = ?";
        $params[] = map_post_type($filters['type']);
    }

    // Filtro de tag (somente se a coluna existir)
    if ($hasTags && !empty($filters['tag'])) {
        $where_conditions[] = "JSON_CONTAINS(cp.tags, JSON_QUOTE(?))";
        $params[] = $filters['tag'];
    }

    // Filtro de busca
    if (!empty($filters['search'])) {
        $where_conditions[] = "(cp.title LIKE ? OR cp.content LIKE ?)";

        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $commentJoinCond = "cp.id = c.post_id" . ($hasCommentActive ? " AND c.is_active = 1" : "");

    $sql = "
        SELECT cp.*, 
               u.name as author_name,
               u.avatar as author_avatar,
               u.bio as author_bio,
               COUNT(DISTINCT l.id) as like_count,
               COUNT(DISTINCT c.id) as comment_count,
               CASE WHEN ul.user_id IS NOT NULL THEN 1 ELSE 0 END as user_liked
        FROM community_posts cp
        JOIN users u ON cp.user_id = u.id
        LEFT JOIN $likesTbl l ON cp.id = l.post_id
        LEFT JOIN $commentsTbl c ON $commentJoinCond
        LEFT JOIN $likesTbl ul ON cp.id = ul.post_id AND ul.user_id = ?
        " . (!empty($where_conditions) ? ("WHERE " . implode(' AND ', $where_conditions)) : "") . "
        GROUP BY cp.id
    ";

    array_unshift($params, $user_id);

    // Ordenação
    switch ($filters['sort']) {
        case 'popular':
            $sql .= " ORDER BY like_count DESC, comment_count DESC, cp.created_at DESC";
            break;
        case 'comments':
            $sql .= " ORDER BY comment_count DESC, cp.created_at DESC";
            break;
        default:
            $sql .= " ORDER BY cp.created_at DESC";
    }

    // Paginação
    $offset = ($filters['page'] - 1) * $filters['limit'];
    if ($offset < 0) { $offset = 0; }
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = (int)$filters['limit'];
    $params[] = (int)$offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helpers de compatibilidade com o banco
function db_table_exists($table){
    global $pdo;
    try {
        // O nome da tabela é controlado pela aplicação, então é seguro interpolar
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $stmt = $pdo->query("SHOW TABLES LIKE '" . $table . "'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : false;
        return (bool)$row;
    } catch (Throwable $e){ return false; }
}
function db_column_exists($table, $column){
    global $pdo;
    try {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $stmt = $pdo->query("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $column . "'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : false;
        return (bool)$row;
    } catch (Throwable $e){ return false; }
}
function map_post_type($t){
    $t = strtolower(trim((string)$t));
    switch($t){
        case 'discussion': return 'text';
        case 'question': return 'question';
        case 'tip': return 'tip';
        case 'project': return 'achievement';
        case 'tutorial':
        case 'news': return 'resource';
        default: return 'text';
    }
}

/**
 * Busca comentários de um post
 */
function getPostComments($post_id, $user_id, $limit = null) {
    global $pdo;
    $tbl = db_table_exists('community_post_comments') ? 'community_post_comments' : (db_table_exists('post_comments') ? 'post_comments' : 'community_post_comments');
    $hasIsActive = db_column_exists($tbl, 'is_active');
    $where = $hasIsActive ? "c.post_id = ? AND c.is_active = 1" : "c.post_id = ?";

    // Detectar colunas existentes para evitar erros de coluna desconhecida
    $contentCol = db_column_exists($tbl, 'content') ? 'c.content' : (db_column_exists($tbl, 'comment') ? 'c.comment' : (db_column_exists($tbl, 'text') ? 'c.text' : "''"));
    $createdCol = db_column_exists($tbl, 'created_at') ? 'c.created_at' : (db_column_exists($tbl, 'createdAt') ? 'c.createdAt' : 'NOW()');

    // Colunas de usuário (tabela users)
    $nameCol = db_column_exists('users', 'name') ? 'u.name' : (db_column_exists('users', 'username') ? 'u.username' : (db_column_exists('users', 'full_name') ? 'u.full_name' : "''"));
    $avatarCol = db_column_exists('users', 'avatar') ? 'u.avatar' : (db_column_exists('users', 'avatar_url') ? 'u.avatar_url' : (db_column_exists('users', 'profile_image') ? 'u.profile_image' : "''"));

    $sql = "
        SELECT
            c.id,
            c.post_id,
            c.user_id,
            $contentCol AS content,
            $createdCol AS created_at,
            $nameCol AS author_name,
            $avatarCol AS author_avatar
        FROM $tbl c
        JOIN users u ON c.user_id = u.id
        WHERE $where
        ORDER BY $createdCol ASC
    ";

    $params = [$post_id];
    if (!is_null($limit)) {
        $sql .= " LIMIT ?";
        $params[] = (int)$limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Valida dados do post
 */
function validatePostData($data, $files) {
    $errors = [];
    
    // Título obrigatório
    if (empty($data['title'])) {
        $errors[] = 'Título é obrigatório';
    } elseif (strlen($data['title']) < 5) {
        $errors[] = 'Título deve ter pelo menos 5 caracteres';
    } elseif (strlen($data['title']) > 200) {
        $errors[] = 'Título deve ter no máximo 200 caracteres';
    }
    
    // Conteúdo obrigatório
    if (empty($data['content'])) {
        $errors[] = 'Conteúdo é obrigatório';
    } elseif (strlen($data['content']) < 10) {
        $errors[] = 'Conteúdo deve ter pelo menos 10 caracteres';
    }
    
    // Tipo válido (aceita valores do front e mapeia para o enum do banco)
    $client_type = $data['type'] ?? 'discussion';
    $db_type = map_post_type($client_type);
    
    if (!empty($errors)) {
        http_response_code(400);
        throw new Exception(implode(', ', $errors));
    }
    
    // Processar tags (opcional, só se existir a coluna)
    $tags = [];
    if (!empty($data['tags'])) {
        $tag_list = explode(',', $data['tags']);
        $tags = array_map('trim', $tag_list);
        $tags = array_filter($tags);
        $tags = array_slice($tags, 0, 10);
    }
    
    // Upload de imagem
    $image_url = null;
    if (isset($files['image']) && $files['image']['error'] === UPLOAD_ERR_OK) {
        $image_url = uploadPostImage($files['image']);
        if (!$image_url) { $errors[] = 'Erro ao fazer upload da imagem'; }
    }
    if (!empty($errors)) { throw new Exception(implode(', ', $errors)); }
    
    return [
        'title' => sanitizeString($data['title']),
        'content' => sanitizeString($data['content']),
        'db_type' => $db_type,
        'tags_json' => json_encode($tags),
        'image_url' => $image_url
    ];
}

/**
 * Faz upload de imagem do post
 */
function uploadPostImage($file) {
    $upload_dir = '../uploads/posts/';
    
    // Criar diretório se não existir
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validar tipo de arquivo
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    // Validar tamanho (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }
    
    // Gerar nome único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return false;
}

/**
 * Cria novo post
 */
function createPost($data, $user_id) {
    global $pdo;
    $hasType = db_column_exists('community_posts','type');
    $hasPostType = db_column_exists('community_posts','post_type');
    $hasTags = db_column_exists('community_posts','tags');
    $hasImage = db_column_exists('community_posts','image_url');
    
    $cols = ['user_id','title','content'];
    $vals = [$user_id, $data['title'], $data['content']];
    if ($hasType) { $cols[]='type'; $vals[]=$data['db_type']; }
    if ($hasPostType) { $cols[]='post_type'; $vals[]=$data['db_type']; }
    if ($hasTags) { $cols[]='tags'; $vals[]=$data['tags_json']; }
    if ($hasImage) { $cols[]='image_url'; $vals[]=$data['image_url']; }
    
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO community_posts (".implode(',', $cols).") VALUES ($placeholders)";
    
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($vals);
    return $ok ? $pdo->lastInsertId() : false;
}

/**
 * Atualiza post existente
 */
function updatePost($post_id, $data, $user_id) {
    global $pdo;
    // Verificar se usuário tem permissão para editar
    $hasPostActive = db_column_exists('community_posts','is_active');
    $sql = "SELECT user_id FROM community_posts WHERE id = ?" . ($hasPostActive ? " AND is_active = 1" : "");
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    if (!$post) { throw new Exception('Post não encontrado'); }
    if ($post['user_id'] != $user_id) { throw new Exception('Você não tem permissão para editar este post'); }

    $hasType = db_column_exists('community_posts','type');
    $hasPostType = db_column_exists('community_posts','post_type');
    $hasTags = db_column_exists('community_posts','tags');
    $hasImage = db_column_exists('community_posts','image_url');

    $sets = ['title = ?','content = ?'];
    $vals = [$data['title'], $data['content']];
    if ($hasType) { $sets[] = 'type = ?'; $vals[] = $data['db_type']; }
    if ($hasPostType) { $sets[] = 'post_type = ?'; $vals[] = $data['db_type']; }
    if ($hasTags) { $sets[] = 'tags = ?'; $vals[] = $data['tags_json']; }
    if ($hasImage && !empty($data['image_url'])) { $sets[] = 'image_url = ?'; $vals[] = $data['image_url']; }
    $sets[] = 'updated_at = NOW()';

    $sql = "UPDATE community_posts SET ".implode(', ', $sets)." WHERE id = ?";
    $vals[] = $post_id;
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($vals);
}

/**
 * Exclui post
 */
function deletePost($post_id, $user_id) {
    global $pdo;

    // Verificar se usuário tem permissão para excluir
    $sql = "SELECT user_id FROM community_posts WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        throw new Exception('Post não encontrado');
    }

    if ($post['user_id'] != $user_id) {
        throw new Exception('Você não tem permissão para excluir este post');
    }

    $hasPostActive = db_column_exists('community_posts','is_active');

    if ($hasPostActive) {
        // Soft delete
        $sql = "UPDATE community_posts SET is_active = 0, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$post_id]);
    } else {
        // Hard delete se não houver coluna de status
        $sql = "DELETE FROM community_posts WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$post_id]);
    }
}

/**
 * Alterna like do post
 */
function togglePostLike($post_id, $user_id) {
    global $pdo;
    $tbl = db_table_exists('community_post_likes') ? 'community_post_likes' : (db_table_exists('post_likes') ? 'post_likes' : 'community_post_likes');
    $sql = "SELECT id FROM $tbl WHERE post_id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$post_id, $user_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $sql = "DELETE FROM $tbl WHERE post_id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$post_id, $user_id]);
    } else {
        $sql = "INSERT INTO $tbl (post_id, user_id, created_at) VALUES (?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$post_id, $user_id]);
    }
}

/**
 * Adiciona comentário
 */
function addComment($post_id, $content, $user_id) {
    global $pdo;
    if (strlen(trim($content)) < 3) { throw new Exception('Comentário deve ter pelo menos 3 caracteres'); }
    $tbl = db_table_exists('community_post_comments') ? 'community_post_comments' : (db_table_exists('post_comments') ? 'post_comments' : 'community_post_comments');
    $sql = "INSERT INTO $tbl (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$post_id, $user_id, sanitizeString($content)]);
}

/**
 * Exclui comentário
 */
function deleteComment($comment_id, $user_id) {
    global $pdo;
    
    // Verificar se usuário tem permissão para excluir
    $sql = "SELECT user_id FROM community_post_comments WHERE id = ? AND is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();
    
    if (!$comment) {
        throw new Exception('Comentário não encontrado');
    }
    
    if ($comment['user_id'] != $user_id) {
        throw new Exception('Você não tem permissão para excluir este comentário');
    }
    
    // Soft delete
    $sql = "UPDATE community_post_comments SET is_active = 0, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$comment_id]);
}

/**
 * Sanitiza string
 */
function sanitizeString($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}
?>
