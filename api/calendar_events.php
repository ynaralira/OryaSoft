<?php
// api/calendar_events.php
header('Content-Type: application/json');
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}


$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$start_time = $_POST['start_time'] ?? '';
$event_type = $_POST['event_type'] ?? '';

// Pega o usuário logado
session_start();
$created_by = $_SESSION['user_id'] ?? null;

if ($title === '' || $start_date === '' || $start_time === '' || !$created_by) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios']);
    exit;
}
if ($end_date === '') {
    $end_date = $start_date;
}

try {
    $sql = "INSERT INTO calendar_events (title, description, start_date, end_date, start_time, event_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $description, $start_date, $end_date, $start_time, $event_type, $created_by]);
    echo json_encode(['success' => true, 'message' => 'Evento criado com sucesso']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar evento: ' . $e->getMessage()]);
}
