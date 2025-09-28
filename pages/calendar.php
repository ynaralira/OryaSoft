<?php
$page_title = 'Calendário';
$page_name = 'calendar';
$additional_css = ['calendar'];

require_once '../includes/header.php';

// Obter mês e ano atual ou da URL
$current_month = (int)($_GET['month'] ?? date('n'));
$current_year = (int)($_GET['year'] ?? date('Y'));

// Validar mês e ano
$current_month = max(1, min(12, $current_month));
$current_year = max(2020, min(2030, $current_year));

// Buscar eventos do mês
try {
    $first_day = "$current_year-" . str_pad($current_month, 2, '0', STR_PAD_LEFT) . "-01";
    $last_day = date('Y-m-t', strtotime($first_day));
    
    // Buscar reuniões
    $sql_meetings = "
        SELECT m.id, m.title, m.start_datetime, m.end_datetime, 
               'meeting' as event_type, '#722f37' as color,
               COUNT(DISTINCT mp.user_id) as participant_count
        FROM meetings m
        LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
        WHERE m.is_active = 1 
        AND DATE(m.start_datetime) BETWEEN ? AND ?
        AND (m.created_by = ? OR m.id IN (
            SELECT meeting_id FROM meeting_participants WHERE user_id = ?
        ))
        GROUP BY m.id
    ";
    
    $stmt = $pdo->prepare($sql_meetings);
    $stmt->execute([$first_day, $last_day, $current_user['id'], $current_user['id']]);
    $meetings = $stmt->fetchAll();
    
    // Buscar tarefas com prazo
    $sql_tasks = "
        SELECT pt.id, pt.title, pt.due_date as start_datetime, pt.due_date as end_datetime,
               'task' as event_type, '#6b7280' as color, pt.priority,
               p.name as project_name
        FROM project_tasks pt
        JOIN projects p ON pt.project_id = p.id
        WHERE pt.is_active = 1 
        AND pt.due_date IS NOT NULL
        AND DATE(pt.due_date) BETWEEN ? AND ?
        AND (p.created_by = ? OR p.id IN (
            SELECT project_id FROM project_members WHERE user_id = ?
        ))
    ";
    
    $stmt = $pdo->prepare($sql_tasks);
    $stmt->execute([$first_day, $last_day, $current_user['id'], $current_user['id']]);
    $tasks = $stmt->fetchAll();
    
    // Combinar eventos
    $events = array_merge($meetings, $tasks);
    
    // Organizar eventos por data
    $events_by_date = [];
    foreach ($events as $event) {
        $date = date('Y-m-d', strtotime($event['start_datetime']));
        if (!isset($events_by_date[$date])) {
            $events_by_date[$date] = [];
        }
        $events_by_date[$date][] = $event;
    }
    
} catch (PDOException $e) {
    error_log("Erro ao buscar eventos: " . $e->getMessage());
    $events_by_date = [];
}

// Gerar dados do calendário
$first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day_of_month);
$first_day_weekday = date('w', $first_day_of_month);

// Nomes dos meses e dias da semana
$month_names = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

$day_names = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
?>

