<?php
/**
 * API de Repositórios - Plataforma Orya
 * ====================================
 * 
 * Esta API gerencia todas as operações relacionadas a repositórios:
 * - Criar, editar e excluir repositórios
 * - Listar repositórios com filtros
 * - Curtir/descurtir repositórios
 * - Importar repositórios do GitHub
 */

require_once '../includes/config.php';

// Verificar autenticação
session_start();
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
    error_log("Erro na API de repositórios: " . $e->getMessage());
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
    
    // Buscar repositório específico
    if (isset($_GET['id'])) {
        $repo_id = (int)$_GET['id'];
        $repository = getRepositoryById($repo_id, $user_id);
        
        if ($repository) {
            echo json_encode(['success' => true, 'repository' => $repository]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Repositório não encontrado']);
        }
        return;
    }
    
    // Listar repositórios com filtros
    $filters = [
        'search' => $_GET['search'] ?? '',
        'language' => $_GET['language'] ?? '',
        'visibility' => $_GET['visibility'] ?? 'all',
        'sort' => $_GET['sort'] ?? 'recent',
        'limit' => min((int)($_GET['limit'] ?? 20), 50),
        'offset' => max((int)($_GET['offset'] ?? 0), 0)
    ];
    
    $repositories = getRepositories($user_id, $filters);
    echo json_encode(['success' => true, 'repositories' => $repositories]);
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
    
    // Criar ou atualizar repositório via form data
    $data = validateRepositoryData($_POST);
    
    if (isset($_POST['repository_id']) && !empty($_POST['repository_id'])) {
        // Atualizar repositório existente
        $repo_id = (int)$_POST['repository_id'];
        $result = updateRepository($repo_id, $data, $user_id);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Repositório atualizado com sucesso',
                'repository_id' => $repo_id
            ]);
        } else {
            throw new Exception('Erro ao atualizar repositório');
        }
    } else {
        // Criar novo repositório
        $repo_id = createRepository($data, $user_id);
        
        if ($repo_id) {
            echo json_encode([
                'success' => true,
                'message' => 'Repositório criado com sucesso',
                'repository_id' => $repo_id
            ]);
        } else {
            throw new Exception('Erro ao criar repositório');
        }
    }
}

/**
 * Manipula requisições PUT
 */
function handlePutRequest() {
    global $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['repository_id'])) {
        throw new Exception('ID do repositório é obrigatório');
    }
    
    $repo_id = (int)$input['repository_id'];
    $data = validateRepositoryData($input);
    
    $result = updateRepository($repo_id, $data, $user_id);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Repositório atualizado com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao atualizar repositório');
    }
}

/**
 * Manipula requisições DELETE
 */
function handleDeleteRequest() {
    global $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['repository_id'])) {
        throw new Exception('ID do repositório é obrigatório');
    }
    
    $repo_id = (int)$input['repository_id'];
    $result = deleteRepository($repo_id, $user_id);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Repositório excluído com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao excluir repositório');
    }
}

/**
 * Manipula ações especiais
 */
function handleSpecialAction($input) {
    global $user_id;
    
    $action = $input['action'];
    
    switch ($action) {
        case 'like':
            if (!isset($input['repository_id'])) {
                throw new Exception('ID do repositório é obrigatório');
            }
            $repo_id = (int)$input['repository_id'];
            $result = toggleRepositoryLike($repo_id, $user_id);
            $message = $result ? 'Like atualizado' : 'Erro ao atualizar like';
            echo json_encode(['success' => (bool)$result, 'message' => $message]);
            return;
            
        case 'import_github':
            if (!isset($input['github_repos']) || !is_array($input['github_repos'])) {
                throw new Exception('Lista de repositórios do GitHub é obrigatória');
            }
            $summary = importGitHubRepositories($input['github_repos'], $user_id);
            $message = "Importação concluída (importados {$summary['imported']}, já existentes {$summary['skipped']}" . ($summary['errors'] ? ", erros {$summary['errors']}" : "") . ")";
            echo json_encode([
                'success' => true,
                'message' => $message,
                'data' => $summary
            ]);
            return;
            
        default:
            throw new Exception('Ação não suportada');
    }
}

