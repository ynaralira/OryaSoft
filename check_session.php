<?php
session_start();
echo "<h1>🔍 Debug de Sessão</h1>";

echo "<h2>Informações da Sessão:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✅ Usuário logado: ID = {$_SESSION['user_id']}</p>";
    
    // Conectar ao banco
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=orya;charset=utf8", 'root', 'root');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<h3>Dados do usuário:</h3>";
            echo "<ul>";
            echo "<li><strong>Nome:</strong> {$user['name']}</li>";
            echo "<li><strong>Email:</strong> {$user['email']}</li>";
            echo "<li><strong>ID:</strong> {$user['id']}</li>";
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>❌ Usuário não encontrado no banco!</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Erro: {$e->getMessage()}</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Usuário NÃO está logado!</p>";
    echo "<p><a href='login.php'>Fazer Login</a></p>";
}

echo "<hr>";
echo "<h2>Links:</h2>";
echo "<ul>";
echo "<li><a href='login.php'>Login</a></li>";
echo "<li><a href='pages/meetings.php'>Reuniões</a></li>";
echo "</ul>";
?>
