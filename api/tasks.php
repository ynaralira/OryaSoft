<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

function json_ok($data = []){ echo json_encode(array_merge(['success'=>true], $data)); exit; }
function json_err($msg='Erro inesperado', $code=400){ http_response_code($code); echo json_encode(['success'=>false,'message'=>$msg]); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function fetch_task(PDO $pdo, int $task_id){
    $sql = "SELECT pt.*, u.name AS assigned_user_name, u.avatar AS assigned_user_avatar
            FROM project_tasks pt
            LEFT JOIN users u ON pt.assigned_to = u.id
            WHERE pt.id = ?";
    $st = $pdo->prepare($sql); $st->execute([$task_id]);
    return $st->fetch();
}

try {
    if ($method === 'GET') {
        if (isset($_GET['task_id'])) {
            $task_id = (int)$_GET['task_id'];
            if (!$task_id) json_err('Tarefa inválida');
            $task = fetch_task($pdo, $task_id);
            if (!$task) json_err('Tarefa não encontrada', 404);
            json_ok(['task'=>$task]);
        }
        json_err('Rota não encontrada', 404);
    }

    if ($method === 'POST' && $action === 'update_status') {
        $task_id = (int)($_POST['task_id'] ?? 0);
        $status  = $_POST['status'] ?? 'todo';
        $position = isset($_POST['position']) ? (int)$_POST['position'] : 0;
        if (!$task_id) json_err('Tarefa inválida');

        // Pegar projeto e status atual
        $row = $pdo->prepare('SELECT project_id, status FROM project_tasks WHERE id = ?');
        $row->execute([$task_id]);
        $curr = $row->fetch();
        if (!$curr) json_err('Tarefa não encontrada', 404);

        $pdo->beginTransaction();
        try {
            // Se mudou de coluna, coloque no final caso position não informado
            if ($curr['status'] !== $status && $position <= 0) {
                $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), -1) AS max_pos FROM project_tasks WHERE project_id = ? AND status = ?');
                $stmt->execute([$curr['project_id'], $status]);
                $mx = (int)$stmt->fetchColumn();
                $position = $mx + 1;
            }

            // Abrir espaço na coluna destino (incrementar posições >= position)
            $stmt = $pdo->prepare('UPDATE project_tasks SET position = position + 1 WHERE project_id = ? AND status = ? AND position >= ? AND id <> ?');
            $stmt->execute([$curr['project_id'], $status, $position, $task_id]);

            // Atualizar tarefa
            $up = $pdo->prepare('UPDATE project_tasks SET status = ?, position = ? WHERE id = ?');
            $up->execute([$status, $position, $task_id]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $task = fetch_task($pdo, $task_id);
        json_ok(['task'=>$task]);
    }

    if ($method === 'POST' && $action !== 'update_status') {
        // Criação/edição via formulário
        $task_id = (int)($_POST['task_id'] ?? 0);
        $project_id = (int)($_POST['project_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'todo';
        $due_date = $_POST['due_date'] ?? null; if ($due_date === '') $due_date = null;
        $assigned_to = $_POST['assigned_to'] ?? null; $assigned_to = ($assigned_to === '' ? null : (int)$assigned_to);

        if ($title === '' || !$project_id) json_err('Título e projeto são obrigatórios');

        if ($task_id) {
            // Atualizar
            // Se status mudou, jogar para o final da nova coluna
            $st0 = $pdo->prepare('SELECT status FROM project_tasks WHERE id = ?');
            $st0->execute([$task_id]);
            $old = $st0->fetchColumn();
            $positionSql = '';
            $params = [$title, $description, $status, $due_date, $assigned_to, $task_id];
            if ($old !== $status) {
                $stMx = $pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM project_tasks WHERE project_id = ? AND status = ?');
                $stMx->execute([$project_id, $status]);
                $newPos = (int)$stMx->fetchColumn() + 1;
                $positionSql = ', position = ' . (int)$newPos;
            }
            $sql = 'UPDATE project_tasks SET title = ?, description = ?, status = ?, due_date = ?, assigned_to = ?' . $positionSql . ' WHERE id = ?';
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $task = fetch_task($pdo, $task_id);
            json_ok(['task'=>$task]);
        } else {
            // Criar
            $stMx = $pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM project_tasks WHERE project_id = ? AND status = ?');
            $stMx->execute([$project_id, $status]);
            $position = (int)$stMx->fetchColumn() + 1;

            $ins = $pdo->prepare('INSERT INTO project_tasks (project_id, title, description, status, due_date, assigned_to, position, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $ins->execute([$project_id, $title, $description, $status, $due_date, $assigned_to, $position]);
            $new_id = (int)$pdo->lastInsertId();
            $task = fetch_task($pdo, $new_id);
            json_ok(['task'=>$task]);
        }
    }

    if ($method === 'DELETE') {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?: [];
        $task_id = (int)($payload['task_id'] ?? 0);
        if (!$task_id) json_err('Tarefa inválida');
        $st = $pdo->prepare('DELETE FROM project_tasks WHERE id = ?');
        $st->execute([$task_id]);
        json_ok();
    }

    json_err('Rota não encontrada', 404);
} catch (PDOException $e) {
    error_log('API tasks.php: ' . $e->getMessage());
    json_err('Erro no servidor', 500);
}
