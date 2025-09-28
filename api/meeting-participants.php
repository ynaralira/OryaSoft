<?php
// API de Participantes de Reunião
// Compatível com o front atual em pages/meetings.php (espera array cru no GET)

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

function json_ok($data = []){
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}
function json_err($message = 'Erro inesperado', $code = 400){
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
function db_table_exists_local($pdo, $table){
    try { $st = $pdo->query("SHOW TABLES LIKE '" . str_replace("'","''", $table) . "'"); return $st && $st->fetchColumn(); } catch(Throwable $e){ return false; }
}

// Verificar login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($method) {
        case 'GET': {
            $meeting_id = (int)($_GET['meeting_id'] ?? 0);
            $active_only = isset($_GET['active']) && $_GET['active'] == '1';
            if (!$meeting_id) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'ID da reunião é obrigatório']); exit; }

            $hasActive = db_table_exists_local($pdo, 'meeting_active_sessions');
            $sql = "SELECT u.id, u.name, u.email, u.avatar";
            if ($hasActive) { $sql .= ", CASE WHEN mas.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_online"; }
            else { $sql .= ", 0 AS is_online"; }
            $sql .= "\nFROM meeting_participants mp\nJOIN users u ON mp.user_id = u.id";
            if ($hasActive) { $sql .= " LEFT JOIN meeting_active_sessions mas ON mp.meeting_id = mas.meeting_id AND mp.user_id = mas.user_id AND mas.is_active = 1"; }
            $sql .= "\nWHERE mp.meeting_id = ?";
            if ($active_only && $hasActive) { $sql .= " AND mas.user_id IS NOT NULL"; }
            $sql .= "\nORDER BY u.name";

            $st = $pdo->prepare($sql);
            $st->execute([$meeting_id]);
            $participants = $st->fetchAll(PDO::FETCH_ASSOC);
            // Compatibilidade: retornar array cru
            echo json_encode($participants);
            break;
        }

        case 'POST': {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { // fallback para form-url-encoded
                $input = $_POST;
            }
            $meeting_id = (int)($input['meeting_id'] ?? 0);
            $user_ids = $input['user_ids'] ?? [];
            if (!$meeting_id || !is_array($user_ids) || count($user_ids) === 0) {
                json_err('Dados incompletos', 400);
            }
            $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids), fn($v)=>$v>0)));

            // Permissão: apenas criador pode gerenciar participantes
            $st = $pdo->prepare('SELECT created_by FROM meetings WHERE id = ? AND is_active = 1');
            $st->execute([$meeting_id]);
            $creator = (int)$st->fetchColumn();
            if (!$creator) json_err('Reunião não encontrada', 404);
            if ($creator !== $uid) json_err('Sem permissão para gerenciar participantes', 403);

            $pdo->beginTransaction();
            try {
                // Inserir participantes (ignora já existentes)
                $hasAddedBy = false; // manter simples; usa joined_at
                $sql = 'INSERT IGNORE INTO meeting_participants (meeting_id, user_id, joined_at) VALUES (?, ?, NOW())';
                $ins = $pdo->prepare($sql);
                foreach ($user_ids as $pid) { $ins->execute([$meeting_id, $pid]); }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            json_ok(['message' => 'Participantes adicionados com sucesso']);
            break;
        }

        case 'DELETE': {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { json_err('Dados incompletos', 400); }
            $meeting_id = (int)($input['meeting_id'] ?? 0);
            $participant_id = (int)($input['user_id'] ?? 0);
            if (!$meeting_id || !$participant_id) json_err('Dados incompletos', 400);

            // Permissão: apenas criador pode remover
            $st = $pdo->prepare('SELECT created_by FROM meetings WHERE id = ? AND is_active = 1');
            $st->execute([$meeting_id]);
            $creator = (int)$st->fetchColumn();
            if (!$creator) json_err('Reunião não encontrada', 404);
            if ($creator !== $uid) json_err('Sem permissão para gerenciar participantes', 403);

            $del = $pdo->prepare('DELETE FROM meeting_participants WHERE meeting_id = ? AND user_id = ?');
            $del->execute([$meeting_id, $participant_id]);
            json_ok(['message' => 'Participante removido com sucesso']);
            break;
        }

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (Throwable $e) {
    error_log('meeting-participants API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>
