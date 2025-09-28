<?php
$page_title = 'Repositórios';
$page_name = 'repositories';
$additional_css = ['repositories'];

require_once '../includes/header.php';

// Helpers de compatibilidade com o banco (somente nesta página)
if (!function_exists('db_table_exists')) {
    function db_table_exists($table){
        global $pdo; try{
            $table = preg_replace('/[^a-zA-Z0-9_]/','',$table);
            $stmt = $pdo->query("SHOW TABLES LIKE '".$table."'");
            return (bool)($stmt && $stmt->fetch());
        }catch(Throwable $e){ return false; }
    }
}
if (!function_exists('db_column_exists')) {
    function db_column_exists($table,$column){
        global $pdo; try{
            $table = preg_replace('/[^a-zA-Z0-9_]/','',$table);
            $column = preg_replace('/[^a-zA-Z0-9_]/','',$column);
            $stmt = $pdo->query("SHOW COLUMNS FROM `".$table."` LIKE '".$column."'");
            return (bool)($stmt && $stmt->fetch());
        }catch(Throwable $e){ return false; }
    }
}

// Buscar repositórios do usuário (compatível com variações de esquema)
try {
    $userCol = db_column_exists('repositories','user_id') ? 'user_id' : 'owner_id';
    $hasVisibility = db_column_exists('repositories','visibility');
    $hasIsPrivate = db_column_exists('repositories','is_private');
    $hasIsActive = db_column_exists('repositories','is_active');
    $likeTblExists = db_table_exists('repository_likes');

    $selectLikes = $likeTblExists ? "COUNT(DISTINCT rl.id) as like_count, CASE WHEN rl_user.user_id IS NOT NULL THEN 1 ELSE 0 END as user_liked" : "0 as like_count, 0 as user_liked";
    $joinLikes = $likeTblExists ? "LEFT JOIN repository_likes rl ON r.id = rl.repository_id\n        LEFT JOIN repository_likes rl_user ON r.id = rl_user.repository_id AND rl_user.user_id = ?" : "";

    $where = [];
    $params = [];
    if ($hasIsActive) { $where[] = "r.is_active = 1"; }

    if ($hasVisibility) {
        $where[] = "(r.visibility = 'public' OR r.$userCol = ?)"; $params[] = $current_user['id'];
    } elseif ($hasIsPrivate) {
        $where[] = "(r.is_private = 0 OR r.$userCol = ?)"; $params[] = $current_user['id'];
    }

    $sql = "
        SELECT r.*, u.name as owner_name,
               $selectLikes
        FROM repositories r
        JOIN users u ON r.$userCol = u.id
        $joinLikes
        " . (!empty($where) ? ("WHERE ".implode(' AND ', $where)) : "") . "
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ";

    if ($likeTblExists) { array_unshift($params, $current_user['id']); }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $repositories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar repositórios: " . $e->getMessage());
    $repositories = [];
}

// Filtros
$search = $_GET['search'] ?? '';
$language = $_GET['language'] ?? '';
$visibility = $_GET['visibility'] ?? 'all';
$sort = $_GET['sort'] ?? 'recent';

// Aplicar filtros se necessário
if ($search || $language || $visibility !== 'all') {
    $repositories = array_filter($repositories, function($repo) use ($search, $language, $visibility) {
        $matchSearch = empty($search) || 
                      stripos($repo['name'], $search) !== false || 
                      stripos($repo['description'], $search) !== false;
        
        $matchLanguage = empty($language) || $repo['primary_language'] === $language;
        
        $matchVisibility = $visibility === 'all' || $repo['visibility'] === $visibility;
        
        return $matchSearch && $matchLanguage && $matchVisibility;
    });
}

// Ordenação client-side (fallback ao SQL padrão)
usort($repositories, function($a, $b) use ($sort) {
    switch ($sort) {
        case 'stars':
            return ($b['like_count'] <=> $a['like_count']) ?: (strtotime($b['created_at']) <=> strtotime($a['created_at']));
        case 'name':
            return strcasecmp($a['name'], $b['name']);
        case 'updated':
            $au = $a['last_update'] ? strtotime($a['last_update']) : strtotime($a['created_at']);
            $bu = $b['last_update'] ? strtotime($b['last_update']) : strtotime($b['created_at']);
            return $bu <=> $au;
        default:
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    }
});

