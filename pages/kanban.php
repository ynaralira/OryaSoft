<?php
$page_title = 'Gerenciamento de Projetos';
$page_name = 'kanban';
$additional_css = ['kanban'];

require_once '../includes/header.php';

// Helpers de compatibilidade com schema
function db_table_exists_local($pdo, $table){
    try { $st = $pdo->query("SHOW TABLES LIKE '".str_replace("'","''",$table)."'"); return $st && $st->fetchColumn(); } catch(Throwable $e){ return false; }
}
function db_column_exists_local($pdo, $table, $col){
    try { $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?"); $st->execute([$col]); return (bool)$st->fetch(); } catch(Throwable $e){ return false; }
}

// Buscar projetos do usuário (compatível com ausência de project_members e is_active)
try {
    $hasMembers = db_table_exists_local($pdo, 'project_members');
    $hasProjectActive = db_column_exists_local($pdo, 'projects', 'is_active');
    $hasTaskActive = db_column_exists_local($pdo, 'project_tasks', 'is_active');

    $select = "SELECT p.*, u.name as creator_name, COUNT(DISTINCT pt.id) as task_count";
    $select .= $hasMembers ? ", COUNT(DISTINCT pm.user_id) as member_count" : ", 0 as member_count";

    $sql = "$select
        FROM projects p
        JOIN users u ON p.created_by = u.id
        LEFT JOIN project_tasks pt ON p.id = pt.project_id";
    if ($hasTaskActive) { $sql .= " AND pt.is_active = 1"; }
    if ($hasMembers) { $sql .= " LEFT JOIN project_members pm ON p.id = pm.project_id"; }

    $sql .= " WHERE 1=1";
    if ($hasProjectActive) { $sql .= " AND p.is_active = 1"; }

    $sql .= " AND (p.created_by = ?";
    if ($hasMembers) { $sql .= " OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)"; }
    $sql .= ") GROUP BY p.id ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    if ($hasMembers) { $stmt->execute([$current_user['id'], $current_user['id']]); }
    else { $stmt->execute([$current_user['id']]); }
    $projects = $stmt->fetchAll();
    // Normalizar campos esperados pelo front (name/end_date)
    foreach ($projects as &$p) {
        if ((!isset($p['name']) || $p['name'] === '') && isset($p['title'])) { $p['name'] = $p['title']; }
        if ((!isset($p['end_date']) || !$p['end_date']) && isset($p['due_date'])) { $p['end_date'] = $p['due_date']; }
    }
    unset($p);

} catch (PDOException $e) {
    error_log("Erro ao buscar projetos: " . $e->getMessage());
    $projects = [];
}

// Projeto selecionado (padrão: primeiro projeto)
$selected_project_id = $_GET['project'] ?? ($projects[0]['id'] ?? null);

// Se o projeto selecionado não estiver na lista (ex.: acabou de ser criado), busque e inclua
if ($selected_project_id && !array_filter($projects, fn($p) => $p['id'] == $selected_project_id)) {
    try {
        $hasMembers = db_table_exists_local($pdo, 'project_members');
        $stmt = $pdo->prepare("SELECT p.*, u.name AS creator_name FROM projects p JOIN users u ON p.created_by = u.id WHERE p.id = ?");
        $stmt->execute([$selected_project_id]);
        $proj = $stmt->fetch();
        if ($proj) {
            // Contagens
            $hasTaskActive = db_column_exists_local($pdo, 'project_tasks', 'is_active');
            $stT = $pdo->prepare("SELECT COUNT(*) FROM project_tasks WHERE project_id = ?" . ($hasTaskActive ? " AND is_active = 1" : ""));
            $stT->execute([$selected_project_id]);
            $proj['task_count'] = (int)$stT->fetchColumn();
            $proj['member_count'] = 0;
            if ($hasMembers) {
                $stM = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM project_members WHERE project_id = ?");
                $stM->execute([$selected_project_id]);
                $proj['member_count'] = (int)$stM->fetchColumn();
            }
            // Normalizar campos
            if ((!isset($proj['name']) || $proj['name'] === '') && isset($proj['title'])) { $proj['name'] = $proj['title']; }
            if ((!isset($proj['end_date']) || !$proj['end_date']) && isset($proj['due_date'])) { $proj['end_date'] = $proj['due_date']; }
            $projects[] = $proj;
        }
    } catch (Throwable $e) { /* silencioso */ }
}

$selected_project = null;
$project_tasks = [];

if ($selected_project_id) {
    $selected_project = array_filter($projects, fn($p) => $p['id'] == $selected_project_id)[0] ?? null;
    
    if ($selected_project) {
        try {
            $hasTaskActive = db_column_exists_local($pdo, 'project_tasks', 'is_active');
            $sql = "
                SELECT pt.*, u.name as assigned_user_name, u.avatar as assigned_user_avatar
                FROM project_tasks pt
                LEFT JOIN users u ON pt.assigned_to = u.id
                WHERE pt.project_id = ?";
            if ($hasTaskActive) { $sql .= " AND pt.is_active = 1"; }
            // Removido filtro por usuário para exibir todas as tarefas do projeto
            $sql .= " ORDER BY pt.position ASC, pt.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$selected_project_id]);
            $project_tasks = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar tarefas: " . $e->getMessage());
            $project_tasks = [];
        }
    }
}

// Organizar tarefas por status
$kanban_columns = [
    'todo' => ['title' => 'A Fazer', 'tasks' => []],
    'in_progress' => ['title' => 'Em Progresso', 'tasks' => []],
    'review' => ['title' => 'Em Revisão', 'tasks' => []],
    'done' => ['title' => 'Concluído', 'tasks' => []]
];

foreach ($project_tasks as $task) {
    $status = $task['status'] ?? 'todo';
    if (isset($kanban_columns[$status])) {
        $kanban_columns[$status]['tasks'][] = $task;
    }
}

// Cálculos para visão de projetos (totais)
$total_projects = count($projects);
$total_members = 0;
foreach ($projects as $pp_) { $total_members += (int)($pp_['member_count'] ?? 0); }
?>

<div class="kanban-container">
    <!-- Header da página -->
    <div class="page-header">
        <div class="header-content">
            <h1>Gerenciamento de Projetos</h1>
            <p class="tasks-hint">Gerencie seus projetos de forma visual e colaborativa</p>
            <div class="projects-summary" aria-live="polite">
                <span class="summary-item"><i class="fas fa-folder"></i> <?php echo (int)$total_projects; ?> projetos</span>
                <span class="summary-item"><i class="fas fa-users"></i> <?php echo (int)$total_members; ?> membros</span>
            </div>
        </div>
        <div class="header-actions">
            <div class="project-selector">
                <select id="project-select" class="form-control" onchange="changeProject()">
                    <option value="">Selecionar Projeto</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" 
                                <?php echo $project['id'] == $selected_project_id ? 'selected' : ''; ?>>
                            <?php echo escape($project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-outline" onclick="openProjectModal()">
                <i class="fas fa-folder-plus"></i>
                Novo Projeto
            </button>
            <div class="view-switch" title="Alternar entre Tarefas e Projetos">
                <span class="vs-label">Tarefas</span>
                <label class="switch">
                    <input type="checkbox" id="view-switch" aria-label="Alternar visão para Projetos">
                    <span class="slider"></span>
                </label>
                <span class="vs-label">Projetos</span>
            </div>
            <?php if ($selected_project): ?>
                <button class="btn btn-primary" onclick="openTaskModal()">
                    <i class="fas fa-plus"></i>
                    Nova Tarefa
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="project-view-wrapper" data-mode="tasks">
        <?php if (!$selected_project): ?>
            <!-- Estado vazio -->
            <div class="empty-state">
                <i class="fas fa-tasks"></i>
                <h3>Nenhum projeto selecionado</h3>
                <p>Selecione um projeto existente ou crie um novo para começar a gerenciar suas tarefas.</p>
                <button class="btn btn-primary" onclick="openProjectModal()">
                    <i class="fas fa-folder-plus"></i>
                    Criar Primeiro Projeto
                </button>
            </div>
        <?php else: ?>
            <!-- Informações do projeto -->
            <div class="project-info">
                <div class="project-details">
                    <h2><?php echo escape($selected_project['name']); ?></h2>
                    <p><?php echo escape($selected_project['description']); ?></p>
                    <div class="project-meta">
                        <span class="meta-item">
                            <i class="fas fa-user"></i>
                            Criado por <?php echo escape($selected_project['creator_name']); ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-tasks"></i>
                            <?php echo $selected_project['task_count']; ?> tarefa<?php echo $selected_project['task_count'] != 1 ? 's' : ''; ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-users"></i>
                            <?php echo $selected_project['member_count']; ?> membro<?php echo $selected_project['member_count'] != 1 ? 's' : ''; ?>
                        </span>
                    </div>
                </div>
                <div class="project-actions">
                    <button class="btn btn-sm btn-outline" onclick="editProject(<?php echo $selected_project['id']; ?>)">
                        <i class="fas fa-edit"></i>
                        Editar
                    </button>
                    <button class="btn btn-sm btn-outline" onclick="manageProjectMembers(<?php echo $selected_project['id']; ?>)">
                        <i class="fas fa-users-cog"></i>
                        Membros
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline dropdown-toggle">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="#" class="dropdown-item" onclick="duplicateProject(<?php echo $selected_project['id']; ?>)">
                                <i class="fas fa-copy"></i> Duplicar Projeto
                            </a>
                            <a href="#" class="dropdown-item" onclick="exportProject(<?php echo $selected_project['id']; ?>)">
                                <i class="fas fa-download"></i> Exportar
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item text-error" onclick="deleteProject(<?php echo $selected_project['id']; ?>)">
                                <i class="fas fa-trash"></i> Excluir Projeto
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Board Kanban -->
            <div class="kanban-board" id="kanban-board">
                <?php foreach ($kanban_columns as $status => $column): ?>
                    <div class="kanban-column" data-status="<?php echo $status; ?>">
                        <div class="column-header">
                            <h3 class="column-title"><?php echo $column['title']; ?></h3>
                            <span class="task-count"><?php echo count($column['tasks']); ?></span>
                            <button class="btn btn-sm btn-ghost" onclick="addTaskToColumn('<?php echo $status; ?>')">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        
                        <div class="column-content" id="column-<?php echo $status; ?>">
                            <?php foreach ($column['tasks'] as $task): ?>
                                <div class="task-card" 
                                     data-task-id="<?php echo $task['id']; ?>" 
                                     draggable="true">
                                    <div class="task-header">
                                        <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                        <div class="task-actions">
                                            <!-- Removido botão de editar; clique no card abre edição -->
                                            <button class="task-action-btn" data-action="delete" onclick="deleteTask(<?php echo $task['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="task-content">
                                        <h4 class="task-title"><?php echo escape($task['title']); ?></h4>
                                        <?php if ($task['description']): ?>
                                            <p class="task-description"><?php echo escape($task['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="task-footer">
                                        <div class="task-meta">
                                            <?php if ($task['due_date']): ?>
                                                <span class="task-due-date <?php echo strtotime($task['due_date']) < time() ? 'overdue' : ''; ?>">
                                                    <i class="fas fa-calendar"></i>
                                                    <?php echo date('d/m', strtotime($task['due_date'])); ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($task['estimated_hours']): ?>
                                                <span class="task-hours">
                                                    <i class="fas fa-clock"></i>
                                                    <?php echo $task['estimated_hours']; ?>h
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($task['assigned_to']): ?>
                                            <div class="task-assignee">
                                                <img src="<?php echo avatar_src_php($task['assigned_user_avatar']); ?>" 
                                                     alt="<?php echo escape($task['assigned_user_name']); ?>"
                                                     class="assignee-avatar"
                                                     title="<?php echo escape($task['assigned_user_name']); ?>">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Board de Projetos (inicialmente oculto, renderizado via JS quando alternar) -->
        <div id="project-board" class="hidden"></div>
     </div>
 </div>
 
<!-- Modal para projeto -->
<div class="modal-overlay" id="project-modal" style="display: none;">
  <div class="modal">
    <div class="modal-header">
      <h3 id="project-modal-title">Novo Projeto</h3>
      <button class="modal-close" onclick="closeProjectModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="project-form" class="ajax-form" action="../api/projects.php" method="POST">
        <input type="hidden" id="project-id" name="project_id">
        <div class="form-group">
          <label for="project-name" class="form-label">Nome do Projeto *</label>
          <input type="text" id="project-name" name="name" class="form-control" placeholder="Ex: Website, App..." required>
        </div>
        <div class="form-group">
          <label for="project-description" class="form-label">Descrição</label>
          <textarea id="project-description" name="description" class="form-control" rows="3" placeholder="Descreva o projeto..."></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="project-start-date" class="form-label">Data de Início</label>
            <input type="date" id="project-start-date" name="start_date" class="form-control">
          </div>
          <div class="form-group">
            <label for="project-end-date" class="form-label">Data de Término</label>
            <input type="date" id="project-end-date" name="end_date" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label for="project-status" class="form-label">Status</label>
          <select id="project-status" name="status" class="form-control">
            <option value="planning">Planejamento</option>
            <option value="active" selected>Ativo</option>
            <option value="on_hold">Em Pausa</option>
            <option value="completed">Concluído</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeProjectModal()">Cancelar</button>
      <button type="submit" form="project-form" class="btn btn-primary">
        <span class="btn-text">Salvar Projeto</span>
        <span class="btn-loading" style="display:none;"><i class="fas fa-spinner fa-spin"></i></span>
      </button>
    </div>
  </div>
</div>

<!-- Modal para tarefa -->
<div class="modal-overlay" id="task-modal" style="display: none;">
  <div class="modal">
    <div class="modal-header">
      <h3 id="task-modal-title">Nova Tarefa</h3>
      <button class="modal-close" onclick="closeTaskModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="task-form" class="ajax-form" action="../api/tasks.php" method="POST">
        <input type="hidden" id="task-id" name="task_id">
        <input type="hidden" id="task-project-id" name="project_id" value="<?php echo htmlspecialchars($selected_project_id ?? '', ENT_QUOTES); ?>">
        <div class="form-group">
          <label for="task-title" class="form-label">Título da Tarefa *</label>
          <input type="text" id="task-title" name="title" class="form-control" placeholder="Ex: Implementar login" required>
        </div>
        <div class="form-group">
          <label for="task-description" class="form-label">Descrição</label>
          <textarea id="task-description" name="description" class="form-control" rows="3" placeholder="Detalhes da tarefa..."></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="task-status" class="form-label">Status</label>
            <select id="task-status" name="status" class="form-control">
              <option value="todo" selected>A Fazer</option>
              <option value="in_progress">Em Progresso</option>
              <option value="review">Em Revisão</option>
              <option value="done">Concluído</option>
            </select>
          </div>
          <div class="form-group">
            <label for="task-priority" class="form-label">Prioridade</label>
            <select id="task-priority" name="priority" class="form-control">
              <option value="low">Baixa</option>
              <option value="medium" selected>Média</option>
              <option value="high">Alta</option>
              <option value="urgent">Urgente</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="task-due-date" class="form-label">Data de Entrega</label>
            <input type="date" id="task-due-date" name="due_date" class="form-control">
          </div>
          <div class="form-group">
            <label for="task-estimated-hours" class="form-label">Horas Estimadas</label>
            <input type="number" id="task-estimated-hours" name="estimated_hours" class="form-control" min="0.5" step="0.5" placeholder="Ex: 8">
          </div>
        </div>
        <div class="form-group">
          <label for="task-assigned-to" class="form-label">Responsável</label>
          <select id="task-assigned-to" name="assigned_to" class="form-control">
            <option value="">Não atribuído</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeTaskModal()">Cancelar</button>
      <button type="submit" form="task-form" class="btn btn-primary">
        <span class="btn-text">Salvar Tarefa</span>
        <span class="btn-loading" style="display:none;"><i class="fas fa-spinner fa-spin"></i></span>
      </button>
    </div>
  </div>
</div>

<!-- Modal para membros do projeto -->
<div class="modal-overlay" id="members-modal" style="display: none;">
  <div class="modal">
    <div class="modal-header">
      <h3 id="members-modal-title">Gerenciar Membros</h3>
      <button class="modal-close" onclick="document.getElementById('members-modal').classList.remove('open')">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="members-project-id" value="">
      <div class="members-toolbar">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" id="members-search" class="form-control" placeholder="Buscar por nome...">
        </div>
        <div class="members-actions-inline">
          <button class="btn btn-sm" id="members-select-all">Selecionar todos</button>
          <button class="btn btn-sm" id="members-clear-all">Limpar seleção</button>
          <span class="members-counter"><span id="members-selected-count">0</span> selecionado(s)</span>
        </div>
      </div>
      <div class="members-list" id="members-list" aria-live="polite"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('members-modal').classList.remove('open')">Cancelar</button>
      <button type="button" class="btn btn-primary" id="members-save-btn">
        <span class="btn-text">Salvar Membros</span>
        <span class="btn-loading" style="display:none;"><i class="fas fa-spinner fa-spin"></i></span>
      </button>
    </div>
  </div>
</div>

<script>
  window.CURRENT_USER_ID = <?php echo (int)$current_user['id']; ?>;
  window.CURRENT_USER_NAME = <?php echo json_encode($current_user['name'] ?? ''); ?>;
</script>
<script>
  window.PROJECTS_DATA = <?php echo json_encode(array_map(function($p){
    return [
      'id' => (int)$p['id'],
      'name' => isset($p['name']) && $p['name'] !== '' ? $p['name'] : ($p['title'] ?? ''),
      'status' => $p['status'] ?? 'planning',
      'task_count' => (int)($p['task_count'] ?? 0),
      'member_count' => (int)($p['member_count'] ?? 0)
    ];
  }, $projects), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
 
<?php 
$additional_js = ['kanban'];
require_once '../includes/footer.php'; 
?>

<?php 
function avatar_src_php($val){
    if (!$val) return '../assets/images/default-avatar.svg';
    if (preg_match('~^https?://~i', $val)) return $val;
    if ($val[0] === '/') return '..' . $val; // caminho absoluto
    if (strpos($val, 'uploads/avatars/') !== false) return (strpos($val, 'uploads/')===0 ? ('../'.$val) : ('../'.ltrim($val,'/')));
    return '../uploads/avatars/' . $val; // filename
}
?>