/**
 * Busca repositório por ID
 */
function getRepositoryById($repo_id, $user_id) {
    global $pdo;

    // Compatibilidade de esquema (owner_id vs user_id, visibility vs is_private, likes opcionais)
    $userCol = db_column_exists('repositories','user_id') ? 'user_id' : 'owner_id';
    $hasVisibility = db_column_exists('repositories','visibility');
    $hasIsPrivate = db_column_exists('repositories','is_private');
    $hasIsActive = db_column_exists('repositories','is_active');
    $likeTblExists = db_table_exists('repository_likes');

    $selectLikes = $likeTblExists ? 
        "COUNT(DISTINCT rl.id) as like_count, CASE WHEN rl_user.user_id IS NOT NULL THEN 1 ELSE 0 END as user_liked" :
        "0 as like_count, 0 as user_liked";
    $joinLikes = $likeTblExists ? "LEFT JOIN repository_likes rl ON r.id = rl.repository_id
        LEFT JOIN repository_likes rl_user ON r.id = rl_user.repository_id AND rl_user.user_id = ?" : "";

    $where = ["r.id = ?"]; 
    $params = [];
    if ($hasIsActive) { $where[] = "r.is_active = 1"; }
    if ($hasVisibility) {
        $where[] = "(r.visibility = 'public' OR r.$userCol = ?)";
    } elseif ($hasIsPrivate) {
        $where[] = "(r.is_private = 0 OR r.$userCol = ?)";
    }

    // Montar SQL
    $sql = "
        SELECT r.*, 
               u.name as owner_name,
               u.avatar as owner_avatar,
               $selectLikes
        FROM repositories r
        JOIN users u ON r.$userCol = u.id
        $joinLikes
        WHERE " . implode(' AND ', $where) . "
        GROUP BY r.id
    ";

    // Montar parâmetros
    if ($likeTblExists) { $params[] = $user_id; }
    $params[] = $repo_id;
    if ($hasVisibility || $hasIsPrivate) { $params[] = $user_id; }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Busca repositórios com filtros
 */
function getRepositories($user_id, $filters) {
    global $pdo;

    $userCol = db_column_exists('repositories','user_id') ? 'user_id' : 'owner_id';
    $hasVisibility = db_column_exists('repositories','visibility');
    $hasIsPrivate = db_column_exists('repositories','is_private');
    $hasIsActive = db_column_exists('repositories','is_active');
    $likeTblExists = db_table_exists('repository_likes');

    $where = [];
    if ($hasIsActive) { $where[] = 'r.is_active = 1'; }

    // Filtro de visibilidade
    if ($filters['visibility'] === 'public') {
        if ($hasVisibility) { $where[] = "r.visibility = 'public'"; }
        elseif ($hasIsPrivate) { $where[] = "r.is_private = 0"; }
    } elseif ($filters['visibility'] === 'private') {
        if ($hasVisibility) { $where[] = "r.visibility = 'private' AND r.$userCol = ?"; }
        elseif ($hasIsPrivate) { $where[] = "r.is_private = 1 AND r.$userCol = ?"; }
    } else {
        if ($hasVisibility) { $where[] = "(r.visibility = 'public' OR r.$userCol = ?)"; }
        elseif ($hasIsPrivate) { $where[] = "(r.is_private = 0 OR r.$userCol = ?)"; }
    }

    $params = [];
    $sql = "
        SELECT r.*, 
               u.name as owner_name,
               u.avatar as owner_avatar,
               " . ($likeTblExists ? "COUNT(DISTINCT rl.id) as like_count, CASE WHEN rl_user.user_id IS NOT NULL THEN 1 ELSE 0 END as user_liked" : "0 as like_count, 0 as user_liked") . "
        FROM repositories r
        JOIN users u ON r.$userCol = u.id
        " . ($likeTblExists ? "LEFT JOIN repository_likes rl ON r.id = rl.repository_id
        LEFT JOIN repository_likes rl_user ON r.id = rl_user.repository_id AND rl_user.user_id = ?" : "") . "
        WHERE " . implode(' AND ', $where);

    if ($likeTblExists) { $params[] = $user_id; }

    // Visibilidade params quando necessário
    if ($filters['visibility'] === 'private' || $filters['visibility'] === 'all') {
        if ($hasVisibility || $hasIsPrivate) { $params[] = $user_id; }
    }

    // Filtro de busca
    if (!empty($filters['search'])) {
        $sql .= " AND (r.name LIKE ? OR r.description LIKE ?)";
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Filtro de linguagem (compat: language ou primary_language)
    $langCol = db_column_exists('repositories','primary_language') ? 'r.primary_language' : (db_column_exists('repositories','language') ? 'r.language' : null);
    if ($langCol && !empty($filters['language'])) {
        $sql .= " AND $langCol = ?";
        $params[] = $filters['language'];
    }

    $sql .= " GROUP BY r.id";

    // Ordenação
    $orderUpdatedCol = db_column_exists('repositories','last_update') ? 'r.last_update' : 'r.updated_at';
    switch ($filters['sort']) {
        case 'stars':
            $sql .= " ORDER BY like_count DESC, r.created_at DESC";
            break;
        case 'name':
            $sql .= " ORDER BY r.name ASC";
            break;
        case 'updated':
            $sql .= " ORDER BY $orderUpdatedCol DESC";
            break;
        default:
            $sql .= " ORDER BY r.created_at DESC";
    }

    // Paginação
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = (int)$filters['limit'];
    $params[] = (int)max(0, (int)$filters['offset']);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Valida dados do repositório
 */
function validateRepositoryData($data) {
    $errors = [];
    
    // Nome obrigatório
    if (empty($data['name'])) {
        $errors[] = 'Nome é obrigatório';
    } elseif (strlen($data['name']) < 2) {
        $errors[] = 'Nome deve ter pelo menos 2 caracteres';
    } elseif (strlen($data['name']) > 100) {
        $errors[] = 'Nome deve ter no máximo 100 caracteres';
    }
    
    // URL obrigatória
    if (empty($data['url'])) {
        $errors[] = 'URL do repositório é obrigatória';
    } elseif (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'URL do repositório deve ser válida';
    }
    
    // URL da demo (opcional)
    if (!empty($data['demo_url']) && !filter_var($data['demo_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'URL da demo deve ser válida';
    }
    
    // Visibilidade
    if (!in_array($data['visibility'] ?? 'public', ['public', 'private'])) {
        $errors[] = 'Visibilidade deve ser pública ou privada';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        throw new Exception(implode(', ', $errors));
    }
    
    // Processar tecnologias
    $technologies = [];
    if (!empty($data['technologies'])) {
        $tech_list = explode(',', $data['technologies']);
        $technologies = array_map('trim', $tech_list);
        $technologies = array_filter($technologies);
    }
    
    return [
        'name' => sanitizeString($data['name']),
        'description' => sanitizeString($data['description'] ?? ''),
        'url' => $data['url'],
        'demo_url' => $data['demo_url'] ?? null,
        'primary_language' => sanitizeString($data['primary_language'] ?? ''),
        'technologies' => json_encode($technologies),
        'visibility' => $data['visibility'] ?? 'public'
    ];
}

/**
 * Cria novo repositório
 */
function createRepository($data, $user_id) {
    global $pdo;

    // Mapear colunas existentes no banco
    $userCol = db_column_exists('repositories','user_id') ? 'user_id' : 'owner_id';
    $langCol = db_column_exists('repositories','primary_language') ? 'primary_language' : (db_column_exists('repositories','language') ? 'language' : null);
    $visCol = db_column_exists('repositories','visibility') ? 'visibility' : (db_column_exists('repositories','is_private') ? 'is_private' : null);
    $demoCol = db_column_exists('repositories','demo_url') ? 'demo_url' : null;
    $techCol = db_column_exists('repositories','technologies') ? 'technologies' : (db_column_exists('repositories','tags') ? 'tags' : null);
    $catCol = db_column_exists('repositories','category') ? 'category' : null;

    $cols = [$userCol, 'name', 'description', 'url'];
    $vals = [$user_id, $data['name'], $data['description'], $data['url']];

    if ($demoCol) { $cols[] = $demoCol; $vals[] = $data['demo_url'] ?? null; }
    if ($langCol) { $cols[] = $langCol; $vals[] = $data['primary_language'] ?? ($data['language'] ?? ''); }

    if ($techCol) {
        // Se o banco usar 'tags', converter JSON de tecnologias em lista separada por vírgula
        if ($techCol === 'tags') {
            $topics = [];
            if (!empty($data['technologies'])) {
                $decoded = json_decode($data['technologies'], true);
                if (is_array($decoded)) { $topics = $decoded; }
            }
            $cols[] = 'tags';
            $vals[] = implode(',', array_map('trim', $topics));
        } else {
            $cols[] = 'technologies';
            $vals[] = $data['technologies'] ?? json_encode([]);
        }
    }

    if ($catCol) { $cols[] = $catCol; $vals[] = $data['category'] ?? null; }

    if ($visCol) {
        if ($visCol === 'visibility') {
            $cols[] = 'visibility';
            $vals[] = $data['visibility'] ?? 'public';
        } else { // is_private
            $cols[] = 'is_private';
            $vals[] = (($data['visibility'] ?? 'public') === 'private') ? 1 : 0;
        }
    }

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO repositories (" . implode(',', $cols) . ") VALUES ($placeholders)";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($vals);
    return $result ? $pdo->lastInsertId() : false;
}

/**
 * Atualiza repositório existente
 */
function updateRepository($repo_id, $data, $user_id) {
    global $pdo;

    $userCol = db_column_exists('repositories','user_id') ? 'user_id' : 'owner_id';
    $hasIsActive = db_column_exists('repositories','is_active');

    // Verificar permissão
    $sql = "SELECT $userCol as owner FROM repositories WHERE id = ?" . ($hasIsActive ? " AND is_active = 1" : "");
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$repo_id]);
    $repository = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$repository) { throw new Exception('Repositório não encontrado'); }
    if ((int)$repository['owner'] !== (int)$user_id) { throw new Exception('Você não tem permissão para editar este repositório'); }

    $sets = ['name = ?', 'description = ?', 'url = ?'];
    $vals = [$data['name'], $data['description'], $data['url']];

    $langCol = db_column_exists('repositories','primary_language') ? 'primary_language' : (db_column_exists('repositories','language') ? 'language' : null);
    if ($langCol) { $sets[] = "$langCol = ?"; $vals[] = $data['primary_language'] ?? ($data['language'] ?? ''); }

    if (db_column_exists('repositories','demo_url')) { $sets[] = 'demo_url = ?'; $vals[] = $data['demo_url'] ?? null; }

    if (db_column_exists('repositories','technologies')) { $sets[] = 'technologies = ?'; $vals[] = $data['technologies'] ?? json_encode([]); }
    elseif (db_column_exists('repositories','tags')) {
        $topics = [];
        if (!empty($data['technologies'])) { $decoded = json_decode($data['technologies'], true); if (is_array($decoded)) { $topics = $decoded; } }
        $sets[] = 'tags = ?'; $vals[] = implode(',', array_map('trim', $topics));
    }

    if (db_column_exists('repositories','category')) { $sets[] = 'category = ?'; $vals[] = $data['category'] ?? null; }

    if (db_column_exists('repositories','visibility')) { $sets[] = 'visibility = ?'; $vals[] = $data['visibility'] ?? 'public'; }
    elseif (db_column_exists('repositories','is_private')) { $sets[] = 'is_private = ?'; $vals[] = (($data['visibility'] ?? 'public') === 'private') ? 1 : 0; }

    if (db_column_exists('repositories','last_update')) { $sets[] = 'last_update = NOW()'; }
    if (db_column_exists('repositories','updated_at')) { $sets[] = 'updated_at = NOW()'; }

    $sql = "UPDATE repositories SET " . implode(', ', $sets) . " WHERE id = ?";
    $vals[] = $repo_id;

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($vals);
}

/**
 * Exclui repositório
 */
function deleteRepository($repo_id, $user_id) {
    global $pdo;

    $userCol = db_column_exists('repositories','user_id') ? 'user_id' : 'owner_id';
    $hasIsActive = db_column_exists('repositories','is_active');

    // Verificar permissão
    $sql = "SELECT $userCol as owner FROM repositories WHERE id = ?" . ($hasIsActive ? " AND is_active = 1" : "");
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$repo_id]);
    $repository = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$repository) { throw new Exception('Repositório não encontrado'); }
    if ((int)$repository['owner'] !== (int)$user_id) { throw new Exception('Você não tem permissão para excluir este repositório'); }

    if ($hasIsActive) {
        $sql = "UPDATE repositories SET is_active = 0, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$repo_id]);
    } else {
        $sql = "DELETE FROM repositories WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$repo_id]);
    }
}

