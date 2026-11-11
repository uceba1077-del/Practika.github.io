<?php
// ... (где-то выше у тебя уже есть $user = require_login();) ...

// Достаем все ожидающие приглашения для текущего пользователя
$invites = q("SELECT i.id as invite_id, t.id as team_id, t.name as team_name, u.nickname as captain_name 
              FROM invitations i 
              JOIN teams t ON i.team_id = t.id 
              JOIN users u ON t.captain_id = u.id 
              WHERE i.user_id = ? AND i.status = 'pending'", [$user['id']])->fetchAll();
?>

<!-- Твой HTML профиля (табы и т.д.) -->
<div class="profile-tab-content">
    <h2>Приглашения</h2>
    
    <?php if (empty($invites)): ?>
        <p>У вас нет новых приглашений.</p>
    <?php else: ?>
        <ul class="invites-list">
            <?php foreach ($invites as $inv): ?>
                <li style="margin-bottom: 15px; border-bottom: 1px solid #ccc; padding-bottom: 10px;">
                    <p>Вас приглашает команда <strong><?= e($inv['team_name']) ?></strong> (Капитан: <?= e($inv['captain_name']) ?>)</p>
                    
                    <!-- Формы для принятия и отклонения -->
                    <form action="actions/invite_action.php" method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="invite_id" value="<?= $inv['invite_id'] ?>">
                        <input type="hidden" name="action" value="accept">
                        <button type="submit" class="btn btn-success">Принять</button>
                    </form>
                    
                    <form action="actions/invite_action.php" method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="invite_id" value="<?= $inv['invite_id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn btn-danger">Отклонить</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>