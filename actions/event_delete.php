<?php
require __DIR__ . '/event_actions.php';

$ev = load_event((int)post('event_id'));
if (!can_edit_event($ev)) {
    forbidden('Удалить событие может только организатор или администратор.');
}
q('DELETE FROM events WHERE id = ?', [$ev['id']]);
flash('info', 'Событие «' . $ev['title'] . '» удалено.');
redirect('events.php');
