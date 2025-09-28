<?php
require_once 'includes/config.php';

// Se já estiver logado, redirecionar para dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/pages/dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validações
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'Por favor, preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Por favor, insira um email válido.';
    } elseif (strlen($password) < 6) {
        $error_message = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'As senhas não coincidem.';
    } else {
        try {
            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error_message = 'Este email já está cadastrado.';
            } else {
                // Criar usuário
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $hashed_password]);
                
                $success_message = 'Conta criada com sucesso! Você pode fazer login agora.';
                
                // Limpar campos
                $name = $email = '';
            }
        } catch (PDOException $e) {
            error_log("Erro no registro: " . $e->getMessage());
            $error_message = 'Erro interno. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Orya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, var(--primary-wine) 0%, var(--wine-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            max-width: 1100px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 700px;
        }
        
        .register-image {
            background: linear-gradient(45deg, var(--primary-wine), var(--wine-light));
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: white;
            text-align: center;
        }
        
        .register-logo {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .register-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        
        .register-subtitle {
            font-size: 18px;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        
        .register-benefits {
            text-align: left;
            max-width: 320px;
        }
        
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .benefit-icon {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .benefit-content h4 {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 14px;
        }
        
        .benefit-content p {
            opacity: 0.8;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .register-form-container {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .register-header h1 {
            font-size: 28px;
            color: var(--primary-black);
            margin-bottom: 8px;
        }
        
        .register-header p {
            color: var(--primary-gray);
            font-size: 16px;
        }
        
        .register-form {
            width: 100%;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary-black);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-medium);
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-wine);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-gray);
        }
        
        .input-group .form-control {
            padding-left: 48px;
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        
        .strength-bar {
            height: 4px;
            background: var(--gray-light);
            border-radius: 2px;
            margin-bottom: 4px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { width: 33%; background: var(--error); }
        .strength-medium { width: 66%; background: var(--warning); }
        .strength-strong { width: 100%; background: var(--success); }
        
        .password-requirements {
            list-style: none;
            padding: 0;
            margin: 8px 0 0 0;
        }
        
        .password-requirements li {
            font-size: 11px;
            color: var(--primary-gray);
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .password-requirements li.valid {
            color: var(--success);
        }
        
        .terms-group {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 24px;
        }
        
        .terms-group input {
            width: auto;
            margin-top: 4px;
        }
        
        .terms-group label {
            font-size: 14px;
            line-height: 1.4;
        }
        
        .terms-group a {
            color: var(--primary-wine);
            text-decoration: none;
        }
        
        .terms-group a:hover {
            text-decoration: underline;
        }
        
        .register-button {
            width: 100%;
            padding: 14px;
            background: var(--primary-wine);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 20px;
        }
        
        .register-button:hover {
            background: var(--wine-hover);
        }
        
        .register-button:disabled {
            background: var(--primary-gray);
            cursor: not-allowed;
        }
        
        .login-link {
            text-align: center;
            color: var(--primary-gray);
        }
        
        .login-link a {
            color: var(--primary-wine);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
            padding: 12px 16px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .success-message {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
            padding: 12px 16px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .register-container {
                grid-template-columns: 1fr;
                max-width: 400px;
            }
            
            .register-image {
                display: none;
            }
            
            .register-form-container {
                padding: 32px 24px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-image">
            <div class="register-logo">
                <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="Orya Logo" style="width: 100px; height: auto;border-radius: 50%;">
            </div>
            <h2 class="register-title">Junte-se ao Orya</h2>
            <p class="register-subtitle">
                Faça parte da maior comunidade de mulheres desenvolvedoras do Brasil.
            </p>
            
            <div class="register-benefits">
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="benefit-content">
                        <h4>Networking</h4>
                        <p>Conecte-se com outras desenvolvedoras e expanda sua rede profissional.</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="benefit-content">
                        <h4>Aprendizado</h4>
                        <p>Acesse workshops, mentorias e recursos exclusivos para seu crescimento.</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="benefit-content">
                        <h4>Carreira</h4>
                        <p>Desenvolva projetos, compartilhe conhecimento e acelere sua carreira.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="register-form-container">
            <div class="register-header">
                <h1>Criar Conta</h1>
                <p>Preencha os dados para começar sua jornada</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo escape($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo escape($success_message); ?>
                </div>
            <?php endif; ?>
            
            <form class="register-form" method="POST" action="">
                <div class="form-group">
                    <label for="name" class="form-label">Nome Completo</label>
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="form-control" 
                               placeholder="Seu nome completo"
                               value="<?php echo escape($name ?? ''); ?>"
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               placeholder="seu@email.com"
                               value="<?php echo escape($email ?? ''); ?>"
                               required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="form-label">Senha</label>
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-control" 
                                   placeholder="Sua senha"
                                   required>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strength-fill"></div>
                            </div>
                            <div id="strength-text">Digite uma senha</div>
                            <ul class="password-requirements">
                                <li id="req-length"><i class="fas fa-times"></i> Pelo menos 6 caracteres</li>
                                <li id="req-uppercase"><i class="fas fa-times"></i> Uma letra maiúscula</li>
                                <li id="req-number"><i class="fas fa-times"></i> Um número</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmar Senha</label>
                        <div class="input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-control" 
                                   placeholder="Confirme sua senha"
                                   required>
                        </div>
                        <div id="password-match" style="font-size: 12px; margin-top: 4px;"></div>
                    </div>
                </div>
                
                <div class="terms-group">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">
                        Eu concordo com os <a href="#" target="_blank">Termos de Uso</a> 
                        e <a href="#" target="_blank">Política de Privacidade</a>
                    </label>
                </div>
                
                <button type="submit" class="register-button" id="register-btn" disabled>
                    Criar Conta
                </button>
            </form>
            
            <div class="login-link">
                Já tem uma conta? <a href="login.php">Faça login aqui</a>
            </div>
        </div>
    </div>
    
    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthFill = document.getElementById('strength-fill');
        const strengthText = document.getElementById('strength-text');
        const registerBtn = document.getElementById('register-btn');
        const termsCheckbox = document.getElementById('terms');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            updatePasswordStrength(strength);
            checkFormValidity();
        });
        
        confirmPasswordInput.addEventListener('input', function() {
            checkPasswordMatch();
            checkFormValidity();
        });
        
        termsCheckbox.addEventListener('change', checkFormValidity);
        
        function checkPasswordStrength(password) {
            let score = 0;
            const requirements = {
                length: password.length >= 6,
                uppercase: /[A-Z]/.test(password),
                number: /\d/.test(password)
            };
            
            updateRequirement('req-length', requirements.length);
            updateRequirement('req-uppercase', requirements.uppercase);
            updateRequirement('req-number', requirements.number);
            
            if (requirements.length) score++;
            if (requirements.uppercase) score++;
            if (requirements.number) score++;
            if (password.length >= 8) score++;
            
            return { score, requirements };
        }
        
        function updateRequirement(id, isValid) {
            const element = document.getElementById(id);
            const icon = element.querySelector('i');
            
            if (isValid) {
                element.classList.add('valid');
                icon.className = 'fas fa-check';
            } else {
                element.classList.remove('valid');
                icon.className = 'fas fa-times';
            }
        }
        
        function updatePasswordStrength(strength) {
            const { score } = strength;
            
            strengthFill.className = 'strength-fill';
            
            if (score < 2) {
                strengthFill.classList.add('strength-weak');
                strengthText.textContent = 'Senha fraca';
                strengthText.style.color = 'var(--error)';
            } else if (score < 3) {
                strengthFill.classList.add('strength-medium');
                strengthText.textContent = 'Senha média';
                strengthText.style.color = 'var(--warning)';
            } else {
                strengthFill.classList.add('strength-strong');
                strengthText.textContent = 'Senha forte';
                strengthText.style.color = 'var(--success)';
            }
        }
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const matchDiv = document.getElementById('password-match');
            
            if (confirmPassword === '') {
                matchDiv.textContent = '';
                return false;
            }
            
            if (password === confirmPassword) {
                matchDiv.textContent = 'Senhas coincidem';
                matchDiv.style.color = 'var(--success)';
                return true;
            } else {
                matchDiv.textContent = 'Senhas não coincidem';
                matchDiv.style.color = 'var(--error)';
                return false;
            }
        }
        
        function checkFormValidity() {
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const terms = termsCheckbox.checked;
            
            const passwordStrength = checkPasswordStrength(password);
            const passwordMatch = checkPasswordMatch();
            
            const isValid = name && email && password && confirmPassword && 
                           passwordStrength.requirements.length && 
                           passwordMatch && terms;
            
            registerBtn.disabled = !isValid;
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('name').focus();
        });
    </script>
</body>
</html>
