// Perfil - alterar avatar e informações
(function(){
  const API_URL = '../api/user.php';
  const qs=(s,r=document)=>r.querySelector(s);

  function notify(msg){ alert(msg); }

  async function loadMe(){
    try{
      const r = await fetch(`${API_URL}`);
      const j = await r.json();
      if (!j.success) throw new Error(j.message||'Falha ao carregar perfil');
      const u = j.user;
      qs('#name').value = u.name||'';
      qs('#email').value = u.email||'';
      qs('#bio').value = u.bio||'';
      qs('#github').value = u.github_url||'';
      qs('#linkedin').value = u.linkedin_url||'';
      const avatar = u.avatar || (window.SITE_URL+'/assets/images/default-avatar.svg');
      qs('#profile-avatar').src = avatar;
      qs('#stat-projects').textContent = u.stats?.projects_count ?? 0;
      qs('#stat-meetings').textContent = u.stats?.meetings_count ?? 0;
      qs('#stat-posts').textContent = u.stats?.posts_count ?? 0;
      qs('#stat-repos').textContent = u.stats?.repositories_count ?? 0;
    }catch(e){ notify(e.message); }
  }

  function bindAvatar(){
    const file = qs('#avatar-file');
    const btn = qs('#btn-upload-avatar');
    const remove = qs('#btn-remove-avatar');
    btn?.addEventListener('click', ()=> file?.click());
    file?.addEventListener('change', async ()=>{
      const f = file.files?.[0]; if (!f) return;
      if (!/^image\//.test(f.type)) { notify('Envie uma imagem'); return; }
      if (f.size > 2*1024*1024) { notify('Máx 2MB'); return; }
      const fd = new FormData(); fd.append('action','upload_avatar'); fd.append('avatar', f);
      try{
        const r = await fetch(API_URL, { method:'POST', body: fd });
        const j = await r.json();
        if (!j.success) throw new Error(j.message||'Erro ao enviar');
        qs('#profile-avatar').src = j.avatar_url;
      }catch(e){ notify(e.message); }
      finally { file.value=''; }
    });
    remove?.addEventListener('click', ()=>{
      // Opcional: endpoint para remover avatar e voltar ao padrão
      qs('#profile-avatar').src = (window.SITE_URL+'/assets/images/default-avatar.svg');
    });
  }

  function bindProfileForm(){
    const form = qs('#profile-form');
    form?.addEventListener('submit', async (ev)=>{
      ev.preventDefault();
      const payload = {
        name: qs('#name').value.trim(),
        bio: qs('#bio').value.trim(),
        github_url: qs('#github').value.trim(),
        linkedin_url: qs('#linkedin').value.trim()
      };
      try{
        const r = await fetch(API_URL, { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const j = await r.json();
        if (!j.success) throw new Error(j.message||'Erro ao salvar');
        notify('Perfil atualizado');
      }catch(e){ notify(e.message); }
    });
  }

  document.addEventListener('DOMContentLoaded', ()=>{ loadMe(); bindAvatar(); bindProfileForm(); });
})();
