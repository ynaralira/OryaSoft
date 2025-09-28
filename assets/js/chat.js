// Chat WebSocket client
(function(){
  const DEFAULT_WS = (window.CHAT_WS_URL) || 'ws://localhost:8080';

  // Helpers
  const qs=(s,r=document)=>r.querySelector(s); const qsa=(s,r=document)=>Array.from(r.querySelectorAll(s));
  function uid(){ return Math.random().toString(36).slice(2,10); }
  function now(){ return new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); }

  let ws=null; let reconnectTimer=null; let reconnectAttempts=0; let userId=null; let userName=null; let currentRoom='geral';

  function setStatus(state){
    const dot = qs('.connection-dot');
    const label = qs('#connection-label');
    dot?.classList.remove('online','connecting');
    if (state==='online'){ dot?.classList.add('online'); label && (label.textContent='Conectado'); }
    else if (state==='connecting'){ dot?.classList.add('connecting'); label && (label.textContent='Conectando...'); }
    else { label && (label.textContent='Offline'); }
  }

  function addMessage({type='chat', user, text, time, self=false}){
    const list = qs('.messages'); if(!list) return;
    const wrap = document.createElement('div');
    wrap.className = type==='system'? 'message system' : `message ${self?'you':''}`;
    const avatar = `<img class="avatar" src="${(window.SITE_URL||'')+'/assets/images/default-avatar.svg'}" alt="">`;
    const bubble = type==='system'
      ? `<div class="bubble"><div class="text">${text}</div></div>`
      : `<div class="bubble"><div class="meta"><strong>${user||'Anônimo'}</strong><span>${time||now()}</span></div><div class="text">${text}</div></div>`;
    wrap.innerHTML = type==='system'? bubble : `${avatar}${bubble}`;
    list.appendChild(wrap);
    list.scrollTop = list.scrollHeight;
  }

  function setUsersCount(n){ const el = qs('#room-users'); if (el) el.textContent = `${n} online`; }

  function send(payload){ if (ws && ws.readyState===1) ws.send(JSON.stringify(payload)); }

  function joinRoom(room){ currentRoom = room; send({type:'join', room}); }

  function connect(){
    setStatus('connecting');
    try {
      ws = new WebSocket(DEFAULT_WS);
    } catch(e){ console.error(e); scheduleReconnect(); return; }

    ws.onopen = () => {
      setStatus('online'); reconnectAttempts=0;
      userId = userId || (window.CURRENT_USER_ID || uid());
      userName = userName || (window.CURRENT_USER_NAME || 'Usuária');
      const token = window.CHAT_AUTH_TOKEN || null;
      send({ type:'hello', userId, userName, token });
      joinRoom(currentRoom);
      addMessage({ type:'system', text:'Conectado ao servidor de chat.' });
    };

    ws.onmessage = (ev) => {
      let msg; try { msg = JSON.parse(ev.data); } catch { return; }
      switch (msg.type){
        case 'chat':
          addMessage({ user: msg.userName, text: msg.text, time: msg.time, self: msg.userId===userId });
          break;
        case 'system':
          addMessage({ type:'system', text: msg.text });
          break;
        case 'users':
          setUsersCount(msg.count||1);
          break;
        case 'rooms':
          renderRooms(msg.rooms||[]);
          break;
      }
    };

    ws.onclose = () => { setStatus('offline'); addMessage({ type:'system', text:'Desconectado. Tentando reconectar...' }); scheduleReconnect(); };
    ws.onerror = () => { setStatus('offline'); };
  }

  function scheduleReconnect(){
    if (reconnectTimer) return;
    const delay = Math.min(30000, 1000 * Math.pow(2, reconnectAttempts++));
    reconnectTimer = setTimeout(()=>{ reconnectTimer=null; connect(); }, delay);
  }

  function renderRooms(rooms){
    const list = qs('.room-list'); if (!list) return;
    list.innerHTML = '';
    rooms.forEach(r =>{
      const el = document.createElement('div');
      el.className = 'room-item' + (r.name===currentRoom? ' active':'');
      el.innerHTML = `<span class="room-name"># ${r.name}</span><span class="room-count">${r.count||0}</span>`;
      el.addEventListener('click', ()=>{ currentRoom = r.name; qsa('.room-item').forEach(i=>i.classList.remove('active')); el.classList.add('active'); qs('#chat-title').textContent = `# ${r.name}`; qs('.messages').innerHTML=''; joinRoom(r.name); });
      list.appendChild(el);
    });
  }

  function bindUI(){
    const form = qs('#chat-form');
    const textarea = qs('#chat-input');
    const roomInput = qs('#new-room');
    const createBtn = qs('#create-room');

    form?.addEventListener('submit', function(ev){
      ev.preventDefault();
      const text = (textarea?.value||'').trim();
      if (!text) return;
      addMessage({ user: userName, text, self:true });
      send({ type:'chat', text, room: currentRoom, time: now(), userId, userName });
      textarea.value=''; textarea.focus();
    });

    textarea?.addEventListener('input', ()=>{ send({ type:'typing', room: currentRoom, userName }); });

    createBtn?.addEventListener('click', ()=>{
      const name = (roomInput?.value||'').trim();
      if (!name) return;
      send({ type:'create_room', name });
      roomInput.value='';
    });
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    bindUI();
    connect();
  });
})();
