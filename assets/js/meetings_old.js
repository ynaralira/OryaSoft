/**
 * Meetings Module - Gerenciamento de Reuniões
 * ========================================
 * 
 * Este módulo é responsável pela funcionalidade de reuniões da plataforma Orya,
 * incluindo criação, edição, visualização em calendário e gerenciamento de participantes.
 */

class MeetingsModule {
    constructor() {
        this.currentView = 'list';
        this.currentMonth = new Date().getMonth();
        this.currentYear = new Date().getFullYear();
        this.meetings = [];
        this.formValidator = null;
        
        this.init();
    }
    
    /**
     * Inicializa o módulo
     */
    init() {
        this.setupEventListeners();
        this.setupFormValidation();
        this.loadMeetings();
        this.initializeDropdowns();
        
        console.log('Meetings Module initialized');
        this.checkAutoOpen();
    }
    
    /**
     * Verifica e abre automaticamente o modal de nova reunião se o parâmetro estiver na URL
     */
    checkAutoOpen() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('open') === 'new') {
            // atraso pequeno para garantir DOM pronto
            setTimeout(()=> this.openCreateMeetingModal(), 200);
        } else {
            // garantir que o modal esteja fechado
            const modal = document.getElementById('meeting-modal');
            if (modal) {
                modal.classList.remove('open');
                modal.style.display = 'none';
            }
        }
    }
    
    /**
     * Configura event listeners
     */
    setupEventListeners() {
        // Formulário de criação/edição de reunião
        const meetingForm = document.getElementById('meeting-form');
        if (meetingForm) {
            meetingForm.addEventListener('submit', (e) => this.handleMeetingFormSubmit(e));
        }
        
        // Filtros
        const statusFilter = document.getElementById('status-filter');
        const typeFilter = document.getElementById('type-filter');
        const periodFilter = document.getElementById('period-filter');
        
        if (statusFilter) statusFilter.addEventListener('change', () => this.filterMeetings());
        if (typeFilter) typeFilter.addEventListener('change', () => this.filterMeetings());
        if (periodFilter) periodFilter.addEventListener('change', () => this.filterMeetings());
        
        // Controle de duração personalizada
        const durationSelect = document.getElementById('duration');
        if (durationSelect) {
            durationSelect.addEventListener('change', (e) => this.handleDurationChange(e));
        }
        
        // Teclas de atalho
        document.addEventListener('keydown', (e) => this.handleKeyboardShortcuts(e));
        
        // Fechar modal ao clicar fora
        const modal = document.getElementById('meeting-modal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeMeetingModal();
                }
            });
        }
    }
    
    /**
     * Configura validação do formulário
     */
    setupFormValidation() {
        this.formValidator = {
            rules: {
                title: {
                    required: true,
                    minlength: 3,
                    maxlength: 200
                },
                start_date: {
                    required: true,
                    date: true,
                    minDate: new Date()
                },
                start_time: {
                    required: true
                },
                meeting_url: {
                    required: true,
                    url: true
                },
                max_participants: {
                    min: 2,
                    max: 500
                }
            },
            messages: {
                title: {
                    required: 'O título da reunião é obrigatório',
                    minlength: 'O título deve ter pelo menos 3 caracteres',
                    maxlength: 'O título deve ter no máximo 200 caracteres'
                },
                start_date: {
                    required: 'A data é obrigatória',
                    minDate: 'A data não pode ser no passado'
                },
                start_time: {
                    required: 'O horário de início é obrigatório'
                },
                meeting_url: {
                    required: 'O link da reunião é obrigatório',
                    url: 'Digite um link válido'
                }
            }
        };
    }
    
    /**
     * Carrega reuniões via AJAX
     */
    async loadMeetings() {
        try {
            const baseUrl = window.BASE_URL || 'http://localhost/Orya';
            const response = await fetch(`${baseUrl}/api/meetings.php`);
            if (!response.ok) {
                const text = await response.text();
                console.error('Resposta meetings:', text);
                throw new Error('Erro ao carregar reuniões');
            }
            const data = await response.json();
            if (data.success) {
                this.meetings = data.meetings;
                this.updateMeetingsDisplay();
            } else {
                this.showNotification('Erro ao carregar reuniões', 'error');
            }
        } catch (error) {
            console.error('Erro ao carregar reuniões:', error);
            this.showNotification('Erro de conexão ao carregar reuniões', 'error');
        }
    }
    
    /**
     * Atualiza a exibição das reuniões
     */
    updateMeetingsDisplay() {
        if (this.currentView === 'list') {
            this.updateListView();
        } else {
            this.updateCalendarView();
        }
    }
    
    /**
     * Atualiza visualização em lista
     */
    updateListView() {
        const container = document.getElementById('meetings-list');
        if (!container) return;
        
        if (this.meetings.length === 0) {
            container.innerHTML = this.getEmptyStateHTML();
            return;
        }
        
        container.innerHTML = this.meetings.map(meeting => this.getMeetingCardHTML(meeting)).join('');
        this.attachCardEventListeners();
    }
    
    /**
     * Gera HTML para card de reunião
     */
    getMeetingCardHTML(meeting) {
        return `
            <div class="meeting-card" data-meeting-id="${meeting.id}">
                <div class="card-header">
                    <div class="meeting-header">
                        <h3 class="meeting-title">${this.escapeHtml(meeting.title)}</h3>
                        <span class="meeting-status ${meeting.status}">
                            ${this.getStatusLabel(meeting.status)}
                        </span>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="meeting-info">
                        <div class="meeting-info-item">
                            <i class="fas fa-calendar"></i>
                            <span>${this.formatDate(meeting.start_datetime)}</span>
                        </div>
                        <div class="meeting-info-item">
                            <i class="fas fa-clock"></i>
                            <span>
                                ${this.formatTime(meeting.start_datetime)} - 
                                ${this.formatTime(meeting.end_datetime)}
                            </span>
                        </div>
                        <div class="meeting-info-item">
                            <i class="fas fa-user"></i>
                            <span>Criado por ${this.escapeHtml(meeting.creator_name)}</span>
                        </div>
                    </div>
                    
                    ${meeting.description ? `
                        <div class="meeting-description">
                            ${this.escapeHtml(meeting.description)}
                        </div>
                    ` : ''}
                    
                    <div class="meeting-participants">
                        <div class="participants-info">
                            <i class="fas fa-users"></i>
                            <span class="participants-count">
                                ${meeting.participant_count} participante${meeting.participant_count !== 1 ? 's' : ''}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    ${this.getMeetingActionsHTML(meeting)}
                </div>
            </div>
        `;
    }
    
    /**
     * Gera HTML para ações da reunião
     */
    getMeetingActionsHTML(meeting) {
        let actions = '<div class="meeting-actions">';
        
        if (meeting.status === 'upcoming') {
            actions += `
                <button class="btn btn-sm btn-outline" onclick="meetingsModule.editMeeting(${meeting.id})">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button class="btn btn-sm btn-outline" onclick="meetingsModule.shareMeeting(${meeting.id})">
                    <i class="fas fa-share"></i> Compartilhar
                </button>
            `;
        }
        
        if (meeting.status === 'live' || meeting.status === 'upcoming') {
            actions += `
                <a href="${this.escapeHtml(meeting.meeting_url)}" 
                   class="btn btn-sm btn-primary" 
                   target="_blank">
                    <i class="fas fa-video"></i>
                    ${meeting.status === 'live' ? 'Entrar' : 'Link da Reunião'}
                </a>
            `;
        }
        
        actions += `
            <div class="dropdown">
                <button class="btn btn-sm btn-outline dropdown-toggle" 
                        onclick="meetingsModule.toggleDropdown(${meeting.id})">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu" id="dropdown-${meeting.id}">
                    <a href="#" class="dropdown-item" 
                       onclick="meetingsModule.viewMeetingDetails(${meeting.id})">
                        <i class="fas fa-eye"></i> Ver Detalhes
                    </a>
        `;
        
        if (meeting.is_creator) {
            actions += `
                <a href="#" class="dropdown-item" 
                   onclick="meetingsModule.manageMeetingParticipants(${meeting.id})">
                    <i class="fas fa-users-cog"></i> Gerenciar Participantes
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item text-error" 
                   onclick="meetingsModule.deleteMeeting(${meeting.id})">
                    <i class="fas fa-trash"></i> Excluir Reunião
                </a>
            `;
        } else {
            actions += `
                <a href="#" class="dropdown-item text-warning" 
                   onclick="meetingsModule.leaveMeeting(${meeting.id})">
                    <i class="fas fa-sign-out-alt"></i> Sair da Reunião
                </a>
            `;
        }
        
        actions += '</div></div></div>';
        return actions;
    }
    
    /**
     * Alterna visualização entre lista e calendário
     */
    toggleCalendarView() {
        const listView = document.getElementById('meetings-list');
        const calendarView = document.getElementById('calendar-view');
        
        if (this.currentView === 'list') {
            listView.style.display = 'none';
            calendarView.style.display = 'block';
            this.currentView = 'calendar';
            this.initializeCalendar();
        } else {
            listView.style.display = 'block';
            calendarView.style.display = 'none';
            this.currentView = 'list';
        }
    }
    
    /**
     * Inicializa o calendário
     */
    initializeCalendar() {
        this.updateCalendarHeader();
        this.generateCalendarGrid();
    }
    
    /**
     * Atualiza cabeçalho do calendário
     */
    updateCalendarHeader() {
        const monthNames = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];
        
        const monthElement = document.getElementById('calendar-month');
        if (monthElement) {
            monthElement.textContent = `${monthNames[this.currentMonth]} ${this.currentYear}`;
        }
    }
    
    /**
     * Muda mês do calendário
     */
    changeMonth(direction) {
        this.currentMonth += direction;
        if (this.currentMonth < 0) {
            this.currentMonth = 11;
            this.currentYear--;
        } else if (this.currentMonth > 11) {
            this.currentMonth = 0;
            this.currentYear++;
        }
        this.updateCalendarHeader();
        this.generateCalendarGrid();
    }
    
    /**
     * Gera grid do calendário
     */
    generateCalendarGrid() {
        const grid = document.getElementById('calendar-grid');
        if (!grid) return;
        
        grid.innerHTML = '';
        
        // Cabeçalhos dos dias
        const dayHeaders = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        dayHeaders.forEach(day => {
            const header = document.createElement('div');
            header.className = 'calendar-day-header';
            header.textContent = day;
            grid.appendChild(header);
        });
        
        // Gerar dias do mês
        const firstDay = new Date(this.currentYear, this.currentMonth, 1);
        const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());
        
        for (let i = 0; i < 42; i++) {
            const cellDate = new Date(startDate);
            cellDate.setDate(startDate.getDate() + i);
            
            const dayCell = document.createElement('div');
            dayCell.className = 'calendar-day';
            
            if (cellDate.getMonth() !== this.currentMonth) {
                dayCell.classList.add('other-month');
            }
            
            if (this.isToday(cellDate)) {
                dayCell.classList.add('today');
            }
            
            // Buscar reuniões do dia
            const dayMeetings = this.getMeetingsForDate(cellDate);
            
            dayCell.innerHTML = `
                <div class="calendar-day-number">${cellDate.getDate()}</div>
                <div class="calendar-day-meetings">
                    ${dayMeetings.map(meeting => `
                        <div class="calendar-meeting ${meeting.status}" 
                             onclick="meetingsModule.viewMeetingDetails(${meeting.id})"
                             title="${this.escapeHtml(meeting.title)}">
                            ${this.formatTime(meeting.start_datetime)} - ${this.escapeHtml(meeting.title)}
                        </div>
                    `).join('')}
                </div>
            `;
            
            // Adicionar evento de clique para criar reunião
            dayCell.addEventListener('click', (e) => {
                if (e.target === dayCell || e.target.className === 'calendar-day-number') {
                    this.openCreateMeetingModal(cellDate);
                }
            });
            
            grid.appendChild(dayCell);
        }
    }
    
    /**
     * Busca reuniões para uma data específica
     */
    getMeetingsForDate(date) {
        return this.meetings.filter(meeting => {
            const meetingDate = new Date(meeting.start_datetime);
            return meetingDate.toDateString() === date.toDateString();
        });
    }
    
    /**
     * Verifica se é hoje
     */
    isToday(date) {
        const today = new Date();
        return date.getDate() === today.getDate() &&
               date.getMonth() === today.getMonth() &&
               date.getFullYear() === today.getFullYear();
    }
    
    /**
     * Abre modal para criar reunião
     */
    openCreateMeetingModal(selectedDate = null) {
        const modal = document.getElementById('meeting-modal');
        // Mover overlay para <body> para evitar efeitos de transform/overflow em ancestrais
        if (modal && modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }
        const form = document.getElementById('meeting-form');
        const title = document.getElementById('modal-title');
        if (!modal || !form) return;
        
        title.textContent = 'Nova Reunião';
        form.reset();
        document.getElementById('meeting-id').value = '';
        
        const dateInput = document.getElementById('start-date');
        const timeInput = document.getElementById('start-time');
        if (selectedDate) {
            dateInput.value = this.formatDateForInput(selectedDate);
        } else {
            const today = new Date();
            dateInput.value = this.formatDateForInput(today);
        }
        dateInput.min = this.formatDateForInput(new Date());
        const now = new Date();
        now.setHours(now.getHours() + 1, 0, 0, 0);
        timeInput.value = now.toTimeString().slice(0, 5);
        
        // abrir usando classe .open
        modal.style.display = 'flex';
        modal.classList.add('open');
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        modal.style.minHeight = '100vh';
        const innerModal = modal.querySelector('.modal');
        if (innerModal) {
            Object.assign(innerModal.style, {
                position: 'relative', top: 'auto', left: 'auto', right: 'auto', bottom: 'auto',
                width: '100%', maxWidth: '640px', height: 'auto', maxHeight: '90vh',
                margin: '0 auto', display: 'flex', flexDirection: 'column'
            });
        }
        setTimeout(() => { document.getElementById('meeting-title').focus(); }, 100);
    }
    
    /**
     * Fecha modal de reunião
     */
    closeMeetingModal() {
        const modal = document.getElementById('meeting-modal');
        if (modal) {
            modal.classList.remove('open');
            modal.style.display = 'none';
            const innerModal = modal.querySelector('.modal');
            if (innerModal) innerModal.style.display = '';
        }
    }
    
    /**
     * Manipula envio do formulário de reunião
     */
    async handleMeetingFormSubmit(event) {
        event.preventDefault();
        const form = event.target;
        if (form.dataset.submitting === '1') { return; }
        form.dataset.submitting = '1';
        try {
            const formData = new FormData(form);
            // Captura botão dentro do form OU o que usa atributo form="meeting-form" fora dele
            let submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn) {
                submitBtn = document.querySelector(`button[type="submit"][form="${form.id}"]`);
            }
            // Validar formulário
            if (!this.validateMeetingForm(form)) {
                return;
            }
            this.setButtonLoading(submitBtn, true);
            try {
                const response = await fetch(form.action, { method: 'POST', body: formData });
                let data;
                const text = await response.text();
                try { data = JSON.parse(text); } catch (e) { data = { success:false, message:'Resposta inválida do servidor', raw:text }; }
                if (!response.ok) {
                    console.error('Erro HTTP meetings POST', response.status, data);
                }
                if (data.success) {
                    this.showNotification('Reunião salva com sucesso!', 'success');
                    this.closeMeetingModal();
                    this.loadMeetings();
                } else {
                    this.showNotification(data.message || 'Erro ao salvar reunião', 'error');
                }
            } catch (error) {
                console.error('Erro ao salvar reunião:', error);
                this.showNotification('Erro de conexão', 'error');
            } finally {
                this.setButtonLoading(submitBtn, false);
            }
        } finally {
            delete form.dataset.submitting;
        }
    }
    
    /**
     * Valida formulário de reunião
     */
    validateMeetingForm(form) {
        let isValid = true;
        const errors = [];
        
        // Validar campos obrigatórios
        const requiredFields = ['meeting-title', 'start-date', 'start-time', 'meeting-url'];
        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!field.value.trim()) {
                isValid = false;
                errors.push(`${field.labels[0].textContent} é obrigatório`);
                field.classList.add('error');
            } else {
                field.classList.remove('error');
            }
        });
        
        // Validar URL
        const urlField = document.getElementById('meeting-url');
        if (urlField.value && !this.isValidUrl(urlField.value)) {
            isValid = false;
            errors.push('Link da reunião deve ser uma URL válida');
            urlField.classList.add('error');
        }
        
        // Validar data e hora
        const dateField = document.getElementById('start-date');
        const timeField = document.getElementById('start-time');
        
        if (dateField.value && timeField.value) {
            const meetingDateTime = new Date(`${dateField.value}T${timeField.value}`);
            const now = new Date();
            
            if (meetingDateTime <= now) {
                isValid = false;
                errors.push('Data e hora devem ser no futuro');
                dateField.classList.add('error');
                timeField.classList.add('error');
            }
        }
        
        if (errors.length > 0) {
            this.showNotification(errors.join('<br>'), 'error');
        }
        
        return isValid;
    }
    
    /**
     * Manipula mudança de duração
     */
    handleDurationChange(event) {
        const endTimeGroup = document.getElementById('end-time-group');
        const endTimeInput = document.getElementById('end-time');
        
        if (event.target.value === 'custom') {
            endTimeGroup.style.display = 'block';
            endTimeInput.required = true;
        } else {
            endTimeGroup.style.display = 'none';
            endTimeInput.required = false;
            
            // Calcular hora de término automaticamente
            const startTime = document.getElementById('start-time').value;
            if (startTime) {
                const duration = parseInt(event.target.value);
                const [hours, minutes] = startTime.split(':');
                const startDate = new Date();
                startDate.setHours(parseInt(hours), parseInt(minutes), 0, 0);
                startDate.setMinutes(startDate.getMinutes() + duration);
                
                endTimeInput.value = startDate.toTimeString().slice(0, 5);
            }
        }
    }
    
    /**
     * Filtra reuniões
     */
    filterMeetings() {
        const status = document.getElementById('status-filter').value;
        const type = document.getElementById('type-filter').value;
        const period = document.getElementById('period-filter').value;
        
        const url = new URL(window.location);
        url.searchParams.set('status', status);
        url.searchParams.set('type', type);
        url.searchParams.set('period', period);
        
        window.location.href = url.toString();
    }
    
    /**
     * Edita reunião
     */
    async editMeeting(meetingId) {
        try {
            const response = await fetch(`../api/meetings.php?id=${meetingId}`);
            const data = await response.json();
            
            if (data.success) {
                this.populateMeetingForm(data.meeting);
                document.getElementById('modal-title').textContent = 'Editar Reunião';
                document.getElementById('meeting-modal').style.display = 'flex';
            } else {
                this.showNotification('Erro ao carregar dados da reunião', 'error');
            }
        } catch (error) {
            console.error('Erro ao editar reunião:', error);
            this.showNotification('Erro de conexão', 'error');
        }
    }
    
    /**
     * Popula formulário com dados da reunião
     */
    populateMeetingForm(meeting) {
        document.getElementById('meeting-id').value = meeting.id;
        document.getElementById('meeting-title').value = meeting.title;
        document.getElementById('meeting-description').value = meeting.description || '';
        document.getElementById('meeting-url').value = meeting.meeting_url;
        document.getElementById('max-participants').value = meeting.max_participants;
        
        const startDate = new Date(meeting.start_datetime);
        const endDate = new Date(meeting.end_datetime);
        
        document.getElementById('start-date').value = this.formatDateForInput(startDate);
        document.getElementById('start-time').value = this.formatTimeForInput(startDate);
        document.getElementById('end-time').value = this.formatTimeForInput(endDate);
        
        // Calcular duração
        const durationMinutes = (endDate - startDate) / (1000 * 60);
        const durationSelect = document.getElementById('duration');
        
        if ([30, 60, 90, 120, 180].includes(durationMinutes)) {
            durationSelect.value = durationMinutes.toString();
        } else {
            durationSelect.value = 'custom';
            document.getElementById('end-time-group').style.display = 'block';
            document.getElementById('end-time').required = true;
        }
    }
    
    /**
     * Exclui reunião
     */
    async deleteMeeting(meetingId) {
        if (!confirm('Tem certeza que deseja excluir esta reunião? Esta ação não pode ser desfeita.')) {
            return;
        }
        
        try {
            const response = await fetch('../api/meetings.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ meeting_id: meetingId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Reunião excluída com sucesso', 'success');
                this.loadMeetings();
            } else {
                this.showNotification(data.message || 'Erro ao excluir reunião', 'error');
            }
        } catch (error) {
            console.error('Erro ao excluir reunião:', error);
            this.showNotification('Erro de conexão', 'error');
        }
    }
    
    /**
     * Compartilha reunião
     */
    shareMeeting(meetingId) {
        const meeting = this.meetings.find(m => m.id === meetingId);
        if (!meeting) return;
        
        const shareText = `Você foi convidado(a) para a reunião: ${meeting.title}\n\nData: ${this.formatDate(meeting.start_datetime)}\nHorário: ${this.formatTime(meeting.start_datetime)}\n\nLink: ${meeting.meeting_url}`;
        
        if (navigator.share) {
            navigator.share({
                title: meeting.title,
                text: shareText,
                url: meeting.meeting_url
            });
        } else {
            // Fallback: copiar para clipboard
            navigator.clipboard.writeText(shareText).then(() => {
                this.showNotification('Link da reunião copiado para a área de transferência', 'success');
            });
        }
    }
    
    /**
     * Visualiza detalhes da reunião
     */
    viewMeetingDetails(meetingId) {
        // Implementar modal de detalhes
        console.log('Ver detalhes da reunião:', meetingId);
    }
    
    /**
     * Gerencia participantes da reunião
     */
    manageMeetingParticipants(meetingId) {
        console.log('🎯 INICIANDO manageMeetingParticipants - ID:', meetingId);
        
        this.currentMeetingId = meetingId;
        window.currentMeetingId = meetingId;

        const modal = document.getElementById('participantsModal');
        
        console.log('Modal elemento:', modal);
        console.log('Modal existe?', !!modal);
        
        if (!modal) {
            alert('❌ ERRO: Modal não encontrado!');
            return;
        }
        
        // Mostrar modal com animação
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        console.log('✅ Modal exibido com sucesso!');
        
        // Carregar dados
        this.loadCurrentParticipants(meetingId);
        this.initializeParticipantsDropdown();
    }

    /**
     * Carrega participantes atuais da reunião
     */
    loadCurrentParticipants(meetingId) {
        const baseUrl = window.location.origin;
        const apiUrl = `${baseUrl}/Orya/api/meeting-participants.php?meeting_id=${meetingId}`;
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
                
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status} - ${response.statusText}`);
                }
                return response.json();
            })
            .then(participants => {
                console.log('Participantes recebidos:', participants);
                
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
                                    onclick="meetingsModule.removeParticipant(${meetingId}, ${participant.id})">
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

    /**
     * Inicializa dropdown de participantes
     */
    initializeParticipantsDropdown() {
        const baseUrl = window.location.origin;
        const apiUrl = `${baseUrl}/Orya/api/users.php`;
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

    /**
     * Fecha o modal de participantes
     */
    closeParticipantsModal() {
        console.log('🚪 Fechando modal de participantes');
        
        const modal = document.getElementById('participantsModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
            console.log('✅ Modal fechado com sucesso');
        }
        
        // Limpar variável global
        this.currentMeetingId = null;
        window.currentMeetingId = null;
    }

    /**
     * Adiciona participantes à reunião
     */
    addParticipants() {
        const select = document.getElementById('add_participants');
        const selectedOptions = Array.from(select.selectedOptions);
        
        if (selectedOptions.length === 0) {
            alert('Selecione pelo menos um participante');
            return;
        }
        
        const userIds = selectedOptions.map(option => option.value);
        const baseUrl = window.location.origin;
        const apiUrl = `${baseUrl}/Orya/api/meeting-participants.php`;
        
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                meeting_id: this.currentMeetingId,
                user_ids: userIds
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadCurrentParticipants(this.currentMeetingId);
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

    /**
     * Remove participante da reunião
     */
    removeParticipant(meetingId, userId) {
        if (confirm('Remover este participante da reunião?')) {
            const baseUrl = window.location.origin;
            const apiUrl = `${baseUrl}/Orya/api/meeting-participants.php`;
            
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
                    this.loadCurrentParticipants(meetingId);
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
    
    /**
     * Outras funcionalidades de reunião
     */
    editMeeting(meetingId) {
        // Implementar edição de reunião
        console.log('Editar reunião:', meetingId);
        alert('Funcionalidade de edição em desenvolvimento');
    }
    
    deleteMeeting(meetingId) {
        if (confirm('Tem certeza que deseja excluir esta reunião?')) {
            console.log('🗑️ Excluindo reunião:', meetingId);
            
            const baseUrl = window.location.origin;
            const apiUrl = `${baseUrl}/Orya/api/meetings.php`;
            
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
    
    toggleDropdown(meetingId) {
        const dropdown = document.getElementById(`dropdown-${meetingId}`);
        if (dropdown) {
            dropdown.classList.toggle('show');
            
            // Fechar outros dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu.id !== `dropdown-${meetingId}`) {
                    menu.classList.remove('show');
                }
            });
        }
    }
    
    viewMeetingDetails(meetingId) {
        console.log('Ver detalhes da reunião:', meetingId);
        alert('Funcionalidade de detalhes em desenvolvimento');
    }
    
    leaveMeeting(meetingId) {
        if (confirm('Tem certeza que deseja sair desta reunião?')) {
            console.log('Sair da reunião:', meetingId);
            alert('Funcionalidade em desenvolvimento');
        }
    }
    
    joinMeetingRoom(meetingId) {
        console.log('Entrar na sala de reunião:', meetingId);
        alert('Funcionalidade de sala de reunião em desenvolvimento');
    }
                        div.className = 'participant-item';
                        div.innerHTML = `
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <img src="${(p.avatar || defAvatar)}" alt="${this.escapeHtml(p.name || '')}" class="avatar-sm rounded-circle me-2">
                                    <span>${this.escapeHtml(p.name || '')}</span>
                                    ${p.is_online ? '<span class="badge badge-success" style="margin-left:8px;">Online</span>' : ''}
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeParticipant(${meetingId}, ${p.id})">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>`;
                        listEl.appendChild(div);
                    });
                })
                .catch(() => { if (listEl) listEl.innerHTML = '<div class="participant-item">Falha ao carregar participantes</div>'; });

            // Popular seletor de adicionar participantes
            const addSel = document.getElementById('add_participants');
            if (addSel) {
                addSel.innerHTML = '';
                fetch(`${baseUrl}/api/users.php`)
                    .then(r => r.json())
                    .then(users => {
                        (users || []).forEach(u => {
                            const opt = new Option(u.name, u.id);
                            addSel.add(opt);
                        });
                    })
                    .catch(() => {/* silencioso */});
            }
        } catch (e) {
            console.error('Erro ao abrir participantes:', e);
            this.showNotification('Erro ao abrir participantes', 'error');
        }
    }
    
    /**
     * Sai da reunião
     */
    async leaveMeeting(meetingId) {
        if (!confirm('Tem certeza que deseja sair desta reunião?')) {
            return;
        }
        
        try {
            const response = await fetch('../api/meetings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'leave',
                    meeting_id: meetingId 
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Você saiu da reunião', 'success');
                this.loadMeetings();
            } else {
                this.showNotification(data.message || 'Erro ao sair da reunião', 'error');
            }
        } catch (error) {
            console.error('Erro ao sair da reunião:', error);
            this.showNotification('Erro de conexão', 'error');
        }
    }
    
    /**
     * Manipula atalhos de teclado
     */
    handleKeyboardShortcuts(event) {
        // Ctrl/Cmd + N: Nova reunião
        if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
            event.preventDefault();
            this.openCreateMeetingModal();
        }
        
        // Escape: Fechar modal
        if (event.key === 'Escape') {
            this.closeMeetingModal();
        }
        
        // Ctrl/Cmd + F: Focar no filtro
        if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
            event.preventDefault();
            document.getElementById('status-filter').focus();
        }
    }
    
    /**
     * Inicializa dropdowns
     */
    initializeDropdowns() {
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                // Fechar todos os dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });
    }
    
    /**
     * Alterna dropdown
     */
    toggleDropdown(meetingId) {
        const dropdown = document.getElementById(`dropdown-${meetingId}`);
        if (dropdown) {
            const isVisible = dropdown.style.display === 'block';
            
            // Fechar todos os dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
            
            // Alternar o dropdown atual
            dropdown.style.display = isVisible ? 'none' : 'block';
        }
    }
    
    /**
     * Anexa event listeners aos cards
     */
    attachCardEventListeners() {
        // Hover effects
        document.querySelectorAll('.meeting-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
    }
    
    /**
     * Utilitários
     */
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR');
    }
    
    formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }
    
    formatDateForInput(date) {
        return date.toISOString().split('T')[0];
    }
    
    formatTimeForInput(date) {
        return date.toTimeString().slice(0, 5);
    }
    
    getStatusLabel(status) {
        const labels = {
            'upcoming': 'Próxima',
            'live': 'Ao Vivo',
            'ended': 'Finalizada'
        };
        return labels[status] || status;
    }
    
    getEmptyStateHTML() {
        return `
            <div class="empty-state">
                <i class="fas fa-video"></i>
                <h3>Nenhuma reunião encontrada</h3>
                <p>Você ainda não tem reuniões agendadas ou participou de alguma.</p>
                <button class="btn btn-primary" onclick="meetingsModule.openCreateMeetingModal()">
                    <i class="fas fa-plus"></i>
                    Agendar Primeira Reunião
                </button>
            </div>
        `;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
    
    setButtonLoading(button, loading) {
        if (!button) return; // proteção caso botão esteja fora do form e não encontrado
        const btnText = button.querySelector('.btn-text');
        const btnLoading = button.querySelector('.btn-loading');
        if (!btnText || !btnLoading) {
            button.disabled = !!loading;
            return;
        }
        if (loading) {
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-block';
            button.disabled = true;
        } else {
            btnText.style.display = 'inline-block';
            btnLoading.style.display = 'none';
            button.disabled = false;
        }
    }
    
    showNotification(message, type = 'info') {
        // Usar o sistema de notificações global
        if (window.showNotification) {
            window.showNotification(message, type);
        } else {
            // Fallback
            alert(message);
        }
    }
}

