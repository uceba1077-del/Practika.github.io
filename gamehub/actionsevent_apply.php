<?php
require_once '../includes/bootstrap.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $event_id = (int)($_POST['event_id'] ?? 0);
    $team_id = (int)($_POST['team_id'] ?? 0);

    // 1. Проверяем, что юзер — капитан этой команды
    $team = one("SELECT id, captain_id FROM teams WHERE id = ?", [$team_id]);
    if (!$team || $team['captain_id'] != $user['id']) {
        flash('error', 'Подать заявку может только капитан команды.');
        redirect("event.php?id=" . $event_id);
    }

    // 2. Проверяем, не подана ли уже заявка
    $existing = one("SELECT status FROM event_participants WHERE event_id = ? AND team_id = ?", [$event_id, $team_id]);
    if ($existing) {
        flash('error', 'Ваша команда уже подала заявку на это событие.');
        redirect("event.php?id=" . $event_id);
    }

    // 3. Проверяем лимит мест (по брифу)
    $event = one("SELECT max_teams FROM events WHERE id = ?", [$event_id]);
    $accepted_count = one("SELECT COUNT(*) as cnt FROM event_participants WHERE event_id = ? AND status = 'accepted'", [$event_id])['cnt'];
    
    if ($accepted_count >= $event['max_teams']) {
        flash('error', 'На событии уже нет свободных мест.');
        redirect("event.php?id=" . $event_id);
    }

    // 4. Сохраняем заявку
    try {
        // По умолчанию статус 'pending' (на рассмотрении)
        $stmt = db()->prepare("INSERT INTO event_participants (event_id, team_id, status, applied_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->execute([$event_id, $team_id]);
        
        flash('ok', 'Заявка успешно отправлена! Ожидайте решения организатора.');
    } catch (Exception $e) {
        flash('error', 'Ошибка при отправке заявки.');
    }

    redirect("event.php?id=" . $event_id);
}
?>