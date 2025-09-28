<?php
require_once '../includes/config.php';
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])){
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Não autenticado']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
try {
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user){ throw new Exception('Usuário inválido'); }
} catch (Exception $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Erro: '.$e->getMessage()]);
    exit;
}

// Gera um token simples (HMAC) com expiração curta
$exp = time() + 3600; // 1h
$payload = base64_encode(json_encode(['uid'=>$user['id'], 'name'=>$user['name'], 'exp'=>$exp]));
$signature = hash_hmac('sha256', $payload, APP_SECRET);
$token = $payload.'.'.$signature;

echo json_encode(['success'=>true,'token'=>$token,'user'=>['id'=>$user['id'],'name'=>$user['name']]]);