<div class="calendar-container unified-calendar">
    <!-- Header da página -->
    <div class="page-header">
        <div class="header-content">
            <h1>Calendário</h1>
            <p>Visualize e gerencie seus compromissos e prazos</p>
        </div>
        <div class="header-actions">
            <div class="view-toggle">
                <button class="btn btn-outline active" onclick="changeView('month')">
                    <i class="fas fa-calendar"></i>
                    Mês
                </button>
                <button class="btn btn-outline" onclick="changeView('week')">
                    <i class="fas fa-calendar-week"></i>
                    Semana
                </button>
                <button class="btn btn-outline" onclick="changeView('day')">
                    <i class="fas fa-calendar-day"></i>
                    Dia
                </button>
            </div>
            <button class="btn btn-primary" onclick="openEventQuickCreate()">
                <i class="fas fa-plus"></i>
                Novo Evento
            </button>
        </div>
    </div>

    <!-- Controles do calendário -->
    <div class="calendar-controls">
        <div class="calendar-navigation">
            <button class="nav-btn" onclick="changeMonth(-1)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <h2 class="calendar-title">
                <?php echo $month_names[$current_month] . ' ' . $current_year; ?>
            </h2>
            <button class="nav-btn" onclick="changeMonth(1)">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <div class="calendar-actions">
            <button class="btn btn-sm btn-outline" onclick="goToToday()">
                <i class="fas fa-calendar-day"></i>
                Hoje
            </button>
            <button class="btn btn-sm btn-outline" onclick="exportCalendar()">
                <i class="fas fa-download"></i>
                Exportar
            </button>
        </div>
    </div>

    <!-- Legendas -->
    <div class="calendar-legend">
        <div class="legend-item">
            <span class="legend-color" style="background-color: #722f37;"></span>
            <span class="legend-label">Reuniões</span>
        </div>
        <div class="legend-item">
            <span class="legend-color" style="background-color: #6b7280;"></span>
            <span class="legend-label">Tarefas</span>
        </div>
        <div class="legend-item">
            <span class="legend-color" style="background-color: #059669;"></span>
            <span class="legend-label">Eventos</span>
        </div>
    </div>

    <!-- Calendário principal -->
    <div class="main-calendar" id="main-calendar">
        <!-- Cabeçalho dos dias da semana -->
        <div class="calendar-header">
            <?php foreach ($day_names as $day): ?>
                <div class="calendar-day-header"><?php echo $day; ?></div>
            <?php endforeach; ?>
        </div>
        
        <!-- Grid do calendário -->
        <div class="calendar-grid">
            <?php
            // Dias em branco no início
            for ($i = 0; $i < $first_day_weekday; $i++) {
                $prev_month_day = date('j', mktime(0, 0, 0, $current_month, -$first_day_weekday + $i + 1, $current_year));
                echo '<div class="calendar-day other-month" data-date="' . 
                     date('Y-m-d', mktime(0, 0, 0, $current_month - 1, $prev_month_day, $current_year)) . '">';
                echo '<div class="day-number">' . $prev_month_day . '</div>';
                echo '</div>';
            }
            
            // Dias do mês atual
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date = "$current_year-" . str_pad($current_month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                $is_today = ($date === date('Y-m-d'));
                $day_events = $events_by_date[$date] ?? [];
                
                echo '<div class="calendar-day' . ($is_today ? ' today' : '') . '" data-date="' . $date . '" onclick="openDayView(\'' . $date . '\')">';
                echo '<div class="day-number">' . $day . '</div>';
                
                if (!empty($day_events)) {
                    echo '<div class="day-events">';
                    $displayed = 0;
                    foreach ($day_events as $event) {
                        if ($displayed >= 3) {
                            $remaining = count($day_events) - $displayed;
                            echo '<div class="event-more">+' . $remaining . ' mais</div>';
                            break;
                        }
                        
                        $event_class = 'event-' . $event['event_type'];
                        if ($event['event_type'] === 'task' && $event['priority'] === 'high') {
                            $event_class .= ' high-priority';
                        }
                        
                        echo '<div class="day-event ' . $event_class . '" style="border-left-color: ' . $event['color'] . ';" onclick="event.stopPropagation(); openEventDetails(' . $event['id'] . ', \'' . $event['event_type'] . '\')">';
                        echo '<span class="event-time">' . date('H:i', strtotime($event['start_datetime'])) . '</span>';
                        echo '<span class="event-title">' . escape(substr($event['title'], 0, 20)) . (strlen($event['title']) > 20 ? '...' : '') . '</span>';
                        echo '</div>';
                        
                        $displayed++;
                    }
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            // Dias do próximo mês para completar a grade
            $total_cells = $first_day_weekday + $days_in_month;
            $remaining_cells = 42 - $total_cells; // 6 semanas × 7 dias
            
            for ($i = 1; $i <= $remaining_cells; $i++) {
                $next_month_date = date('Y-m-d', mktime(0, 0, 0, $current_month + 1, $i, $current_year));
                echo '<div class="calendar-day other-month" data-date="' . $next_month_date . '">';
                echo '<div class="day-number">' . $i . '</div>';
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <!-- Sidebar com agenda do dia -->
    <div class="calendar-sidebar" id="calendar-sidebar">
        <div class="sidebar-header">
            <h3>Agenda de Hoje</h3>
            <span class="sidebar-date"><?php echo date('d/m/Y'); ?></span>
        </div>
        
        <div class="sidebar-content">
            <?php 
            $today = date('Y-m-d');
            $today_events = $events_by_date[$today] ?? [];
            ?>
            
            <?php if (empty($today_events)): ?>
                <div class="no-events">
                    <i class="fas fa-calendar-check"></i>
                    <p>Nenhum compromisso para hoje</p>
                </div>
            <?php else: ?>
                <div class="events-list">
                    <?php foreach ($today_events as $event): ?>
                        <div class="sidebar-event <?php echo $event['event_type']; ?>" 
                             onclick="openEventDetails(<?php echo $event['id']; ?>, '<?php echo $event['event_type']; ?>')">
                            <div class="event-time">
                                <?php echo date('H:i', strtotime($event['start_datetime'])); ?>
                            </div>
                            <div class="event-details">
                                <h4><?php echo escape($event['title']); ?></h4>
                                <?php if ($event['event_type'] === 'meeting' && isset($event['participant_count'])): ?>
                                    <p><?php echo $event['participant_count']; ?> participante<?php echo $event['participant_count'] != 1 ? 's' : ''; ?></p>
                                <?php elseif ($event['event_type'] === 'task' && isset($event['project_name'])): ?>
                                    <p><?php echo escape($event['project_name']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="event-indicator" style="background-color: <?php echo $event['color']; ?>;"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="sidebar-footer">
            <button class="btn btn-sm btn-primary w-full" onclick="openEventQuickCreate()">
                <i class="fas fa-plus"></i>
                Adicionar Evento
            </button>
        </div>
    </div>
</div>

<!-- Modal de visualização rápida de evento -->
<div class="modal-overlay" id="event-quick-view" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 id="event-title">Detalhes do Evento</h3>
            <button class="modal-close" onclick="closeEventQuickView()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="event-details-content">
                <!-- Conteúdo será preenchido via JavaScript -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEventQuickView()">
                Fechar
            </button>
            <button type="button" class="btn btn-outline" id="edit-event-btn">
                <i class="fas fa-edit"></i>
                Editar
            </button>
        </div>
    </div>
</div>

<!-- Modal para criação rápida de evento -->
<div class="modal-overlay" id="quick-create-modal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Novo Evento</h3>
            <button class="modal-close" onclick="closeQuickCreateModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="quick-event-form">
                <div class="form-group">
                    <label class="form-label">Tipo de Evento</label>
                    <div class="event-type-tabs">
                        <label class="type-tab">
                            <input type="radio" name="event_type" value="meeting" checked>
                            <span class="tab-content">
                                <i class="fas fa-video"></i>
                                Reunião
                            </span>
                        </label>
                        <label class="type-tab">
                            <input type="radio" name="event_type" value="task">
                            <span class="tab-content">
                                <i class="fas fa-tasks"></i>
                                Tarefa
                            </span>
                        </label>
                        <label class="type-tab">
                            <input type="radio" name="event_type" value="event">
                            <span class="tab-content">
                                <i class="fas fa-calendar"></i>
                                Evento
                            </span>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="quick-title" class="form-label">Título *</label>
                    <input type="text" 
                           id="quick-title" 
                           name="title" 
                           class="form-control" 
                           placeholder="Digite o título..."
                           required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quick-date" class="form-label">Data *</label>
                        <input type="date" 
                               id="quick-date" 
                               name="date" 
                               class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>"
                               required>
                    </div>
                    <div class="form-group">
                        <label for="quick-time" class="form-label">Horário *</label>
                        <input type="time" 
                               id="quick-time" 
                               name="time" 
                               class="form-control" 
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="quick-description" class="form-label">Descrição</label>
                    <textarea id="quick-description" 
                              name="description" 
                              class="form-control" 
                              rows="3"
                              placeholder="Descrição opcional..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeQuickCreateModal()">
                Cancelar
            </button>
            <button type="submit" form="quick-event-form" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Criar Evento
            </button>
        </div>
    </div>
</div>

<!-- Substitui o JS inline por configuração inicial para o script externo -->
<script>
  window.CALENDAR_INIT = {
    currentMonth: <?php echo $current_month; ?>,
    currentYear: <?php echo $current_year; ?>
  };
</script>

<?php 
$additional_js = ['calendar'];
require_once '../includes/footer.php'; 
?>
