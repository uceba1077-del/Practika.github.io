<?php
require_once '../includes/bootstrap.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    $invite_id = (int)($_POST['invite_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // Получаем приглашение (и проверяем, что оно принадлежит текущему юзеру)
    $invite = one("SELECT * FROM invitations WHERE id = ? AND user_id = ? AND status = 'pending'", [$invite_id, $user['id']]);
    
    if (!$invite) {
        flash('error', 'Приглашение не найдено.');
        redirect('profile.php');
    }

    if ($action === 'accept') {
        // Проверяем, не заполнена ли команда (по брифу)
        $team = one("SELECT id, max_players FROM teams WHERE id = ?", [$invite['team_id']]);
        $members_count = one("SELECT COUNT(*) as cnt FROM team_members WHERE team_id = ?", [$invite['team_id']])['cnt'];
        
        if ($members_count >= $team['max_players']) {
            flash('error', 'В команде уже нет свободных мест.');
            // Меняем статус на отклоненное
            q("UPDATE invitations SET status = 'rejected' WHERE id = ?", [$invite_id]);
            redirect('profile.php');
        }

        try {
            db()->beginTransaction();
            // 1. Обновляем статус приглашения
            q("UPDATE invitations SET status = 'accepted' WHERE id = ?", [$invite_id]);
            // 2. Добавляем игрока в состав команды
            q("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'member')", [$invite['team_id'], $user['id']]);
            db()->commit();
            
            flash('ok', 'Вы вступили в команду!');
        } catch (Exception $e) {
            db()->rollBack();
            flash('error', 'Ошибка при вступлении в команду.');
        }

    } elseif ($action === 'reject') {
        q("UPDATE invitations SET status = 'rejected' WHERE id = ?", [$invite_id]);
        flash('ok', 'Приглашение отклонено.');
    }

    redirect('profile.php'); // Возвращаемся в профиль
}
?>