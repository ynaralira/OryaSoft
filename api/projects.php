<?php
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

function db_table_exists($pdo, $table){
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace("'","''", $table) . "'");
        return $stmt && $stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}
function db_column_exists($pdo, $table, $column){
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) { return false; }
}
function map_status_for_db($status){
    $status = strtolower(trim($status ?: ''));
    // DB enum típico: planning, in_progress, testing, completed, archived
    if ($status === 'active') return 'in_progress';
    if ($status === 'on_hold') return 'testing';
    if (in_array($status, ['planning','in_progress','testing','completed','archived'], true)) return $status;
    if ($status === 'done') return 'completed';
    return 'planning';
}
function ensure_project_members_table($pdo){
    if (db_table_exists($pdo, 'project_members')) return true;
    try {
        $sql = "CREATE TABLE IF NOT EXISTS project_members (
            project_id INT NOT NULL,
            user_id INT NOT NULL,
            role VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (project_id, user_id),
            INDEX idx_pm_user (user_id),
            CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_pm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
        return true;
    } catch (Throwable $e) {
        error_log('Falha ao criar project_members: ' . $e->getMessage());
        return false;
    }
}

// Autenticação básica para API
if (!isset($_SESSION['user_id'])) {
    json_err('Não autenticado', 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if ($method === 'GET' && $action === 'members') {
        $project_id = (int)($_GET['project_id'] ?? 0);
        if (!$project_id) json_err('Projeto inválido');

        if (!db_table_exists($pdo, 'project_members')) {
            json_ok(['members' => []]);
        }

        $sql = "SELECT u.id, u.name, u.avatar FROM project_members pm JOIN users u ON pm.user_id = u.id WHERE pm.project_id = ? ORDER BY u.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$project_id]);
        $members = $stmt->fetchAll();

        json_ok(['members' => $members]);
    }

    if ($method === 'GET' && $action === 'details') {
        $project_id = (int)($_GET['project_id'] ?? 0);
        if (!$project_id) json_err('Projeto inválido');
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch();
        if (!$project) json_err('Projeto não encontrado', 404);
        // Normalizar campos esperados pelo front
        if (!isset($project['name']) && isset($project['title'])) $project['name'] = $project['title'];
        if (!isset($project['end_date']) && isset($project['due_date'])) $project['end_date'] = $project['due_date'];
        json_ok(['project' => $project]);
    }

    // Atualizar apenas o status do projeto (DnD no board de projetos)
    if ($method === 'POST' && $action === 'update_status') {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $status_in = $_POST['status'] ?? '';
        if (!$project_id) json_err('Projeto inválido');
        $status = map_status_for_db($status_in);
        $uid = (int)$_SESSION['user_id'];

        // Verificar permissão básica
        $hasMembers = db_table_exists($pdo, 'project_members');
        if ($hasMembers) {
            // Criador, responsável (assigned_to) ou membro podem atualizar
            $sql = "UPDATE projects SET status = ? WHERE id = ? AND (created_by = ? OR assigned_to = ? OR id IN (SELECT project_id FROM project_members WHERE user_id = ?))";
            $st = $pdo->prepare($sql);
            $st->execute([$status, $project_id, $uid, $uid, $uid]);
        } else {
            // Sem tabela de membros: permitir criador ou responsável
            $sql = "UPDATE projects SET status = ? WHERE id = ? AND (created_by = ? OR assigned_to = ?)";
            $st = $pdo->prepare($sql);
            $st->execute([$status, $project_id, $uid, $uid]);
        }
        if ($st->rowCount() < 1) json_err('Sem permissão ou projeto não encontrado', 403);
        json_ok(['project_id' => $project_id, 'status' => $status]);
    }

    // Atualizar membros do projeto
    if ($method === 'POST' && $action === 'update_members') {
        $project_id = (int)($_POST['project_id'] ?? 0);
        if (!$project_id) json_err('Projeto inválido');
        $uid = (int)$_SESSION['user_id'];
        $membersRaw = $_POST['members'] ?? [];
        $members = is_array($membersRaw) ? $membersRaw : explode(',', (string)$membersRaw);
        $members = array_values(array_unique(array_filter(array_map('intval', $members), function($v){ return $v > 0; })));

        if (!ensure_project_members_table($pdo)) json_err('Estrutura de membros indisponível', 500);

        // Permissão: somente criador pode alterar membros
        $st = $pdo->prepare('SELECT created_by FROM projects WHERE id = ?');
        $st->execute([$project_id]);
        $owner = (int)$st->fetchColumn();
        if ($owner !== $uid) json_err('Sem permissão para alterar membros', 403);

        $pdo->beginTransaction();
        try {
            // Remover os que não estão mais
            if (count($members) > 0) {
                $in = implode(',', array_fill(0, count($members), '?'));
                $sqlDel = "DELETE FROM project_members WHERE project_id = ? AND user_id NOT IN ($in)";
                $stDel = $pdo->prepare($sqlDel);
                $stDel->execute(array_merge([$project_id], $members));
            } else {
                $stDel = $pdo->prepare('DELETE FROM project_members WHERE project_id = ?');
                $stDel->execute([$project_id]);
            }
            // Inserir os novos
            if (count($members) > 0) {
                $stIns = $pdo->prepare('INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?, ?)');
                foreach ($members as $mid) { $stIns->execute([$project_id, $mid]); }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        json_ok(['project_id' => $project_id, 'members' => $members]);
    }

    // Excluir projeto (soft delete se houver is_active, senão hard delete)
    if ($method === 'POST' && $action === 'delete') {
        $project_id = (int)($_POST['project_id'] ?? 0);
        if (!$project_id) json_err('Projeto inválido');
        $uid = (int)$_SESSION['user_id'];

        // Verificar permissão: apenas criador pode excluir
        $st = $pdo->prepare('SELECT created_by FROM projects WHERE id = ?');
        $st->execute([$project_id]);
        $owner = (int)$st->fetchColumn();
        if ($owner !== $uid) json_err('Sem permissão para excluir projeto', 403);

        $hasProjActive = db_column_exists($pdo, 'projects', 'is_active');
        $hasTaskActive = db_column_exists($pdo, 'project_tasks', 'is_active');
        $hasMembers = db_table_exists($pdo, 'project_members');

        $pdo->beginTransaction();
        try {
            if ($hasProjActive) {
                $up = $pdo->prepare('UPDATE projects SET is_active = 0 WHERE id = ?');
                $up->execute([$project_id]);
            } else {
                // Remover membros primeiro (se tabela existir)
                if ($hasMembers) {
                    $delM = $pdo->prepare('DELETE FROM project_members WHERE project_id = ?');
                    $delM->execute([$project_id]);
                }
                // Remover tarefas do projeto
                $delT = $pdo->prepare('DELETE FROM project_tasks WHERE project_id = ?');
                $delT->execute([$project_id]);
                // Remover projeto
                $delP = $pdo->prepare('DELETE FROM projects WHERE id = ?');
                $delP->execute([$project_id]);
            }

            // Soft delete de tarefas se aplicável (quando projeto é soft)
            if ($hasProjActive && $hasTaskActive) {
                $upT = $pdo->prepare('UPDATE project_tasks SET is_active = 0 WHERE project_id = ?');
                $upT->execute([$project_id]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        json_ok(['project_id' => $project_id]);
    }

    if ($method === 'POST') {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start_date = $_POST['start_date'] ?? null;
        $end_date = $_POST['end_date'] ?? null; // pode mapear para due_date
        $status_in = $_POST['status'] ?? 'planning';
        $status = map_status_for_db($status_in);

        if ($name === '') json_err('Nome do projeto é obrigatório');

        // Mapear colunas conforme schema
        $colTitle = db_column_exists($pdo, 'projects', 'title') ? 'title' : (db_column_exists($pdo,'projects','name') ? 'name' : 'title');
        $colEnd = db_column_exists($pdo, 'projects', 'due_date') ? 'due_date' : (db_column_exists($pdo,'projects','end_date') ? 'end_date' : 'due_date');
        $colStatus = 'status';

        if ($project_id) {
            $sql = "UPDATE projects SET $colTitle = ?, description = ?, start_date = ?, $colEnd = ?, $colStatus = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $start_date ?: null, $end_date ?: null, $status, $project_id]);
            json_ok(['project_id' => $project_id]);
        } else {
            $creator = (int)$_SESSION['user_id'];
            $sql = "INSERT INTO projects ($colTitle, description, start_date, $colEnd, $colStatus, created_by) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $description, $start_date ?: null, $end_date ?: null, $status, $creator]);
            $new_id = (int)$pdo->lastInsertId();
            json_ok(['project_id' => $new_id]);
        }
    }

    json_err('Rota não encontrada', 404);
} catch (PDOException $e) {
    error_log('API projects.php: ' . $e->getMessage());
    json_err('Erro no servidor', 500);
}
