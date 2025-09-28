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
            <button class="btn btn-outline" onclick="toggleCalendarView()">
                <i class="fas fa-calendar"></i>
                Visualização do Calendário
            </button>
            <button class="btn btn-primary" onclick="openCreateMeetingModal()">
                <i class="fas fa-plus"></i>
                Nova Reunião
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="meeting-filters">
        <div class="filter-group">
            <label class="filter-label">Status</label>
            <select class="filter-select" id="status-filter" onchange="filterMeetings()">
                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Todas</option>
                <option value="upcoming" <?php echo $filter_status === 'upcoming' ? 'selected' : ''; ?>>Próximas</option>
                <option value="live" <?php echo $filter_status === 'live' ? 'selected' : ''; ?>>Ao Vivo</option>
                <option value="ended" <?php echo $filter_status === 'ended' ? 'selected' : ''; ?>>Finalizadas</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label class="filter-label">Tipo</label>
            <select class="filter-select" id="type-filter" onchange="filterMeetings()">
                <option value="all">Todos os tipos</option>
                <option value="meeting">Reunião</option>
                <option value="workshop">Workshop</option>
                <option value="mentoring">Mentoria</option>
                <option value="presentation">Apresentação</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label class="filter-label">Período</label>
            <select class="filter-select" id="period-filter" onchange="filterMeetings()">
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
                <button class="btn btn-primary" onclick="openCreateMeetingModal()">
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
                                <button class="btn btn-sm btn-outline" onclick="editMeeting(<?php echo $meeting['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                    Editar
                                </button>
                                <button class="btn btn-sm btn-outline" onclick="manageMeetingParticipants(<?php echo $meeting['id']; ?>)">
                                    <i class="fas fa-user-plus"></i>
                                    Participantes
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($meeting['status'] === 'live'): ?>
                                <button class="btn btn-sm btn-success" onclick="joinMeetingRoom(<?php echo $meeting['id']; ?>)">
                                    <i class="fas fa-video"></i>
                                    <span class="live-indicator"></span>
                                    Entrar na Sala
                                </button>
                            <?php elseif ($meeting['status'] === 'upcoming'): ?>
                                <button class="btn btn-sm btn-primary" onclick="joinMeetingRoom(<?php echo $meeting['id']; ?>)">
                                    <i class="fas fa-video"></i>
                                    Sala de Reunião
                                </button>
                            <?php endif; ?>
                            
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline dropdown-toggle" onclick="toggleDropdown(<?php echo $meeting['id']; ?>)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu" id="dropdown-<?php echo $meeting['id']; ?>">
                                    <a href="#" class="dropdown-item" onclick="viewMeetingDetails(<?php echo $meeting['id']; ?>)">
                                        <i class="fas fa-eye"></i> Ver Detalhes
                                    </a>
                                    <?php if ($meeting['is_creator']): ?>
                                        <a href="#" class="dropdown-item" onclick="manageMeetingParticipants(<?php echo $meeting['id']; ?>)">
                                            <i class="fas fa-users-cog"></i> Gerenciar Participantes
                                        </a>
                                        <a href="#" class="dropdown-item" onclick="editMeeting(<?php echo $meeting['id']; ?>)">
                                            <i class="fas fa-edit"></i> Editar Reunião
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a href="#" class="dropdown-item text-error" onclick="deleteMeeting(<?php echo $meeting['id']; ?>)">
                                            <i class="fas fa-trash"></i> Excluir Reunião
                                        </a>
                                    <?php else: ?>
                                        <a href="#" class="dropdown-item text-warning" onclick="leaveMeeting(<?php echo $meeting['id']; ?>)">
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
                <button class="calendar-nav-btn" onclick="changeMonth(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="calendar-month" id="calendar-month"></span>
                <button class="calendar-nav-btn" onclick="changeMonth(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="calendar-grid" id="calendar-grid">
                <!-- Será preenchido pelo JavaScript -->
        </div>
    </div>
</div>

<!-- Modais para funcionalidade completa de reuniões -->

