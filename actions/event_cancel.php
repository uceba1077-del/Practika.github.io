<?php
// Игрок отзывает заявку или отказывается от участия
require __DIR__ . '/event_actions.php';

$ev = load_event((int)post('event_id'));
if ($ev['is_past']) {
    flash('err', 'Событие уже прошло.');
} elseif ((int)$ev['organizer_id'] === (int)$me['id']) {
    flash('err', 'Организатор не может отказаться от своего события — его можно удалить.');
} else {
    q('DELETE FROM event_participants WHERE event_id = ? AND user_id = ?', [$ev['id'], $me['id']]);
    flash('info', 'Готово, твоя заявка удалена.');
}
if (post('back') === 'profile') {
    redirect('profile.php', ['tab' => 'apps']);
}
back_to_event((int)$ev['id']);
