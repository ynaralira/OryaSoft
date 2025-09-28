<?php
/**
 * API de Reuniões - Plataforma Orya
 * ================================
 * 
 * Esta API gerencia todas as operações relacionadas a reuniões:
 * - Criar, editar e excluir reuniões
 * - Listar reuniões do usuário
 * - Gerenciar participantes
 * - Buscar reuniões por filtros
 */

// Corrigido include
require_once '../includes/config.php';

// Verificar autenticação (sessão já iniciada em config.php)
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
    error_log("Erro na API de reuniões: " . $e->getMessage());
    // Só força 500 se ainda não foi definido um código diferente
    $current = http_response_code();
    if (!in_array($current, [400,401,403,404])) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}

/**
 * Manipula requisições GET
 */
function handleGetRequest() {
    global $pdo, $user_id;
    
    // Buscar reunião específica
    if (isset($_GET['id'])) {
        $meeting_id = (int)$_GET['id'];
        $meeting = getMeetingById($meeting_id, $user_id);
        
        if ($meeting) {
            echo json_encode(['success' => true, 'meeting' => $meeting]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Reunião não encontrada']);
        }
        return;
    }
    
    // Listar reuniões com filtros
    $filters = [
        'status' => $_GET['status'] ?? 'all',
        'type' => $_GET['type'] ?? 'all',
        'period' => $_GET['period'] ?? 'all',
        'search' => $_GET['search'] ?? '',
        'limit' => min((int)($_GET['limit'] ?? 50), 100),
        'offset' => max((int)($_GET['offset'] ?? 0), 0)
    ];
    
    $meetings = getMeetings($user_id, $filters);
    echo json_encode(['success' => true, 'meetings' => $meetings]);
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
    
    // Criar ou atualizar reunião via form data
    $data = validateMeetingData($_POST);

    $isAjax = false;
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $isAjax = true;
    }

    if (isset($_POST['meeting_id']) && !empty($_POST['meeting_id'])) {
        // Atualizar reunião existente
        $meeting_id = (int)$_POST['meeting_id'];
        $result = updateMeeting($meeting_id, $data, $user_id);

        if ($result) {
            if ($isAjax) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Reunião atualizada com sucesso',
                    'meeting_id' => $meeting_id
                ]);
            } else {
                header('Location: /Orya/pages/meetings.php');
                exit;
            }
        } else {
            throw new Exception('Erro ao atualizar reunião');
        }
    } else {
        // Criar nova reunião
        $meeting_id = createMeeting($data, $user_id);

        if ($meeting_id) {
            if ($isAjax) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Reunião criada com sucesso',
                    'meeting_id' => $meeting_id
                ]);
            } else {
                header('Location: /Orya/pages/meetings.php');
                exit;
            }
        } else {
            throw new Exception('Erro ao criar reunião');
        }
    }
}

/**
 * Manipula requisições PUT
 */
function handlePutRequest() {
    global $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['meeting_id'])) {
        throw new Exception('ID da reunião é obrigatório');
    }
    
    $meeting_id = (int)$input['meeting_id'];
    $data = validateMeetingData($input);
    
    $result = updateMeeting($meeting_id, $data, $user_id);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Reunião atualizada com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao atualizar reunião');
    }
}

/**
 * Manipula requisições DELETE
 */
function handleDeleteRequest() {
    global $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['meeting_id'])) {
        throw new Exception('ID da reunião é obrigatório');
    }
    
    $meeting_id = (int)$input['meeting_id'];
    $result = deleteMeeting($meeting_id, $user_id);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Reunião excluída com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao excluir reunião');
    }
}

/**
 * Manipula ações especiais
 */