<!-- Modal para criar reunião -->
<div class="modal fade" id="createMeetingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nova Reunião</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="createMeetingForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="meeting_title">Título da Reunião</label>
                        <input type="text" class="form-control" id="meeting_title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="meeting_description">Descrição</label>
                        <textarea class="form-control" id="meeting_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="meeting_date">Data</label>
                                <input type="date" class="form-control" id="meeting_date" name="date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="meeting_time">Horário</label>
                                <input type="time" class="form-control" id="meeting_time" name="time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="meeting_duration">Duração (minutos)</label>
                        <select class="form-control" id="meeting_duration" name="duration">
                            <option value="30">30 minutos</option>
                            <option value="60" selected>1 hora</option>
                            <option value="90">1h 30min</option>
                            <option value="120">2 horas</option>
                            <option value="180">3 horas</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="meeting_participants">Convidar Participantes</label>
                        <select class="form-control" id="meeting_participants" name="participants[]" multiple>
                            <!-- Options will be populated via AJAX -->
                        </select>
                        <small class="form-text text-muted">Selecione os usuários para convidar</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="meeting_recording" name="recording">
                            <label class="form-check-label" for="meeting_recording">
                                Permitir gravação da reunião
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Reunião</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para editar reunião -->
<div class="modal fade" id="editMeetingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Reunião</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="editMeetingForm">
                <input type="hidden" id="edit_meeting_id" name="meeting_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_meeting_title">Título da Reunião</label>
                        <input type="text" class="form-control" id="edit_meeting_title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_meeting_description">Descrição</label>
                        <textarea class="form-control" id="edit_meeting_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_meeting_date">Data</label>
                                <input type="date" class="form-control" id="edit_meeting_date" name="date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_meeting_time">Horário</label>
                                <input type="time" class="form-control" id="edit_meeting_time" name="time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_meeting_duration">Duração (minutos)</label>
                        <select class="form-control" id="edit_meeting_duration" name="duration">
                            <option value="30">30 minutos</option>
                            <option value="60">1 hora</option>
                            <option value="90">1h 30min</option>
                            <option value="120">2 horas</option>
                            <option value="180">3 horas</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="edit_meeting_recording" name="recording">
                            <label class="form-check-label" for="edit_meeting_recording">
                                Permitir gravação da reunião
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para gerenciar participantes -->
<div class="modal" id="participantsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gerenciar Participantes</h5>
                <button type="button" class="close" onclick="closeParticipantsModal()">
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
                        <button type="button" class="btn btn-primary btn-sm" onclick="addParticipants()">
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
                <button type="button" class="btn btn-secondary" onclick="closeParticipantsModal()">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal da Sala de Reunião -->
<div class="modal fade" id="meetingRoomModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="room-title">Sala de Reunião</h5>
                <div class="room-controls">
                    <button type="button" class="btn btn-sm btn-outline-light" onclick="toggleMicrophone()">
                        <i class="fas fa-microphone" id="mic-icon"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light" onclick="toggleCamera()">
                        <i class="fas fa-video" id="camera-icon"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light" onclick="shareScreen()">
                        <i class="fas fa-desktop"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="leaveMeetingRoom()">
                        <i class="fas fa-phone-slash"></i> Sair
                    </button>
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="meeting-room-container">
                    <div class="video-grid" id="video-grid">
                        <!-- Video streams will be added here -->
                    </div>
                    <div class="meeting-sidebar">
                        <div class="participants-panel">
                            <h6>Participantes (<span id="participants-count">0</span>)</h6>
                            <div id="participants-list">
                                <!-- Participants will be listed here -->
                            </div>
                        </div>
                        <!-- <div class="chat-panel">
                            <h6>Chat</h6>
                            <div class="chat-messages" id="chat-messages">
                                <!-- Chat messages will appear here -->
                            </div>
                            <div class="chat-input">
                                <input type="text" class="form-control" id="chat-input" placeholder="Digite sua mensagem...">
                                <button type="button" class="btn btn-primary" onclick="sendChatMessage()">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para criar/editar reunião -->
<div class="modal-overlay" id="meeting-modal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-title">Nova Reunião</h3>
            <button class="modal-close" onclick="closeMeetingModal()">&times;</button>
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
            <button type="button" class="btn btn-secondary" onclick="closeMeetingModal()">
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
    justify-content: between;
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
let socket = null;
let isMicrophoneEnabled = true;
let isCameraEnabled = true;

// Initialize participants dropdown
function initializeParticipantsDropdown() {
    const apiUrl = `${window.location.origin}/Orya/api/users.php`;
    console.log('Carregando usuários da URL:', apiUrl);
    
    const addDropdown = document.getElementById('add_participants');
    
    if (!addDropdown) {
        console.error('Select add_participants não encontrado!');
        return;
    }
    
    // Mostrar loading no select
    addDropdown.innerHTML = '<option>Carregando usuários...</option>';
    addDropdown.disabled = true;
    
    fetch(apiUrl)
        .then(response => { 
            console.log('Status resposta users API:', response.status);
            if(!response.ok) {
                throw new Error(`Erro HTTP: ${response.status} - ${response.statusText}`); 
            }
            return response.json(); 
        })
        .then(users => {
            console.log('Usuários recebidos:', users);
            
            addDropdown.innerHTML = '';
            addDropdown.disabled = false;
            
            if (!Array.isArray(users) || users.length === 0) {
                addDropdown.innerHTML = '<option>Nenhum usuário disponível</option>';
                return;
            }
            
            // Adicionar option padrão
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Selecione usuários para adicionar...';
            addDropdown.appendChild(defaultOption);
            
            users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.name;
                addDropdown.appendChild(option);
            });
            
            console.log(`${users.length} usuários carregados no dropdown`);
        })
        .catch(err => {
            console.error('Erro ao carregar usuários:', err);
            addDropdown.innerHTML = '<option>Erro ao carregar usuários</option>';
            addDropdown.disabled = true;
        });
}

