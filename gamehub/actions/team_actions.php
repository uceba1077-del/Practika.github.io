<?php
// Общие проверки для действий с командами. Подключается из actions/team_*.php
require __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) {
    redirect('teams.php');
}
check_csrf();
$me = require_login();

function load_team(int $id): array
{
    $t = one('SELECT * FROM teams WHERE id = ?', [$id]);
    if (!$t) {
        not_found('Команда');
    }
    $t['members'] = (int)val("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND status = 'member'", [$id]);
    return $t;
}
function back_to_team(int $id): never
{
    redirect('team.php', ['id' => $id]);
}
