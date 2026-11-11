<?php
require_once '../includes/bootstrap.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $app_id = (int)($_POST['app_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // 1. Получаем заявку и событие
    $app = one("SELECT ep.*, e.organizer_id, e.max_teams, e.id as event_id 
                FROM event_participants ep
                JOIN events e ON ep.event_id = e.id
                WHERE ep.id = ?", [$app_id]);

    if (!$app) {
        flash('error', 'Заявка не найдена.');
        redirect('events.php');
    }

    // 2. Проверяем, что текущий юзер — организатор этого события
    if ($app['organizer_id'] != $user['id']) {
        fatal_page('Нет доступа', 'Вы не являетесь организатором этого события.', null, 403);
    }

    if ($action === 'accept') {
        // Проверяем, не набралось ли уже максимум команд
        $accepted = one("SELECT COUNT(*) as cnt FROM event_participants WHERE event_id = ? AND status = 'accepted'", [$app['event_id']])['cnt'];
        if ($accepted >= $app['max_teams']) {
            flash('error', 'Нельзя принять заявку: все места уже заняты.');
            redirect("event.php?id=" . $app['event_id']);
        }
        
        q("UPDATE event_participants SET status = 'accepted' WHERE id = ?", [$app_id]);
        flash('ok', 'Команда принята на событие!');
        
    } elseif ($action === 'reject') {
        q("UPDATE event_participants SET status = 'rejected' WHERE id = ?", [$app_id]);
        flash('ok', 'Заявка отклонена.');
    }

    redirect("event.php?id=" . $app['event_id']);
}
?>