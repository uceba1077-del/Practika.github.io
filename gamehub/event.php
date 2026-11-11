<?php
require __DIR__ . '/includes/bootstrap.php';

// 1. Получаем ID события
$event_id = (int)($_GET['id'] ?? 0);
if ($event_id <= 0) die('Событие не найдено');

// 2. Достаем данные события (используем starts_at вместо event_date)
$event = one("SELECT e.*, g.title as game_title, g.cover, u.nickname as organizer_name 
              FROM events e 
              JOIN games g ON e.game_id = g.id 
              JOIN users u ON e.organizer_id = u.id 
              WHERE e.id = ?", [$event_id]);

if (!$event) die('Событие не существует');

$current_user = current_user();
$is_organizer = ($current_user && $current_user['id'] == $event['organizer_id']);

// 3. Достаем заявки
$applications = all("SELECT ep.id as app_id, ep.status, t.name as team_name, u.nickname as captain_name 
                     FROM event_participants ep 
                     JOIN teams t ON ep.team_id = t.id 
                     JOIN users u ON t.captain_id = u.id
                     WHERE ep.event_id = ?", [$event_id]);

// 4. Проверка для подачи заявки
$can_apply = false;
$my_team = null;
$applied = null;

if ($current_user) {
    $my_team = one("SELECT id, name FROM teams WHERE captain_id = ?", [$current_user['id']]);
    if ($my_team) {
        $applied = one("SELECT status FROM event_participants WHERE event_id = ? AND team_id = ?", [$event_id, $my_team['id']]);
        if (!$applied) $can_apply = true;
    }
}

require __DIR__ . '/includes/header.php';
?>

<style>
    /* Стили для красоты */
    .event-wrapper { max-width: 900px; margin: 40px auto; padding: 0 20px; font-family: 'Onest', sans-serif; }
    .event-header-card { background: #1E2226; color: white; border-radius: 16px; padding: 40px; margin-bottom: 30px; position: relative; overflow: hidden; }
    .event-header-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(123, 97, 255, 0.2), transparent); pointer-events: none; }
    .event-title { font-size: 36px; font-weight: 800; margin: 0 0 10px 0; }
    .event-meta { display: flex; gap: 20px; color: #a0aab2; font-size: 15px; }
    .event-meta span { display: flex; align-items: center; gap: 5px; }
    
    .card { background: white; border-radius: 16px; padding: 30px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #eef0f2; }
    .card h3 { font-size: 20px; margin-top: 0; margin-bottom: 20px; color: #1E2226; }
    
    .btn { display: inline-block; padding: 12px 24px; border-radius: 8px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: 0.2s; font-size: 15px; }
    .btn-primary { background: #7B61FF; color: white; width: 100%; text-align: center; }
    .btn-primary:hover { background: #6a50e0; }
    .btn-success { background: #007A52; color: white; }
    .btn-success:hover { background: #006644; }
    .btn-danger { background: #f4f5f6; color: #C62828; }
    .btn-danger:hover { background: #ffe5e5; }
    
    .app-list { list-style: none; padding: 0; margin: 0; }
    .app-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #eef0f2; }
    .app-item:last-child { border-bottom: none; }
    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .status-pending { background: #FFF4E5; color: #B76E00; }
    .status-accepted { background: #E5F7ED; color: #007A52; }
    .status-rejected { background: #FFEBEB; color: #C62828; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
    .alert-success { background: #E5F7ED; color: #007A52; }
    
    @media (max-width: 768px) {
        .event-wrapper div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
    }
</style>

<div class="event-wrapper">
    <!-- Шапка события -->
    <div class="event-header-card">
        <h1 class="event-title"><?= e($event['title']) ?></h1>
        <div class="event-meta">
            <span>🎮 <?= e($event['game_title']) ?></span>
            <span>📅 <?= (!empty($event['starts_at'])) ? date('d.m.Y H:i', strtotime($event['starts_at'])) : 'Дата уточняется' ?></span>
            <span>👤 <?= e($event['organizer_name']) ?></span>
        </div>
        <?php if (!empty($event['description'])): ?>
            <p style="margin-top: 20px; color: #ccc; line-height: 1.5;"><?= nl2br(e($event['description'])) ?></p>
        <?php endif; ?>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Блок подачи заявки -->
        <div class="card">
            <h3>Участие</h3>
            <?php if (!$current_user): ?>
                <p><a href="login.php" style="color: #7B61FF;">Войдите</a>, чтобы подать заявку.</p>
            <?php elseif ($can_apply): ?>
                <form action="actions/event_apply.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                    <input type="hidden" name="team_id" value="<?= $my_team['id'] ?>">
                    <p style="margin-bottom: 15px;">Ваша команда: <strong><?= e($my_team['name']) ?></strong></p>
                    <button type="submit" class="btn btn-primary">Подать заявку</button>
                </form>
            <?php elseif ($my_team && $applied): ?>
                <div class="alert alert-success">
                    Заявка отправлена!<br>
                    Статус: 
                    <?php 
                        if ($applied['status'] === 'pending') echo 'На рассмотрении';
                        if ($applied['status'] === 'accepted') echo 'Принята';
                        if ($applied['status'] === 'rejected') echo 'Отклонена';
                    ?>
                </div>
            <?php else: ?>
                <p>Подать заявку может только капитан команды.<br><a href="team_create.php" style="color: #7B61FF;">Создать команду</a></p>
            <?php endif; ?>
        </div>

        <!-- Блок заявок -->
        <div class="card">
            <h3>Заявки (<?= count($applications) ?>)</h3>
            <?php if (empty($applications)): ?>
                <p style="color: #888;">Пока нет заявок.</p>
            <?php else: ?>
                <ul class="app-list">
                    <?php foreach ($applications as $app): ?>
                        <li class="app-item">
                            <div>
                                <strong style="display:block; margin-bottom: 5px;"><?= e($app['team_name']) ?></strong>
                                <span style="color: #888; font-size: 13px;">Капитан: <?= e($app['captain_name']) ?></span>
                            </div>
                            <div style="text-align: right;">
                                <span class="status-badge status-<?= $app['status'] ?>">
                                    <?php 
                                        if ($app['status'] === 'pending') echo 'Ожидает';
                                        if ($app['status'] === 'accepted') echo 'Принята';
                                        if ($app['status'] === 'rejected') echo 'Отклонена';
                                    ?>
                                </span>
                                <?php if ($is_organizer && $app['status'] === 'pending'): ?>
                                    <div style="margin-top: 10px; display: flex; gap: 5px; justify-content: flex-end;">
                                        <form action="actions/event_process.php" method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="app_id" value="<?= $app['app_id'] ?>">
                                            <input type="hidden" name="action" value="accept">
                                            <button type="submit" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">✓</button>
                                        </form>
                                        <form action="actions/event_process.php" method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="app_id" value="<?= $app['app_id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">✕</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>