// Create Meeting
document.getElementById('createMeetingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/api/meetings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('createMeetingModal').style.display = 'none';
            document.getElementById('createMeetingModal').classList.remove('show');
            document.body.style.overflow = 'auto';
            location.reload();
        } else {
            alert('Erro ao criar reunião: ' + data.message);
        }
    });
});

// Edit Meeting
function editMeeting(meetingId) {
    fetch(`/api/meetings.php?id=${meetingId}`)
        .then(response => response.json())
        .then(meeting => {
            document.getElementById('edit_meeting_id').value = meeting.id;
            document.getElementById('edit_meeting_title').value = meeting.title;
            document.getElementById('edit_meeting_description').value = meeting.description;
            document.getElementById('edit_meeting_date').value = meeting.date;
            document.getElementById('edit_meeting_time').value = meeting.time;
            document.getElementById('edit_meeting_duration').value = meeting.duration;
            document.getElementById('edit_meeting_recording').checked = meeting.recording;
            
            document.getElementById('editMeetingModal').style.display = 'block';
            document.getElementById('editMeetingModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        });
}

document.getElementById('editMeetingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/api/meetings.php', {
        method: 'PUT',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('editMeetingModal').style.display = 'none';
            document.getElementById('editMeetingModal').classList.remove('show');
            document.body.style.overflow = 'auto';
            location.reload();
        } else {
            alert('Erro ao editar reunião: ' + data.message);
        }
    });
});

// Delete Meeting - CORRIGIDA
function deleteMeeting(meetingId) {
    if (confirm('Tem certeza que deseja excluir esta reunião?')) {
        console.log('🗑️ Excluindo reunião (função 2):', meetingId);
        
        const apiUrl = `${window.location.origin}/Orya/api/meetings.php`;
        
        fetch(apiUrl, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                meeting_id: meetingId
            })
        })
        .then(response => {
            console.log('Status da resposta:', response.status);
            
            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status} - ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Resposta da API:', data);
            
            if (data.success) {
                alert('✅ Reunião excluída com sucesso!');
                location.reload();
            } else {
                alert('❌ Erro ao excluir reunião: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro detalhado ao excluir reunião:', error);
            alert('❌ Erro ao excluir reunião: ' + error.message);
        });
    }
}

// FUNÇÃO DE TESTE SUPER SIMPLES - APENAS PARA MOSTRAR O MODAL
function testShowModal() {
    console.log('🧪 TESTE: Mostrando modal de participantes...');
    
    const modal = document.getElementById('participantsModal');
    if (!modal) {
        console.error('❌ Modal não encontrado!');
        return;
    }
    
    // MÉTODO SUPER DIRETO
    modal.style.display = 'flex';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100vw';
    modal.style.height = '100vh';
    modal.style.backgroundColor = 'rgba(0,0,0,0.8)';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '999999';
    
    console.log('✅ Modal deveria estar visível agora!');
}

// Manage Participants - VERSÃO SIMPLES
function manageMeetingParticipants(meetingId) {
    console.log('� INICIANDO manageMeetingParticipants - ID:', meetingId);
    
    const modal = document.getElementById('participantsModal');
    
    console.log('Modal elemento:', modal);
    console.log('Modal existe?', !!modal);
    
    if (!modal) {
        alert('❌ ERRO: Modal não encontrado!');
        return;
    }
    
    // MÉTODO SIMPLES - DISPLAY DIRETO
    modal.style.display = 'flex';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100vw';
    modal.style.height = '100vh';
    modal.style.backgroundColor = 'rgba(0,0,0,0.8)';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.zIndex = '999999';
    modal.className = 'modal show';
    
    // BLOQUEAR SCROLL DO BODY
    document.body.style.overflow = 'hidden';
    
    console.log('✅ Modal forçado a aparecer!');
    console.log('CSS aplicado:', modal.style.cssText);
    
    // Definir meeting ID global
    currentMeetingId = meetingId;
    
    // Carregar dados após 500ms para garantir que modal apareceu
    setTimeout(() => {
        console.log('📋 Carregando dados...');
        loadCurrentParticipants(meetingId);
        initializeParticipantsDropdown();
    }, 500);
    
    // Debug final
    setTimeout(() => {
        const rect = modal.getBoundingClientRect();
        console.log('� Posição final do modal:', {
            width: rect.width,
            height: rect.height,
            top: rect.top,
            left: rect.left,
            visível: rect.width > 0 && rect.height > 0 && rect.top >= 0
        });
        
        if (rect.width === 0 || rect.height === 0) {
            alert('⚠️ ATENÇÃO: Modal não está visível! Verifique o console.');
        }
    }, 1000);
}

