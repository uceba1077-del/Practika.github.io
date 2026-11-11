<?php
// Игрок подаёт заявку на вступление в команду
require __DIR__ . '/team_actions.php';

$team = load_team((int)post('team_id'));
$message = post('message');
if (mb_strlen($message) > 200) {
    flash('err', 'Сообщение слишком длинное — не больше 200 символов.');
    back_to_team((int)$team['id']);
}
$status = val('SELECT status FROM team_members WHERE team_id = ? AND user_id = ?', [$team['id'], $me['id']]);
if ($me['role'] === 'admin') {
    flash('info', 'Администратор не вступает в команды.');
} elseif ($status) {
    flash('info', $status === 'invited' ? 'Тебя уже пригласили — прими приглашение.' : 'Ты уже в команде или заявка уже отправлена.');
} elseif ($team['members'] >= $team['size']) {
    flash('err', 'В команде нет свободных мест.');
} else {
    q("INSERT INTO team_members (team_id, user_id, status, message) VALUES (?, ?, 'requested', ?)", [$team['id'], $me['id'], $message]);
    flash('ok', 'Заявка отправлена. Капитан увидит её в карточке команды.');
}
back_to_team((int)$team['id']);
