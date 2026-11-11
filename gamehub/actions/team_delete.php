<?php
// Удаление команды (D из CRUD). Права проверяются на сервере.
require __DIR__ . '/team_actions.php';

$team = load_team((int)post('team_id'));
if (!can_edit_team($team)) {
    forbidden('Удалить команду может только её капитан или администратор.');
}
q('DELETE FROM teams WHERE id = ?', [$team['id']]);   // участники удалятся каскадом
if ($team['emblem'] && is_file(__DIR__ . '/../' . $team['emblem'])) {
    @unlink(__DIR__ . '/../' . $team['emblem']);
}
flash('info', 'Команда «' . $team['name'] . '» удалена.');
redirect('teams.php');
