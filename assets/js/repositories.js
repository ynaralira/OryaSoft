// Repositórios - interações da página
(function(){
  const API_URL = '../api/repositories.php';

  // Helpers
  const qs = (s, r=document)=>r.querySelector(s);
  const qsa = (s, r=document)=>Array.from(r.querySelectorAll(s));

  // Modal Repo
  function openRepositoryModal(){
    qs('#repo-modal-title').textContent = 'Adicionar Repositório';
    qs('#repository-form').reset();
    qs('#repository-id').value = '';
    qs('#repository-modal').style.display = 'flex';
  }
  function closeRepositoryModal(){ qs('#repository-modal').style.display = 'none'; }

  // Modal GitHub Import
  function importFromGitHub(){ qs('#github-import-modal').style.display = 'flex'; }
  function closeGitHubImportModal(){
    qs('#github-import-modal').style.display = 'none';
    const list = qs('#github-repos-list'); if (list){ list.style.display = 'none'; list.innerHTML=''; }
  }

  function handleSearch(ev){ if (ev.key === 'Enter') applyFilters(); }
  function applyFilters(){
    const search = qs('#search-input')?.value || '';
    const language = qs('#language-filter')?.value || '';
    const visibility = qs('#visibility-filter')?.value || 'all';
    const sort = qs('#sort-filter')?.value || 'recent';
    const url = new URL(window.location.href);
    url.searchParams.set('search', search);
    url.searchParams.set('language', language);
    url.searchParams.set('visibility', visibility);
    url.searchParams.set('sort', sort);
    window.location.href = url.toString();
  }

  // CRUD
  async function editRepository(id){
    try {
      const r = await fetch(`${API_URL}?id=${id}`);
      const j = await r.json();
      if (!j.success) throw new Error(j.message||'Erro ao carregar');
      const repo = j.repository;
      qs('#repo-modal-title').textContent = 'Editar Repositório';
      qs('#repository-id').value = repo.id;
      qs('#repo-name').value = repo.name||'';
      qs('#repo-description').value = repo.description||'';
      qs('#repo-url').value = repo.url||'';
      qs('#repo-demo-url').value = repo.demo_url||'';
      qs('#repo-language').value = repo.primary_language||'';
      qs('#repo-visibility').value = repo.visibility||'public';
      // Tecnologias
      try{ const techs = JSON.parse(repo.technologies||'[]'); qs('#repo-technologies').value = Array.isArray(techs)? techs.join(', '):''; }catch{}
      qs('#repository-modal').style.display = 'flex';
    } catch(e){ alert(e.message||'Erro ao editar'); }
  }

  async function deleteRepository(id){
    if (!confirm('Tem certeza que deseja excluir este repositório?')) return;
    try {
      const r = await fetch(API_URL, { method:'DELETE', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ repository_id: id }) });
      const j = await r.json();
      if (!j.success) throw new Error(j.message||'Erro ao excluir');
      // remover card
      const cards = qsa('.repository-card');
      const card = cards.find(c=> c.querySelector('.repo-action-btn[onclick^="editRepository("]')?.getAttribute('onclick')?.includes(`(${id})`));
      (card||{}).remove?.();
    } catch(e){ alert(e.message||'Erro ao excluir'); }
  }

  async function toggleLike(id){
    try {
      const btn = document.querySelector(`.repo-action-btn.like-btn[onclick="toggleLike(${id})"]`);
      const countEl = btn?.querySelector('.like-count');
      const r = await fetch(API_URL, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'like', repository_id: id }) });
      const j = await r.json();
      if (!j.success) throw new Error(j.message||'Erro ao curtir');
      if (btn){
        const liked = !btn.classList.contains('liked');
        btn.classList.toggle('liked', liked);
        if (countEl){
          let n = parseInt(countEl.textContent||'0',10)||0;
          countEl.textContent = String(liked? n+1: Math.max(0, n-1));
        }
      }
    } catch(e){ alert(e.message||'Erro ao curtir'); }
  }

  // GitHub Import
  const GITHUB_API = 'https://api.github.com';
  function buildLangPill(lang){ return lang? `<span class="language-tag"><span class="language-color" style="background:#d1d5db"></span>${lang}</span>`:''; }

  async function fetchGitHubRepos(){
    const username = qs('#github-username')?.value?.trim();
    const includePrivate = qs('#include-private')?.checked;
    if (!username){ alert('Digite um nome de usuário do GitHub'); return; }
    const list = qs('#github-repos-list');
    const fetchBtn = qs('#fetch-repos-btn');
    const importBtn = qs('#import-repos-btn');
    if (fetchBtn){ fetchBtn.disabled = true; fetchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...'; }
    try {
      const resp = await fetch(`${GITHUB_API}/users/${encodeURIComponent(username)}/repos?per_page=100&type=${includePrivate?'all':'public'}`);
      if (!resp.ok) throw new Error('Falha ao buscar repositórios');
      const repos = await resp.json();
      if (!Array.isArray(repos)) throw new Error('Resposta inesperada do GitHub');
      list.style.display = 'block';
      list.innerHTML = repos.map(r=>`
        <label class="gh-repo">
          <input type="checkbox" value="${encodeURIComponent(r.html_url)}" data-name="${r.name}" data-desc="${r.description||''}" data-homepage="${r.homepage||''}" data-lang="${r.language||''}" data-topics='${JSON.stringify(r.topics||[])}' />
          <div class="gh-info">
            <div class="gh-name">${r.name}</div>
            <div class="gh-desc">${r.description||''}</div>
            <div class="gh-meta">
              ${buildLangPill(r.language||'')} <span><i class="far fa-star"></i> ${r.stargazers_count}</span> <span><i class="fas fa-code-branch"></i> ${r.forks_count}</span>
            </div>
          </div>
        </label>
      `).join('');
      if (importBtn){ importBtn.style.display = 'inline-block'; }
    } catch(e){ alert(e.message||'Erro ao buscar GitHub'); }
    finally { if (fetchBtn){ fetchBtn.disabled=false; fetchBtn.innerHTML = '<i class="fab fa-github"></i> Buscar Repositórios'; } }
  }

  async function importSelectedRepos(){
    const list = qs('#github-repos-list');
    const checkboxes = qsa('input[type="checkbox"]', list).filter(cb=>cb.checked);
    if (checkboxes.length === 0){ alert('Selecione ao menos um repositório'); return; }
    const payload = checkboxes.map(cb=>({
      name: cb.dataset.name,
      description: cb.dataset.desc,
      html_url: decodeURIComponent(cb.value),
      homepage: cb.dataset.homepage || null,
      language: cb.dataset.lang || '',
      topics: JSON.parse(cb.dataset.topics||'[]'),
      private: false
    }));
    const importBtn = qs('#import-repos-btn');
    if (importBtn){ importBtn.disabled = true; importBtn.textContent = 'Importando...'; }
    try {
      const r = await fetch(API_URL, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'import_github', github_repos: payload }) });
      const j = await r.json();
      if (!j.success) throw new Error(j.message||'Erro ao importar');
      alert('Importação concluída');
      window.location.reload();
    } catch(e){ alert(e.message||'Erro ao importar'); }
    finally { if (importBtn){ importBtn.disabled=false; importBtn.textContent = 'Importar Selecionados'; } }
  }

  // Submit form create/update
  function bindForm(){
    const form = qs('#repository-form');
    if (!form) return;
    form.addEventListener('submit', async function(ev){
      ev.preventDefault();
      const btn = form.closest('.modal').querySelector('.btn-primary');
      const btnText = btn.querySelector('.btn-text');
      const btnLoading = btn.querySelector('.btn-loading');
      if (btn){ btn.disabled = true; if(btnText) btnText.style.display='none'; if(btnLoading) btnLoading.style.display='inline-block'; }
      try {
        const fd = new FormData(form);
        const r = await fetch(API_URL, { method:'POST', body: fd });
        const j = await r.json();
        if (!j.success) throw new Error(j.message||'Erro ao salvar');
        closeRepositoryModal();
        window.location.reload();
      } catch(e){ alert(e.message||'Erro ao salvar'); }
      finally { if (btn){ btn.disabled=false; if(btnText) btnText.style.display='inline'; if(btnLoading) btnLoading.style.display='none'; } }
    });
  }

  // Expor globais usadas no HTML
  window.openRepositoryModal = openRepositoryModal;
  window.closeRepositoryModal = closeRepositoryModal;
  window.importFromGitHub = importFromGitHub;
  window.closeGitHubImportModal = closeGitHubImportModal;
  window.handleSearch = handleSearch;
  window.applyFilters = applyFilters;
  window.editRepository = editRepository;
  window.deleteRepository = deleteRepository;
  window.toggleLike = toggleLike;
  window.fetchGitHubRepos = fetchGitHubRepos;
  window.importSelectedRepos = importSelectedRepos;

  document.addEventListener('DOMContentLoaded', bindForm);
})();
