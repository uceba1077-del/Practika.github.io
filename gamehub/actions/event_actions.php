<?php
// Общие проверки для действий с событиями
require __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) {
    redirect('events.php');
}
check_csrf();
$me = require_login();

function load_event(int $id): array
{
    $ev = one('SELECT * FROM events WHERE id = ?', [$id]);
    if (!$ev) {
        not_found('Событие');
    }
    $ev['taken'] = (int)val("SELECT COUNT(*) FROM event_participants WHERE event_id = ? AND status = 'accepted'", [$id]);
    $ev['is_past'] = strtotime($ev['starts_at']) < time();
    return $ev;
}
function back_to_event(int $id, string $anchor = ''): never
{
    redirect(url('event.php', ['id' => $id]) . $anchor);
}
