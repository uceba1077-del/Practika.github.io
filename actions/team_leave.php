<?php
// Выйти из команды / исключить участника / отозвать приглашение
require __DIR__ . '/team_actions.php';

$team = load_team((int)post('team_id'));
$userId = (int)post('user_id');

if ($userId === (int)$team['captain_id']) {
    flash('err', 'Капитан не может выйти из своей команды. Удали команду или передай её другому игроку.');
    back_to_team((int)$team['id']);
}
// выйти может сам игрок, исключить — капитан или администратор
if ($userId !== (int)$me['id'] && !can_edit_team($team)) {
    forbidden('Исключать игроков может только капитан.');
}
q('DELETE FROM team_members WHERE team_id = ? AND user_id = ?', [$team['id'], $userId]);
flash('info', $userId === (int)$me['id'] ? 'Ты вышел из команды.' : 'Готово.');
back_to_team((int)$team['id']);
