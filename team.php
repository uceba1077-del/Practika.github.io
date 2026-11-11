<?php
require __DIR__ . '/includes/bootstrap.php';

// Получаем ID команды
$team_id = (int)($_GET['id'] ?? 0);
if ($team_id <= 0) die('Команда не найдена');

// 1. Достаем данные команды
$team = one("SELECT t.*, u.nickname as captain_name, g.title as game_title, g.cover 
        FROM teams t 
        JOIN users u ON t.captain_id = u.id 
        JOIN games g ON t.game_id = g.id 
        WHERE t.id = ?", [$team_id]);

if (!$team) die('Команда не существует');

// 2. Достаем состав (УБРАЛИ tm.role, так как этой колонки нет в базе)
$members = all("SELECT u.id, u.nickname, u.avatar 
              FROM team_members tm 
              JOIN users u ON tm.user_id = u.id 
              WHERE tm.team_id = ?", [$team_id]);

// 3. Умный поиск текущего пользователя
$current_user = null;
if (function_exists('current_user')) {
    $current_user = current_user();
} elseif (function_exists('auth')) {
    $current_user = auth();
} elseif (!empty($_SESSION['user_id'])) {
    $current_user = one("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

$is_captain = ($current_user && $current_user['id'] == $team['captain_id']);
$is_admin = ($current_user && $current_user['role'] === 'admin');

require __DIR__ . '/includes/header.php'; 
?>

<style>
    .team-hero { display: flex; gap: 20px; align-items: center; margin-bottom: 30px; margin-top: 30px; }
    .team-logo { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; background: #eee; display: flex; align-items: center; justify-content: center; font-size: 40px; font-weight: bold; color: #7B61FF; }
    .team-member-card { display: flex; align-items: center; gap: 15px; padding: 15px; border-bottom: 1px solid #eee; }
    .team-member-card:last-child { border-bottom: none; }
</style>

<main class="wrap">
    <!-- Шапка команды -->
    <div class="team-hero">
        <?php if (!empty($team['cover'])): ?>
            <img src="<?= url($team['cover']) ?>" alt="Логотип" class="team-logo">
        <?php else: ?>
            <div class="team-logo"><?= mb_strtoupper(mb_substr($team['name'], 0, 1)) ?></div>
        <?php endif; ?>
        
        <div>
            <h1 class="h1" style="margin-bottom: 5px;"><?= e($team['name']) ?></h1>
            <p class="sub" style="margin-bottom: 10px;">Игра: <strong><?= e($team['game_title']) ?></strong> · Капитан: <?= e($team['captain_name']) ?></p>
            
            <!-- Кнопки управления -->
            <?php if ($is_captain || $is_admin): ?>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <a href="team_edit.php?id=<?= $team['id'] ?>" class="pill">Редактировать</a>
                    <form action="actions/team_delete.php" method="POST" onsubmit="return confirm('Вы уверены, что хотите удалить команду?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $team['id'] ?>">
                        <button type="submit" class="pill" style="color: #C62828; border-color: #ffcccc; background: #fff5f5;">Удалить</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Описание -->
    <?php if (!empty($team['description'])): ?>
        <div style="background: #f9f9f9; padding: 20px; border-radius: 12px; margin-bottom: 30px;">
            <p style="margin: 0;"><?= nl2br(e($team['description'])) ?></p>
        </div>
    <?php endif; ?>

    <!-- Состав -->
    <h2 class="h2">Состав команды (<?= count($members) ?>)</h2>
    <div style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 10px 20px; margin-top: 15px;">
        <?php if (empty($members)): ?>
            <p>Пока никого нет.</p>
        <?php else: ?>
            <?php foreach ($members as $m): ?>
                <div class="team-member-card">
                    <?php if (!empty($m['avatar'])): ?>
                        <img src="<?= e($m['avatar']) ?>" alt="Аватар" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 50px; height: 50px; border-radius: 50%; background: #7B61FF; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                            <?= mb_strtoupper(mb_substr($m['nickname'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <strong><?= e($m['nickname']) ?></strong>
                        <?php if ($m['id'] == $team['captain_id']): ?>
                            <span style="background: #E5F7ED; color: #007A52; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 5px;">Капитан</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>