function loadCurrentParticipants(meetingId) {
    const apiUrl = `${window.location.origin}/Orya/api/meeting-participants.php?meeting_id=${meetingId}`;
    console.log('Carregando participantes da URL:', apiUrl);
    
    const container = document.getElementById('current_participants');
    if (!container) {
        console.error('Container current_participants não encontrado!');
        return;
    }
    
    // Mostrar loading
    container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>';
    
    fetch(apiUrl)
        .then(response => {
            console.log('Status da resposta:', response.status);
            console.log('Headers da resposta:', response.headers);
            
            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status} - ${response.statusText}`);
            }
            return response.text(); // Primeiro como text para debug
        })
        .then(text => {
            console.log('Resposta raw da API:', text);
            
            // Tentar fazer parse do JSON
            let participants;
            try {
                participants = JSON.parse(text);
            } catch (e) {
                throw new Error('Resposta não é um JSON válido: ' + text);
            }
            
            console.log('Participantes parseados:', participants);
            
            container.innerHTML = '';
            
            // Verificar se é array
            if (!Array.isArray(participants)) {
                if (participants.success === false) {
                    container.innerHTML = `<p class="error-message">Erro da API: ${participants.message}</p>`;
                    return;
                } else {
                    throw new Error('Resposta da API não é um array de participantes');
                }
            }
            
            if (participants.length === 0) {
                container.innerHTML = '<p class="no-participants">Nenhum participante encontrado nesta reunião</p>';
                return;
            }
            
            participants.forEach((participant, index) => {
                console.log(`Participante ${index}:`, participant);
                
                const participantDiv = document.createElement('div');
                participantDiv.className = 'participant-item';
                
                const avatarUrl = participant.avatar || '/Orya/assets/images/default-avatar.svg';
                const isOnline = participant.is_online ? '<span class="badge badge-success">Online</span>' : '';
                
                participantDiv.innerHTML = `
                    <div class="participant-row">
                        <div class="participant-info">
                            <img src="${avatarUrl}" 
                                 alt="${participant.name}" 
                                 class="avatar-sm"
                                 style="width: 32px; height: 32px; border-radius: 50%; margin-right: 10px;"
                                 onerror="this.src='/Orya/assets/images/default-avatar.svg'">
                            <span class="participant-name">${participant.name}</span>
                            ${isOnline}
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="removeParticipant(${meetingId}, ${participant.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                container.appendChild(participantDiv);
            });
            
            console.log(`${participants.length} participantes carregados com sucesso`);
        })
        .catch(error => {
            console.error('Erro detalhado ao carregar participantes:', error);
            container.innerHTML = `
                <div class="error-message">
                    <strong>Erro ao carregar participantes:</strong><br>
                    ${error.message}<br><br>
                    <small>URL tentada: ${apiUrl}</small>
                </div>
            `;
        });
}

// Função para fechar o modal de participantes
function closeParticipantsModal() {
    console.log('🚪 Fechando modal de participantes');
    
    const modal = document.getElementById('participantsModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
        console.log('✅ Modal fechado com sucesso');
    }
    
    // Limpar variável global
    currentMeetingId = null;
}

function addParticipants() {
    const select = document.getElementById('add_participants');
    const selectedOptions = Array.from(select.selectedOptions);
    
    if (selectedOptions.length === 0) {
        alert('Selecione pelo menos um participante');
        return;
    }
    
    const userIds = selectedOptions.map(option => option.value);
    const apiUrl = `${window.location.origin}/Orya/api/meeting-participants.php`;
    
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            meeting_id: currentMeetingId,
            user_ids: userIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadCurrentParticipants(currentMeetingId);
            select.selectedIndex = -1;
        } else {
            alert('Erro ao adicionar participantes: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro ao adicionar participantes:', error);
        alert('Erro ao adicionar participantes: ' + error.message);
    });
}

function removeParticipant(meetingId, userId) {
    if (confirm('Remover este participante da reunião?')) {
        const apiUrl = `${window.location.origin}/Orya/api/meeting-participants.php`;
        
        fetch(apiUrl, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadCurrentParticipants(meetingId);
            } else {
                alert('Erro ao remover participante: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro ao remover participante:', error);
            alert('Erro ao remover participante: ' + error.message);
        });
    }
}

// Meeting Room Functions
function joinMeetingRoom(meetingId) {
    currentMeetingId = meetingId;
    document.getElementById('meetingRoomModal').style.display = 'block';
    document.getElementById('meetingRoomModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    initializeMeetingRoom(meetingId);
}

async function initializeMeetingRoom(meetingId) {
    try {
        // Get user media
        localStream = await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: true
        });
        
        // Add local video
        addVideoStream('local', localStream, 'Você');
        
        // Connect to WebSocket server (placeholder for now)
        console.log('Conectando à sala de reunião:', meetingId);
        
        // Load meeting participants
        loadMeetingParticipants(meetingId);
        
        // Simulate other participants for demo
        setTimeout(() => {
            addDemoParticipants();
        }, 2000);
        
    } catch (error) {
        console.error('Error accessing media devices:', error);
        alert('Erro ao acessar câmera/microfone. Verifique as permissões.');
    }
}

