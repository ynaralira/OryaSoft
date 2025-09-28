<?php
header('Content-Type: application/json');
require_once '../includes/config.php';

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

try {
    switch ($method) {
        case 'GET':
            // Buscar dados do usuário atual ou de outro usuário
            $target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : $user_id;
            
            $stmt = $pdo->prepare("
                SELECT id, name, email, avatar, bio, github_url, linkedin_url, created_at 
                FROM users 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$target_user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Usuário não encontrado');
            }
            
            // Se for o próprio usuário, incluir mais informações
            if ($target_user_id === $user_id) {
                // Buscar estatísticas
                $stats = [];
                
                // Projetos
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE created_by = ? OR assigned_to = ?");
                $stmt->execute([$user_id, $user_id]);
                $stats['projects_count'] = $stmt->fetchColumn();
                
                // Reuniões participadas
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM meeting_participants WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $stats['meetings_count'] = $stmt->fetchColumn();
                
                // Posts na comunidade
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM community_posts WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $stats['posts_count'] = $stmt->fetchColumn();
                
                // Repositórios
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM repositories WHERE owner_id = ?");
                $stmt->execute([$user_id]);
                $stats['repositories_count'] = $stmt->fetchColumn();
                
                $user['stats'] = $stats;
                
                // Buscar configurações
                $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $settings_rows = $stmt->fetchAll();
                
                $settings = [];
                foreach ($settings_rows as $setting) {
                    $settings[$setting['setting_key']] = $setting['setting_value'];
                }
                $user['settings'] = $settings;
            }
            
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
            break;

        case 'PUT':
            // Atualizar perfil do usuário
            $input = json_decode(file_get_contents('php://input'), true);
            
            $allowed_fields = ['name', 'bio', 'github_url', 'linkedin_url'];
            $update_fields = [];
            $update_values = [];
            
            foreach ($allowed_fields as $field) {
                if (isset($input[$field])) {
                    $value = trim($input[$field]);
                    
                    // Validações específicas
                    switch ($field) {
                        case 'name':
                            if (empty($value)) {
                                throw new Exception('Nome não pode estar vazio');
                            }
                            if (strlen($value) > 100) {
                                throw new Exception('Nome muito longo');
                            }
                            break;
                            
                        case 'bio':
                            if (strlen($value) > 500) {
                                throw new Exception('Bio muito longa');
                            }
                            break;
                            
                        case 'github_url':
                        case 'linkedin_url':
                            if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                                throw new Exception('URL inválida para ' . $field);
                            }
                            break;
                    }
                    
                    $update_fields[] = "$field = ?";
                    $update_values[] = $value ?: null;
                }
            }
            
            if (empty($update_fields)) {
                throw new Exception('Nenhum campo para atualizar');
            }
            
            $update_values[] = $user_id;
            $sql = "UPDATE users SET " . implode(', ', $update_fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($update_values);
            
            echo json_encode([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso'
            ]);
            break;

        case 'POST':
            // Aceita tanto JSON (para update_settings) quanto multipart (para upload_avatar)
            $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
            if (stripos($contentType, 'multipart/form-data') !== false) {
                $action = $_POST['action'] ?? (isset($_FILES['avatar']) ? 'upload_avatar' : null);
                if ($action === 'upload_avatar') {
                    // Upload de avatar (implementação básica)
                    if (!isset($_FILES['avatar'])) {
                        throw new Exception('Nenhum arquivo enviado');
                    }
                    $file = $_FILES['avatar'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 2 * 1024 * 1024; // 2MB
                    if ($file['error'] !== UPLOAD_ERR_OK) { throw new Exception('Erro no upload do arquivo'); }
                    if (!in_array($file['type'], $allowed_types)) { throw new Exception('Tipo de arquivo não permitido'); }
                    if ($file['size'] > $max_size) { throw new Exception('Arquivo muito grande (máximo 2MB)'); }
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
                    $upload_path = '../uploads/avatars/';
                    if (!is_dir($upload_path)) { mkdir($upload_path, 0755, true); }
                    $full_path = $upload_path . $filename;
                    if (!move_uploaded_file($file['tmp_name'], $full_path)) { throw new Exception('Erro ao salvar arquivo'); }
                    // Persistir no BD (armazenar apenas filename ou URL completa)
                    $avatar_url = SITE_URL . '/uploads/avatars/' . $filename;
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$avatar_url, $user_id]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Avatar atualizado com sucesso',
                        'avatar_url' => $avatar_url
                    ]);
                    break;
                }
                throw new Exception('Ação inválida');
            }
            // JSON
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['action'])) { throw new Exception('Ação não especificada'); }
            if ($input['action'] === 'update_settings') {
                if (!isset($input['settings']) || !is_array($input['settings'])) {
                    throw new Exception('Configurações inválidas');
                }
                $allowed_settings = [ 'email_notifications','theme','language','timezone','privacy_profile','privacy_activity' ];
                foreach ($input['settings'] as $key => $value) {
                    if (!in_array($key, $allowed_settings)) { continue; }
                    switch ($key) {
                        case 'email_notifications':
                        case 'privacy_profile':
                        case 'privacy_activity':
                            $value = $value ? '1' : '0';
                            break;
                        case 'theme':
                            if (!in_array($value, ['light','dark'])) { continue 2; }
                            break;
                        case 'language':
                            if (!in_array($value, ['pt-BR','en-US','es-ES'])) { continue 2; }
                            break;
                    }
                    $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP");
                    $stmt->execute([$user_id, $key, $value]);
                }
                echo json_encode(['success'=>true,'message'=>'Configurações atualizadas com sucesso']);
            } else {
                throw new Exception('Ação inválida');
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
            break;
    }

} catch (Exception $e) {
    // Mantém código se já definido (ex: 401, 403, 404, 405); caso contrário define 400
    $current = http_response_code();
    if (!in_array($current, [401,403,404,405])) { http_response_code(400); }
    echo json_encode([ 'success' => false, 'message' => $e->getMessage() ]);
} catch (PDOException $e) {
    error_log("Erro na API de usuário: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([ 'success' => false, 'message' => 'Erro interno do servidor' ]);
}
?>
