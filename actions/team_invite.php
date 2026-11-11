<?php
// Капитан приглашает игрока в свою команду
require __DIR__ . '/team_actions.php';

$team = load_team((int)post('team_id'));
$userId = (int)post('user_id');
$message = mb_substr(post('message'), 0, 200);

if (!can_edit_team($team)) {
    forbidden('Приглашать в команду может только капитан.');
}
$user = one("SELECT id, nickname FROM users WHERE id = ? AND role = 'user'", [$userId]);
if (!$user || $userId === (int)$team['captain_id']) {
    flash('err', 'Такого игрока нельзя пригласить.');
    redirect('player.php', ['id' => $userId]);
}
$status = val('SELECT status FROM team_members WHERE team_id = ? AND user_id = ?', [$team['id'], $userId]);
if ($status === 'member') {
    flash('info', $user['nickname'] . ' уже в команде.');
} elseif ($status === 'invited') {
    flash('info', 'Приглашение уже отправлено, ждём ответа.');
} elseif ($team['members'] >= $team['size']) {
    flash('err', 'В команде нет свободных мест. Увеличь размер команды или освободи место.');
} elseif ($status === 'requested') {
    // игрок сам просился — сразу принимаем
    q("UPDATE team_members SET status = 'member' WHERE team_id = ? AND user_id = ?", [$team['id'], $userId]);
    flash('ok', $user['nickname'] . ' уже подавал заявку — теперь он в команде.');
} else {
    q("INSERT INTO team_members (team_id, user_id, status, message) VALUES (?, ?, 'invited', ?)", [$team['id'], $userId, $message]);
    flash('ok', 'Приглашение отправлено ' . $user['nickname'] . '.');
}
redirect('player.php', ['id' => $userId]);
