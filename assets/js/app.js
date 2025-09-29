// JavaScript principal da plataforma Orya
class OryaApp {
    constructor() {
        this.currentUser = null;
        this.notifications = [];
        this.baseUrl = window.BASE_URL || 'http://localhost/Orya';
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadUserData();
        this.setupSidebar();
        this.setupNotifications();
        this.setupSearch();
        this.initializeModules();
    }

    setupEventListeners() {
        // Toggle sidebar
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }

        // Dropdown menus
        document.addEventListener('click', (e) => {
            if (e.target.closest('.dropdown')) {
                e.stopPropagation();
                this.toggleDropdown(e.target.closest('.dropdown'));
            } else {
                this.closeAllDropdowns();
            }
        });

        // Modal close buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay') || e.target.classList.contains('modal-close')) {
                this.closeModal();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModal();
            }
        });

        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.classList.contains('ajax-form')) {
                if (form.id === 'meeting-form' || form.id === 'project-form' || form.id === 'task-form') {
                    return; 
                }
    
                if (form.dataset.submitting === '1') {
                    e.preventDefault();
                    return;
                }
                e.preventDefault();
                form.dataset.submitting = '1';
                this.handleFormSubmission(form).finally(()=> { delete form.dataset.submitting; });
            }
        });
    }

    setupSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        

        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';

        if (isCollapsed && window.innerWidth > 900) {
            sidebar?.classList.add('collapsed');
            mainContent?.classList.add('sidebar-collapsed');
        } else {
            sidebar?.classList.remove('collapsed');
            mainContent?.classList.remove('sidebar-collapsed');
        }


        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
            }
        });
    }

    toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');

        if (window.innerWidth > 900) {
            sidebar?.classList.toggle('collapsed');
            mainContent?.classList.toggle('sidebar-collapsed');
            // Save state to localStorage
            const isCollapsed = sidebar?.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        }
    }

    setupNotifications() {
        this.loadNotifications();
        
        setInterval(() => {
            this.loadNotifications();
        }, 30000);
    }

    
    async loadNotifications() {
        try {
            const response = await fetch(`${this.baseUrl}/api/notifications.php`);
            if (!response.ok) return;
            const data = await response.json();
            if (data.success) {
                this.notifications = (data.notifications || []).slice().sort((a, b) => {
                    if (a.created_at && b.created_at) {
                        return new Date(b.created_at) - new Date(a.created_at);
                    } else if (a.id && b.id) {
                        return b.id - a.id;
                    } else {
                        return 0;
                    }
                });
                this.updateNotificationBadge();
                this.updateNotificationDropdown();
            }
        } catch (error) {
            console.error('Erro ao carregar notificações:', error);
        }
    }

    updateNotificationBadge() {
        const badge = document.querySelector('.notification-badge');
        const unreadCount = this.notifications.filter(n => !n.is_read).length;
        
        if (badge) {
            if (unreadCount > 0) {
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    updateNotificationDropdown() {
        const dropdown = document.querySelector('.notifications-dropdown');
        if (!dropdown) return;

        const notificationsList = dropdown.querySelector('.notifications-list');
        if (!notificationsList) return;

        notificationsList.innerHTML = '';

        if (this.notifications.length === 0) {
            notificationsList.innerHTML = '<div class="notification-empty">Nenhuma notificação</div>';
            return;
        }

        this.notifications.slice(0, 10).forEach(notification => {
            const item = document.createElement('div');
            item.className = `notification-item ${!notification.is_read ? 'unread' : ''}`;
            item.innerHTML = `
                <div class="notification-content">
                    <div class="notification-title">${this.escapeHtml(notification.title)}</div>
                    <div class="notification-message">${this.escapeHtml(notification.message)}</div>
                    <div class="notification-time">${this.formatTimeAgo(notification.created_at)}</div>
                </div>
                ${!notification.is_read ? '<div class="notification-unread-indicator"></div>' : ''}
            `;
            
            item.addEventListener('click', () => {
                this.markNotificationAsRead(notification.id);
                if (notification.reference_id) {
                    this.navigateToNotification(notification);
                }
            });
            
            notificationsList.appendChild(item);
        });
    }

    async markNotificationAsRead(notificationId) {
        try {
            await fetch(`${this.baseUrl}/api/notifications.php`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: notificationId
                })
            });
            
            const notification = this.notifications.find(n => n.id === notificationId);
            if (notification) {
                notification.is_read = true;
                this.updateNotificationBadge();
                this.updateNotificationDropdown();
            }
        } catch (error) {
            console.error('Erro ao marcar notificação como lida:', error);
        }
    }

    navigateToNotification(notification) {
        const routes = {
            'meeting': `/pages/meetings.php?id=${notification.reference_id}`,
            'project': `/pages/kanban.php?project=${notification.reference_id}`,
            'community': `/pages/community.php?post=${notification.reference_id}`,
            'chat': `/pages/chat.php?conversation=${notification.reference_id}`
        };

        const route = routes[notification.type];
        if (route) {
            window.location.href = route;
        }
    }

    setupSearch() {
        const searchInput = document.querySelector('.search-input');
        if (!searchInput) return;

        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.performSearch(e.target.value);
            }, 300);
        });

        this.createSearchDropdown();
    }

    createSearchDropdown() {
        const searchBox = document.querySelector('.search-box');
        if (!searchBox) return;

        const dropdown = document.createElement('div');
        dropdown.className = 'search-dropdown';
        dropdown.innerHTML = `
            <div class="search-results">
                <div class="search-loading">Pesquisando...</div>
            </div>
        `;
        searchBox.appendChild(dropdown);
    }

    async performSearch(query) {
        if (query.length < 2) {
            this.hideSearchResults();
            return;
        }

        const dropdown = document.querySelector('.search-dropdown');
        const results = dropdown.querySelector('.search-results');
        
        results.innerHTML = '<div class="search-loading">Pesquisando...</div>';
        dropdown.style.display = 'block';

        try {
            const response = await fetch(`${this.baseUrl}/api/search.php?q=${encodeURIComponent(query)}`);
            if (!response.ok) return;
            const data = await response.json();

            if (data.success) {
                this.displaySearchResults(data.results);
            } else {
                results.innerHTML = '<div class="search-empty">Nenhum resultado encontrado</div>';
            }
        } catch (error) {
            console.error('Erro na busca:', error);
        }
    }

    displaySearchResults(results) {
        const dropdown = document.querySelector('.search-dropdown');
        const resultsContainer = dropdown.querySelector('.search-results');

        if (results.length === 0) {
            resultsContainer.innerHTML = '<div class="search-empty">Nenhum resultado encontrado</div>';
            return;
        }

        const html = results.map(result => `
            <a href="${result.url}" class="search-result-item">
                <div class="search-result-icon">
                    <i class="fas fa-${this.getSearchIcon(result.type)}"></i>
                </div>
                <div class="search-result-content">
                    <div class="search-result-title">${this.highlightSearchTerm(result.title, result.query)}</div>
                    <div class="search-result-type">${result.type_label}</div>
                </div>
            </a>
        `).join('');

        resultsContainer.innerHTML = html;
    }

    getSearchIcon(type) {
        const icons = {
            'user': 'user',
            'project': 'project-diagram',
            'meeting': 'video',
            'post': 'comments',
            'repository': 'code-branch'
        };
        return icons[type] || 'search';
    }

    highlightSearchTerm(text, term) {
        if (!term) return this.escapeHtml(text);
        const regex = new RegExp(`(${this.escapeRegExp(term)})`, 'gi');
        return this.escapeHtml(text).replace(regex, '<mark>$1</mark>');
    }

    hideSearchResults() {
        const dropdown = document.querySelector('.search-dropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }

    toggleDropdown(dropdown) {
        const menu = dropdown.querySelector('.dropdown-menu');
        const isOpen = menu.classList.contains('show');
        
        this.closeAllDropdowns();
        
        if (!isOpen) {
            menu.classList.add('show');
        }
    }

    closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
        this.hideSearchResults();
    }

    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal() {
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.style.display = 'none';
        });
        document.body.style.overflow = 'auto';
    }

    async handleFormSubmission(form) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn?.textContent;

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Carregando...';
        }

        try {
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification('Sucesso!', data.message || 'Operação realizada com sucesso', 'success');
                
            
                if (form.closest('.modal-overlay')) {
                    this.closeModal();
       
                if (data.reload) {
                    window.location.reload();
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                }
            } else {
                this.showNotification('Erro', data.message || 'Erro ao processar solicitação', 'error');
            }
        } catch (error) {
            console.error('Erro no formulário:', error);
            this.showNotification('Erro', 'Erro de conexão', 'error');
        } finally {
            // Restore button state
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    }

    showNotification(title, message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <div class="notification-title">${this.escapeHtml(title)}</div>
                <div class="notification-message">${this.escapeHtml(message)}</div>
            </div>
            <button class="notification-close">&times;</button>
        `;


        let container = document.querySelector('.notifications-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'notifications-container';
            document.body.appendChild(container);
        }
        
        container.appendChild(notification);


        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });

        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    async loadUserData() {
        try {
            const response = await fetch(`${this.baseUrl}/api/user.php`);
            if (!response.ok) return; 
            const data = await response.json();
            
            if (data.success) {
                this.currentUser = data.user;
                this.updateUserInterface();
            }
        } catch (error) {
            console.error('Erro ao carregar dados do usuário:', error);
        }
    }

    updateUserInterface() {
        if (!this.currentUser) return;

        const userAvatar = document.querySelector('.user-avatar');
        const userName = document.querySelector('.user-name');

        if (userAvatar) {
            userAvatar.src = this.currentUser.avatar || `${this.baseUrl}/assets/images/default-avatar.svg`;
            userAvatar.alt = this.currentUser.name;
        }

        if (userName) {
            userName.textContent = this.currentUser.name;
        }
    }

    initializeModules() {
 
        const currentPage = document.body.dataset.page;
        
        switch (currentPage) {
            case 'meetings':
                if (window.MeetingsModule) {
                    new window.MeetingsModule();
                }
                break;
            case 'kanban':
                if (window.KanbanModule) {
                    new window.KanbanModule();
                }
                break;
            case 'community':
                if (window.CommunityModule) {
                    new window.CommunityModule();
                }
                break;
            case 'chat':
                if (window.ChatModule) {
                    new window.ChatModule();
                }
                break;
            case 'repositories':
                if (window.RepositoriesModule) {
                    new window.RepositoriesModule();
                }
                break;
            case 'calendar':
                if (window.CalendarModule) {
                    new window.CalendarModule();
                }
                break;
        }
    }


    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) {
            return 'Agora mesmo';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `${minutes} minuto${minutes > 1 ? 's' : ''} atrás`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `${hours} hora${hours > 1 ? 's' : ''} atrás`;
        } else if (diffInSeconds < 604800) {
            const days = Math.floor(diffInSeconds / 86400);
            return `${days} dia${days > 1 ? 's' : ''} atrás`;
        } else {
            return date.toLocaleDateString('pt-BR');
        }
    }

    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }

    formatDate(dateString, options = {}) {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            ...options
        });
    }

    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('pt-BR');
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.oryaApp = new OryaApp();
});

if (typeof module !== 'undefined' && module.exports) {
    module.exports = OryaApp;
}
