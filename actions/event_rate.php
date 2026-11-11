<?php
// Оценка участника после события — из оценок считается надёжность
require __DIR__ . '/event_actions.php';

$ev = load_event((int)post('event_id'));
$to = (int)post('to_user_id');
$score = (int)post('score');
$comment = post('comment');
$id = (int)$ev['id'];

$both = (int)val("SELECT COUNT(*) FROM event_participants WHERE event_id = ? AND status = 'accepted' AND user_id IN (?, ?)", [$id, $me['id'], $to]);
$error = null;
if (!$ev['is_past']) {
    $error = 'Оценивать можно только после события.';
} elseif ($to === (int)$me['id']) {
    $error = 'Себя оценить нельзя.';
} elseif ($both < 2) {
    $error = 'Оценивать могут только участники этого события.';
} elseif ($score < 1 || $score > 5) {
    $error = 'Выбери оценку от 1 до 5 звёзд.';
} elseif (mb_strlen($comment) > 200) {
    $error = 'Комментарий — не больше 200 символов.';
} elseif (val('SELECT 1 FROM ratings WHERE event_id = ? AND from_user_id = ? AND to_user_id = ?', [$id, $me['id'], $to])) {
    $error = 'Ты уже оценил этого игрока.';
}
if ($error) {
    flash('err', $error);
    back_to_event($id, '#rate');
}
q('INSERT INTO ratings (event_id, from_user_id, to_user_id, score, came_on_time, comment) VALUES (?, ?, ?, ?, ?, ?)',
    [$id, $me['id'], $to, $score, empty($_POST['on_time']) ? 0 : 1, $comment]);
flash('ok', 'Спасибо! Оценка сохранена.');
back_to_event($id, '#rate');