function addVideoStream(userId, stream, userName) {
    const videoGrid = document.getElementById('video-grid');
    
    // Remove existing video if present
    const existingVideo = document.getElementById(`video-${userId}`);
    if (existingVideo) {
        existingVideo.remove();
    }
    
    const videoContainer = document.createElement('div');
    videoContainer.className = 'video-container';
    videoContainer.id = `video-${userId}`;
    
    const video = document.createElement('video');
    video.srcObject = stream;
    video.autoplay = true;
    video.playsInline = true;
    if (userId === 'local') {
        video.muted = true;
    }
    
    const nameLabel = document.createElement('div');
    nameLabel.className = 'video-name';
    nameLabel.textContent = userName;
    
    videoContainer.appendChild(video);
    videoContainer.appendChild(nameLabel);
    videoGrid.appendChild(videoContainer);
}

function addDemoParticipants() {
    // Add demo participants for testing
    const demoParticipants = [
        { id: 'demo1', name: 'Ana Silva' },
        { id: 'demo2', name: 'João Santos' }
    ];
    
    demoParticipants.forEach(participant => {
        // Create a colored div instead of video for demo
        const videoGrid = document.getElementById('video-grid');
        const videoContainer = document.createElement('div');
        videoContainer.className = 'video-container demo-video';
        videoContainer.id = `video-${participant.id}`;
        
        const demoVideo = document.createElement('div');
        demoVideo.className = 'demo-participant';
        demoVideo.style.background = `linear-gradient(45deg, #${Math.floor(Math.random()*16777215).toString(16)}, #${Math.floor(Math.random()*16777215).toString(16)})`;
        demoVideo.innerHTML = `<i class="fas fa-user fa-3x"></i>`;
        
        const nameLabel = document.createElement('div');
        nameLabel.className = 'video-name';
        nameLabel.textContent = participant.name;
        
        videoContainer.appendChild(demoVideo);
        videoContainer.appendChild(nameLabel);
        videoGrid.appendChild(videoContainer);
    });
}

function toggleMicrophone() {
    if (localStream) {
        const audioTrack = localStream.getAudioTracks()[0];
        if (audioTrack) {
            audioTrack.enabled = !audioTrack.enabled;
            isMicrophoneEnabled = audioTrack.enabled;
            
            const micIcon = document.getElementById('mic-icon');
            micIcon.className = isMicrophoneEnabled ? 'fas fa-microphone' : 'fas fa-microfone-slash';
        }
    }
}

function toggleCamera() {
    if (localStream) {
        const videoTrack = localStream.getVideoTracks()[0];
        if (videoTrack) {
            videoTrack.enabled = !videoTrack.enabled;
            isCameraEnabled = videoTrack.enabled;
            
            const cameraIcon = document.getElementById('camera-icon');
            cameraIcon.className = isCameraEnabled ? 'fas fa-video' : 'fas fa-video-slash';
        }
    }
}

function shareScreen() {
    navigator.mediaDevices.getDisplayMedia({ video: true })
        .then(stream => {
            // Replace video track with screen share
            const videoTrack = stream.getVideoTracks()[0];
            
            // Update local video
            const localVideo = document.querySelector('#video-local video');
            if (localVideo) {
                localVideo.srcObject = stream;
            }
            
            videoTrack.onended = () => {
                // Screen share ended, switch back to camera
                if (localStream) {
                    const cameraTrack = localStream.getVideoTracks()[0];
                    if (cameraTrack) {
                        const localVideo = document.querySelector('#video-local video');
                        if (localVideo) {
                            localVideo.srcObject = localStream;
                        }
                    }
                }
            };
        })
        .catch(error => {
            console.error('Error sharing screen:', error);
        });
}

function sendChatMessage() {
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    
    if (message) {
        // Add message to chat (demo)
        addChatMessage({
            user_name: 'Você',
            message: message,
            timestamp: new Date().toISOString()
        });
        
        input.value = '';
    }
}

