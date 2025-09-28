<?php
$page_title = 'Reuniões';
$page_name = 'meetings';
$additional_css = ['meetings'];
$additional_js = ['meetings'];

require_once '../includes/header.php';

// Buscar reuniões do usuário
try {
    $filter_status = $_GET['status'] ?? 'all';
    $filter_type = $_GET['type'] ?? 'all';
    
    $sql = "
        SELECT m.*, 
               u.name as creator_name,
               u.avatar as creator_avatar,
               COUNT(DISTINCT mp.user_id) as participant_count,
               COUNT(DISTINCT mas.user_id) as online_participants,
               CASE 
                   WHEN m.start_datetime > NOW() THEN 'upcoming'
                   WHEN m.start_datetime <= NOW() AND m.end_datetime > NOW() THEN 'live'
                   ELSE 'ended'
               END as status,
               CASE WHEN m.created_by = ? THEN 1 ELSE 0 END as is_creator
        FROM meetings m
        JOIN users u ON m.created_by = u.id
        LEFT JOIN meeting_participants mp ON m.id = mp.meeting_id
        LEFT JOIN meeting_active_sessions mas ON m.id = mas.meeting_id AND mas.is_active = 1
        WHERE m.is_active = 1 
        AND (m.created_by = ? OR m.id IN (
            SELECT meeting_id FROM meeting_participants WHERE user_id = ?
        ))
    ";
    
    $params = [$current_user['id'], $current_user['id'], $current_user['id']];
    
    if ($filter_status !== 'all') {
        $sql .= " HAVING status = ?";
        $params[] = $filter_status;
    }
    
    $sql .= " GROUP BY m.id ORDER BY m.start_datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $meetings = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erro ao buscar reuniões: " . $e->getMessage());
    $meetings = [];
}
?>