/**
 * Alterna like do repositório
 */
function toggleRepositoryLike($repo_id, $user_id) {
    global $pdo;

    if (!db_table_exists('repository_likes')) {
        // Sem tabela de likes configurada
        return false;
    }

    // Verificar se já curtiu
    $sql = "SELECT id FROM repository_likes WHERE repository_id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$repo_id, $user_id]);
    $existing_like = $stmt->fetch();

    if ($existing_like) {
        // Remover like
        $sql = "DELETE FROM repository_likes WHERE repository_id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$repo_id, $user_id]);
    } else {
        // Adicionar like
        $sql = "INSERT INTO repository_likes (repository_id, user_id, created_at) VALUES (?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$repo_id, $user_id]);
    }
}

/**
 * Importa repositórios do GitHub
 */
function importGitHubRepositories($github_repos, $user_id) {
    global $pdo;
    
    $imported = 0; $skipped = 0; $errors = 0; $details = [];
    
    // Detectar coluna de proprietário (user_id vs owner_id)
    $userCol = db_column_exists('repositories','user_id') ? 'user_id' : 'owner_id';
    
    foreach ($github_repos as $repo_data) {
        try {
            // Verificar se já existe (por URL + dono)
            $sql = "SELECT id FROM repositories WHERE url = ? AND $userCol = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$repo_data['html_url'], $user_id]);
            
            if ($stmt->fetch()) {
                $skipped++;
                $details[] = ['name' => $repo_data['name'], 'status' => 'skipped', 'reason' => 'already_exists'];
                continue; // Já existe
            }
            
            // Criar repositório
            $data = [
                'name' => $repo_data['name'],
                'description' => $repo_data['description'] ?? '',
                'url' => $repo_data['html_url'],
                'demo_url' => $repo_data['homepage'] ?? null,
                'primary_language' => $repo_data['language'] ?? '',
                'technologies' => json_encode($repo_data['topics'] ?? []),
                'visibility' => $repo_data['private'] ? 'private' : 'public'
            ];
            
            if (createRepository($data, $user_id)) {
                $imported++;
                $details[] = ['name' => $repo_data['name'], 'status' => 'imported'];
            } else {
                $errors++;
                $details[] = ['name' => $repo_data['name'], 'status' => 'error', 'reason' => 'insert_failed'];
            }
            
        } catch (Throwable $e) {
            $errors++;
            $details[] = ['name' => ($repo_data['name'] ?? 'desconhecido'), 'status' => 'error', 'reason' => $e->getMessage()];
            error_log("Erro ao importar repositório {$repo_data['name']}: " . $e->getMessage());
        }
    }
    
    return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'details' => $details];
}

/**
 * Sanitiza string
 */
function sanitizeString($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// Helpers de compatibilidade com o banco
function db_table_exists($table){
    global $pdo;
    try {
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
?>
