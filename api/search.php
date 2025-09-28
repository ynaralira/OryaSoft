<?php
header('Content-Type: application/json');
require_once '../includes/config.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all'; // all, users, projects, meetings, posts, repositories
$limit = isset($_GET['limit']) ? min(20, max(1, (int)$_GET['limit'])) : 10;

if (empty($query) || strlen($query) < 2) {
    echo json_encode([
        'success' => true,
        'results' => [],
        'message' => 'Digite pelo menos 2 caracteres para pesquisar'
    ]);
    exit();
}

try {
    $results = [];
    $search_term = "%{$query}%";
    
    // Função para destacar termos de busca
    function highlightSearch($text, $term) {
        return str_ireplace($term, "<mark>$term</mark>", $text);
    }
    
    // Pesquisar usuários
    if ($type === 'all' || $type === 'users') {
        $stmt = $pdo->prepare("
            SELECT id, name, email, bio, avatar 
            FROM users 
            WHERE (name LIKE ? OR email LIKE ? OR bio LIKE ?) 
            AND is_active = 1 
            AND id != ?
            ORDER BY 
                CASE 
                    WHEN name LIKE ? THEN 1
                    WHEN email LIKE ? THEN 2
                    ELSE 3
                END,
                name ASC
            LIMIT ?
        ");
        $stmt->execute([
            $search_term, $search_term, $search_term, $_SESSION['user_id'],
            "{$query}%", "{$query}%", $limit
        ]);
        
        $users = $stmt->fetchAll();
        foreach ($users as $user) {
            $results[] = [
                'type' => 'user',
                'type_label' => 'Usuária',
                'id' => $user['id'],
                'title' => $user['name'],
                'subtitle' => $user['email'],
                'description' => $user['bio'] ? substr($user['bio'], 0, 100) : null,
                'avatar' => $user['avatar'],
                'url' => SITE_URL . "/pages/profile.php?id={$user['id']}",
                'query' => $query
            ];
        }
    }
    
    // Pesquisar projetos
    if ($type === 'all' || $type === 'projects') {
        $stmt = $pdo->prepare("
            SELECT p.*, u.name as creator_name 
            FROM projects p
            JOIN users u ON p.created_by = u.id
            WHERE (p.title LIKE ? OR p.description LIKE ? OR p.tags LIKE ?)
            AND (p.created_by = ? OR p.assigned_to = ? OR p.id IN (
                SELECT DISTINCT project_id FROM project_tasks WHERE assigned_to = ?
            ))
            ORDER BY 
                CASE 
                    WHEN p.title LIKE ? THEN 1
                    WHEN p.description LIKE ? THEN 2
                    ELSE 3
                END,
                p.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([
            $search_term, $search_term, $search_term,
            $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'],
            "{$query}%", "{$query}%", $limit
        ]);
        
        $projects = $stmt->fetchAll();
        foreach ($projects as $project) {
            $results[] = [
                'type' => 'project',
                'type_label' => 'Projeto',
                'id' => $project['id'],
                'title' => $project['title'],
                'subtitle' => "por {$project['creator_name']}",
                'description' => $project['description'] ? substr($project['description'], 0, 100) : null,
                'status' => $project['status'],
                'url' => SITE_URL . "/pages/kanban.php?project={$project['id']}",
                'query' => $query
            ];
        }
    }
    
    // Pesquisar reuniões
    if ($type === 'all' || $type === 'meetings') {
        $stmt = $pdo->prepare("
            SELECT m.*, u.name as creator_name 
            FROM meetings m
            JOIN users u ON m.created_by = u.id
            WHERE (m.title LIKE ? OR m.description LIKE ?)
            AND (m.created_by = ? OR m.id IN (
                SELECT meeting_id FROM meeting_participants WHERE user_id = ?
            ))
            AND m.is_active = 1
            ORDER BY 
                CASE 
                    WHEN m.title LIKE ? THEN 1
                    WHEN m.description LIKE ? THEN 2
                    ELSE 3
                END,
                m.start_datetime ASC
            LIMIT ?
        ");
        $stmt->execute([
            $search_term, $search_term,
            $_SESSION['user_id'], $_SESSION['user_id'],
            "{$query}%", "{$query}%", $limit
        ]);
        
        $meetings = $stmt->fetchAll();
        foreach ($meetings as $meeting) {
            $results[] = [
                'type' => 'meeting',
                'type_label' => 'Reunião',
                'id' => $meeting['id'],
                'title' => $meeting['title'],
                'subtitle' => "por {$meeting['creator_name']} - " . date('d/m/Y H:i', strtotime($meeting['start_datetime'])),
                'description' => $meeting['description'] ? substr($meeting['description'], 0, 100) : null,
                'datetime' => $meeting['start_datetime'],
                'url' => SITE_URL . "/pages/meetings.php?id={$meeting['id']}",
                'query' => $query
            ];
        }
    }
    
    // Pesquisar posts da comunidade
    if ($type === 'all' || $type === 'posts') {
        $stmt = $pdo->prepare("
            SELECT cp.*, u.name as author_name, u.avatar as author_avatar 
            FROM community_posts cp
            JOIN users u ON cp.user_id = u.id
            WHERE (cp.title LIKE ? OR cp.content LIKE ?)
            ORDER BY 
                CASE 
                    WHEN cp.title LIKE ? THEN 1
                    WHEN cp.content LIKE ? THEN 2
                    ELSE 3
                END,
                cp.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([
            $search_term, $search_term,
            "{$query}%", "{$query}%", $limit
        ]);
        
        $posts = $stmt->fetchAll();
        foreach ($posts as $post) {
            $results[] = [
                'type' => 'post',
                'type_label' => 'Post da Comunidade',
                'id' => $post['id'],
                'title' => $post['title'] ?: substr($post['content'], 0, 50) . '...',
                'subtitle' => "por {$post['author_name']} - " . date('d/m/Y', strtotime($post['created_at'])),
                'description' => substr($post['content'], 0, 100),
                'author_avatar' => $post['author_avatar'],
                'url' => SITE_URL . "/pages/community.php?post={$post['id']}",
                'query' => $query
            ];
        }
    }
    
    // Pesquisar repositórios
    if ($type === 'all' || $type === 'repositories') {
        $stmt = $pdo->prepare("
            SELECT r.*, u.name as owner_name 
            FROM repositories r
            JOIN users u ON r.owner_id = u.id
            WHERE (r.name LIKE ? OR r.description LIKE ? OR r.tags LIKE ?)
            ORDER BY 
                CASE 
                    WHEN r.name LIKE ? THEN 1
                    WHEN r.description LIKE ? THEN 2
                    ELSE 3
                END,
                r.stars DESC,
                r.updated_at DESC
            LIMIT ?
        ");
        $stmt->execute([
            $search_term, $search_term, $search_term,
            "{$query}%", "{$query}%", $limit
        ]);
        
        $repositories = $stmt->fetchAll();
        foreach ($repositories as $repo) {
            $results[] = [
                'type' => 'repository',
                'type_label' => 'Repositório',
                'id' => $repo['id'],
                'title' => $repo['name'],
                'subtitle' => "por {$repo['owner_name']} - {$repo['language']}",
                'description' => $repo['description'] ? substr($repo['description'], 0, 100) : null,
                'language' => $repo['language'],
                'stars' => $repo['stars'],
                'url' => SITE_URL . "/pages/repositories.php?id={$repo['id']}",
                'external_url' => $repo['url'],
                'query' => $query
            ];
        }
    }
    
    // Ordenar resultados por relevância
    usort($results, function($a, $b) {
        // Priorizar correspondências exatas no título
        $aExact = stripos($a['title'], $_GET['q']) === 0 ? 1 : 0;
        $bExact = stripos($b['title'], $_GET['q']) === 0 ? 1 : 0;
        
        if ($aExact !== $bExact) {
            return $bExact - $aExact;
        }
        
        // Depois por tipo (usuários primeiro, depois projetos, etc.)
        $typeOrder = ['user' => 1, 'project' => 2, 'meeting' => 3, 'post' => 4, 'repository' => 5];
        $aOrder = $typeOrder[$a['type']] ?? 99;
        $bOrder = $typeOrder[$b['type']] ?? 99;
        
        return $aOrder - $bOrder;
    });
    
    // Limitar resultados totais
    $results = array_slice($results, 0, $limit);
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'total' => count($results),
        'query' => $query
    ]);

} catch (PDOException $e) {
    error_log("Erro na API de pesquisa: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor'
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
