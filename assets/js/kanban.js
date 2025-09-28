// Kanban interações da página de Projetos
(function(){
  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.from((root||document).querySelectorAll(sel)); }
  function on(el, ev, fn, opts){ el && el.addEventListener(ev, fn, opts); }

  function getProjectId(){
    const inp = qs('#task-project-id');
    if (inp && inp.value) return inp.value;
    const params = new URLSearchParams(location.search);
    return params.get('project');
  }

  function updateColumnCounts(){
    qsa('.kanban-column').forEach(col => {
      const cnt = col.querySelector('.task-count');
      const list = col.querySelector('.column-content, .kanban-tasks');
      if (cnt && list) cnt.textContent = list.querySelectorAll('.task-card').length;
    });
  }

  function mapTaskToProjectStatus(taskStatus){
    switch ((taskStatus||'todo')) {
      case 'in_progress': return 'in_progress';
      case 'review': return 'testing';
      case 'done': return 'completed';
      default: return 'planning';
    }
  }
  async function updateProjectStatusFromTaskColumn(taskStatus){
    try {
      const projectId = document.querySelector('#task-project-id')?.value || document.querySelector('#project-select')?.value;
      if (!projectId) return;
      const status = mapTaskToProjectStatus(taskStatus);
      const fd = new FormData();
      fd.append('action','update_status');
      fd.append('project_id', projectId);
      fd.append('status', status);
      await fetch('../api/projects.php', { method: 'POST', body: fd });
    } catch (e) { /* silencioso */ }
  }

  function enableDragAndDrop(){
    // Cards
    qsa('.task-card').forEach(card => {
      card.setAttribute('draggable', 'true');
      on(card, 'dragstart', (e) => {
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.dataset.taskId || '');
      });
      on(card, 'dragend', () => {
        card.classList.remove('dragging');
        qsa('.column-content.drag-over, .kanban-tasks.drag-over').forEach(c => c.classList.remove('drag-over'));
      });
    });

    // Column drop areas
    qsa('.column-content, .kanban-tasks').forEach(col => {
      on(col, 'dragover', (e) => {
        e.preventDefault();
        col.classList.add('drag-over');
        const afterElement = getDragAfterElement(col, e.clientY);
        const dragging = qs('.task-card.dragging');
        if (!dragging) return;
        if (afterElement == null) {
          col.appendChild(dragging);
        } else {
          col.insertBefore(dragging, afterElement);
        }
      });
      on(col, 'dragleave', () => col.classList.remove('drag-over'));
      on(col, 'drop', (e) => {
        e.preventDefault();
        col.classList.remove('drag-over');
        const dragging = qs('.task-card.dragging');
        if (!dragging) return;
        const status = col.closest('.kanban-column')?.dataset.status;
        const position = Array.from(col.children).indexOf(dragging);
        persistTaskPosition(dragging.dataset.taskId, status, position);
        updateProjectStatusFromTaskColumn(status);
        updateColumnCounts();
      });
    });
  }

  function getDragAfterElement(container, y){
    const draggableElements = [...container.querySelectorAll('.task-card:not(.dragging)')];
    return draggableElements.reduce((closest, child) => {
      const box = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        return { offset, element: child };
      } else {
        return closest;
      }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
  }

  async function persistTaskPosition(taskId, status, position){
    if (!taskId || !status) return;
    try {
      const form = new FormData();
      form.append('action', 'update_status');
      form.append('task_id', taskId);
      form.append('status', status);
      form.append('position', position);
      await fetch('../api/tasks.php', { method: 'POST', body: form });
    } catch (e) { console.error('Falha ao atualizar posição/status da tarefa', e); }
  }

  function enhanceDueDates(){
    const now = new Date();
    qsa('.task-due-date').forEach(el => {
      const text = el.textContent?.trim(); // formato dd/mm
      if (!text) return;
      const [d, m] = text.split('/').map(n => parseInt(n, 10));
      if (!d || !m) return;
      const year = now.getFullYear();
      const due = new Date(year, m - 1, d);
      // Ajuste se data já passou e estiver com ano errado (p/ virada de ano)
      if (due < new Date(year - 1, 11, 31)) due.setFullYear(year + 1);
      const diffDays = Math.ceil((due - now) / (1000*60*60*24));
      if (diffDays <= 3 && diffDays >= 0) el.classList.add('due-soon');
    });
  }

  async function loadProjectMembers(){
    const projectId = getProjectId();
    if (!projectId) return;
    try {
      const url = `../api/projects.php?action=members&project_id=${encodeURIComponent(projectId)}`;
      const res = await fetch(url);
      const data = await res.json().catch(()=>null);
      if (!data) return;
      const members = data.members || data; // compatibilidade
      const sel = qs('#task-assigned-to');
      if (!sel) return;
      // manter primeira opção "Não atribuído"
      const first = sel.querySelector('option[value=""]');
      sel.innerHTML = '';
      if (first) sel.appendChild(first);

      // Sempre incluir o usuário atual na lista
      const meId = window.CURRENT_USER_ID ? String(window.CURRENT_USER_ID) : null;
      const meName = window.CURRENT_USER_NAME || 'Você';
      if (meId) {
        const meOpt = document.createElement('option');
        meOpt.value = meId; meOpt.textContent = meName + ' (Eu)';
        sel.appendChild(meOpt);
      }

      (members || []).forEach(u => {
        // Evitar duplicar eu
        if (meId && String(u.id) === meId) return;
        const opt = document.createElement('option');
        opt.value = u.id; opt.textContent = u.name; sel.appendChild(opt);
      });

      // Pré-selecionar eu para facilitar ver minhas tarefas
      if (meId) sel.value = meId;
    } catch (e) { console.warn('Não foi possível carregar membros do projeto', e); }
  }

  function wireForms(){
    const taskForm = qs('#task-form');
    if (taskForm){
      on(taskForm, 'submit', async (e) => {
        e.preventDefault();
        if (taskForm.dataset.submitting === '1') return; // evitar dupla submissão
        taskForm.dataset.submitting = '1';
        const btn = qs('[form="task-form"][type="submit"]');
        const loading = btn?.querySelector('.btn-loading');
        const textSpan = btn?.querySelector('.btn-text');
        if (loading && textSpan){ loading.style.display = 'inline-block'; textSpan.style.display = 'none'; }
        try {
          const fd = new FormData(taskForm);
          // Se não escolher responsável, atribuir ao usuário atual
          if ((!fd.get('assigned_to') || fd.get('assigned_to')==='') && window.CURRENT_USER_ID) {
            fd.set('assigned_to', String(window.CURRENT_USER_ID));
          }
          const res = await fetch(taskForm.action, { method: 'POST', body: fd });
          const json = await res.json().catch(()=>null);
          // Esperado: {success: true, task: {id, status, title, ...}}
          if (json && json.success && json.task){
            upsertTaskCard(json.task);
            closeTaskModal();
            updateColumnCounts();
          } else {
            alert(json?.message || 'Não foi possível salvar a tarefa.');
          }
        } catch (err){ console.error(err); alert('Erro ao salvar tarefa.'); }
        finally {
          if (loading && textSpan){ loading.style.display = 'none'; textSpan.style.display = 'inline'; }
          delete taskForm.dataset.submitting;
        }
      });
    }

    const projectForm = qs('#project-form');
    if (projectForm){
      on(projectForm, 'submit', async (e) => {
        e.preventDefault();
        if (projectForm.dataset.submitting === '1') return; // evitar dupla submissão
        projectForm.dataset.submitting = '1';
        const btn = qs('[form="project-form"][type="submit"]');
        const loading = btn?.querySelector('.btn-loading');
        const textSpan = btn?.querySelector('.btn-text');
        if (loading && textSpan){ loading.style.display = 'inline-block'; textSpan.style.display = 'none'; }
        try {
          const fd = new FormData(projectForm);
          const res = await fetch(projectForm.action, { method: 'POST', body: fd });
          const json = await res.json().catch(()=>null);
          if (json && json.success){
            const id = json.project_id;
            if (id){
              const url = new URL(window.location.href);
              url.searchParams.set('project', id);
              window.location.href = url.toString();
            } else {
              location.reload();
            }
          }
          else { alert(json?.message || 'Não foi possível salvar o projeto.'); }
        } catch (err){ console.error(err); alert('Erro ao salvar projeto.'); }
        finally {
          if (loading && textSpan){ loading.style.display = 'none'; textSpan.style.display = 'inline'; }
          delete projectForm.dataset.submitting;
        }
      });
    }
  }

  function avatarSrc(val){
    if (!val) return '../assets/images/default-avatar.svg';
    try {
      const s = String(val);
      if (/^https?:\/\//i.test(s)) return s; // URL absoluta
      if (s.startsWith('/')) return '..' + s; // caminho absoluto do site
      if (s.includes('uploads/avatars/')) {
        // já contém a pasta
        return s.startsWith('uploads/') ? ('../' + s) : ('../' + s.replace(/^\.\//,'').replace(/^\/+/,''));
      }
      // apenas filename
      return `../uploads/avatars/${s}`;
    } catch { return '../assets/images/default-avatar.svg'; }
  }

  function upsertTaskCard(task){
    const status = task.status || 'todo';
    const col = qs(`#column-${status}`);
    if (!col) return;
    let card = qs(`.task-card[data-task-id="${task.id}"]`);
    const assigneeAvatar = avatarSrc(task.assigned_user_avatar);
    const dueDateShort = task.due_date ? new Date(task.due_date).toLocaleDateString('pt-BR', { day:'2-digit', month:'2-digit' }).slice(0,5) : '';

    const html = `
      <div class="task-card" data-task-id="${task.id}" draggable="true">
        <div class="task-header">
          <span class="task-priority priority-${task.priority || 'medium'}">${(task.priority||'').toUpperCase()}</span>
          <div class="task-actions">
            <button class="task-action-btn" data-action="delete"><i class="fas fa-trash"></i></button>
          </div>
        </div>
        <div class="task-content">
          <h4 class="task-title"></h4>
          ${task.description ? `<p class="task-description"></p>` : ''}
        </div>
        <div class="task-footer">
          <div class="task-meta">
            ${task.due_date ? `<span class="task-due-date"><i class="fas fa-calendar"></i>${dueDateShort}</span>` : ''}
            ${task.estimated_hours ? `<span class="task-hours"><i class="fas fa-clock"></i>${task.estimated_hours}h</span>` : ''}
          </div>
          ${task.assigned_to ? `<div class="task-assignee"><img class="assignee-avatar" src="${assigneeAvatar}" alt=""></div>` : '<div></div>'}
        </div>
      </div>`;

    if (!card){
      const wrapper = document.createElement('div');
      wrapper.innerHTML = html.trim();
      card = wrapper.firstElementChild;
      col.appendChild(card);
      bindCardActions(card);
      enableDragAndDrop();
    } else {
      card.outerHTML = html;
      card = qs(`.task-card[data-task-id="${task.id}"]`);
      bindCardActions(card);
      enableDragAndDrop();
    }
    const titleEl = card.querySelector('.task-title');
    if (titleEl) titleEl.textContent = task.title || '';
    const descEl = card.querySelector('.task-description');
    if (descEl) descEl.textContent = task.description || '';
  }

  function bindCardActions(card){
    // Abrir edição ao clicar no card (exceto cliques nos botões de ação)
    card.addEventListener('click', (e) => {
      if (card.classList.contains('dragging')) return;
      if (e.target.closest('.task-actions')) return;
      openEditTask(card.dataset.taskId);
    });
    // Impedir propagação ao clicar nos botões de ação
    card.querySelectorAll('.task-actions .task-action-btn').forEach(btn => {
      btn.addEventListener('click', (e) => e.stopPropagation());
    });
    // Botão de excluir
    const delBtn = card.querySelector('[data-action="delete"]');
    if (delBtn){ delBtn.addEventListener('click', () => deleteTask(card.dataset.taskId)); }
  }

  async function openEditTask(taskId){
    try {
      const r = await fetch(`../api/tasks.php?task_id=${encodeURIComponent(taskId)}`);
      const j = await r.json();
      if (!j.success) throw new Error(j.message||'Erro ao carregar tarefa');
      const t = j.task;
      const form = qs('#task-form');
      if (!form) return;
      qs('#task-modal-title').textContent = 'Editar Tarefa';
      qs('#task-id').value = t.id;
      qs('#task-title').value = t.title || '';
      qs('#task-description').value = t.description || '';
      qs('#task-status').value = t.status || 'todo';
      qs('#task-priority').value = t.priority || 'medium';
      qs('#task-due-date').value = (t.due_date||'').slice(0,10);
      qs('#task-estimated-hours').value = t.estimated_hours || '';
      const sel = qs('#task-assigned-to'); if (sel && t.assigned_to){ sel.value = String(t.assigned_to); }
      const modal = qs('#task-modal'); if (modal) modal.classList.add('open');
    } catch(e){ alert(e.message||'Erro ao editar tarefa'); }
  }

  async function deleteTask(taskId){
    if (!confirm('Tem certeza que deseja excluir esta tarefa?')) return;
    try {
      const r = await fetch('../api/tasks.php', { method:'DELETE', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ task_id: taskId }) });
      const j = await r.json();
      if (!j.success) throw new Error(j.message||'Erro ao excluir');
      const card = qs(`.task-card[data-task-id="${taskId}"]`);
      if (card){ const parent = card.parentElement; card.remove(); if (parent) updateColumnCounts(); }
    } catch(e){ alert(e.message||'Erro ao excluir tarefa'); }
  }

  // Fechar modais ao clicar no overlay e ESC
  function wireModals(){
    qsa('.modal-overlay').forEach(ov => {
      on(ov, 'click', (e) => { if (e.target === ov) ov.classList.remove('open'); });
    });
    on(document, 'keydown', (e) => { if (e.key === 'Escape') qsa('.modal-overlay').forEach(ov => ov.classList.remove('open')); });
  }

  // Globais
  window.openProjectModal = function openProjectModal(){
    const modal = qs('#project-modal'); if (!modal) return;
    const title = qs('#project-modal-title'); const form = qs('#project-form');
    if (title) title.textContent = 'Novo Projeto';
    if (form){ form.reset(); const id=qs('#project-id'); if (id) id.value=''; }
    modal.classList.add('open');
  };
  window.closeProjectModal = function closeProjectModal(){ const modal = qs('#project-modal'); if (modal) modal.classList.remove('open'); };

  window.openTaskModal = function openTaskModal(status){
    const modal = qs('#task-modal'); if (!modal) return;
    const title = qs('#task-modal-title'); const form = qs('#task-form');
    if (title) title.textContent = 'Nova Tarefa';
    if (form){ form.reset(); const id = qs('#task-id'); if (id) id.value = ''; const st = qs('#task-status'); if (st) st.value = status || 'todo'; }
    modal.classList.add('open');
  };
  window.closeTaskModal = function closeTaskModal(){ const modal = qs('#task-modal'); if (modal) modal.classList.remove('open'); };

  window.addTaskToColumn = function addTaskToColumn(status){ window.openTaskModal(status || 'todo'); };

  // Novo: trocar projeto via select
  window.changeProject = function changeProject(){
    const sel = qs('#project-select');
    const id = sel?.value || '';
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('project', id); else url.searchParams.delete('project');
    window.location.href = url.toString();
  };

  window.editProject = async function editProject(projectId){
    try {
      const r = await fetch(`../api/projects.php?action=details&project_id=${encodeURIComponent(projectId)}`);
      const j = await r.json();
      if (!j.success) throw new Error(j.message||'Erro ao carregar projeto');
      const p = j.project;
      const title = qs('#project-modal-title'); if (title) title.textContent = 'Editar Projeto';
      qs('#project-id').value = p.id;
      qs('#project-name').value = p.name || '';
      qs('#project-description').value = p.description || '';
      qs('#project-start-date').value = (p.start_date||'').slice(0,10);
      qs('#project-end-date').value = (p.end_date||'').slice(0,10);
      qs('#project-status').value = p.status || 'active';
      const modal = qs('#project-modal'); if (modal) modal.classList.add('open');
    } catch(e){ alert(e.message||'Erro ao editar projeto'); }
  };

  window.editTask = function editTask(taskId){ openEditTask(taskId); };

  window.deleteTask = function deleteTaskGlobal(taskId){ deleteTask(taskId); };

  window.deleteProject = async function deleteProject(projectId){
    if (!confirm('Tem certeza que deseja excluir este projeto? Todas as tarefas serão perdidas.')) return;
    try {
      const fd = new FormData();
      fd.append('action','delete');
      fd.append('project_id', projectId);
      const res = await fetch('../api/projects.php', { method:'POST', body: fd });
      const j = await res.json().catch(()=>null);
      if (!j || !j.success) throw new Error(j?.message || 'Falha ao excluir projeto');

      // Remover do board de projetos (se visível)
      const card = document.querySelector(`.project-card[data-project-id="${projectId}"]`);
      if (card) card.remove();

      // Remover do seletor e lidar com projeto selecionado
      const sel = document.querySelector('#project-select');
      const opt = sel?.querySelector(`option[value="${projectId}"]`);
      const selectedId = sel?.value;
      if (opt) opt.remove();

      if (selectedId && String(selectedId) === String(projectId)) {
        const url = new URL(window.location.href);
        url.searchParams.delete('project');
        window.location.href = url.toString();
      } else {
        // Atualiza contagens/resumo rapidamente ou recarrega
        try { localStorage.setItem('kanban-refresh','1'); } catch(e){}
        window.location.reload();
      }
    } catch(e){
      alert(e.message || 'Erro ao excluir projeto');
    }
  };
  window.manageProjectMembers = async function manageProjectMembers(projectId){
    try {
      const modal = document.getElementById('members-modal');
      if (!modal) { alert('Modal de membros não disponível.'); return; }
      // Set project id
      document.getElementById('members-project-id').value = String(projectId);
      // UI baseline
      const listEl = document.getElementById('members-list');
      const searchEl = document.getElementById('members-search');
      const countEl = document.getElementById('members-selected-count');
      listEl.innerHTML = '<div style="padding:10px; color:var(--primary-gray);">Carregando usuários...</div>';

      // Carregar todos os usuários
      const usersRes = await fetch('../api/users.php');
      const users = await usersRes.json();
      if (!Array.isArray(users)) throw new Error('Falha ao carregar usuários');

      // Carregar membros atuais
      const memRes = await fetch(`../api/projects.php?action=members&project_id=${encodeURIComponent(projectId)}`);
      const memJson = await memRes.json().catch(()=>({ members: [] }));
      const currentMembers = new Set((memJson.members||[]).map(m => String(m.id)));

      // Render lista com checkboxes
      const html = users.map(u => {
        const checked = currentMembers.has(String(u.id)) ? 'checked' : '';
        const avatar = avatarSrc(u.avatar);
        return `
          <label class="member-item">
            <input type="checkbox" value="${u.id}" ${checked} />
            <img src="${avatar}" class="member-avatar" alt=""/>
            <div>
              <div class="member-name">${(u.name||'').replace(/</g,'&lt;')}</div>
              ${u.role ? `<div class="member-role">${String(u.role)}</div>` : ''}
            </div>
          </label>`;
      }).join('');
      listEl.innerHTML = html || '<div style="padding:10px; color:var(--primary-gray);">Nenhum usuário encontrado.</div>';

      function updateCount(){
        const selected = listEl.querySelectorAll('input[type="checkbox"]:checked').length;
        if (countEl) countEl.textContent = String(selected);
      }
      updateCount();

      // Busca
      function applyFilter(){
        const term = (searchEl.value||'').toLowerCase();
        listEl.querySelectorAll('.member-item').forEach(item => {
          const name = (item.querySelector('.member-name')?.textContent||'').toLowerCase();
          item.style.display = name.includes(term) ? '' : 'none';
        });
      }
      searchEl.oninput = applyFilter;

      // Selecionar/limpar
      document.getElementById('members-select-all').onclick = () => {
        listEl.querySelectorAll('.member-item input[type="checkbox"]').forEach(cb => { if (cb.offsetParent !== null) cb.checked = true; });
        updateCount();
      };
      document.getElementById('members-clear-all').onclick = () => {
        listEl.querySelectorAll('.member-item input[type="checkbox"]').forEach(cb => { if (cb.offsetParent !== null) cb.checked = false; });
        updateCount();
      };
      listEl.addEventListener('change', (e) => { if (e.target.matches('input[type="checkbox"]')) updateCount(); });

      // Salvar
      const saveBtn = document.getElementById('members-save-btn');
      saveBtn.onclick = async () => {
        if (saveBtn.dataset.loading === '1') return;
        saveBtn.dataset.loading = '1';
        const loading = saveBtn.querySelector('.btn-loading');
        const text = saveBtn.querySelector('.btn-text');
        if (loading && text) { loading.style.display = 'inline-block'; text.style.display = 'none'; }
        try {
          const selected = Array.from(listEl.querySelectorAll('input[type="checkbox"]:checked')).map(cb => cb.value);
          const fd = new FormData();
          fd.append('action','update_members');
          fd.append('project_id', projectId);
          selected.forEach(id => fd.append('members[]', id));
          const res = await fetch('../api/projects.php', { method:'POST', body: fd });
          const j = await res.json();
          if (!j.success) throw new Error(j.message || 'Falha ao salvar membros');
          modal.classList.remove('open');
        } catch(e) {
          alert(e.message || 'Erro ao salvar membros');
        } finally {
          if (loading && text) { loading.style.display = 'none'; text.style.display = 'inline'; }
          delete saveBtn.dataset.loading;
        }
      };

      // Abrir modal
      modal.classList.add('open');
    } catch(e){
      alert(e.message || 'Erro ao abrir membros');
    }
  };

  // Adicionado: função setup ausente
  function setup(){
    try {
      updateColumnCounts();
      enableDragAndDrop();
      enhanceDueDates();
      loadProjectMembers();
      wireForms();
      wireModals();
      // Garantir que os cards existentes também tenham os binds necessários
      qsa('.task-card').forEach(bindCardActions);
    } catch(e) { /* silencioso */ }
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', setup); } else { setup(); }
})();

(function() {
  const board = document.querySelector('.kanban-board');
  if (!board) return;

  // Auto-scroll ao arrastar próximo das bordas
  let autoScrollRAF = null;
  const SCROLL_EDGE_PX = 60;
  const SCROLL_SPEED = 18; // px/frame aprox

  function autoScrollOnDrag(e) {
    const container = document.scrollingElement || document.documentElement;
    const rect = { top: 0, bottom: window.innerHeight };
    const y = e.clientY;

    let delta = 0;
    if (y < rect.top + SCROLL_EDGE_PX) delta = -SCROLL_SPEED;
    else if (y > rect.bottom - SCROLL_EDGE_PX) delta = SCROLL_SPEED;

    if (delta !== 0) {
      if (!autoScrollRAF) {
        autoScrollRAF = requestAnimationFrame(function step() {
          container.scrollTop += delta;
          autoScrollRAF = requestAnimationFrame(step);
        });
      }
    } else if (autoScrollRAF) {
      cancelAnimationFrame(autoScrollRAF);
      autoScrollRAF = null;
    }
  }

  // WIP limits por coluna (customizável via data-wip)
  function getWipLimit(col) {
    const v = parseInt(col.getAttribute('data-wip') || '0', 10);
    return isNaN(v) ? 0 : v; // 0 = sem limite
  }
  function canAcceptMore(col) {
    const limit = getWipLimit(col);
    if (!limit) return true;
    const count = col.querySelectorAll('.task-card').length;
    return count < limit;
  }

  // Colapsar/expandir colunas
  board.querySelectorAll('.kanban-column .column-header').forEach(header => {
    const col = header.closest('.kanban-column');
    // Botão de toggle minimalista (usa o próprio header como alvo)
    header.style.cursor = 'pointer';
    header.addEventListener('dblclick', () => {
      col.classList.toggle('collapsed');
    });
  });

  // Quick-add dentro do header da coluna
  board.querySelectorAll('.kanban-column').forEach(col => {
    const header = col.querySelector('.column-header');
    if (!header || header.querySelector('.quick-add')) return;

    const quick = document.createElement('div');
    quick.className = 'quick-add';
    quick.style.display = 'flex';
    quick.style.gap = '6px';
    quick.style.marginTop = '8px';

    quick.innerHTML = `
      <input type="text" class="quick-title" placeholder="Adicionar tarefa rápida..." style="flex:1; padding:6px 8px; border:1px solid var(--gray-medium); border-radius:6px; font-size:13px;" />
      <button class="btn btn-sm" style="white-space:nowrap">Adicionar</button>
    `;

    header.appendChild(quick);

    const input = quick.querySelector('.quick-title');
    const btn = quick.querySelector('button');

    function submitQuick() {
      const title = (input.value || '').trim();
      if (!title) return;
      // Usa API existente via fetch para criar tarefa com mínimos campos
      const status = col.getAttribute('data-status') || 'todo';
      const projectId = document.querySelector('#project-select')?.value || '';
      const payload = new FormData();
      payload.append('action', 'create_task');
      payload.append('title', title);
      payload.append('status', status);
      if (projectId) payload.append('project_id', projectId);
      if (window.CURRENT_USER_ID) payload.append('assigned_to', String(window.CURRENT_USER_ID));

      fetch('../api/tasks.php', { method: 'POST', body: payload })
        .then(r => r.json()).then(res => {
          if (res?.success && res.task) {
            // Otimista: renderiza cartão mínimo; callback real do seu código cuidará do resto ao recarregar
            const card = document.createElement('div');
            card.className = 'task-card';
            card.setAttribute('draggable', 'true');
            card.dataset.taskId = res.task.id;
            card.innerHTML = `
              <div class="task-header">
                <span class="task-title">${title.replace(/</g,'&lt;')}</span>
              </div>
              <div class="task-meta">
                <span class="task-due-date">Sem data</span>
              </div>
            `;
            col.querySelector('.column-content').prepend(card);
            // Abrir edição ao clicar
            card.addEventListener('click', (e) => {
              if (e.target.closest('.task-actions')) return;
              window.editTask?.(res.task.id);
            });
            input.value = '';
            updateColumnCounts?.();
            toast('Tarefa criada');
          } else {
            toast('Falha ao criar tarefa');
          }
        }).catch(() => toast('Erro de rede ao criar tarefa'));
    }

    btn.addEventListener('click', submitQuick);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') submitQuick();
    });
  });

  // Toast simples reutilizável
  let toastEl = null, toastTimer = null;
  function toast(msg) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.style.position = 'fixed';
      toastEl.style.right = '16px';
      toastEl.style.bottom = '16px';
      toastEl.style.background = 'rgba(17,17,17,.92)';
      toastEl.style.color = '#fff';
      toastEl.style.padding = '10px 14px';
      toastEl.style.borderRadius = '8px';
      toastEl.style.fontSize = '13px';
      toastEl.style.zIndex = 9999;
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.style.opacity = '0';
    toastEl.style.transform = 'translateY(6px)';
    requestAnimationFrame(() => {
      toastEl.style.transition = 'opacity .18s ease, transform .18s ease';
      toastEl.style.opacity = '1';
      toastEl.style.transform = 'translateY(0)';
    });
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toastEl.style.opacity = '0';
      toastEl.style.transform = 'translateY(6px)';
    }, 1800);
  }

  // Integra com DnD existente: melhorar feedback e WIP
  const columns = board.querySelectorAll('.kanban-column');
  columns.forEach(col => {
    const content = col.querySelector('.column-content');
    if (!content) return;

    // Drag feedback + WIP check
    content.addEventListener('dragover', (e) => {
      e.preventDefault();
      autoScrollOnDrag(e);
      if (!canAcceptMore(col)) {
        content.classList.remove('drag-over');
        content.style.cursor = 'not-allowed';
        return;
      }
      content.style.cursor = 'grab';
      content.classList.add('drag-over');
    });
    ['dragleave','drop'].forEach(evt => content.addEventListener(evt, () => {
      content.classList.remove('drag-over');
      content.style.cursor = '';
      if (autoScrollRAF) { cancelAnimationFrame(autoScrollRAF); autoScrollRAF = null; }
    }));
  });

  // Teclas de atalho básicas
  document.addEventListener('keydown', (e) => {
    if (e.key.toLowerCase() === 'n' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      const firstInput = board.querySelector('.quick-title');
      if (firstInput) {
        firstInput.focus();
      }
    }
  });

})();

