<?php
require_once 'includes/bootstrap.php';

// 1. Получаем ID игрока из адреса
$player_id = (int)($_GET['id'] ?? 0);
if ($player_id <= 0) die('Игрок не найден');

// 2. Достаем данные игрока
$player = one("SELECT * FROM users WHERE id = ?", [$player_id]);
if (!$player) die('Игрок не существует');

// 3. УМНЫЙ ПОИСК ТЕКУЩЕГО ПОЛЬЗОВАТЕЛЯ (чтобы не было ошибок)
$current_user = null;
if (function_exists('current_user')) {
    $current_user = current_user();
} elseif (function_exists('auth')) {
    $current_user = auth();
} elseif (function_exists('user')) {
    $current_user = user();
} elseif (!empty($_SESSION['user_id'])) {
    $current_user = one("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

$my_teams = [];

// 4. Проверяем, является ли текущий пользователь капитаном каких-либо команд
if ($current_user) {
    // Ищем команды, где этот юзер капитан
    $my_teams = all("SELECT id, name FROM teams WHERE captain_id = ?", [$current_user['id']]);
}

// Подключаем шапку
require_once 'includes/header.php'; 
?>

<main class="container" style="max-width: 800px; margin: 40px auto; padding: 20px;">
    
    <!-- Карточка игрока -->
    <div class="player-card" style="background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
            <!-- Аватарка -->
            <?php if (!empty($player['avatar'])): ?>
                <img src="<?= e($player['avatar']) ?>" alt="Аватар" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
                <div style="width: 100px; height: 100px; border-radius: 50%; background: #7B61FF; color: white; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: bold;">
                    <?= mb_strtoupper(mb_substr($player['nickname'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            
            <div>
                <h1 style="margin: 0 0 5px 0; font-size: 28px; color: #1E2226;"><?= e($player['nickname']) ?></h1>
                <p style="color: #666; margin: 0;">На GameHub с <?= date('Y', strtotime($player['created_at'] ?? 'now')) ?></p>
            </div>
        </div>

        <!-- Форма приглашения в команду -->
        <?php if ($current_user && $current_user['id'] != $player['id'] && count($my_teams) > 0): ?>
            <div class="invite-block" style="margin-top: 20px; padding: 20px; background: #f4f5f6; border-radius: 12px;">
                <h3 style="margin-top: 0; font-size: 18px;">Пригласить в команду</h3>
                <form action="actions/invite_send.php" method="POST" style="display: flex; gap: 10px; align-items: flex-end;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $player['id'] ?>">
                    
                    <div style="flex: 1;">
                        <label style="font-size: 14px; color: #666; margin-bottom: 5px; display: block;">Выберите команду</label>
                        <select name="team_id" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ccc;">
                            <?php foreach ($my_teams as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="background: #7B61FF; color: white; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold;">Отправить</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

</main>

<?php require_once 'includes/footer.php'; ?>