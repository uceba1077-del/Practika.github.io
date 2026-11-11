<?php
// Организатор принимает или отклоняет заявку
require __DIR__ . '/event_actions.php';

$app = one('SELECT * FROM event_participants WHERE id = ?', [(int)post('id')]);
if (!$app) {
    not_found('Заявка');
}
$ev = load_event((int)$app['event_id']);
if (!can_edit_event($ev)) {
    forbidden('Решать по заявкам может только организатор события.');
}
if ($app['status'] !== 'pending') {
    flash('info', 'Заявка уже обработана.');
} elseif (post('decision') === 'accept') {
    if ($ev['taken'] >= $ev['slots']) {
        flash('err', 'Мест больше нет — сначала увеличь количество мест в событии.');
    } else {
        q("UPDATE event_participants SET status = 'accepted' WHERE id = ?", [$app['id']]);
        flash('ok', 'Игрок добавлен в событие.');
    }
} else {
    q("UPDATE event_participants SET status = 'rejected' WHERE id = ?", [$app['id']]);
    flash('info', 'Заявка отклонена.');
}
if (post('back') === 'profile') {
    redirect('profile.php', ['tab' => 'apps']);
}
back_to_event((int)$ev['id']);
