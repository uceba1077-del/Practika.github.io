<?php
// Принять/отклонить: капитан решает по заявке, игрок — по приглашению
require __DIR__ . '/team_actions.php';

$team = load_team((int)post('team_id'));
$userId = (int)post('user_id');
$accept = post('decision') === 'accept';
$row = one('SELECT status FROM team_members WHERE team_id = ? AND user_id = ?', [$team['id'], $userId]);
if (!$row || $row['status'] === 'member') {
    flash('info', 'Заявка уже обработана.');
    back_to_team((int)$team['id']);
}

// права: на заявку отвечает капитан, на приглашение — сам приглашённый
if ($row['status'] === 'requested' && !can_edit_team($team)) {
    forbidden('Отвечать на заявки может только капитан.');
}
if ($row['status'] === 'invited' && $userId !== (int)$me['id']) {
    forbidden('Ответить на приглашение может только приглашённый игрок.');
}

if ($accept) {
    if ($team['members'] >= $team['size']) {
        flash('err', 'В команде нет свободных мест.');
        back_to_team((int)$team['id']);
    }
    q("UPDATE team_members SET status = 'member' WHERE team_id = ? AND user_id = ?", [$team['id'], $userId]);
    flash('ok', $row['status'] === 'invited' ? 'Ты в команде «' . $team['name'] . '»!' : 'Игрок принят в команду.');
} else {
    q('DELETE FROM team_members WHERE team_id = ? AND user_id = ?', [$team['id'], $userId]);
    flash('info', $row['status'] === 'invited' ? 'Приглашение отклонено.' : 'Заявка отклонена.');
}
$back = post('back');
if ($back === 'profile') {
    redirect('profile.php', ['tab' => 'teams']);
}
back_to_team((int)$team['id']);