// Expor funções globais necessárias
window.toggleCalendarView = () => window.meetingsModule?.toggleCalendarView();
window.openCreateMeetingModal = () => window.meetingsModule?.openCreateMeetingModal();
window.closeMeetingModal = () => window.meetingsModule?.closeMeetingModal();
window.changeMonth = (direction) => window.meetingsModule?.changeMonth(direction);
window.filterMeetings = () => window.meetingsModule?.filterMeetings();
window.editMeeting = (id) => window.meetingsModule?.editMeeting(id);
window.deleteMeeting = (id) => window.meetingsModule?.deleteMeeting(id);
window.manageMeetingParticipants = (id) => window.meetingsModule?.manageMeetingParticipants(id);
window.closeParticipantsModal = () => window.meetingsModule?.closeParticipantsModal();
window.addParticipants = () => window.meetingsModule?.addParticipants();
window.removeParticipant = (meetingId, userId) => window.meetingsModule?.removeParticipant(meetingId, userId);
window.toggleDropdown = (id) => window.meetingsModule?.toggleDropdown(id);
window.viewMeetingDetails = (id) => window.meetingsModule?.viewMeetingDetails(id);
window.leaveMeeting = (id) => window.meetingsModule?.leaveMeeting(id);
window.joinMeetingRoom = (id) => window.meetingsModule?.joinMeetingRoom(id);

// Inicializar quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.meetingsModule = new MeetingsModule();
    });
} else {
    window.meetingsModule = new MeetingsModule();
}
