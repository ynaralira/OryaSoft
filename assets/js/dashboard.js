// Dashboard Module
class DashboardModule {
    constructor() {
        this.stats = {};
        this.charts = {};
        this.init();
    }

    init() {
        this.loadDashboardData();
        this.setupRefreshInterval();
        this.setupQuickActions();
        this.initializeCharts();
    }

    async loadDashboardData() {
        try {
            // Carregar estatísticas atualizadas
            const response = await fetch('/api/dashboard.php');
            const data = await response.json();

            if (data.success) {
                this.stats = data.stats;
                this.updateStatsDisplay();
                this.updateRecentActivity(data.recent_activity);
            }
        } catch (error) {
            console.error('Erro ao carregar dados do dashboard:', error);
        }
    }

    updateStatsDisplay() {
        // Atualizar cards de estatísticas com animação
        Object.keys(this.stats).forEach(key => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                this.animateNumber(element, this.stats[key]);
            }
        });
    }

    animateNumber(element, targetValue) {
        const currentValue = parseInt(element.textContent) || 0;
        const increment = targetValue > currentValue ? 1 : -1;
        const duration = 1000;
        const stepTime = duration / Math.abs(targetValue - currentValue);

        let current = currentValue;
        const timer = setInterval(() => {
            current += increment;
            element.textContent = current;

            if (current === targetValue) {
                clearInterval(timer);
            }
        }, stepTime);
    }

    updateRecentActivity(activities) {
        const container = document.querySelector('.recent-activity-list');
        if (!container || !activities) return;

        container.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-${this.getActivityIcon(activity.type)}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">${activity.title}</div>
                    <div class="activity-time">${this.formatTimeAgo(activity.created_at)}</div>
                </div>
            </div>
        `).join('');
    }

    getActivityIcon(type) {
        const icons = {
            'project_created': 'project-diagram',
            'meeting_joined': 'video',
            'post_created': 'pencil-alt',
            'task_completed': 'check-circle',
            'repository_added': 'code-branch'
        };
        return icons[type] || 'circle';
    }

    setupRefreshInterval() {
        // Atualizar dados a cada 5 minutos
        setInterval(() => {
            this.loadDashboardData();
        }, 300000);
    }

    setupQuickActions() {
        // Setup dos botões de ação rápida
        document.querySelectorAll('.quick-action').forEach(action => {
            action.addEventListener('click', (e) => {
                // Adicionar efeito de ripple
                this.createRippleEffect(e.target, e);
            });
        });

        // Setup do botão de criar reunião rápida
        const quickMeetingBtn = document.querySelector('[data-action="quick-meeting"]');
        if (quickMeetingBtn) {
            quickMeetingBtn.addEventListener('click', () => {
                this.openQuickMeetingModal();
            });
        }

        // Setup do botão de criar projeto rápido
        const quickProjectBtn = document.querySelector('[data-action="quick-project"]');
        if (quickProjectBtn) {
            quickProjectBtn.addEventListener('click', () => {
                this.openQuickProjectModal();
            });
        }
    }

    createRippleEffect(element, event) {
        const ripple = document.createElement('span');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;

        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        `;

        // Adicionar keyframes se não existirem
        if (!document.querySelector('#ripple-keyframes')) {
            const style = document.createElement('style');
            style.id = 'ripple-keyframes';
            style.textContent = `
                @keyframes ripple {
                    to { transform: scale(4); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }

        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);

        setTimeout(() => {
            if (ripple.parentNode) {
                ripple.remove();
            }
        }, 600);
    }

    openQuickMeetingModal() {
        // Implementar modal de criação rápida de reunião
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3>Agendar Reunião Rápida</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form class="quick-meeting-form">
                        <div class="form-group">
                            <label>Título da Reunião</label>
                            <input type="text" name="title" class="form-control" placeholder="Ex: Daily da equipe" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Data</label>
                                <input type="date" name="date" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Horário</label>
                                <input type="time" name="time" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Duração (minutos)</label>
                            <select name="duration" class="form-control">
                                <option value="30">30 minutos</option>
                                <option value="60" selected>1 hora</option>
                                <option value="90">1h 30min</option>
                                <option value="120">2 horas</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                    <button type="submit" class="btn btn-primary" form="quick-meeting-form">Agendar</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        modal.style.display = 'flex';

        // Setup do formulário
        const form = modal.querySelector('.quick-meeting-form');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.createQuickMeeting(new FormData(form));
            modal.remove();
        });

        // Setup dos botões de fechar
        modal.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', () => modal.remove());
        });

        // Definir data mínima como hoje
        const dateInput = modal.querySelector('input[name="date"]');
        dateInput.min = new Date().toISOString().split('T')[0];
        dateInput.value = new Date().toISOString().split('T')[0];

        // Definir horário padrão como próxima hora
        const timeInput = modal.querySelector('input[name="time"]');
        const now = new Date();
        now.setHours(now.getHours() + 1, 0, 0, 0);
        timeInput.value = now.toTimeString().slice(0, 5);
    }

    async createQuickMeeting(formData) {
        try {
            const response = await fetch('/api/meetings.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                window.oryaApp.showNotification('Sucesso', 'Reunião agendada com sucesso!', 'success');
                this.loadDashboardData(); // Atualizar dados
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            window.oryaApp.showNotification('Erro', error.message, 'error');
        }
    }

    openQuickProjectModal() {
        // Implementar modal de criação rápida de projeto
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3>Criar Projeto Rápido</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <form class="quick-project-form">
                        <div class="form-group">
                            <label>Nome do Projeto</label>
                            <input type="text" name="title" class="form-control" placeholder="Ex: Sistema de Blog" required>
                        </div>
                        <div class="form-group">
                            <label>Descrição</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Descreva brevemente o projeto..."></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Prioridade</label>
                                <select name="priority" class="form-control">
                                    <option value="low">Baixa</option>
                                    <option value="medium" selected>Média</option>
                                    <option value="high">Alta</option>
                                    <option value="urgent">Urgente</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Prazo</label>
                                <input type="date" name="due_date" class="form-control">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                    <button type="submit" class="btn btn-primary" form="quick-project-form">Criar Projeto</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        modal.style.display = 'flex';

        // Setup do formulário
        const form = modal.querySelector('.quick-project-form');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.createQuickProject(new FormData(form));
            modal.remove();
        });

        // Setup dos botões de fechar
        modal.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', () => modal.remove());
        });

        // Definir data mínima como hoje
        const dateInput = modal.querySelector('input[name="due_date"]');
        dateInput.min = new Date().toISOString().split('T')[0];
    }

    async createQuickProject(formData) {
        try {
            const response = await fetch('/api/projects.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                window.oryaApp.showNotification('Sucesso', 'Projeto criado com sucesso!', 'success');
                this.loadDashboardData(); // Atualizar dados
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            window.oryaApp.showNotification('Erro', error.message, 'error');
        }
    }

    initializeCharts() {
        this.initProjectsChart();
        this.initActivityChart();
    }

    initProjectsChart() {
        const canvas = document.getElementById('projectsChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        this.charts.projects = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Planejamento', 'Em Progresso', 'Teste', 'Concluído'],
                datasets: [{
                    data: [0, 0, 0, 0], // Será atualizado com dados reais
                    backgroundColor: [
                        '#3B82F6',
                        '#F59E0B',
                        '#8B4513',
                        '#10B981'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    initActivityChart() {
        const canvas = document.getElementById('activityChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        this.charts.activity = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [], // Últimos 7 dias
                datasets: [{
                    label: 'Atividades',
                    data: [],
                    borderColor: '#722F37',
                    backgroundColor: 'rgba(114, 47, 55, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    formatTimeAgo(dateString) {
        return window.oryaApp.formatTimeAgo(dateString);
    }

    // Método para atualizar charts com dados reais
    updateCharts(data) {
        if (this.charts.projects && data.projects_by_status) {
            this.charts.projects.data.datasets[0].data = [
                data.projects_by_status.planning || 0,
                data.projects_by_status.in_progress || 0,
                data.projects_by_status.testing || 0,
                data.projects_by_status.completed || 0
            ];
            this.charts.projects.update();
        }

        if (this.charts.activity && data.activity_last_week) {
            this.charts.activity.data.labels = data.activity_last_week.labels;
            this.charts.activity.data.datasets[0].data = data.activity_last_week.data;
            this.charts.activity.update();
        }
    }
}

// Initialize module when page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.DashboardModule = DashboardModule;
    });
} else {
    window.DashboardModule = DashboardModule;
}