<div class="meetings-container">
    <!-- Header da página -->
    <div class="page-header">
        <div class="header-content">
            <h1>Reuniões</h1>
            <p>Gerencie suas reuniões, workshops e eventos</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-outline" onclick="meetingsModule.toggleCalendarView()">
                <i class="fas fa-calendar"></i>
                Visualização do Calendário
            </button>
            <button class="btn btn-primary" onclick="meetingsModule.openCreateMeetingModal()">
                <i class="fas fa-plus"></i>
                Nova Reunião
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="meeting-filters">
        <div class="filter-group">
            <label class="filter-label">Status</label>
            <select class="filter-select" id="status-filter" onchange="meetingsModule.filterMeetings()">
                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Todas</option>
                <option value="upcoming" <?php echo $filter_status === 'upcoming' ? 'selected' : ''; ?>>Próximas</option>
                <option value="live" <?php echo $filter_status === 'live' ? 'selected' : ''; ?>>Ao Vivo</option>
                <option value="ended" <?php echo $filter_status === 'ended' ? 'selected' : ''; ?>>Finalizadas</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label class="filter-label">Tipo</label>
            <select class="filter-select" id="type-filter" onchange="meetingsModule.filterMeetings()">
                <option value="all">Todos os tipos</option>
                <option value="meeting">Reunião</option>
                <option value="workshop">Workshop</option>
                <option value="mentoring">Mentoria</option>
                <option value="presentation">Apresentação</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label class="filter-label">Período</label>
            <select class="filter-select" id="period-filter" onchange="meetingsModule.filterMeetings()">
                <option value="all">Todos os períodos</option>
                <option value="today">Hoje</option>
                <option value="week">Esta semana</option>
                <option value="month">Este mês</option>
            </select>
        </div>
    </div>

    <!-- Lista de reuniões -->
    <div class="meetings-list" id="meetings-list">
        <?php if (empty($meetings)): ?>
            <div class="empty-state">
                <i class="fas fa-video"></i>
                <h3>Nenhuma reunião encontrada</h3>
                <p>Você ainda não tem reuniões agendadas ou participou de alguma.</p>
                <button class="btn btn-primary" onclick="meetingsModule.openCreateMeetingModal()">
                    <i class="fas fa-plus"></i>
                    Agendar Primeira Reunião
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($meetings as $meeting): ?>
                <div class="meeting-card" data-meeting-id="<?php echo $meeting['id']; ?>">
                    <div class="card-header">
                        <div class="meeting-header">
                            <h3 class="meeting-title"><?php echo escape($meeting['title']); ?></h3>
                            <span class="meeting-status <?php echo $meeting['status']; ?>">
                                <?php
                                $status_labels = [
                                    'upcoming' => 'Próxima',
                                    'live' => 'Ao Vivo',
                                    'ended' => 'Finalizada'
                                ];
                                echo $status_labels[$meeting['status']];
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="meeting-info">
                            <div class="meeting-info-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('d/m/Y', strtotime($meeting['start_datetime'])); ?></span>
                            </div>
                            <div class="meeting-info-item">
                                <i class="fas fa-clock"></i>
                                <span>
                                    <?php echo date('H:i', strtotime($meeting['start_datetime'])); ?> - 
                                    <?php echo date('H:i', strtotime($meeting['end_datetime'])); ?>
                                </span>
                            </div>
                            <div class="meeting-info-item">
                                <i class="fas fa-user"></i>
                                <span>Criado por <?php echo escape($meeting['creator_name']); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($meeting['description']): ?>
                            <div class="meeting-description">
                                <?php echo escape($meeting['description']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="meeting-participants">
                            <div class="participants-info">
                                <i class="fas fa-users"></i>
                                <span class="participants-count">
                                    <?php echo $meeting['participant_count']; ?> participante<?php echo $meeting['participant_count'] != 1 ? 's' : ''; ?>
                                    <?php if ($meeting['status'] === 'live' && $meeting['online_participants'] > 0): ?>
                                        • <span class="online-count">
                                            <i class="fas fa-circle online-indicator"></i>
                                            <?php echo $meeting['online_participants']; ?> online
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <!-- Mostrar avatares dos participantes -->
                            <div class="participants-avatars" id="participants-<?php echo $meeting['id']; ?>">
                                <!-- Será preenchido via JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div class="meeting-actions">
                            <?php if ($meeting['status'] === 'upcoming'): ?>
                                <button class="btn btn-sm btn-outline" onclick="meetingsModule.editMeeting(<?php echo $meeting['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                    Editar
                                </button>
                                <button class="btn btn-sm btn-outline" onclick="meetingsModule.manageMeetingParticipants(<?php echo $meeting['id']; ?>)">
                                    <i class="fas fa-user-plus"></i>
                                    Participantes
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($meeting['status'] === 'live'): ?>
                                <button class="btn btn-sm btn-success" onclick="meetingsModule.joinMeetingRoom(<?php echo $meeting['id']; ?>)">
                                    <i class="fas fa-video"></i>
                                    <span class="live-indicator"></span>
                                    Entrar na Sala
                                </button>
                            <?php elseif ($meeting['status'] === 'upcoming'): ?>
                                <button class="btn btn-sm btn-primary" onclick="meetingsModule.joinMeetingRoom(<?php echo $meeting['id']; ?>)">
                                    <i class="fas fa-video"></i>
                                    Sala de Reunião
                                </button>
                            <?php endif; ?>
                            
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline dropdown-toggle" onclick="meetingsModule.toggleDropdown(<?php echo $meeting['id']; ?>)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu" id="dropdown-<?php echo $meeting['id']; ?>">
                                    <a href="#" class="dropdown-item" onclick="meetingsModule.viewMeetingDetails(<?php echo $meeting['id']; ?>)">
                                        <i class="fas fa-eye"></i> Ver Detalhes
                                    </a>
                                    <?php if ($meeting['is_creator']): ?>
                                        <a href="#" class="dropdown-item" onclick="meetingsModule.manageMeetingParticipants(<?php echo $meeting['id']; ?>)">
                                            <i class="fas fa-users-cog"></i> Gerenciar Participantes
                                        </a>
                                        <a href="#" class="dropdown-item" onclick="meetingsModule.editMeeting(<?php echo $meeting['id']; ?>)">
                                            <i class="fas fa-edit"></i> Editar Reunião
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a href="#" class="dropdown-item text-error" onclick="meetingsModule.deleteMeeting(<?php echo $meeting['id']; ?>)">
                                            <i class="fas fa-trash"></i> Excluir Reunião
                                        </a>
                                    <?php else: ?>
                                        <a href="#" class="dropdown-item text-warning" onclick="meetingsModule.leaveMeeting(<?php echo $meeting['id']; ?>)">
                                            <i class="fas fa-sign-out-alt"></i> Sair da Reunião
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Visualização do calendário (inicialmente oculta) -->
    <div class="calendar-view" id="calendar-view" style="display: none;">
        <div class="meeting-calendar">
            <div class="calendar-header">
                <button class="calendar-nav-btn" onclick="meetingsModule.changeMonth(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="calendar-month" id="calendar-month"></span>
                <button class="calendar-nav-btn" onclick="meetingsModule.changeMonth(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="calendar-grid" id="calendar-grid">
                <!-- Será preenchido pelo JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Modal para gerenciar participantes -->
<div class="modal" id="participantsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gerenciar Participantes</h5>
                <button type="button" class="close" onclick="meetingsModule.closeParticipantsModal()">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="participants-manager">
                    <div class="add-participants-section">
                        <h6>Adicionar Participantes</h6>
                        <div class="form-group">
                            <select class="form-control" id="add_participants" multiple>
                                <!-- Options will be populated via AJAX -->
                            </select>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" onclick="meetingsModule.addParticipants()">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                    <div class="current-participants-section">
                        <h6>Participantes Atuais</h6>
                        <div id="current_participants" class="participants-list">
                            <!-- Participants will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="meetingsModule.closeParticipantsModal()">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para criar/editar reunião -->
<div class="modal-overlay" id="meeting-modal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-title">Nova Reunião</h3>
            <button class="modal-close" onclick="meetingsModule.closeMeetingModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="meeting-form" class="ajax-form" action="../api/meetings.php" method="POST">
                <input type="hidden" id="meeting-id" name="meeting_id">
                
                <div class="form-group">
                    <label for="meeting-title" class="form-label">Título da Reunião *</label>
                    <input type="text" 
                           id="meeting-title" 
                           name="title" 
                           class="form-control" 
                           placeholder="Ex: Daily da equipe, Workshop de React..."
                           required>
                </div>
                
                <div class="form-group">
                    <label for="meeting-description" class="form-label">Descrição</label>
                    <textarea id="meeting-description" 
                              name="description" 
                              class="form-control" 
                              rows="3"
                              placeholder="Descreva o objetivo e agenda da reunião..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start-date" class="form-label">Data *</label>
                        <input type="date" 
                               id="start-date" 
                               name="start_date" 
                               class="form-control" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="start-time" class="form-label">Horário de Início *</label>
                        <input type="time" 
                               id="start-time" 
                               name="start_time" 
                               class="form-control" 
                               required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="duration" class="form-label">Duração</label>
                        <select id="duration" name="duration" class="form-control">
                            <option value="30">30 minutos</option>
                            <option value="60" selected>1 hora</option>
                            <option value="90">1h 30min</option>
                            <option value="120">2 horas</option>
                            <option value="180">3 horas</option>
                            <option value="custom">Personalizado</option>
                        </select>
                    </div>
                    <div class="form-group" id="end-time-group" style="display: none;">
                        <label for="end-time" class="form-label">Horário de Término</label>
                        <input type="time" 
                               id="end-time" 
                               name="end_time" 
                               class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="meeting-url" class="form-label">Link da Reunião *</label>
                    <input type="url" 
                           id="meeting-url" 
                           name="meeting_url" 
                           class="form-control" 
                           placeholder="https://meet.google.com/xxx-xxx-xxx"
                           required>
                    <small class="form-help">
                        Você pode usar Google Meet, Zoom, Teams ou qualquer plataforma de videoconferência.
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="max-participants" class="form-label">Máximo de Participantes</label>
                    <input type="number" 
                           id="max-participants" 
                           name="max_participants" 
                           class="form-control" 
                           value="50"
                           min="2"
                           max="500">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="meetingsModule.closeMeetingModal()">
                Cancelar
            </button>
            <button type="submit" form="meeting-form" class="btn btn-primary">
                <span class="btn-text">Salvar Reunião</span>
                <span class="btn-loading" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                </span>
            </button>
        </div>
    </div>
</div>

<!-- CSS adicional necessário para o modal de participantes -->
<style>
#participantsModal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

#participantsModal.show {
    display: flex !important;
}