function handleSpecialAction($input) {
    global $user_id;
    
    $action = $input['action'];
    $meeting_id = (int)$input['meeting_id'];
    
    switch ($action) {
        case 'join':
            $result = joinMeeting($meeting_id, $user_id);
            $message = $result ? 'Inscrito na reunião com sucesso' : 'Erro ao se inscrever na reunião';
            break;
            
        case 'leave':
            $result = leaveMeeting($meeting_id, $user_id);
            $message = $result ? 'Saiu da reunião com sucesso' : 'Erro ao sair da reunião';
            break;
            
        case 'invite':
            if (!isset($input['user_ids']) || !is_array($input['user_ids'])) {
                throw new Exception('Lista de usuários é obrigatória');
            }
            $result = inviteUsersToMeeting($meeting_id, $input['user_ids'], $user_id);
            $message = $result ? 'Convites enviados com sucesso' : 'Erro ao enviar convites';
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
 * Busca reunião por ID
 */
function getMeetingById($meeting_id, $user_id) {
    global $pdo;
    
    $sql = "
        SELECT m.*, 
               u.name as creator_name,
               u.avatar as creator_avatar,
               COUNT(DISTINCT mp.user_id) as participant_count,
               CASE 
                   WHEN m.start_datetime > NOW() THEN 'upcoming'
                   WHEN m.start_datetime <= NOW() AND m.end_datetime > NOW() THEN 'live'
                   ELSE 'ended'
               END as status,
               CASE WHEN m.created_by = ? THEN 1 ELSE 0 END as is_creator,
               CASE WHEN mp_user.user_id IS NOT NULL THEN 1 ELSE 0 END as is_participant
        FROM meetings m
        JOIN users u ON m.created_by = u.id
        LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
        LEFT JOIN meeting_participants mp_user ON m.id = mp_user.meeting_id AND mp_user.user_id = ?
        WHERE m.id = ? AND m.is_active = 1
        AND (m.created_by = ? OR mp_user.user_id IS NOT NULL)
        GROUP BY m.id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_id, $meeting_id, $user_id]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($meeting) {
        // Buscar participantes
        $meeting['participants'] = getMeetingParticipants($meeting_id);
    }
    
    return $meeting;
}

/**
 * Busca reuniões com filtros
 */
function getMeetings($user_id, $filters) {
    global $pdo;

    $params = [];

    $sql = "SELECT 
                m.id, m.title, m.description, m.start_datetime, m.end_datetime, m.meeting_url,
                m.max_participants, m.created_by, m.created_at, m.is_active,
                u.name AS creator_name, u.avatar AS creator_avatar,
                COUNT(DISTINCT mp.user_id) AS participant_count,
                CASE 
                    WHEN m.start_datetime > NOW() THEN 'upcoming'
                    WHEN m.start_datetime <= NOW() AND m.end_datetime > NOW() THEN 'live'
                    ELSE 'ended'
                END AS status,
                CASE WHEN m.created_by = ? THEN 1 ELSE 0 END AS is_creator
            FROM meetings m
            JOIN users u ON m.created_by = u.id
            LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
            WHERE m.is_active = 1
              AND (m.created_by = ? OR m.id IN (SELECT meeting_id FROM meeting_participants WHERE user_id = ?))";

    // Bind para is_creator e participação
    $params[] = $user_id; // para is_creator CASE WHEN
    $params[] = $user_id; // created_by = ?
    $params[] = $user_id; // participante

    // Filtro de busca (antes do GROUP BY)
    if (!empty($filters['search'])) {
        $sql .= " AND (m.title LIKE ? OR m.description LIKE ?)";
        $search_term = '%' . $filters['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Filtro de período (antes do GROUP BY)
    if (!empty($filters['period']) && $filters['period'] !== 'all') {
        $period_condition = getPeriodCondition($filters['period']);
        if ($period_condition) {
            $sql .= " AND " . $period_condition;
        }
    }

    $sql .= " GROUP BY m.id";

    // Filtro de status (após GROUP BY => HAVING)
    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        $sql .= " HAVING status = ?";
        $params[] = $filters['status'];
    }

    $sql .= " ORDER BY m.start_datetime ASC";

    // Paginação
    $limit  = isset($filters['limit']) ? (int)$filters['limit'] : 50;
    $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
    if ($limit > 100) { $limit = 100; }
    if ($offset < 0) { $offset = 0; }
    // Usar interpolação direta (valores já saneados como inteiros) pois alguns drivers não aceitam placeholder em LIMIT
    $sql .= " LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Gera condição SQL para filtro de período
 */
function getPeriodCondition($period) {
    switch ($period) {
        case 'today':
            return "DATE(m.start_datetime) = CURDATE()";
        case 'week':
            return "YEARWEEK(m.start_datetime, 1) = YEARWEEK(CURDATE(), 1)";
        case 'month':
            return "YEAR(m.start_datetime) = YEAR(CURDATE()) AND MONTH(m.start_datetime) = MONTH(CURDATE())";
        default:
            return null;
    }
}

/**
 * Busca participantes de uma reunião
 */
function getMeetingParticipants($meeting_id) {
    global $pdo;
    
    $sql = "
        SELECT u.id, u.name, u.email, u.avatar, mp.joined_at
        FROM meeting_participants mp
        JOIN users u ON mp.user_id = u.id
        WHERE mp.meeting_id = ?
        ORDER BY mp.joined_at ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meeting_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Valida dados da reunião
 */
function validateMeetingData($data) {
    $errors = [];
    
    // Título obrigatório
    if (empty($data['title'])) {
        $errors[] = 'Título é obrigatório';
    } elseif (strlen($data['title']) < 3) {
        $errors[] = 'Título deve ter pelo menos 3 caracteres';
    } elseif (strlen($data['title']) > 200) {
        $errors[] = 'Título deve ter no máximo 200 caracteres';
    }
    
    // Data e hora obrigatórias
    if (empty($data['start_date']) || empty($data['start_time'])) {
        $errors[] = 'Data e hora de início são obrigatórias';
    } else {
        $start_datetime = $data['start_date'] . ' ' . $data['start_time'];
        if (strtotime($start_datetime) <= time()) {
            $errors[] = 'Data e hora devem ser no futuro';
        }
    }
    
    // Não exige mais link da reunião, pois a sala é interna
    
    // Máximo de participantes
    if (isset($data['max_participants'])) {
        $max_participants = (int)$data['max_participants'];
        if ($max_participants < 2 || $max_participants > 500) {
            $errors[] = 'Máximo de participantes deve estar entre 2 e 500';
        }
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        throw new Exception(implode(', ', $errors));
    }
    
    // Calcular data/hora de término
    $start_datetime = $data['start_date'] . ' ' . $data['start_time'];
    
    if (!empty($data['end_time'])) {
        $end_datetime = $data['start_date'] . ' ' . $data['end_time'];
    } else {
        $duration = (int)($data['duration'] ?? 60);
        $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime) + ($duration * 60));
    }
    
    return [
        'title' => sanitizeString($data['title']),
        'description' => sanitizeString($data['description'] ?? ''),
        'start_datetime' => $start_datetime,
        'end_datetime' => $end_datetime,
        // 'meeting_url' removido
        'max_participants' => (int)($data['max_participants'] ?? 50),
        'type' => isset($data['type']) ? sanitizeString($data['type']) : 'meeting'
    ];
}

/**
 * Cria nova reunião
 */
function createMeeting($data, $user_id) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        // Verificar se coluna type existe
        $hasType = false;
        $cols = $pdo->query("SHOW COLUMNS FROM meetings LIKE 'type'")->fetch();
        if ($cols) { $hasType = true; }
        $sql = "INSERT INTO meetings (title, description, start_datetime, end_datetime, max_participants, created_by, created_at" . ($hasType?", type":"") . ") VALUES (?,?,?,?,?,?,NOW()" . ($hasType?",?":"") . ")";
        $stmt = $pdo->prepare($sql);
        $params = [
            $data['title'],
            $data['description'],
            $data['start_datetime'],
            $data['end_datetime'],
            $data['max_participants'],
            $user_id
        ];
        if ($hasType) { $params[] = $data['type']; }
        $result = $stmt->execute($params);
        if (!$result) { throw new Exception('Erro ao criar reunião'); }
        $meeting_id = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO meeting_participants (meeting_id, user_id, joined_at) VALUES (?,?,NOW())")->execute([$meeting_id,$user_id]);
        $pdo->commit();
        return $meeting_id;
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

/**
 * Atualiza reunião existente
 */
function updateMeeting($meeting_id, $data, $user_id) {
    global $pdo;
    
    // Verificar se usuário tem permissão para editar
    $sql = "SELECT created_by FROM meetings WHERE id = ? AND is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch();
    
    if (!$meeting) {
        throw new Exception('Reunião não encontrada');
    }
    
    if ($meeting['created_by'] != $user_id) {
        throw new Exception('Você não tem permissão para editar esta reunião');
    }
    
    $hasType = (bool)$pdo->query("SHOW COLUMNS FROM meetings LIKE 'type'")->fetch();
    $hasUpdatedAt = (bool)$pdo->query("SHOW COLUMNS FROM meetings LIKE 'updated_at'")->fetch();
    $sql = "UPDATE meetings SET title=?, description=?, start_datetime=?, end_datetime=?, max_participants=?" . ($hasType?", type=?":"") . ($hasUpdatedAt?", updated_at=NOW()":"") . " WHERE id=?";
    $params = [
        $data['title'],
        $data['description'],
        $data['start_datetime'],
        $data['end_datetime'],
        $data['max_participants']
    ];
    if ($hasType) { $params[] = $data['type']; }
    $params[] = $meeting_id;
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Exclui reunião
 */
function deleteMeeting($meeting_id, $user_id) {
    global $pdo;
    
    // Verificar se usuário tem permissão para excluir
    $sql = "SELECT created_by FROM meetings WHERE id = ? AND is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch();
    
    if (!$meeting) {
        throw new Exception('Reunião não encontrada');
    }
    
    if ($meeting['created_by'] != $user_id) {
        throw new Exception('Você não tem permissão para excluir esta reunião');
    }
    
    // Soft delete
    $sql = "UPDATE meetings SET is_active = 0 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$meeting_id]);
    
    return $result;
}

/**
 * Usuário se inscreve na reunião
 */
function joinMeeting($meeting_id, $user_id) {
    global $pdo;
    
    // Verificar se reunião existe e está ativa
    $sql = "SELECT max_participants FROM meetings WHERE id = ? AND is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch();
    
    if (!$meeting) {
        throw new Exception('Reunião não encontrada');
    }
    
    // Verificar se já está participando
    $sql = "SELECT id FROM meeting_participants WHERE meeting_id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meeting_id, $user_id]);
    
    if ($stmt->fetch()) {
        throw new Exception('Você já está participando desta reunião');
    }
    
    // Verificar limite de participantes
    $sql = "SELECT COUNT(*) as count FROM meeting_participants WHERE meeting_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meeting_id]);
    $current_count = $stmt->fetchColumn();
    
    if ($current_count >= $meeting['max_participants']) {
        throw new Exception('Reunião lotada');
    }
    
    // Adicionar participante
    $sql = "INSERT INTO meeting_participants (meeting_id, user_id, joined_at) VALUES (?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$meeting_id, $user_id]);
}

/**
 * Usuário sai da reunião
 */
function leaveMeeting($meeting_id, $user_id) {
    global $pdo;
    
    // Verificar se é o criador da reunião
    $sql = "SELECT created_by FROM meetings WHERE id = ? AND is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch();
    
    if (!$meeting) {
        throw new Exception('Reunião não encontrada');
    }
    
    if ($meeting['created_by'] == $user_id) {
        throw new Exception('O criador da reunião não pode sair. Exclua a reunião se necessário.');
    }
    
    // Remover participante
    $sql = "DELETE FROM meeting_participants WHERE meeting_id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$meeting_id, $user_id]);
}

/**
 * Convida usuários para reunião
 */
function inviteUsersToMeeting($meeting_id, $user_ids, $inviter_id) {
    global $pdo;
    
    // Verificar se o usuário tem permissão para convidar
    $sql = "SELECT created_by FROM meetings WHERE id = ? AND is_active = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$meeting_id]);
    $meeting = $stmt->fetch();
    
    if (!$meeting) {
        throw new Exception('Reunião não encontrada');
    }
    
    if ($meeting['created_by'] != $inviter_id) {
        throw new Exception('Apenas o criador pode convidar participantes');
    }
    
    $success_count = 0;
    
    foreach ($user_ids as $user_id) {
        try {
            // Verificar se usuário existe
            $sql = "SELECT id FROM users WHERE id = ? AND is_active = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            
            if (!$stmt->fetch()) {
                continue;
            }
            
            // Verificar se já está participando
            $sql = "SELECT id FROM meeting_participants WHERE meeting_id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$meeting_id, $user_id]);
            
            if ($stmt->fetch()) {
                continue;
            }
            
            // Adicionar participante
            $sql = "INSERT INTO meeting_participants (meeting_id, user_id, joined_at) VALUES (?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$meeting_id, $user_id])) {
                $success_count++;
            }
            
        } catch (Exception $e) {
            error_log("Erro ao convidar usuário $user_id: " . $e->getMessage());
        }
    }
    
    return $success_count > 0;
}

/**
 * Sanitiza string
 */
function sanitizeString($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}
?>