// Buscar linguagens disponíveis (compat: primary_language ou language)
try {
    $langCol = db_column_exists('repositories','primary_language') ? 'primary_language' : (db_column_exists('repositories','language') ? 'language' : null);
    if ($langCol) {
        $stmt = $pdo->prepare("SELECT DISTINCT $langCol FROM repositories WHERE $langCol IS NOT NULL AND $langCol != '' ORDER BY $langCol");
        $stmt->execute();
        $languages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else { $languages = []; }
} catch (PDOException $e) { $languages = []; }
?>

<div class="repositories-container">
    <!-- Header da página -->
    <div class="page-header">
        <div class="header-content">
            <h1>Repositórios</h1>
            <p>Descubra e compartilhe projetos incríveis da comunidade</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-outline" onclick="importFromGitHub()">
                <i class="fab fa-github"></i>
                Importar do GitHub
            </button>
            <button class="btn btn-primary" onclick="openRepositoryModal()">
                <i class="fas fa-plus"></i>
                Adicionar Repositório
            </button>
        </div>
    </div>

    <!-- Filtros e busca -->
    <div class="repositories-filters">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" 
                   placeholder="Buscar repositórios..." 
                   class="search-input"
                   id="search-input"
                   value="<?php echo escape($search); ?>"
                   onkeyup="handleSearch(event)">
        </div>
        
        <div class="filter-group">
            <select class="filter-select" id="language-filter" onchange="applyFilters()">
                <option value="">Todas as linguagens</option>
                <?php foreach ($languages as $lang): ?>
                    <option value="<?php echo escape($lang); ?>" <?php echo $lang === $language ? 'selected' : ''; ?>>
                        <?php echo escape($lang); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <select class="filter-select" id="visibility-filter" onchange="applyFilters()">
                <option value="all" <?php echo $visibility === 'all' ? 'selected' : ''; ?>>Todos os repositórios</option>
                <option value="public" <?php echo $visibility === 'public' ? 'selected' : ''; ?>>Públicos</option>
                <option value="private" <?php echo $visibility === 'private' ? 'selected' : ''; ?>>Privados</option>
            </select>
        </div>
        
        <div class="filter-group">
            <select class="filter-select" id="sort-filter" onchange="applyFilters()">
                <option value="recent" <?php echo $sort === 'recent' ? 'selected' : ''; ?>>Mais recentes</option>
                <option value="stars" <?php echo $sort === 'stars' ? 'selected' : ''; ?>>Mais curtidos</option>
                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nome (A-Z)</option>
                <option value="updated" <?php echo $sort === 'updated' ? 'selected' : ''; ?>>Última atualização</option>
            </select>
        </div>
    </div>

    <!-- Lista de repositórios -->
    <div class="repositories-grid" id="repositories-grid">
        <?php if (empty($repositories)): ?>
            <div class="empty-state">
                <i class="fab fa-git-alt"></i>
                <h3>Nenhum repositório encontrado</h3>
                <p>Comece adicionando seus primeiros repositórios ou importe do GitHub.</p>
                <div class="empty-actions">
                    <button class="btn btn-primary" onclick="openRepositoryModal()">
                        <i class="fas fa-plus"></i>
                        Adicionar Repositório
                    </button>
                    <button class="btn btn-outline" onclick="importFromGitHub()">
                        <i class="fab fa-github"></i>
                        Importar do GitHub
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($repositories as $repo): ?>
                <div class="repository-card" data-repo-id="<?php echo $repo['id']; ?>">
                    <div class="repo-header">
                        <div class="repo-info">
                            <h3 class="repo-name">
                                <a href="<?php echo escape($repo['url']); ?>" target="_blank" rel="noopener">
                                    <?php echo escape($repo['name']); ?>
                                </a>
                            </h3>
                            <span class="repo-visibility <?php echo $repo['visibility']; ?>">
                                <i class="fas fa-<?php echo $repo['visibility'] === 'private' ? 'lock' : 'globe'; ?>"></i>
                                <?php echo ucfirst($repo['visibility']); ?>
                            </span>
                        </div>
                        <div class="repo-actions">
                            <?php if ($repo['user_id'] == $current_user['id']): ?>
                                <button class="repo-action-btn" onclick="editRepository(<?php echo $repo['id']; ?>)" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="repo-action-btn" onclick="deleteRepository(<?php echo $repo['id']; ?>)" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                            <button class="repo-action-btn like-btn <?php echo $repo['user_liked'] ? 'liked' : ''; ?>" 
                                    onclick="toggleLike(<?php echo $repo['id']; ?>)" 
                                    title="Curtir">
                                <i class="fas fa-star"></i>
                                <span class="like-count"><?php echo $repo['like_count']; ?></span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="repo-content">
                        <?php if ($repo['description']): ?>
                            <p class="repo-description"><?php echo escape($repo['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="repo-languages">
                            <?php if ($repo['primary_language']): ?>
                                <span class="language-tag primary">
                                    <span class="language-color" style="background-color: <?php echo getLanguageColor($repo['primary_language']); ?>"></span>
                                    <?php echo escape($repo['primary_language']); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($repo['technologies']): ?>
                                <?php $technologies = json_decode($repo['technologies'], true) ?: []; ?>
                                <?php foreach (array_slice($technologies, 0, 3) as $tech): ?>
                                    <span class="tech-tag"><?php echo escape($tech); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($technologies) > 3): ?>
                                    <span class="tech-tag more">+<?php echo count($technologies) - 3; ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="repo-footer">
                        <div class="repo-meta">
                            <span class="meta-item">
                                <i class="fas fa-user"></i>
                                <?php echo escape($repo['owner_name']); ?>
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d/m/Y', strtotime($repo['created_at'])); ?>
                            </span>
                            <?php if ($repo['last_update']): ?>
                                <span class="meta-item">
                                    <i class="fas fa-sync"></i>
                                    Atualizado em <?php echo date('d/m/Y', strtotime($repo['last_update'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="repo-links">
                            <a href="<?php echo escape($repo['url']); ?>" 
                               target="_blank" 
                               rel="noopener"
                               class="btn btn-sm btn-outline">
                                <i class="fas fa-external-link-alt"></i>
                                Ver Repositório
                            </a>
                            <?php if ($repo['demo_url']): ?>
                                <a href="<?php echo escape($repo['demo_url']); ?>" 
                                   target="_blank" 
                                   rel="noopener"
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-play"></i>
                                    Demo
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para repositório -->
<div class="modal-overlay" id="repository-modal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 id="repo-modal-title">Adicionar Repositório</h3>
            <button class="modal-close" onclick="closeRepositoryModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="repository-form" class="ajax-form" action="../api/repositories.php" method="POST">
                <input type="hidden" id="repository-id" name="repository_id">
                
                <div class="form-group">
                    <label for="repo-name" class="form-label">Nome do Repositório *</label>
                    <input type="text" 
                           id="repo-name" 
                           name="name" 
                           class="form-control" 
                           placeholder="Ex: meu-projeto-incrivel"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="repo-description" class="form-label">Descrição</label>
                    <textarea id="repo-description" 
                              name="description" 
                              class="form-control" 
                              rows="3"
                              placeholder="Descreva seu projeto..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="repo-url" class="form-label">URL do Repositório *</label>
                    <input type="url" 
                           id="repo-url" 
                           name="url" 
                           class="form-control" 
                           placeholder="https://github.com/usuario/repositorio"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="repo-demo-url" class="form-label">URL da Demo</label>
                    <input type="url" 
                           id="repo-demo-url" 
                           name="demo_url" 
                           class="form-control" 
                           placeholder="https://meu-projeto.vercel.app">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="repo-language" class="form-label">Linguagem Principal</label>
                        <select id="repo-language" name="primary_language" class="form-control">
                            <option value="">Selecionar linguagem</option>
                            <option value="JavaScript">JavaScript</option>
                            <option value="TypeScript">TypeScript</option>
                            <option value="Python">Python</option>
                            <option value="Java">Java</option>
                            <option value="PHP">PHP</option>
                            <option value="C#">C#</option>
                            <option value="C++">C++</option>
                            <option value="Go">Go</option>
                            <option value="Rust">Rust</option>
                            <option value="Swift">Swift</option>
                            <option value="Kotlin">Kotlin</option>
                            <option value="Ruby">Ruby</option>
                            <option value="Dart">Dart</option>
                            <option value="HTML">HTML</option>
                            <option value="CSS">CSS</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="repo-visibility" class="form-label">Visibilidade</label>
                        <select id="repo-visibility" name="visibility" class="form-control">
                            <option value="public">Público</option>
                            <option value="private">Privado</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="repo-technologies" class="form-label">Tecnologias</label>
                    <input type="text" 
                           id="repo-technologies" 
                           name="technologies" 
                           class="form-control" 
                           placeholder="React, Node.js, MongoDB (separadas por vírgula)">
                    <small class="form-help">
                        Digite as tecnologias utilizadas separadas por vírgula
                    </small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeRepositoryModal()">
                Cancelar
            </button>
            <button type="submit" form="repository-form" class="btn btn-primary">
                <span class="btn-text">Salvar Repositório</span>
                <span class="btn-loading" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i>
                </span>
            </button>
        </div>
    </div>
</div>

<!-- Modal de importação do GitHub -->
<div class="modal-overlay" id="github-import-modal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Importar do GitHub</h3>
            <button class="modal-close" onclick="closeGitHubImportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="github-import-content">
                <div class="import-step">
                    <h4>Como importar repositórios do GitHub</h4>
                    <ol>
                        <li>Digite seu nome de usuário do GitHub</li>
                        <li>Conecte sua conta (opcional)</li>
                        <li>Selecione os repositórios que deseja importar</li>
                        <li>Confirme a importação</li>
                    </ol>
                </div>
                
                <form id="github-import-form">
                    <div class="form-group">
                        <label for="github-username" class="form-label">Nome de usuário do GitHub</label>
                        <input type="text" 
                               id="github-username" 
                               name="username" 
                               class="form-control" 
                               placeholder="Seu username do GitHub">
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="include-private" name="include_private">
                            <span class="checkmark"></span>
                            Incluir repositórios privados (requer autenticação)
                        </label>
                    </div>
                </form>
                
                <div id="github-repos-list" class="github-repos-list" style="display: none;">
                    <!-- Será preenchido via JavaScript -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeGitHubImportModal()">
                Cancelar
            </button>
            <button type="button" class="btn btn-outline" onclick="fetchGitHubRepos()" id="fetch-repos-btn">
                <i class="fab fa-github"></i>
                Buscar Repositórios
            </button>
            <button type="button" class="btn btn-primary" onclick="importSelectedRepos()" id="import-repos-btn" style="display: none;">
                Importar Selecionados
            </button>
        </div>
    </div>
</div>

<script>
// Removido JS inline: agora a página usa assets/js/repositories.js
</script>

<?php 
$additional_js = ['repositories'];
require_once '../includes/footer.php'; 

// Função auxiliar para cores das linguagens
function getLanguageColor($language) {
    $colors = [
        'JavaScript' => '#f1e05a',
        'TypeScript' => '#2b7489',
        'Python' => '#3572A5',
        'Java' => '#b07219',
        'PHP' => '#4F5D95',
        'C#' => '#239120',
        'C++' => '#f34b7d',
        'Go' => '#00ADD8',
        'Rust' => '#dea584',
        'Swift' => '#ffac45',
        'Kotlin' => '#F18E33',
        'Ruby' => '#701516',
        'Dart' => '#00B4AB',
        'HTML' => '#e34c26',
        'CSS' => '#1572B6'
    ];
    
    return $colors[$language] ?? '#586069';
}
?>
