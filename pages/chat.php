<?php
$page_title = 'Chat';
$page_name = 'chat';
$additional_css = ['chat'];

require_once '../includes/header.php';
?>

<div class="chat-container">
  <div class="page-header">
    <div class="header-content">
      <h1>Chat em Tempo Real</h1>
      <p>Converse com a comunidade em salas públicas</p>
    </div>
    <div class="header-actions">
      <div class="connection-status"><span class="connection-dot" title="status"></span><span id="connection-label">Offline</span></div>
    </div>
  </div>

  <div class="chat-layout">
    <!-- Sidebar -->
    <aside class="chat-sidebar">
      <div class="sidebar-header">
        <strong>Salas</strong>
        <div class="room-actions">
          <div class="create-room">
            <input id="new-room" type="text" placeholder="nova-sala" />
            <button class="btn btn-sm btn-outline" id="create-room">Criar</button>
          </div>
        </div>
      </div>
      <div class="room-list">
        <!-- Preenchido via WS: rooms -->
      </div>
    </aside>

    <!-- Main chat -->
    <section class="chat-main">
      <div class="chat-header">
        <div class="chat-title">
          <i class="fas fa-hashtag"></i>
          <h3 id="chat-title"># geral</h3>
        </div>
        <div class="chat-users" id="room-users">0 online</div>
      </div>

      <div class="messages">
        <!-- mensagens -->
      </div>
      <div class="typing" id="typing-indicator" style="display:none;">Alguém está digitando...</div>

      <form class="input-area" id="chat-form">
        <textarea id="chat-input" placeholder="Escreva uma mensagem..." rows="1"></textarea>
        <button type="submit" class="btn btn-primary send-btn"><i class="fas fa-paper-plane"></i> Enviar</button>
      </form>
    </section>
  </div>
</div>

<script>
// Configurações do cliente
window.SITE_URL = '<?php echo SITE_URL; ?>';
window.CURRENT_USER_ID = '<?php echo (int)$current_user['id']; ?>';
window.CURRENT_USER_NAME = '<?php echo escape($current_user['name']); ?>';
window.CHAT_WS_URL = 'ws://localhost:8080';

// Obter token de autenticação do chat
fetch('<?php echo SITE_URL; ?>/api/chat-auth.php', { credentials:'include' })
  .then(r=>r.json())
  .then(j=>{ if (j.success){ window.CHAT_AUTH_TOKEN = j.token; } else { console.warn('Falha ao obter token do chat'); } })
  .catch(()=>{});
</script>

<?php 
$additional_js = ['chat'];
require_once '../includes/footer.php';
?>
