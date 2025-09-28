<?php
require_once '../includes/header.php';

$meeting_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$meeting_id) {
    echo '<div class="container"><h2>Reunião não encontrada.</h2></div>';
    require_once '../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM meetings WHERE id = ? AND is_active = 1');
$stmt->execute([$meeting_id]);
$meeting = $stmt->fetch();
if (!$meeting) {
    echo '<div class="container"><h2>Reunião não encontrada.</h2></div>';
    require_once '../includes/footer.php';
    exit;
}
?>

<link rel="stylesheet" href="../assets/css/meetings.css">
<style>
.meeting-room-box { max-width: 70vw; width: 70vw;}
.meeting-room-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
.meeting-room-title { font-size: 1.3rem; color: #722F5C; font-weight: bold; display: flex; align-items: center; gap: 10px; }
.meeting-room-back { margin: 0; }
#jitsi-loading { display: flex; align-items: center; justify-content: center; height: 600px; font-size: 1.5rem; color: #722F5C; background: #f8f8fa; border-radius: 12px; }
</style>
<div class="container meeting-room-page">
    <div class="meeting-room-box" style="background: #fff; border-radius: 14px; box-shadow: 0 2px 16px rgba(114,47,92,0.08); padding: 32px 24px; margin: 0 auto; max-width: 900px;">
        <div class="meeting-room-header">
            <a href="meetings.php" class="btn btn-outline meeting-room-back">Voltar para Reuniões</a>
            <div class="meeting-room-title">
                <i class="fas fa-users"></i> <?php echo htmlspecialchars($meeting['title']); ?>
            </div>
            <div style="width:120px;"></div> <!-- Espaço para alinhar central -->
        </div>
        <div id="jitsi-loading"><span>Carregando reunião...</span></div>
        <iframe
            id="jitsi-iframe"
            src="https://meet.jit.si/OryaMeeting_<?php echo md5('orya_'.$meeting['start_datetime'].'_'.$meeting['title']); ?>"
            style="width: 100%; height: 600px; border: 0; border-radius: 12px; margin-top: 0; display: none;"
            allow="camera; microphone; fullscreen; display-capture"
            allowfullscreen
            onload="document.getElementById('jitsi-loading').style.display='none'; this.style.display='block';"
        ></iframe>
    </div>
</div>
