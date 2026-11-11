<?php
require __DIR__ . '/includes/bootstrap.php';

const TEAM_RATING_SQL = "(SELECT ROUND(AVG(r.score) * 2) FROM ratings r JOIN team_members m ON m.user_id = r.to_user_id WHERE m.team_id = t.id AND m.status = 'member')";

$search = get('q');
$game = get_int('game');
$open = !empty($_GET['open']);
$mode = in_array(get('mode'), ['chill', 'tryhard'], true) ? get('mode') : '';
$sort = in_array(get('sort'), ['rating', 'new', 'name'], true) ? get('sort') : 'default';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = 't.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($game) {
    $where[] = 't.game_id = ?';
    $params[] = $game;
}
if ($mode) {
    $where[] = 't.play_mode = ?';
    $params[] = $mode;
}
$having = $open ? 'HAVING members < t.size' : '';
$order = match ($sort) {
    'rating' => 'rating IS NULL, rating DESC',
    'new' => 't.created_at DESC',
    'name' => 't.name',
    default => '(members < t.size) DESC, rating IS NULL, rating DESC',
};
$teams = all("SELECT t.*, g.title AS game_title, g.cover,
        (SELECT COUNT(*) FROM team_members m WHERE m.team_id = t.id AND m.status = 'member') AS members,
        " . TEAM_RATING_SQL . " AS rating
    FROM teams t JOIN games g ON g.id = t.game_id " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . " $having ORDER BY $order", $params);

// участники для кружков
$membersOf = [];
if ($teams) {
    $ids = array_column($teams, 'id');
    foreach (all("SELECT m.team_id, u.nickname, u.avatar FROM team_members m JOIN users u ON u.id = m.user_id
                  WHERE m.status = 'member' AND m.team_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY m.id', $ids) as $m) {
        $membersOf[$m['team_id']][] = $m;
    }
}
$top = all("SELECT t.id, t.name, g.title AS game_title, g.cover, " . TEAM_RATING_SQL . " AS rating
            FROM teams t JOIN games g ON g.id = t.game_id HAVING rating IS NOT NULL ORDER BY rating DESC, t.name LIMIT 4");
$games = all('SELECT id, title FROM games ORDER BY title');

$active = 'teams';
$page_title = 'Команды';
require __DIR__ . '/includes/header.php';
?>
<main>
  <section class="wrap page-head">
    <div>
      <div class="crumb"><a href="<?= url('index.php') ?>">главная</a> / команды</div>
      <h1 class="h1">Команды</h1>
      <p class="sub">Вступай в готовую команду или собери свою.</p>
    </div>
    <a class="pill g lg" href="<?= url('team_edit.php') ?>">+ Создать команду</a>
  </section>
  <section class="wrap" style="padding-bottom:72px">
    <form class="toolbar" method="get" action="<?= url('teams.php') ?>" role="search">
      <input class="inp" type="search" name="q" value="<?= e($search) ?>" placeholder="⌕  Название команды" aria-label="Название команды">
      <select class="inp" name="game" data-autosubmit aria-label="Игра">
        <option value="">Все игры</option>
        <?php foreach ($games as $g): ?><option value="<?= $g['id'] ?>" <?= $game === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['title']) ?></option><?php endforeach; ?>
      </select>
      <a class="tag<?= $open ? ' on' : '' ?>" style="height:40px;padding:0 18px" href="<?= url('teams.php', array_filter(array_merge($_GET, ['open' => $open ? null : 1]))) ?>">ищут игроков</a>
      <a class="tag<?= $mode === 'chill' ? ' on' : '' ?>" style="height:40px;padding:0 18px" href="<?= url('teams.php', array_filter(array_merge($_GET, ['mode' => $mode === 'chill' ? null : 'chill']))) ?>">спокойно</a>
      <a class="tag<?= $mode === 'tryhard' ? ' on' : '' ?>" style="height:40px;padding:0 18px" href="<?= url('teams.php', array_filter(array_merge($_GET, ['mode' => $mode === 'tryhard' ? null : 'tryhard']))) ?>">на результат</a>
      <?php if ($open): ?><input type="hidden" name="open" value="1"><?php endif; ?>
      <?php if ($mode): ?><input type="hidden" name="mode" value="<?= e($mode) ?>"><?php endif; ?>
      <label class="sort">сортировка:
        <select name="sort" data-autosubmit>
          <option value="default" <?= $sort === 'default' ? 'selected' : '' ?>>по умолчанию</option>
          <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>по рейтингу</option>
          <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>сначала новые</option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>по названию</option>
        </select>
      </label>
    </form>

    <?php if (!$teams): ?><div class="empty" style="margin-top:28px">Команд по фильтрам нет. <a class="more" href="<?= url('teams.php') ?>">Сбросить фильтры</a> или <a class="more" href="<?= url('team_edit.php') ?>">создай свою</a>.</div><?php endif; ?>
    <div class="teams-grid" style="margin-top:28px">
      <?php foreach ($teams as $t): $free = max(0, $t['size'] - $t['members']); ?>
        <a class="card team-card" href="<?= url('team.php', ['id' => $t['id']]) ?>">
          <?= cover($t, 120, 180) ?>
          <div class="body">
            <div class="row" style="justify-content:space-between"><b class="h3"><?= e($t['name']) ?></b><?= lvl($t['rating'], 36) ?></div>
            <div class="mut" style="font-size:14px"><?= e($t['game_title']) ?><?= $t['schedule'] ? ' · ' . e($t['schedule']) : '' ?></div>
            <div class="tags"><span class="tag sm"><?= MODES[$t['play_mode']] ?></span><?= $free ? '<span class="tag gr sm">ищут игроков</span>' : '<span class="tag sm">состав полный</span>' ?></div>
            <div class="row" style="margin-top:auto">
              <span class="avs"><?php foreach ($membersOf[$t['id']] ?? [] as $m) echo ava($m, 36); ?><?php for ($i = 0; $i < $free; $i++): ?><span class="slot-free" style="width:36px;height:36px">+</span><?php endfor; ?></span>
              <span class="hint"><?= $t['members'] ?> из <?= $t['size'] ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($top): ?>
    <div class="dark rating-box">
      <div>
        <h2 class="h2">Рейтинг команд</h2>
        <p class="sub">Средняя надёжность участников, округлённая до целого.</p>
        <div class="panel" style="margin-top:24px">
          <table class="tbl">
            <tbody>
            <?php foreach ($top as $i => $t): ?>
              <tr>
                <td style="width:50px;color:#9AA2AA"><b>#<?= $i + 1 ?></b></td>
                <td><a class="game-cell" href="<?= url('team.php', ['id' => $t['id']]) ?>"><?= cover($t, 24, 36) ?><b><?= e($t['name']) ?></b></a></td>
                <td class="m-hide" style="color:#9AA2AA"><?= e($t['game_title']) ?></td>
                <td class="r"><?= lvl($t['rating'], 34) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <img src="<?= url('assets/img/photos/photo-club.jpg') ?>" alt="Игроки в компьютерном клубе" loading="lazy">
    </div>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php';