// Board de Projetos - visão alternativa
(function(){
  function qs(sel, root){ return (root||document).querySelector(sel); }
  // Cache de usuários para evitar múltiplas requisições
  let ALL_USERS_CACHE = null;
  async function getAllUsers(){
    if (ALL_USERS_CACHE) return ALL_USERS_CACHE;
    try {
      const r = await fetch('../api/users.php');
      const j = await r.json();
      ALL_USERS_CACHE = Array.isArray(j) ? j : [];
    } catch { ALL_USERS_CACHE = []; }
    return ALL_USERS_CACHE;
  }

  function renderProjectBoard(projects){
    const container = document.querySelector('#project-board');
    if (!container) return;

    const cols = { planning: [], in_progress: [], testing: [], completed: [] };
    projects.forEach(p => { (cols[p.status||'planning'] || cols.planning).push(p); });

    function memberSelectorHtml(p){
      const selId = `mselect-${p.id}`;
      return `
        <div class="members">
          <select id="${selId}" class="form-control" multiple size="3" data-project-id="${p.id}"></select>
          <div class="members-actions">
            <button class="btn btn-sm" title="Adicionar membro" data-action="add-member" data-project-id="${p.id}"><i class="fas fa-user-plus"></i></button>
            <button class="btn btn-sm" data-action="save-members" data-project-id="${p.id}">Salvar membros</button>
          </div>
          <div class="add-member-inline" data-project-id="${p.id}" style="display:none;"></div>
        </div>`;
    }

    function cardHtml(p){
      const name = p.name || p.title || 'Sem título';
      return `
      <div class="project-card" draggable="true" data-project-id="${p.id}">
        <div class="card-actions">
          <button class="icon-btn danger" title="Excluir projeto" data-action="delete-project" data-project-id="${p.id}"><i class="fas fa-trash"></i></button>
        </div>
        <div class="name">${name.replace(/</g,'&lt;')}</div>
        <div class="meta">
          <span><i class="fas fa-tasks"></i> ${p.task_count||0}</span>
          <span class="meta-members"><i class="fas fa-users"></i> ${p.member_count||0}</span>
        </div>
        ${memberSelectorHtml(p)}
      </div>`;
    }

    container.innerHTML = `
      <div class="project-board">
        ${Object.entries({planning:'Planejamento',in_progress:'Em Progresso',testing:'Em Pausa',completed:'Concluído'}).map(([k,label]) => `
          <div class="project-column" data-status="${k}">
            <div class="column-header"><span class="column-title">${label}</span><span class="task-count"></span></div>
            <div class="column-content" id="pcol-${k}">${(cols[k]||[]).map(cardHtml).join('')}</div>
          </div>
        `).join('')}
      </div>`;

    // Carregar apenas os membros atuais de cada projeto no seletor do card
    container.querySelectorAll('.members select').forEach(sel => {
      const pid = sel.getAttribute('data-project-id');
      sel.innerHTML = '<option disabled>Carregando membros...</option>';
      fetch(`../api/projects.php?action=members&project_id=${encodeURIComponent(pid)}`)
        .then(r => r.json())
        .then(j => {
          const members = (j && Array.isArray(j.members)) ? j.members : [];
          sel.innerHTML = '';
          if (members.length === 0) {
            const opt = document.createElement('option');
            opt.disabled = true;
            opt.textContent = 'Sem membros';
            sel.appendChild(opt);
          } else {
            members.forEach(m => {
              const opt = document.createElement('option');
              opt.value = String(m.id);
              opt.textContent = m.name || `Usuário #${m.id}`;
              opt.selected = true; // mostrar apenas os membros adicionados
              sel.appendChild(opt);
            });
          }
        })
        .catch(() => {
          sel.innerHTML = '<option disabled>Falha ao carregar membros</option>';
        });
    });

    // Handler de ações nos cards (adicionar/salvar membros, excluir)
    container.addEventListener('click', async (e) => {
      const addBtn = e.target.closest('[data-action="add-member"]');
      if (addBtn) {
        const pid = addBtn.getAttribute('data-project-id');
        const wrap = addBtn.closest('.project-card');
        const holder = wrap?.querySelector(`.add-member-inline[data-project-id="${pid}"]`);
        const sel = wrap?.querySelector(`select[data-project-id="${pid}"]`);
        if (!holder || !sel) return;
        // Toggle
        if (holder.style.display === 'none' || holder.style.display === '') {
          holder.style.display = 'block';
          holder.innerHTML = '<div style="padding:8px; color:var(--primary-gray);">Carregando usuários...</div>';
          const all = await getAllUsers();
          const existing = new Set(Array.from(sel.options).map(o => o.value));
          const candidates = all.filter(u => !existing.has(String(u.id)));
          if (candidates.length === 0) {
            holder.innerHTML = '<div style="padding:8px; color:var(--primary-gray);">Todos os usuários já são membros.</div>';
          } else {
            const options = candidates.map(u => `<option value="${u.id}">${(u.name||`Usuário #${u.id}`).replace(/</g,'&lt;')}</option>`).join('');
            holder.innerHTML = `
              <select class="form-control add-member-select" multiple size="5">${options}</select>
              <div class="inline-actions" style="margin-top:6px; display:flex; gap:6px;">
                <button class="btn btn-sm" data-action="confirm-add-member" data-project-id="${pid}">Adicionar</button>
                <button class="btn btn-sm btn-outline" data-action="cancel-add-member" data-project-id="${pid}">Cancelar</button>
              </div>`;
          }
        } else {
          holder.style.display = 'none';
          holder.innerHTML = '';
        }
        return;
      }

      const confirmAdd = e.target.closest('[data-action="confirm-add-member"]');
      if (confirmAdd) {
        const pid = confirmAdd.getAttribute('data-project-id');
        const wrap = confirmAdd.closest('.project-card');
        const holder = wrap?.querySelector(`.add-member-inline[data-project-id="${pid}"]`);
        const picker = holder?.querySelector('.add-member-select');
        const sel = wrap?.querySelector(`select[data-project-id="${pid}"]`);
        if (!picker || !sel) return;
        const selected = Array.from(picker.selectedOptions).map(o => ({ id: o.value, name: o.textContent }));
        if (selected.length === 0) return;
        // Remover placeholder "Sem membros" se existir
        Array.from(sel.options).forEach(o => { if (o.disabled) sel.removeChild(o); });
        selected.forEach(user => {
          // Evitar duplicados
          if (!Array.from(sel.options).some(o => o.value === String(user.id))) {
            const opt = document.createElement('option');
            opt.value = String(user.id);
            opt.textContent = user.name || `Usuário #${user.id}`;
            opt.selected = true;
            sel.appendChild(opt);
          }
        });
        // Atualizar contagem visual de membros no card
        const metaCount = wrap?.querySelector('.meta .meta-members');
        if (metaCount) {
          const count = sel.selectedOptions.length;
          metaCount.innerHTML = `<i class="fas fa-users"></i> ${count}`;
        }
        // Fechar picker
        holder.style.display = 'none';
        holder.innerHTML = '';
        return;
      }

      const cancelAdd = e.target.closest('[data-action="cancel-add-member"]');
      if (cancelAdd) {
        const pid = cancelAdd.getAttribute('data-project-id');
        const wrap = cancelAdd.closest('.project-card');
        const holder = wrap?.querySelector(`.add-member-inline[data-project-id="${pid}"]`);
        if (holder) { holder.style.display = 'none'; holder.innerHTML = ''; }
        return;
      }

      const saveBtn = e.target.closest('[data-action="save-members"]');
      if (saveBtn) {
        const pid = saveBtn.getAttribute('data-project-id');
        const sel = container.querySelector(`select[data-project-id="${pid}"]`);
        const members = Array.from(sel?.selectedOptions || []).map(o => o.value);
        const fd = new FormData();
        fd.append('action','update_members');
        fd.append('project_id', pid);
        members.forEach(m => fd.append('members[]', m));
        fetch('../api/projects.php', { method:'POST', body: fd }).then(r=>r.json()).then(j => {
          if (!j.success) alert(j.message||'Falha ao salvar membros');
        }).catch(()=> alert('Erro ao salvar membros'));
        return;
      }
      const delBtn = e.target.closest('[data-action="delete-project"]');
      if (delBtn) {
        const pid = delBtn.getAttribute('data-project-id');
        if (pid) window.deleteProject?.(pid);
        return;
      }
    });

    // Bind DnD para projetos
    const cards = container.querySelectorAll('.project-card');
    cards.forEach(card => {
      card.addEventListener('dragstart', () => card.classList.add('dragging'));
      card.addEventListener('dragend', () => card.classList.remove('dragging'));
    });

    container.querySelectorAll('.project-column .column-content').forEach(col => {
      col.addEventListener('dragover', (e) => {
        e.preventDefault();
        col.classList.add('drag-over');
        const dragging = container.querySelector('.project-card.dragging');
        if (!dragging) return;
        col.appendChild(dragging);
      });
      ['dragleave','drop'].forEach(evt => col.addEventListener(evt, () => col.classList.remove('drag-over')));
      col.addEventListener('drop', async (e) => {
        const dragging = container.querySelector('.project-card.dragging');
        if (!dragging) return;
        const projectId = dragging.getAttribute('data-project-id');
        const status = col.closest('.project-column')?.getAttribute('data-status') || 'planning';
        try {
          const fd = new FormData();
          fd.append('action','update_status');
          fd.append('project_id', projectId);
          fd.append('status', status);
          const r = await fetch('../api/projects.php', { method:'POST', body: fd });
          const j = await r.json();
          if (!j.success) throw new Error(j.message||'Falha ao mover projeto');
        } catch(e){ console.warn(e.message||e); }
      });
    });
  }

  function applySwitchBehavior(){
    const wrap = document.querySelector('#project-view-wrapper');
    const switchInput = document.querySelector('#view-switch');
    const hint = document.querySelector('.tasks-hint');
    const selectorWrap = document.querySelector('.project-selector');
    const projInfo = document.querySelector('.project-info');
    const newTaskBtn = document.querySelector('.header-actions .btn.btn-primary');
    const projSummary = document.querySelector('.projects-summary');
    if (!wrap || !switchInput) return;

    // Pré-render (mantém oculto até ativar)
    if (window.PROJECTS_DATA && Array.isArray(window.PROJECTS_DATA)) {
      const pb = document.querySelector('#project-board');
      if (pb && !pb.hasChildNodes()) {
        renderProjectBoard(window.PROJECTS_DATA);
      }
    }

    function setMode(isProjects){
      const tasksEl = document.querySelector('#kanban-board');
      const projEl = document.querySelector('#project-board');
      wrap.setAttribute('data-mode', isProjects ? 'projects' : 'tasks');
      if (isProjects) {
        tasksEl?.classList.add('hidden');
        projEl?.classList.remove('hidden');
        if (hint) hint.style.display = 'none';
        if (selectorWrap) selectorWrap.style.display = 'none';
        if (projInfo) projInfo.style.display = 'none';
        if (newTaskBtn) newTaskBtn.style.display = 'none';
        if (projSummary) projSummary.style.display = '';
      } else {
        projEl?.classList.add('hidden');
        tasksEl?.classList.remove('hidden');
        if (hint) hint.style.display = '';
        if (selectorWrap) selectorWrap.style.display = '';
        if (projInfo) projInfo.style.display = '';
        if (newTaskBtn) newTaskBtn.style.display = '';
        if (projSummary) projSummary.style.display = 'none';
      }
    }

    // Estado inicial a partir do localStorage
    const saved = localStorage.getItem('kanban-view-mode');
    const initialIsProjects = saved === 'projects';
    switchInput.checked = initialIsProjects;
    setMode(initialIsProjects);

    // Listener do switch
    switchInput.addEventListener('change', () => {
      const isProjects = switchInput.checked;
      setMode(isProjects);
      localStorage.setItem('kanban-view-mode', isProjects ? 'projects' : 'tasks');
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    applySwitchBehavior();
  });

})();