#participantsModal .modal-dialog {
    max-width: 800px;
    width: 90%;
    margin: auto;
    animation: modalSlideIn 0.3s ease-out;
}

#participantsModal .modal-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

#participantsModal .modal-header {
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--primary-wine);
    color: white;
}

#participantsModal .modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
}

#participantsModal .close {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.2s;
}

#participantsModal .close:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

#participantsModal .modal-body {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

#participantsModal .modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
}

.participants-manager {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    min-height: 300px;
}

.add-participants-section,
.current-participants-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.add-participants-section h6,
.current-participants-section h6 {
    color: var(--primary-wine);
    font-weight: 600;
    margin-bottom: 15px;
    font-size: 1rem;
}

.participants-list {
    max-height: 250px;
    overflow-y: auto;
}

.participant-item {
    padding: 10px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 8px;
    background: white;
}

.participant-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.participant-info {
    display: flex;
    align-items: center;
}

.participant-info img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    margin-right: 10px;
    object-fit: cover;
}

.participant-name {
    font-weight: 500;
    color: #333;
}

.no-participants {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 20px;
}

.error-message {
    color: #dc3545;
    padding: 15px;
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 6px;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .participants-manager {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    #participantsModal .modal-dialog {
        width: 95%;
    }
}
</style>

<?php 
require_once '../includes/footer.php'; 
?>
