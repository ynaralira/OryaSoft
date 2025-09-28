// Comunidade - funcionalidades interativas (feed, likes, comentários, compartilhamento, menções/hashtags)
(function(){
  const API_URL = '../api/community.php';

  // Utils
  function qs(sel, root=document){ return root.querySelector(sel); }
  function qsa(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }
  function timeAgo(dateStr){
    const d = new Date(dateStr);
    const s = Math.floor((Date.now() - d.getTime())/1000);
    if (s < 60) return 'agora';
    const m = Math.floor(s/60); if (m < 60) return m+'m';
    const h = Math.floor(m/60); if (h < 24) return h+'h';
    const dd = Math.floor(h/24); if (dd < 30) return dd+'d';
    const mo = Math.floor(dd/30); if (mo < 12) return mo+' meses';
    return Math.floor(mo/12)+' anos';
  }

  function formatContent(text) {
    if (!text) return '';
    let esc = text
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;');
    // Links
    esc = esc.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1<\/a>');
    // Hashtags
    esc = esc.replace(/(^|\s)#([\p{L}\p{N}_-]+)/gu, (m, pre, tag)=> `${pre}<a class="hashtag" href="?tag=${encodeURIComponent(tag)}">#${tag}<\/a>`);
    // Mentions (somente visual e linka para busca)
    esc = esc.replace(/(^|\s)@([\p{L}\p{N}_._-]+)/gu, (m, pre, user)=> `${pre}<a class="mention" href="?search=%40${encodeURIComponent(user)}">@${user}<\/a>`);
    return esc.replace(/\n/g,'<br>');
  }

  // Post Modal
  function openPostModal() {
    const modal = qs('#post-modal');
    if (!modal) return;
    qs('#post-modal-title').textContent = 'Nova Publicação';
    const form = qs('#post-form');
    if (form) { form.reset(); }
    const id = qs('#post-id'); if (id) id.value = '';
    modal.style.display = 'flex';
  }
  function closePostModal(){ const modal = qs('#post-modal'); if (modal) modal.style.display = 'none'; }

  // CRUD de Post
  async function editPost(postId){
    try {
      const r = await fetch(`${API_URL}?post_id=${postId}`);
      const t = await r.text();
      let j; try { j = JSON.parse(t); } catch { throw new Error('Resposta inválida do servidor'); }
      if (!j.success) throw new Error(j.message||'Erro ao carregar post');
      const p = j.post;
      qs('#post-modal-title').textContent = 'Editar Publicação';
      qs('#post-id').value = p.id;
      qs('#post-type').value = p.type || 'discussion';
      qs('#post-title').value = p.title || '';
      qs('#post-content').value = p.content || '';
      const tagsInput = qs('#post-tags');
      if (tagsInput) {
        try {
          const tags = JSON.parse(p.tags||'[]');
          tagsInput.value = Array.isArray(tags) ? tags.join(', ') : '';
        } catch { tagsInput.value = ''; }
      }
      openPostModal();
    } catch(e){ alert(e.message||'Erro ao editar'); }
  }

  async function deletePost(postId){
    if (!confirm('Tem certeza que deseja excluir esta publicação?')) return;
    try {
      const r = await fetch(API_URL, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId })
      });
      const t = await r.text();
      let j; try { j = JSON.parse(t); } catch { throw new Error('Resposta inválida do servidor'); }
      if (!j.success) throw new Error(j.message||'Erro ao excluir');
      const card = qs(`.post-card[data-post-id="${postId}"]`);
      if (card) card.remove();
    } catch(e){ alert(e.message||'Erro ao excluir'); }
  }

  // Likes
  async function togglePostLike(postId){
    try {
      const btn = qs(`.post-card[data-post-id="${postId}"] .like-btn`);
      const countEl = qs(`.post-card[data-post-id="${postId}"] .like-count`);
      const r = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify({ action: 'like_post', post_id: postId })
      });
      const t = await r.text();
      let j; try { j = JSON.parse(t); } catch { throw new Error('Resposta inválida do servidor'); }
      if (!j.success) throw new Error(j.message||'Erro ao curtir');
      if (btn) btn.classList.toggle('liked');
      if (countEl) {
        let n = parseInt(countEl.textContent || '0', 10) || 0;
        if (btn && btn.classList.contains('liked')) n++; else n = Math.max(0, n-1);
        countEl.textContent = String(n);
      }
    } catch(e){ alert(e.message||'Erro ao curtir'); }
  }

  // Comentários
  async function toggleComments(postId){
    const box = qs(`#comments-${postId}`);
    if (!box) return;
    const isHidden = box.style.display === 'none' || !box.style.display;
    box.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
      const list = box.querySelector('.comments-list');
      const needsFetch = !box.dataset.loaded || !(list && list.children && list.children.length);
      if (needsFetch) {
        await loadComments(postId);
      }
    }
  }

  function resolveAvatarUrl(avatar){
    const base = window.BASE_URL || window.SITE_URL || '';
    if (!avatar) return `${base}/assets/images/default-avatar.svg`;
    const val = String(avatar);
    const low = val.toLowerCase();
    if (low.startsWith('http://') || low.startsWith('https://') || low.startsWith('data:')) return val;
    if (val.startsWith('//')) return window.location.protocol + val;
    if (val.startsWith('/')) return `${base}${val}`;
    if (val.includes('/')) return `${base}/${val}`;
    return `${base}/uploads/avatars/${val}`;
  }

  async function loadComments(postId){
    try {
      const r = await fetch(`${API_URL}?comments=1&post_id=${postId}`);
      const t = await r.text();
      let j; try { j = JSON.parse(t); } catch { console.error('Resposta inválida dos comentários:', t); return; }
      if (!j.success) throw new Error(j.message||'Erro ao carregar comentários');
      const box = qs(`#comments-${postId}`);
      const list = qs(`#comments-${postId} .comments-list`);
      if (!list) return;
      list.innerHTML = '';
      if (!j.comments || !j.comments.length) {
        list.innerHTML = '<div class="comment-empty" style="color:#6b7280;font-size:14px;">Seja o primeiro a comentar</div>';
      } else {
        j.comments.forEach(c => {
          const el = document.createElement('div');
          el.className = 'comment-item';
          const avatarUrl = resolveAvatarUrl(c.author_avatar);
          el.innerHTML = `
            <div class="comment-header">
              <img class="comment-avatar" src="${avatarUrl}" alt="${(c.author_name||'Usuária').replace(/"/g,'&quot;')}">
              <span class="comment-author">${c.author_name||'Usuária'}</span>
              <span class="comment-time">${timeAgo(c.created_at||new Date().toISOString())}</span>
            </div>
            <div class="comment-content">${formatContent(c.content||'')}</div>
          `;
          list.appendChild(el);
        });
      }
      if (box) box.dataset.loaded = '1';
    } catch(e){ console.error(e); }
  }

  async function submitComment(ev, postId){
    ev.preventDefault();
    const form = ev.target.closest('form');
    if (!form) return;
    const textarea = form.querySelector('textarea');
    const content = (textarea?.value || '').trim();
    if (!content) return;
    try {
      const r = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add_comment', post_id: postId, content })
      });
      const t = await r.text();
      let j; try { j = JSON.parse(t); } catch { throw new Error('Resposta inválida do servidor'); }
      if (!j.success) throw new Error(j.message||'Erro ao comentar');
      textarea.value = '';
      const box = qs(`#comments-${postId}`);
      if (box) { box.dataset.loaded = ''; }
      await loadComments(postId);
      const btn = qs(`.post-card[data-post-id="${postId}"] .comment-count`);
      if (btn) { btn.textContent = String((parseInt(btn.textContent||'0',10)||0)+1); }
    } catch(e){ alert(e.message||'Erro ao comentar'); }
  }

  // Compartilhar
  async function sharePost(postId){
    const url = `${window.location.origin}${window.location.pathname}?post=${postId}`;
    if (navigator.share) {
      try { await navigator.share({ title: 'Publicação da Comunidade', url }); }
      catch(e){ /* cancelado */ }
    } else {
      try { await navigator.clipboard.writeText(url); alert('Link copiado!'); }
      catch { prompt('Copie o link:', url); }
    }
  }

  // Lightbox simples para imagem
  function openImageModal(imageUrl){
    try {
      const overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;z-index:10000;padding:24px;';
      overlay.setAttribute('role','dialog');
      overlay.setAttribute('aria-modal','true');

      const imgWrap = document.createElement('div');
      imgWrap.style.cssText = 'position:relative;max-width:90vw;max-height:90vh;';

      const img = document.createElement('img');
      img.src = imageUrl;
      img.alt = 'Imagem ampliada';
      img.style.cssText = 'max-width:100%;max-height:90vh;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.5);';

      const closeBtn = document.createElement('button');
      closeBtn.innerHTML = '&times;';
      closeBtn.title = 'Fechar';
      closeBtn.style.cssText = 'position:absolute;top:-12px;right:-12px;width:36px;height:36px;border:none;border-radius:50%;background:#fff;color:#111;font-size:22px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.3);';

      function close(){ document.removeEventListener('keydown', onKey); overlay.remove(); }
      function onKey(e){ if (e.key === 'Escape') close(); }

      overlay.addEventListener('click', (e)=>{ if (e.target === overlay) close(); });
      closeBtn.addEventListener('click', close);
      document.addEventListener('keydown', onKey);

      imgWrap.appendChild(img);
      imgWrap.appendChild(closeBtn);
      overlay.appendChild(imgWrap);
      document.body.appendChild(overlay);
    } catch { window.open(imageUrl, '_blank'); }
  }

  // Filtro por tag
  function filterByTag(tag){
    const u = new URL(window.location.href);
    u.searchParams.set('tag', tag);
    u.searchParams.delete('page');
    window.location.href = u.toString();
  }

  // Funções para abrir/fechar modal de evento
  function openEventModal() {
    let overlay = document.getElementById('create-event-modal');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'modal-overlay';
      overlay.id = 'create-event-modal';
      overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
      overlay.innerHTML = `
        <div class="modal bg-white" style="max-width:480px;width:100%;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.18);background:#fff;">
          <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px 0 20px;">
            <h3 style="margin:0;">Criar Evento</h3>
            <button class="modal-close" onclick="closeEventModal()" style="font-size:28px;background:none;border:none;cursor:pointer;">&times;</button>
          </div>
          <div class="modal-body" style="padding:20px;">
            <form id="event-form" class="ajax-form">
              <div class="form-group">
                <label for="event-title" class="form-label">Título *</label>
                <input type="text" id="event-title" name="title" class="form-control" required>
              </div>
              <div class="form-group">
                <label for="event-description" class="form-label">Descrição</label>
                <textarea id="event-description" name="description" class="form-control" rows="3"></textarea>
              </div>
              <div class="form-row" style="display:flex;gap:16px;">
                <div class="form-group" style="flex:1;">
                  <label for="event-date" class="form-label">Data *</label>
                  <input type="date" id="event-date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group" style="flex:1;">
                  <label for="event-end-date" class="form-label">Data Final</label>
                  <input type="date" id="event-end-date" name="end_date" class="form-control">
                </div>
                <div class="form-group" style="flex:1;">
                  <label for="event-time" class="form-label">Horário *</label>
                  <input type="time" id="event-time" name="start_time" class="form-control" required>
                </div>
              </div>
              <div class="form-group">
                <label for="event-type" class="form-label">Tipo</label>
                <select id="event-type" name="event_type" class="form-control">
                  <option value="workshop">Workshop</option>
                  <option value="mentoring">Mentoria</option>
                  <option value="presentation">Apresentação</option>
                  <option value="deadline">Deadline</option>
                  <option value="other">Outro</option>
                </select>
              </div>
            </form>
          </div>
          <div class="modal-footer" style="padding:0 20px 20px 20px;display:flex;justify-content:flex-end;gap:12px;">
            <button type="button" class="btn btn-secondary" onclick="closeEventModal()">Cancelar</button>
            <button type="submit" form="event-form" class="btn btn-primary" id="event-submit-btn">
              <span class="btn-text">Criar Evento</span>
              <span class="btn-loading" style="display:none;"><i class="fas fa-spinner fa-spin"></i></span>
            </button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
    } else {
      overlay.style.display = 'flex';
    }
    document.body.style.overflow = 'hidden';
    // Adiciona submit handler para criar evento
    const eventForm = overlay.querySelector('#event-form');
    const submitBtn = overlay.querySelector('#event-submit-btn');
    if (eventForm && submitBtn && !eventForm.dataset.bound) {
      eventForm.addEventListener('submit', async function(e){
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.querySelector('.btn-text').style.display = 'none';
        submitBtn.querySelector('.btn-loading').style.display = 'inline-block';
        const fd = new FormData(eventForm);
        // Se end_date não informado, usa start_date
        if (!fd.get('end_date')) {
          fd.set('end_date', fd.get('start_date'));
        }
        try {
          const r = await fetch('../api/calendar_events.php', { method: 'POST', body: fd });
          const t = await r.text();
          let j; try { j = JSON.parse(t); } catch { throw new Error('Resposta inválida do servidor'); }
          if (!j.success) throw new Error(j.message||'Erro ao criar evento');
          closeEventModal();
          window.location.reload();
        } catch(e){
          alert(e.message||'Erro ao criar evento');
        } finally {
          submitBtn.disabled = false;
          submitBtn.querySelector('.btn-text').style.display = 'inline';
          submitBtn.querySelector('.btn-loading').style.display = 'none';
        }
      });
      eventForm.dataset.bound = '1';
    }
  }
  function closeEventModal() {
    const modal = document.getElementById('create-event-modal');
    if (modal) {
      modal.style.display = 'none';
      document.body.style.overflow = 'auto';
    }
  }

  // Registrar globais usadas nos atributos onclick do HTML
  window.openPostModal = openPostModal;
  window.closePostModal = closePostModal;
  window.editPost = editPost;
  window.deletePost = deletePost;
  window.togglePostLike = togglePostLike;
  window.toggleComments = toggleComments;
  window.loadComments = loadComments;
  window.submitComment = submitComment;
  window.sharePost = sharePost;
  window.openImageModal = openImageModal;
  window.openEventModal = openEventModal;
  window.closeEventModal = closeEventModal;
  window.filterByTag = filterByTag;

  // Submit do formulário de post (criar/editar)
  document.addEventListener('DOMContentLoaded', function(){
    const form = qs('#post-form');
    if (form) {
      form.addEventListener('submit', async function(e){
        e.preventDefault();
        const fd = new FormData(form);
        try {
          const r = await fetch(API_URL, { method: 'POST', body: fd });
          const t = await r.text();
          let j; try { j = JSON.parse(t); } catch { throw new Error('Resposta inválida do servidor'); }
          if (!j.success) throw new Error(j.message||'Erro ao salvar');
          closePostModal();
          window.location.reload();
        } catch(err){ alert(err.message||'Erro ao salvar'); }
      });
    }

    // Mentions/hashtags sugestão simples no conteúdo
    const contentEl = qs('#post-content');
    if (contentEl) {
      let box = document.createElement('div');
      box.id = 'suggestions-box';
      box.className = 'suggestions-box';
      box.style.display = 'none';
      contentEl.parentElement.appendChild(box);

      let trigger = null; // '@' ou '#'
      let triggerIndex = -1;

      const popularTags = ['javascript','react','python','css','nodejs','frontend','backend','devops','php','laravel'];

      async function showUserSuggestions(query){
        try {
          const baseUrl = window.BASE_URL || (window.location.origin + '/Orya');
          const resp = await fetch(`${baseUrl}/api/users.php`);
          const users = await resp.json();
          const filtered = users.filter(u => u.name && u.name.toLowerCase().includes(query.toLowerCase())).slice(0,5);
          renderSuggestions(filtered.map(u => ({ type:'user', label: u.name, value: u.name })));
        } catch {}
      }

      function showTagSuggestions(query){
        const filtered = popularTags.filter(t => t.toLowerCase().includes(query.toLowerCase())).slice(0,8);
        renderSuggestions(filtered.map(t => ({ type:'tag', label: '#'+t, value: t })));
      }

      function renderSuggestions(items){
        if (!items.length){ box.style.display='none'; return; }
        box.innerHTML = items.map(it => `<div class="suggestion-item" data-type="${it.type}" data-value="${it.value}">${it.label}</div>`).join('');
        box.style.display = 'block';
      }

      function insertSuggestion(value){
        const val = contentEl.value;
        if (triggerIndex >=0) {
          const before = val.slice(0, triggerIndex);
          const after = val.slice(contentEl.selectionStart);
          const insert = trigger === '@' ? `@${value} ` : `#${value} `;
          contentEl.value = before + insert + after;
          const pos = (before + insert).length;
          contentEl.setSelectionRange(pos, pos);
        }
        box.style.display = 'none';
        trigger = null; triggerIndex = -1;
      }

      contentEl.addEventListener('keyup', function(e){
        const caret = contentEl.selectionStart;
        const val = contentEl.value;
        const lastChar = val[caret-1];
        if (!trigger && (lastChar === '@' || lastChar === '#')){
          trigger = lastChar; triggerIndex = caret-1;
          if (trigger === '#') showTagSuggestions(''); else showUserSuggestions('');
          return;
        }
        if (!trigger) return;
        // coletar token após trigger
        let i = triggerIndex+1; let token='';
        while(i < val.length){ const ch = val[i]; if (/\s/.test(ch)) break; token += ch; i++; }
        if (token.length === 0){ if (trigger === '#') showTagSuggestions(''); else showUserSuggestions(''); }
        else { if (trigger === '#') showTagSuggestions(token); else showUserSuggestions(token); }
        if (e.key === 'Escape'){ box.style.display='none'; trigger=null; triggerIndex=-1; }
      });

      box.addEventListener('click', function(e){
        const item = e.target.closest('.suggestion-item');
        if (!item) return;
        insertSuggestion(item.dataset.value);
      });

      document.addEventListener('click', function(e){ if (!box.contains(e.target) && e.target !== contentEl){ box.style.display='none'; } });
    }

    // Seção de links pessoais (toggle)
    const linksToggle = qs('#hidden-links-toggle');
    const linksBox = qs('#hidden-links');
    if (linksToggle && linksBox){
      linksToggle.addEventListener('click', ()=>{
        const vis = linksBox.style.display === 'block';
        linksBox.style.display = vis ? 'none' : 'block';
        linksToggle.textContent = vis ? 'Mostrar' : 'Esconder';
      });
    }

    // Composer de post (estilo Facebook)
    const textEl = qs('#composer-text');
    const titleEl = qs('#composer-title');
    const tagsEl = qs('#composer-tags');
    const tagsBtn = qs('#composer-tags-btn');
    const photoBtn = qs('#composer-photo-btn');
    const fileEl = qs('#composer-image');
    const submitBtn = qs('#composer-submit');
    const preview = qs('#composer-preview');
    const dropzone = qs('#composer-dropzone');

    let attachedFile = null;

    function updateSubmitState(){
      const hasText = (textEl?.value.trim().length || 0) > 0;
      submitBtn.disabled = !hasText && !attachedFile;
    }

    if (textEl) {
      textEl.addEventListener('input', updateSubmitState);
      // Paste image support
      textEl.addEventListener('paste', function(e){
        if (!e.clipboardData) return;
        const item = Array.from(e.clipboardData.items).find(i => i.type.startsWith('image/'));
        if (item) {
          const file = item.getAsFile();
          if (file) handleFile(file);
        }
      });
      // Drag and drop events
      ;['dragenter','dragover'].forEach(evt => textEl.addEventListener(evt, (e)=>{ e.preventDefault(); if (dropzone){ dropzone.style.display='block'; dropzone.classList.add('active'); dropzone.textContent = 'Solte a imagem aqui'; }}));
      ;['dragleave','drop'].forEach(evt => textEl.addEventListener(evt, (e)=>{ e.preventDefault(); if (dropzone){ dropzone.classList.remove('active'); if(evt==='drop'){ dropzone.style.display='none'; } }}));
      textEl.addEventListener('drop', (e)=>{
        const file = e.dataTransfer?.files?.[0];
        if (file && file.type.startsWith('image/')) handleFile(file);
      });
    }

    if (tagsBtn && tagsEl){
      tagsBtn.addEventListener('click', ()=>{ tagsEl.style.display = tagsEl.style.display==='none' || !tagsEl.style.display ? 'block':'none'; tagsEl.focus(); });
    }

    function handleFile(file){
      attachedFile = file;
      const reader = new FileReader();
      reader.onload = function(){
        preview.style.display = 'block';
        preview.innerHTML = `<img src="${reader.result}" alt="Pré-visualização"><div class="preview-actions"><button type="button" class="btn btn-sm btn-outline" id="remove-attachment"><i class="fas fa-times"></i> Remover</button></div>`;
        qs('#remove-attachment')?.addEventListener('click', ()=>{ attachedFile=null; preview.style.display='none'; preview.innerHTML=''; fileEl.value=''; updateSubmitState(); });
      };
      reader.readAsDataURL(file);
      updateSubmitState();
    }

    if (photoBtn && fileEl){ photoBtn.addEventListener('click', ()=> fileEl.click()); }
    if (fileEl){ fileEl.addEventListener('change', ()=>{ const f = fileEl.files?.[0]; if (f && f.type.startsWith('image/')) handleFile(f); }); }

    if (submitBtn){
      submitBtn.addEventListener('click', async function(){
        const content = textEl?.value.trim() || '';
        const title = titleEl?.value.trim() || (content.length > 60 ? content.slice(0,60)+'...' : content);
        const tags = (tagsEl?.value || '').trim();
        if (!content && !attachedFile){ return; }
        const fd = new FormData();
        fd.append('title', title || 'Publicação');
        fd.append('content', content);
        fd.append('type', 'discussion');
        if (tags) fd.append('tags', tags);
        if (attachedFile) fd.append('image', attachedFile);
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publicando...';
        try {
          const r = await fetch(API_URL, { method: 'POST', body: fd });
          const t = await r.text();
          let j; try { j = JSON.parse(t); } catch { throw new Error('Resposta inválida do servidor'); }
          if (!j.success) throw new Error(j.message||'Erro ao publicar');
          window.location.reload();
        } catch(e){
          alert(e.message||'Erro ao publicar');
        } finally {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Publicar';
        }
      });
    }
  });
})();
