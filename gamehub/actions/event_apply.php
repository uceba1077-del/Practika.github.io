<?php
// Заявка на событие — последний шаг главного сценария. Все правила проверяются здесь, на сервере.
require __DIR__ . '/event_actions.php';

$ev = load_event((int)post('event_id'));
$id = (int)$ev['id'];
$comment = post('comment');
$asTeam = post('as') === 'team';
$teamId = $asTeam ? (int)post('team_id') : null;
$_SESSION['apply_draft'][$id] = ['comment' => $comment, 'as' => $asTeam ? 'team' : 'solo'];

$error = null;
if ($ev['is_past']) {
    $error = 'Событие уже прошло — заявки не принимаются.';
} elseif ($me['role'] === 'admin') {
    $error = 'Администратор не участвует в событиях.';
} elseif ((int)$ev['organizer_id'] === (int)$me['id']) {
    $error = 'Ты организатор этого события.';
} elseif (val('SELECT 1 FROM event_participants WHERE event_id = ? AND user_id = ?', [$id, $me['id']])) {
    $error = 'Ты уже подавал заявку на это событие.';
} elseif ($ev['taken'] >= $ev['slots']) {
    $error = 'Мест больше нет.';
} elseif (mb_strlen($comment) > 200) {
    $error = 'Комментарий слишком длинный — не больше 200 символов.';
} elseif ($ev['need_mic'] && !val('SELECT has_mic FROM player_profiles WHERE user_id = ?', [$me['id']])) {
    $error = 'Для этого события нужен микрофон — отметь его в анкете профиля.';
} elseif ($ev['min_reliability'] > 0 && (reliability((int)$me['id']) ?? 5) < $ev['min_reliability']) {
    $error = 'Твоя надёжность ниже, чем требует организатор (' . $ev['min_reliability'] . ').';
} elseif ($asTeam && !val("SELECT 1 FROM team_members WHERE team_id = ? AND user_id = ? AND status = 'member'", [$teamId, $me['id']])) {
    $error = 'Подать заявку можно только от команды, в которой ты состоишь.';
}

if ($error) {
    flash('err', $error);
    back_to_event($id);
}
q("INSERT INTO event_participants (event_id, user_id, team_id, comment, status) VALUES (?, ?, ?, ?, 'pending')", [$id, $me['id'], $teamId, $comment]);
unset($_SESSION['apply_draft'][$id]);
flash('ok', 'Заявка отправлена! Её статус видно в профиле, во вкладке «Мои заявки».');
back_to_event($id);
