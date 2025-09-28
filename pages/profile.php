<?php
$page_title = 'Perfil';
$page_name = 'profile';
$additional_css = ['profile'];

require_once '../includes/header.php';
?>

<div class="profile-container">
  <aside class="profile-card">
    <div class="profile-avatar-wrap">
      <img id="profile-avatar" class="profile-avatar" src="<?php echo $current_user['avatar'] ?: SITE_URL . '/assets/images/default-avatar.svg'; ?>" alt="Avatar">
      <div class="avatar-actions">
        <input id="avatar-file" type="file" accept="image/*" style="display:none">
        <button class="btn btn-sm btn-outline" id="btn-upload-avatar"><i class="fas fa-camera"></i> Trocar foto</button>
        <button class="btn btn-sm btn-ghost" id="btn-remove-avatar"><i class="fas fa-trash"></i></button>
      </div>
      <div class="stats">
        <div class="stat"><div class="num" id="stat-projects">0</div><div>Projetos</div></div>
        <div class="stat"><div class="num" id="stat-meetings">0</div><div>Reuniões</div></div>
        <div class="stat"><div class="num" id="stat-posts">0</div><div>Posts</div></div>
        <div class="stat"><div class="num" id="stat-repos">0</div><div>Repositórios</div></div>
      </div>
      <div class="links">
        <?php if (!empty($current_user['github_url'])): ?>
          <a href="<?php echo escape($current_user['github_url']); ?>" target="_blank" rel="noopener"><i class="fab fa-github"></i> GitHub</a>
        <?php endif; ?>
        <?php if (!empty($current_user['linkedin_url'])): ?>
          <a href="<?php echo escape($current_user['linkedin_url']); ?>" target="_blank" rel="noopener"><i class="fab fa-linkedin"></i> LinkedIn</a>
        <?php endif; ?>
      </div>
    </div>
  </aside>

  <section class="profile-card profile-info">
    <h3>Informações do Perfil</h3>
    <form id="profile-form">
      <div class="form-group">
        <label class="form-label" for="name">Nome</label>
        <input id="name" type="text" class="form-control" value="<?php echo escape($current_user['name']); ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="email">E-mail</label>
        <input id="email" type="email" class="form-control" value="<?php echo escape($current_user['email']); ?>" disabled>
      </div>
      <div class="form-group">
        <label class="form-label" for="bio">Bio</label>
        <textarea id="bio" class="form-control" rows="4" placeholder="Fale um pouco sobre você..."><?php echo escape($current_user['bio'] ?? ''); ?></textarea>
      </div>
      <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div class="form-group">
          <label class="form-label" for="github">GitHub</label>
          <input id="github" type="url" class="form-control" placeholder="https://github.com/usuario" value="<?php echo escape($current_user['github_url'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="linkedin">LinkedIn</label>
          <input id="linkedin" type="url" class="form-control" placeholder="https://linkedin.com/in/usuario" value="<?php echo escape($current_user['linkedin_url'] ?? ''); ?>">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
      </div>
    </form>
  </section>
</div>

<script>
window.SITE_URL = '<?php echo SITE_URL; ?>';
</script>
<?php $additional_js = ['profile']; require_once '../includes/footer.php'; ?>
