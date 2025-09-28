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
            listView.style.display = 'none';
            calendarView.style.display = 'block';
            this.currentView = 'calendar';
        } else {
            listView.style.display = 'block';
            calendarView.style.display = 'none';
            this.currentView = 'list';
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
    }
    
    /**
     * Modal de reunião
     */
    openCreateMeetingModal() {
        const modal = document.getElementById('meeting-modal');
        if (modal) {
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
