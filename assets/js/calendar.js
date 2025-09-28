// Calendar page script placeholder
// This file exists to avoid 404 when included by pages/calendar.php via $additional_js = ['calendar'].
// The page currently uses inline <script> for its logic.

document.addEventListener('DOMContentLoaded', function () {
  // Placeholder. Move inline calendar JS here in the future if desired.
  // console.log('calendar.js carregado');
});

// Calendar page logic (migrated from inline script)
(function() {
  const init = window.CALENDAR_INIT || {};
  let currentMonth = parseInt(init.currentMonth, 10) || (new Date().getMonth() + 1);
  let currentYear = parseInt(init.currentYear, 10) || (new Date().getFullYear());
  let currentView = 'month';

  // Navegação do calendário
  function changeMonth(direction) {
    currentMonth += direction;
    if (currentMonth < 1) {
      currentMonth = 12;
      currentYear--;
    } else if (currentMonth > 12) {
      currentMonth = 1;
      currentYear++;
    }
    window.location.href = `?month=${currentMonth}&year=${currentYear}`;
  }

  function goToToday() {
    const today = new Date();
    currentMonth = today.getMonth() + 1;
    currentYear = today.getFullYear();
    window.location.href = `?month=${currentMonth}&year=${currentYear}`;
  }

  function changeView(view, ev) {
    currentView = view;
    const container = document.querySelector('.view-toggle');
    if (container) {
      container.querySelectorAll('.btn').forEach(btn => btn.classList.remove('active'));
      const e = ev || window.event;
      if (e && e.target && container.contains(e.target)) {
        e.target.classList.add('active');
      } else {
        const idx = view === 'month' ? 0 : view === 'week' ? 1 : 2;
        const btns = container.querySelectorAll('.btn');
        if (btns[idx]) btns[idx].classList.add('active');
      }
    }
    console.log('Mudando para visualização:', view);
  }

  // Abrir detalhes do dia
  function openDayView(date) {
    document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
    const el = document.querySelector(`.calendar-day[data-date="${date}"]`);
    if (el) { el.classList.add('selected'); }
  }

  // Eventos
  function openEventDetails(eventId, eventType) {
    console.log('Abrir detalhes do evento:', eventId, eventType);
    const modal = document.getElementById('event-quick-view');
    if (modal) modal.style.display = 'flex';
  }

  function closeEventQuickView() {
    const modal = document.getElementById('event-quick-view');
    if (modal) modal.style.display = 'none';
  }

  function openEventQuickCreate() {
    const now = new Date();
    now.setHours(now.getHours() + 1, 0, 0, 0);
    const timeEl = document.getElementById('quick-time');
    if (timeEl) timeEl.value = now.toTimeString().slice(0, 5);
    const modal = document.getElementById('quick-create-modal');
    if (modal) { modal.style.display = 'flex'; }
  }

  function closeQuickCreateModal() {
    const modal = document.getElementById('quick-create-modal');
    if (modal) { modal.style.display = 'none'; }
  }

  function exportCalendar() {
    console.log('Exportar calendário');
  }

  // Expor no escopo global para uso pelos atributos onclick no HTML
  window.changeMonth = changeMonth;
  window.goToToday = goToToday;
  window.changeView = changeView;
  window.openDayView = openDayView;
  window.openEventDetails = openEventDetails;
  window.closeEventQuickView = closeEventQuickView;
  window.openEventQuickCreate = openEventQuickCreate;
  window.closeQuickCreateModal = closeQuickCreateModal;
  window.exportCalendar = exportCalendar;

  // Inicializações ao carregar a página
  document.addEventListener('DOMContentLoaded', function () {
    // Formulário de criação rápida
    const quickForm = document.getElementById('quick-event-form');
    if (quickForm) {
      quickForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const eventType = formData.get('event_type');
        if (eventType === 'meeting') {
          const title = formData.get('title');
          const date = formData.get('date');
          const time = formData.get('time');
          const description = formData.get('description');
          const payload = new FormData();
          payload.append('title', title || '');
          payload.append('start_date', date || '');
          payload.append('start_time', time || '');
          payload.append('meeting_url', 'https://');
          if (description) payload.append('description', description);
          fetch('../api/meetings.php', { method: 'POST', body: payload })
            .then(r => r.json())
            .then(d => {
              if (d && d.success) { alert('Reunião criada. Edite detalhes na página de Reuniões.'); }
              else { alert('Erro: ' + ((d && d.message) || 'falha ao criar')); }
            })
            .catch(() => alert('Erro de conexão'));
          closeQuickCreateModal();
          return;
        } else if (eventType === 'task') {
          window.location.href = '../pages/kanban.php?open=new';
          return;
        } else if (eventType === 'event') {
          alert('Criação de evento genérico em desenvolvimento.');
          closeQuickCreateModal();
          return;
        }
      });
    }

    // Melhor feedback ao passar o mouse
    const gridDays = document.querySelectorAll('.calendar-day');
    gridDays.forEach(day => {
      day.addEventListener('mouseenter', () => day.classList.add('hover'));
      day.addEventListener('mouseleave', () => day.classList.remove('hover'));
    });
  });
})();
