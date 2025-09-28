<?php
// webrtc-signaling.php - Sinalização WebRTC via arquivos para múltiplos participantes
// Recebe: sala, user_id, action (offer, answer, candidate, join, leave), data
// Salva e lê arquivos temporários para troca de mensagens

session_start();
header('Content-Type: application/json');

$room = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['room'] ?? '');
$user = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['user'] ?? '');
$action = $_POST['action'] ?? '';
$data = $_POST['data'] ?? '';

if (!$room || !$user) {
    echo json_encode(['success' => false, 'error' => 'room/user required']);
    exit;
}

$dir = __DIR__ . '/../tmp/webrtc_' . $room;
if (!is_dir($dir)) mkdir($dir, 0777, true);

// JOIN: registra presença
if ($action === 'join') {
    file_put_contents("$dir/{$user}.join", time());
    echo json_encode(['success' => true]);
    exit;
}
// LEAVE: remove arquivos do usuário
if ($action === 'leave') {
    array_map('unlink', glob("$dir/{$user}.*"));
    echo json_encode(['success' => true]);
    exit;
}
// OFFER/ANSWER/CANDIDATE: salva mensagem
if (in_array($action, ['offer','answer','candidate'])) {
    $to = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['to'] ?? '');
    if (!$to) {
        echo json_encode(['success' => false, 'error' => 'to required']);
        exit;
    }
    file_put_contents("$dir/{$to}_from_{$user}.{$action}", $data);
    echo json_encode(['success' => true]);
    exit;
}
// POLL: busca mensagens para este usuário
if ($action === 'poll') {
    $msgs = [];
    foreach (glob("$dir/{$user}_from_*.{offer,answer,candidate}", GLOB_BRACE) as $file) {
        $content = file_get_contents($file);
        $parts = explode('_from_', basename($file));
        $from = explode('.', $parts[1])[0];
        $type = explode('.', $parts[1])[1];
        $msgs[] = [ 'from' => $from, 'type' => $type, 'data' => $content ];
        unlink($file);
    }
    // Lista de participantes ativos
    $peers = [];
    foreach (glob("$dir/*.join") as $f) {
        if (time() - filemtime($f) < 30) {
            $peers[] = basename($f, '.join');
        }
    }
    echo json_encode(['success' => true, 'messages' => $msgs, 'peers' => $peers]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'invalid action']);
