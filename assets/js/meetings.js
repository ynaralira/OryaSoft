/**
 * Meetings Module - Gerenciamento de Reuniões
 * ========================================
 */

class MeetingsModule {
    constructor() {
        this.currentView = 'list';
        this.currentMonth = new Date().getMonth();
        this.currentYear = new Date().getFullYear();
        this.currentMeetingId = null;
        
        this.init();
    }
    
    init() {
        console.log('Meetings Module initialized');

        // Adicionar evento para fechar dropdowns ao clicar fora
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                console.log('🔒 Fechando todos os dropdowns (clique fora)');
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });

        // Interceptar submit do formulário de reunião para AJAX
        const meetingForm = document.getElementById('meeting-form');
        if (meetingForm) {
            meetingForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = meetingForm.querySelector('button[type="submit"]');
                const btnText = btn.querySelector('.btn-text');
                const btnLoading = btn.querySelector('.btn-loading');
                if (btnText && btnLoading) {
                    btnText.style.display = 'none';
                    btnLoading.style.display = 'inline-block';
                }
                const formData = new FormData(meetingForm);
                try {
                    const response = await fetch(meetingForm.action, {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    if (data.success) {
                        // Fechar modal e redirecionar para a lista de reuniões
                        meetingsModule.closeMeetingModal();
                        window.location.href = '../pages/meetings.php';
                    } else {
                        alert(data.message || 'Erro ao criar reunião.');
                    }
                } catch (err) {
                    alert('Erro ao criar reunião.');
                } finally {
                    if (btnText && btnLoading) {
                        btnText.style.display = 'inline-block';
                        btnLoading.style.display = 'none';
                    }
                }
            });
        }
    }
    
    /**
     * Filtrar reuniões
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
     * Alternar visualização
     */
    toggleCalendarView() {
        const listView = document.getElementById('meetings-list');
        const calendarView = document.getElementById('calendar-view');
        if (this.currentView === 'list') {
            if (listView) listView.style.display = 'none';
            if (calendarView) calendarView.style.display = 'block';
            this.currentView = 'calendar';
            this.renderCalendar();
        } else {
            if (listView) listView.style.display = 'block';
            if (calendarView) calendarView.style.display = 'none';
            this.currentView = 'list';
        }
    }

    async renderCalendar() {
        // Atualiza o header do mês
        const monthNames = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];
        const monthEl = document.getElementById('calendar-month');
        if (monthEl) {
            monthEl.textContent = `${monthNames[this.currentMonth]} ${this.currentYear}`;
        }
        // Buscar reuniões do mês
        let meetingsByDay = {};
        try {
            const baseUrl = window.location.origin;
            const start = `${this.currentYear}-${String(this.currentMonth+1).padStart(2,'0')}-01`;
            const endDate = new Date(this.currentYear, this.currentMonth + 1, 0);
            const end = `${this.currentYear}-${String(this.currentMonth+1).padStart(2,'0')}-${String(endDate.getDate()).padStart(2,'0')}`;
            const url = `../api/meetings.php?period=custom&start=${start}&end=${end}`;
            const resp = await fetch(url);
            const data = await resp.json();
            if (data.success && Array.isArray(data.meetings)) {
                data.meetings.forEach(m => {
                    const d = (m.start_date || m.start_datetime || '').split('T')[0];
                    if (d) {
                        const day = parseInt(d.split('-')[2]);
                        if (!meetingsByDay[day]) meetingsByDay[day] = [];
                        meetingsByDay[day].push(m);
                    }
                });
            }
        } catch (e) {
            // Se erro, apenas não mostra reuniões
        }
        this.generateCalendarGrid(meetingsByDay);
    }

    generateCalendarGrid(meetingsByDay = {}) {
        const grid = document.getElementById('calendar-grid');
        if (!grid) return;
        grid.innerHTML = '';
        // Cabeçalho dos dias da semana
        const weekDays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        const headerRow = document.createElement('div');
        headerRow.className = 'calendar-row calendar-header-row';
        weekDays.forEach(day => {
            const cell = document.createElement('div');
            cell.className = 'calendar-cell calendar-day-header';
            cell.textContent = day;
            headerRow.appendChild(cell);
        });
        grid.appendChild(headerRow);
        // Dias do mês
        const firstDay = new Date(this.currentYear, this.currentMonth, 1);
        const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
        const startDay = firstDay.getDay();
        const totalDays = lastDay.getDate();
        let day = 1;
        const today = new Date();
        const isCurrentMonth = today.getFullYear() === this.currentYear && today.getMonth() === this.currentMonth;
        for (let row = 0; row < 6; row++) {
            const weekRow = document.createElement('div');
            weekRow.className = 'calendar-row';
            for (let col = 0; col < 7; col++) {
                const cell = document.createElement('div');
                cell.className = 'calendar-cell';
                if ((row === 0 && col < startDay) || day > totalDays) {
                    cell.classList.add('empty');
                    cell.innerHTML = '&nbsp;';
                } else {
                    cell.classList.add('calendar-day');
                    cell.textContent = day;
                    if (isCurrentMonth && day === today.getDate()) {
                        cell.classList.add('today');
                    }
                    // Mostrar reuniões do dia
                    if (meetingsByDay[day]) {
                        meetingsByDay[day].forEach(m => {
                            const evt = document.createElement('div');
                            evt.className = 'calendar-meeting';
                            evt.title = m.title;
                            evt.textContent = m.title.length > 16 ? m.title.slice(0, 16) + '…' : m.title;
                            evt.onclick = (e) => {
                                e.stopPropagation();
                                alert(`Reunião: ${m.title}\nInício: ${m.start_time || m.start_datetime || ''}`);
                            };
                            cell.appendChild(evt);
                        });
                        cell.classList.add('has-meeting');
                    }
                    cell.onclick = () => {
                        if (meetingsByDay[day]) {
                            let msg = meetingsByDay[day].map(m => `• ${m.title} (${m.start_time || m.start_datetime || ''})`).join('\n');
                            alert(`Reuniões do dia ${day}:\n` + msg);
                        }
                    };
                    day++;
                }
                weekRow.appendChild(cell);
            }
            grid.appendChild(weekRow);
            if (day > totalDays) break;
        }
    }
    
    /**
     * Mudar mês no calendário
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
        if (this.currentView === 'calendar') {
            this.renderCalendar();
        }
    }
    
    /**
     * Modal de reunião
     */
    openCreateMeetingModal() {
        const modal = document.getElementById('meeting-modal');
        if (modal) {
            // Resetar formulário
            const form = document.getElementById('meeting-form');
            if (form) form.reset();
            document.getElementById('meeting-id').value = '';
            document.getElementById('modal-title').innerText = 'Nova Reunião';
            document.getElementById('end-time-group').style.display = 'none';
            modal.style.display = 'flex';
        }
    }
    
    closeMeetingModal() {
        const modal = document.getElementById('meeting-modal');
        if (modal) {
            modal.style.display = 'none';
        }
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
        const apiUrl = `../api/meeting-participants.php?meeting_id=${meetingId}`;
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
                    
                    const avatarUrl = participant.avatar || '../assets/images/default-avatar.svg';
                    const isOnline = participant.is_online ? '<span class="badge badge-success">Online</span>' : '';
                    
                    participantDiv.innerHTML = `
                        <div class="participant-row">
                            <div class="participant-info">
                                <img src="${avatarUrl}" 
                                     alt="${participant.name}" 
                                     class="avatar-sm"
                                     style="width: 32px; height: 32px; border-radius: 50%; margin-right: 10px;"
                                     onerror="this.src='../assets/images/default-avatar.svg'">
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
        const apiUrl = `../api/users.php`;
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
        const apiUrl = `../api/meeting-participants.php`;
        
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
            const apiUrl = `../api/meeting-participants.php`;
            
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
        console.log('Editar reunião:', meetingId);
        // Buscar dados da reunião via AJAX
        const baseUrl = window.location.origin;
        const apiUrl = `../api/meetings.php?id=${meetingId}`;
        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.meeting) {
                    // Preencher o formulário
                    document.getElementById('meeting-id').value = data.meeting.id;
                    document.getElementById('meeting-title').value = data.meeting.title || '';
                    document.getElementById('meeting-description').value = data.meeting.description || '';
                    document.getElementById('start-date').value = data.meeting.start_date || '';
                    document.getElementById('start-time').value = data.meeting.start_time || '';
                    document.getElementById('duration').value = data.meeting.duration || '60';
                    document.getElementById('max-participants').value = data.meeting.max_participants || 50;
                    // Se houver end_time personalizado
                    if (data.meeting.end_time) {
                        document.getElementById('end-time').value = data.meeting.end_time;
                        document.getElementById('end-time-group').style.display = 'block';
                        document.getElementById('duration').value = 'custom';
                    } else {
                        document.getElementById('end-time').value = '';
                        document.getElementById('end-time-group').style.display = 'none';
                    }
                    // Trocar título do modal
                    document.getElementById('modal-title').innerText = 'Editar Reunião';
                    // Abrir modal
                    document.getElementById('meeting-modal').style.display = 'flex';
                } else {
                    alert('Erro ao carregar dados da reunião para edição.');
                }
            })
            .catch(err => {
                alert('Erro ao buscar dados da reunião.');
            });
    }
    
    deleteMeeting(meetingId) {
        if (confirm('Tem certeza que deseja excluir esta reunião?')) {
            
            const baseUrl = window.location.origin;
            const apiUrl = `../api/meetings.php`;
            
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
        console.log('🔽 TOGGLE DROPDOWN - Meeting ID:', meetingId);
        
        const dropdown = document.getElementById(`dropdown-${meetingId}`);
        console.log('Dropdown element:', dropdown);
        
        if (dropdown) {
            console.log('Dropdown classes antes:', dropdown.className);
            dropdown.classList.toggle('show');
            console.log('Dropdown classes depois:', dropdown.className);
            
            // Fechar outros dropdowns
            const allDropdowns = document.querySelectorAll('.dropdown-menu');
            console.log('Total de dropdowns na página:', allDropdowns.length);
            
            allDropdowns.forEach(menu => {
                if (menu.id !== `dropdown-${meetingId}`) {
                    menu.classList.remove('show');
                    console.log('Fechando dropdown:', menu.id);
                }
            });
        } else {
            console.error('❌ Dropdown não encontrado para meeting ID:', meetingId);
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
        // Redireciona para a página da sala de reunião
    window.location.href = `../pages/meeting-room.php?id=${meetingId}`;
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