function addChatMessage(data) {
    const chatMessages = document.getElementById('chat-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'chat-message';
    messageDiv.innerHTML = `
        <div class="chat-message-header">
            <strong>${data.user_name}</strong>
            <span class="chat-time">${new Date(data.timestamp).toLocaleTimeString()}</span>
        </div>
        <div class="chat-message-body">${data.message}</div>
    `;
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function leaveMeetingRoom() {
    if (confirm('Tem certeza que deseja sair da reunião?')) {
        // Stop local stream
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
        }
        
        // Close modal
        document.getElementById('meetingRoomModal').style.display = 'none';
        document.getElementById('meetingRoomModal').classList.remove('show');
        document.body.style.overflow = 'auto';
        
        // Clear video grid
        document.getElementById('video-grid').innerHTML = '';
        
        // Reset variables
        localStream = null;
        currentMeetingId = null;
    }
}

function loadMeetingParticipants(meetingId) {
    fetch(`/api/meeting-participants.php?meeting_id=${meetingId}&active=1`)
        .then(response => response.json())
        .then(participants => {
            const participantsList = document.getElementById('participants-list');
            const participantsCount = document.getElementById('participants-count');
            
            participantsList.innerHTML = '';
            participantsCount.textContent = participants.length + 1; // +1 for current user
            
            // Add current user
            const currentUserDiv = document.createElement('div');
            currentUserDiv.className = 'participant-item';
            currentUserDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <img src="/assets/images/default-avatar.svg" 
                         alt="Você" class="avatar-xs rounded-circle me-2">
                    <span>Você</span>
                    <div class="participant-status ms-auto">
                        <i class="fas fa-microphone text-success" id="mic-status"></i>
                        <i class="fas fa-video text-success" id="camera-status"></i>
                    </div>
                </div>
            `;
            participantsList.appendChild(currentUserDiv);
            
            participants.forEach(participant => {
                const participantDiv = document.createElement('div');
                participantDiv.className = 'participant-item';
                participantDiv.innerHTML = `
                    <div class="d-flex align-items-center">
                        <img src="${participant.avatar || '/assets/images/default-avatar.svg'}" 
                             alt="${participant.name}" class="avatar-xs rounded-circle me-2">
                        <span>${participant.name}</span>
                        <div class="participant-status ms-auto">
                            <i class="fas fa-microphone text-success"></i>
                            <i class="fas fa-video text-success"></i>
                        </div>
                    </div>
                `;
                participantsList.appendChild(participantDiv);
            });
        })
        .catch(() => {
            // Demo data if API fails
            const participantsList = document.getElementById('participants-list');
            const participantsCount = document.getElementById('participants-count');
            
            participantsCount.textContent = '3';
            participantsList.innerHTML = `
                <div class="participant-item">
                    <div class="d-flex align-items-center">
                        <img src="/assets/images/default-avatar.svg" alt="Você" class="avatar-xs rounded-circle me-2">
                        <span>Você</span>
                        <div class="participant-status ms-auto">
                            <i class="fas fa-microphone text-success"></i>
                            <i class="fas fa-video text-success"></i>
                        </div>
                    </div>
                </div>
                <div class="participant-item">
                    <div class="d-flex align-items-center">
                        <img src="/assets/images/default-avatar.svg" alt="Ana Silva" class="avatar-xs rounded-circle me-2">
                        <span>Ana Silva</span>
                        <div class="participant-status ms-auto">
                            <i class="fas fa-microphone text-success"></i>
                            <i class="fas fa-video text-success"></i>
                        </div>
                    </div>
                </div>
                <div class="participant-item">
                    <div class="d-flex align-items-center">
                        <img src="/assets/images/default-avatar.svg" alt="João Santos" class="avatar-xs rounded-circle me-2">
                        <span>João Santos</span>
                        <div class="participant-status ms-auto">
                            <i class="fas fa-microphone text-success"></i>
                            <i class="fas fa-video text-success"></i>
                        </div>
                    </div>
                </div>
            `;
        });
}

function toggleDropdown(meetingId) {
    const dropdown = document.getElementById(`dropdown-${meetingId}`);
    dropdown.classList.toggle('show');
}

function viewMeetingDetails(meetingId) {
    alert('Ver detalhes da reunião ' + meetingId);
}

function leaveMeeting(meetingId) {
    if (confirm('Tem certeza que deseja sair desta reunião?')) {
        alert('Você saiu da reunião ' + meetingId);
        location.reload();
    }
}

// Chat input Enter key support
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM carregado, inicializando...');
    
    const chatInput = document.getElementById('chat-input');
    if (chatInput) {
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });
    }
    
    // Initialize participants dropdown
    setTimeout(initializeParticipantsDropdown, 1000);
    
    // Test se modal existe
    const modal = document.getElementById('participantsModal');
    console.log('Modal participantsModal encontrado no DOM:', !!modal);
    
    // Adicionar evento para debugging
    window.testModal = function() {
        console.log('🧪 Testando modal...');
        const modal = document.getElementById('participantsModal');
        if (modal) {
            // Remover conflitos
            modal.classList.remove('fade');
            modal.style.opacity = '1';
            modal.style.visibility = 'visible';
            modal.style.display = 'flex';
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            console.log('✅ Modal forçado a aparecer');
            console.log('📊 Propriedades do modal:', {
                display: modal.style.display,
                classes: modal.className,
                zIndex: getComputedStyle(modal).zIndex,
                opacity: getComputedStyle(modal).opacity,
                visibility: getComputedStyle(modal).visibility
            });
            
            // Verificar dimensões
            setTimeout(() => {
                const rect = modal.getBoundingClientRect();
                console.log('📐 Dimensões:', rect);
            }, 100);
        } else {
            console.log('❌ Modal não encontrado!');
        }
    };
    
    // Função para forçar exibição do modal de participantes
    window.forceShowParticipantsModal = function(meetingId = 1) {
        console.log('🚀 Forçando exibição do modal de participantes...');
        
        const modal = document.getElementById('participantsModal');
        if (!modal) {
            console.error('❌ Modal não encontrado');
            return;
        }
        
        // Aplicar CSS direto
        modal.style.cssText = `
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background-color: rgba(0,0,0,0.5) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            z-index: 99999 !important;
            opacity: 1 !important;
            visibility: visible !important;
        `;
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        console.log('✅ Modal forçado com CSS inline');
        
        // Carregar dados
        currentMeetingId = meetingId;
        if (typeof loadCurrentParticipants === 'function') {
            loadCurrentParticipants(meetingId);
        }
        if (typeof initializeParticipantsDropdown === 'function') {
            initializeParticipantsDropdown();
        }
    };
    
    // Função para testar a API diretamente
    window.testAPI = function(meetingId = 1) {
        const apiUrl = `${window.location.origin}/Orya/api/meeting-participants.php?meeting_id=${meetingId}`;
        console.log('Testando API:', apiUrl);
        
        fetch(apiUrl)
            .then(response => response.text())
            .then(text => {
                console.log('Resposta da API:', text);
                try {
                    const json = JSON.parse(text);
                    console.log('JSON parseado:', json);
                } catch(e) {
                    console.error('Erro ao parsear JSON:', e);
                }
            })
            .catch(error => console.error('Erro na requisição:', error));
    };
    
    // Função para testar participantes com meeting ID real
    window.testParticipants = function(meetingId) {
        if (!meetingId) {
            // Tentar encontrar um meeting ID na página
            const meetingCard = document.querySelector('.meeting-card');
            if (meetingCard) {
                meetingId = meetingCard.getAttribute('data-meeting-id');
            }
        }
        
        if (!meetingId) {
            console.log('Nenhum meeting ID fornecido ou encontrado');
            return;
        }
        
        console.log('Testando participantes para meeting ID:', meetingId);
        manageMeetingParticipants(meetingId);
    };
});

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.matches('.dropdown-toggle')) {
        const dropdowns = document.querySelectorAll('.dropdown-menu.show');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});

// Update microphone/camera status in participants list
function updateParticipantStatus() {
    const micStatus = document.getElementById('mic-status');
    const cameraStatus = document.getElementById('camera-status');
    
    if (micStatus) {
        micStatus.className = isMicrophoneEnabled ? 'fas fa-microphone text-success' : 'fas fa-microfone-slash text-danger';
    }
    
    if (cameraStatus) {
        cameraStatus.className = isCameraEnabled ? 'fas fa-video text-success' : 'fas fa-video-slash text-danger';
    }
}

// Modal management
function showCreateMeetingModal() {
    document.getElementById('createMeetingModal').style.display = 'block';
    document.getElementById('createMeetingModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

// Simple modal functionality to replace Bootstrap
function hideModal(modalEl) {
    if (!modalEl) return;
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    document.body.style.overflow = 'auto';
}

document.addEventListener('click', function(e) {
    // Botões com data-dismiss="modal" (ex: Cancelar ou X)
    const dismissBtn = e.target.matches('[data-dismiss="modal"]') ? e.target : e.target.closest('[data-dismiss="modal"]');
    if (dismissBtn) {
        const modal = dismissBtn.closest('.modal');
        hideModal(modal);
    }

    // Fechar ao clicar fora do conteúdo (na área de backdrop do próprio .modal)
    if (e.target.classList && e.target.classList.contains('modal')) {
        hideModal(e.target);
    }
});
</script>

<style>
.participants-manager {
    display: flex;
    gap: 2rem;
    min-height: 300px;
}

.add-participants-section,
.current-participants-section {
    flex: 1;
}

.add-participants-section h6,
.current-participants-section h6 {
    margin-bottom: 1rem;
    font-weight: 600;
    color: #333;
}

.participant-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0;
}

.participant-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.participant-name {
    font-weight: 500;
}

.no-participants, .error-message {
    text-align: center;
    padding: 2rem;
    color: #666;
    font-style: italic;
}

.error-message {
    color: #dc3545;
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 0.375rem;
    padding: 1rem;
}

.meeting-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.live-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    background-color: #dc3545;
    border-radius: 50%;
    animation: pulse 2s infinite;
    margin-left: 5px;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.3; }
    100% { opacity: 1; }
}

.dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    z-index: 1000;
    display: none;
    min-width: 200px;
    padding: 5px 0;
    margin: 2px 0 0;
    background-color: white;
    border: 1px solid rgba(0,0,0,.15);
    border-radius: 0.375rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: block;
    width: 100%;
    padding: 8px 16px;
    clear: both;
    font-weight: 400;
    color: #212529;
    text-align: inherit;
    text-decoration: none;
    white-space: nowrap;
    background-color: transparent;
    border: 0;
}

.dropdown-item:hover {
    color: #16181b;
    background-color: #f8f9fa;
}

.dropdown-item.text-error {
    color: #dc3545;
}

.dropdown-item.text-warning {
    color: #fd7e14;
}

.dropdown-divider {
    height: 0;
    margin: 4px 0;
    overflow: hidden;
    border-top: 1px solid #e9ecef;
}

.participants-list {
    max-height: 300px;
    overflow-y: auto;
}

.participant-item {
    padding: 8px;
    border-bottom: 1px solid #eee;
}

.meeting-room-container {
    display: flex;
    height: calc(100vh - 60px);
}

.video-grid {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 10px;
    padding: 20px;
    background-color: #1a1a1a;
    overflow-y: auto;
}

.video-container {
    position: relative;
    background-color: #2a2a2a;
    border-radius: 8px;
    overflow: hidden;
    aspect-ratio: 16/9;
}

.video-container video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.demo-participant {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
}

.video-name {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background-color: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.875rem;
}

.meeting-sidebar {
    width: 300px;
    background-color: #f8f9fa;
    border-left: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
}

.participants-panel {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    max-height: 40%;
    overflow-y: auto;
}

.chat-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 20px;
}

.chat-messages {
    flex: 1;
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px;
    margin-bottom: 10px;
    background-color: white;
}

.chat-message {
    margin-bottom: 10px;
    padding: 8px;
    border-radius: 4px;
    background-color: #f8f9fa;
}

.chat-message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
}

.chat-time {
    font-size: 0.75rem;
    color: #6c757d;
}

.chat-input {
    display: flex;
    gap: 10px;
}

.chat-input input {
    flex: 1;
}

.room-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.modal-fullscreen {
    width: 100vw;
    max-width: none;
    height: 100vh;
    margin: 0;
}

.modal-fullscreen .modal-content {
    height: 100vh;
    border: 0;
    border-radius: 0;
}

.participant-status {
    display: flex;
    gap: 5px;
}

.avatar-xs {
    width: 24px;
    height: 24px;
}

.avatar-sm {
    width: 32px;
    height: 32px;
}

.badge {
    padding: 0.25em 0.4em;
    font-size: 0.75em;
    font-weight: 600;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.375rem;
}

.badge-success {
    background-color: #198754;
}

.form-check {
    display: block;
    min-height: 1.5rem;
    padding-left: 1.5em;
    margin-bottom: 0.125rem;
}

.form-check-input {
    width: 1em;
    height: 1em;
    margin-top: 0.25em;
    margin-left: -1.5em;
    vertical-align: top;
    background-color: #fff;
    background-repeat: no-repeat;
    background-position: center;
    background-size: contain;
    border: 1px solid rgba(0, 0, 0, 0.25);
    border-radius: 0.25em;
}

.form-check-label {
    cursor: pointer;
}

.modal.fade .modal-dialog {
    transition: transform 0.3s ease-out;
    transform: translateY(-50px);
}

.modal.show .modal-dialog {
    transform: none;
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1040;
    width: 100vw;
    height: 100vh;
    background-color: #000;
    opacity: 0.5;
}

/* Better modal styling */
.modal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    z-index: 9999 !important;
    display: none !important;
    width: 100% !important;
    height: 100% !important;
    overflow-x: hidden;
    overflow-y: auto;
    outline: 0;
    background-color: rgba(0, 0, 0, 0.5) !important;
}

.modal.show {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.modal-dialog {
    position: relative;
    width: auto;
    max-width: 500px;
    margin: 1.75rem;
    pointer-events: none;
}

.modal-dialog.modal-lg {
    max-width: 800px;
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    pointer-events: auto;
    background-color: #fff !important;
    background-clip: padding-box;
    border: 1px solid rgba(0, 0, 0, 0.2);
    border-radius: 0.3rem;
    outline: 0;
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.5);
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    pointer-events: auto;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0, 0, 0, 0.2);
    border-radius: 0.3rem;
    outline: 0;
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 1rem 1rem;
    border-bottom: 1px solid #dee2e6;
    border-top-left-radius: calc(0.3rem - 1px);
    border-top-right-radius: calc(0.3rem - 1px);
    background-color: #fff;
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 1rem;
    background-color: #fff;
}

.modal-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    padding: 0.75rem;
    border-top: 1px solid #dee2e6;
    border-bottom-right-radius: calc(0.3rem - 1px);
    border-bottom-left-radius: calc(0.3rem - 1px);
    background-color: #fff;
}

.close {
    padding: 0;
    background-color: transparent;
    border: 0;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
    color: #000;
    text-shadow: 0 1px 0 #fff;
    opacity: 0.5;
    cursor: pointer;
}

.close:hover {
    color: #000;
    text-decoration: none;
    opacity: 0.75;
}

/* Força o modal a aparecer */
#participantsModal.show {
    display: flex !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    z-index: 99999 !important;
    background-color: rgba(0, 0, 0, 0.5) !important;
}

#participantsModal .modal-dialog {
    margin: auto !important;
}

#participantsModal .modal-content {
    background: white !important;
    border-radius: 8px !important;
    min-height: 400px !important;
}
</style>

<?php 
$additional_js = ['meetings'];
require_once '../includes/footer.php'; 
?>
