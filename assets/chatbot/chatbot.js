(function(){
  const BASE = window.BASE_URL || '';
  function qs(sel, ctx=document){ return ctx.querySelector(sel); }
  function el(tag, attrs={}){ const n=document.createElement(tag); Object.assign(n, attrs); return n; }

  function ensurePanel(){
    let panel = qs('#orya-chatbot');
    if (panel) return panel;
    panel = el('div', { id: 'orya-chatbot', className: 'orya-chatbot-panel' });
    panel.innerHTML = `
      <div class="orya-chatbot-header">
        <img src="${BASE}/assets/chatbot/icon.svg" alt="bot" width="22" height="22"/>
        <div class="title">Orya Assistente</div>
        <button class="close" aria-label="Fechar">&times;</button>
      </div>
      <div class="orya-chatbot-messages"></div>
      <div class="orya-chatbot-suggestions"></div>
      <div class="orya-chatbot-input">
        <input type="text" placeholder="Digite sua pergunta..."/>
        <button class="send">Enviar</button>
      </div>`;
    document.body.appendChild(panel);

    // Eventos
    qs('.close', panel).addEventListener('click', () => toggle(false));
    const input = qs('input', panel);
    const sendBtn = qs('.send', panel);
    const suggestions = qs('.orya-chatbot-suggestions', panel);

    function send(text){
      if (!text) return;
      addMsg('user', text);
      input.value='';
      setTimeout(()=> respond(text), 350);
    }

    input.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ send(input.value.trim()); }});
    sendBtn.addEventListener('click', ()=> send(input.value.trim()));

    // Sugestões iniciais
    const chips = getSuggestions();
    chips.forEach(c => {
      const b = el('button', { className:'chip', type:'button' });
      b.textContent = c;
      b.addEventListener('click', ()=> send(c));
      suggestions.appendChild(b);
    });

    greet();
    return panel;
  }

  function getSuggestions(){
    return [
      'Como criar uma reunião?',
      'Onde vejo meus projetos (Kanban)?',
      'Como importar repositório do GitHub?',
      'Como falar com a comunidade?',
      'Como editar meu perfil?'
    ];
  }

  function greet(){
    addMsg('bot', 'Olá! Sou a assistente da Orya. Posso te ajudar com atalhos e respostas rápidas. O que você precisa hoje?');
  }

  function addMsg(who, text){
    const panel = ensurePanel();
    const box = qs('.orya-chatbot-messages', panel);
    const wrap = el('div', { className: 'orya-chatbot-msg ' + who });
    const bubble = el('div', { className: 'orya-chatbot-bubble' });
    bubble.textContent = text;
    wrap.appendChild(bubble);
    box.appendChild(wrap);
    box.scrollTop = box.scrollHeight;
  }

  function respond(text){
    const t = text.toLowerCase();
    // Respostas estáticas configuradas
    if (t.includes('reuni') || t.includes('meeting')) {
      addMsg('bot', 'Para criar uma nova reunião, vá em Reuniões > botão "Nova Reunião". Você também pode clicar em um dia no calendário.');
      return;
    }
    if (t.includes('kanban') || t.includes('projeto')) {
      addMsg('bot', 'Seus projetos estão em Projetos. Lá você pode criar quadros, listas e tarefas, e arrastar para mudar o status.');
      return;
    }
    if (t.includes('github') || t.includes('repo')) {
      addMsg('bot', 'Na página Repositórios use "Importar do GitHub" e selecione um repositório público para trazer os dados.');
      return;
    }
    if (t.includes('comunidade')) {
      addMsg('bot', 'A Comunidade mostra o feed de posts. Você pode publicar, comentar e usar #hashtags e @menções.');
      return;
    }
    if (t.includes('perfil') || t.includes('avatar')) {
      addMsg('bot', 'Edite seu perfil em Perfil. Dá para trocar nome, bio e foto (arraste uma imagem ou escolha um arquivo).');
      return;
    }
    addMsg('bot', 'Desculpe, não entendi. Tente uma das sugestões abaixo ou reformule sua pergunta.');
  }

  function toggle(force){
    const panel = ensurePanel();
    const open = force !== undefined ? force : !panel.classList.contains('open');
    panel.style.display = open ? 'flex' : 'none';
    panel.classList.toggle('open', open);
  }

  // Expor global e botão
  window.OryaChatbot = { toggle };

  // Injeta botão no topo ao lado do sino
  document.addEventListener('DOMContentLoaded', () => {
    const topbarRight = document.querySelector('.topbar-right');
    const notifDropdown = topbarRight?.querySelector('.dropdown');
    if (!topbarRight || !notifDropdown) return;

    const btn = document.createElement('button');
    btn.id = 'orya-chatbot-toggle';
    btn.title = 'Abrir Assistente';
    btn.innerHTML = `<img alt="assistente" src="${BASE}/assets/chatbot/icon.svg"/>`;
    btn.addEventListener('click', ()=> toggle());

    // Inserir antes do dropdown de perfil (após notificações)
    notifDropdown.insertAdjacentElement('afterend', btn);
  });
})();
