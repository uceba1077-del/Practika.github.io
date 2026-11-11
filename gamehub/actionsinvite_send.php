<?php
require_once '../includes/bootstrap.php';

$user = require_login(); // Должен быть авторизован

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $team_id = (int)($_POST['team_id'] ?? 0);
    $invited_user_id = (int)($_POST['user_id'] ?? 0);

    // 1. Проверяем, что команда существует и текущий юзер — её капитан
    $team = one("SELECT id, captain_id FROM teams WHERE id = ?", [$team_id]);
    if (!$team || $team['captain_id'] != $user['id']) {
        fatal_page('Нет доступа', 'Приглашать в команду может только капитан.', null, 403);
    }

    // 2. Нельзя пригласить самого себя
    if ($invited_user_id == $user['id']) {
        flash('error', 'Вы не можете пригласить самого себя.');
        redirect("player.php?id=" . $invited_user_id);
    }

    // 3. Проверяем, не находится ли игрок уже в команде
    $is_member = one("SELECT 1 FROM team_members WHERE team_id = ? AND user_id = ?", [$team_id, $invited_user_id]);
    if ($is_member) {
        flash('error', 'Этот игрок уже в вашей команде.');
        redirect("player.php?id=" . $invited_user_id);
    }

    // 4. Проверяем, нет ли уже активного приглашения
    $existing_invite = one("SELECT 1 FROM invitations WHERE team_id = ? AND user_id = ? AND status = 'pending'", [$team_id, $invited_user_id]);
    if ($existing_invite) {
        flash('error', 'Приглашение уже отправлено этому игроку.');
        redirect("player.php?id=" . $invited_user_id);
    }

    // 5. Отправляем (сохраняем в БД)
    try {
        $stmt = db()->prepare("INSERT INTO invitations (team_id, user_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->execute([$team_id, $invited_user_id]);
        
        // Тут в будущем можно добавить уведомление
        flash('ok', 'Приглашение отправлено!');
    } catch (Exception $e) {
        flash('error', 'Ошибка при отправке приглашения.');
    }

    redirect("team.php?id=" . $team_id); // Возвращаемся на страницу команды
}
?>