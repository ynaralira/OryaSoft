<?php
header('Content-Type: application/json');
require_once '../includes/config.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

try {
    switch ($method) {
        case 'GET':
            try {
                $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
                $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
                $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
                $sql = "SELECT * FROM notifications WHERE user_id = ?";
                $params = [$user_id];
                if ($unread_only) { $sql .= " AND is_read = 0"; }
                $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
                $params[] = $limit; $params[] = $offset;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $notifications = $stmt->fetchAll();
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                $stmt->execute([$user_id]);
                $unread_count = (int)$stmt->fetchColumn();
                echo json_encode([
                    'success' => true,
                    'notifications' => $notifications ?: [],
                    'unread_count' => $unread_count
                ]);
            } catch (Exception $inner) {
                error_log('Notif GET falhou: ' . $inner->getMessage());
                echo json_encode(['success' => true, 'notifications' => [], 'unread_count' => 0, 'debug' => isset($_GET['debug']) ? $inner->getMessage() : null]);
            }
            break;

        case 'PUT':
            // Marcar notificação como lida
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['action'])) {
                throw new Exception('Ação não especificada');
            }
            
            if ($input['action'] === 'mark_read') {
                if (isset($input['notification_id'])) {
                    // Marcar uma notificação específica
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                    $stmt->execute([$input['notification_id'], $user_id]);
                    
                    if ($stmt->rowCount() === 0) {
                        throw new Exception('Notificação não encontrada');
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Notificação marcada como lida'
                    ]);
                } else {
                    // Marcar todas como lidas
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
                    $stmt->execute([$user_id]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Todas as notificações foram marcadas como lidas',
                        'updated_count' => $stmt->rowCount()
                    ]);
                }
            } else {
                throw new Exception('Ação inválida');
            }
            break;

        case 'DELETE':
            // Deletar notificação
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['notification_id'])) {
                throw new Exception('ID da notificação não especificado');
            }
            
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$input['notification_id'], $user_id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Notificação não encontrada');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Notificação deletada'
            ]);
            break;

        case 'POST':
            // Criar nova notificação (normalmente feito pelo sistema)
            $input = json_decode(file_get_contents('php://input'), true);
            
            $required_fields = ['title', 'message'];
            foreach ($required_fields as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    throw new Exception("Campo '$field' é obrigatório");
                }
            }
            
            $type = $input['type'] ?? 'system';
            $reference_id = $input['reference_id'] ?? null;
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, reference_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                trim($input['title']),
                trim($input['message']),
                $type,
                $reference_id
            ]);
            
            $notification_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Notificação criada',
                'notification_id' => $notification_id
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
            break;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Erro na API de notificações: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor'
    ]);
}
?>
