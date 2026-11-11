<?php
require __DIR__ . '/includes/bootstrap.php';

$search = get('q');
$genre = get('genre');
$sort = get('sort', 'title');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = 'g.title LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($genre !== '') {
    $where[] = 'g.genre = ?';
    $params[] = $genre;
}
$order = match ($sort) {
    'popular' => 'players DESC, g.title',
    'looking' => 'looking DESC, g.title',
    default => 'g.title',
};
$games = all('SELECT g.*,
        (SELECT COUNT(*) FROM player_games pg WHERE pg.game_id = g.id) AS players,
        (SELECT COUNT(*) FROM player_games pg JOIN player_profiles p ON p.user_id = pg.user_id WHERE pg.game_id = g.id AND p.looking_now = 1) AS looking,
        (SELECT COUNT(*) FROM events e WHERE e.game_id = g.id AND e.starts_at > NOW()) AS events
    FROM games g ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $order, $params);
$genres = all('SELECT DISTINCT genre FROM games ORDER BY genre');

$active = 'games';
$page_title = 'Каталог игр';
require __DIR__ . '/includes/header.php';
?>
<main>
  <section class="page-banner dark">
    <img src="<?= url('assets/img/games/banner-drg.jpg') ?>" alt="">
    <div class="wrap">
      <div class="crumb"><a href="<?= url('index.php') ?>">главная</a> / игры</div>
      <h1 class="h1" style="margin-top:10px">Каталог игр</h1>
      <p class="sub" style="color:#C9CED3">Выбери игру, чтобы увидеть игроков, команды и события по ней.</p>
    </div>
  </section>
  <section class="wrap" style="padding-top:32px;padding-bottom:72px">
    <form class="toolbar" method="get" action="<?= url('games.php') ?>" role="search">
      <input class="inp" type="search" name="q" value="<?= e($search) ?>" placeholder="⌕  Название игры" aria-label="Название игры">
      <a class="tag<?= $genre === '' ? ' on' : '' ?>" style="height:40px;padding:0 18px" href="<?= url('games.php', array_filter(['q' => $search, 'sort' => $sort])) ?>">все</a>
      <?php foreach ($genres as $gr): ?>
        <a class="tag<?= $genre === $gr['genre'] ? ' on' : '' ?>" style="height:40px;padding:0 18px" href="<?= url('games.php', array_filter(['q' => $search, 'genre' => $gr['genre'], 'sort' => $sort])) ?>"><?= e($gr['genre']) ?></a>
      <?php endforeach; ?>
      <?php if ($genre !== ''): ?><input type="hidden" name="genre" value="<?= e($genre) ?>"><?php endif; ?>
      <label class="sort">сортировка:
        <select name="sort" data-autosubmit>
          <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>по названию</option>
          <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>по числу игроков</option>
          <option value="looking" <?= $sort === 'looking' ? 'selected' : '' ?>>ищут сейчас</option>
        </select>
      </label>
      <?php if (is_admin()): ?><a class="pill dk sm" href="<?= url('admin/games.php') ?>">Управлять играми</a><?php endif; ?>
    </form>

    <?php if (!$games): ?>
      <div class="empty" style="margin-top:28px">По запросу «<?= e($search) ?>» игр не нашлось. <a class="more" href="<?= url('games.php') ?>">Сбросить фильтры</a></div>
    <?php endif; ?>
    <div class="games-grid">
      <?php foreach ($games as $g): ?>
        <article class="card game-card">
          <?= cover($g, 200, 300) ?>
          <div class="body">
            <div class="tags"><span class="tag"><?= e($g['genre']) ?></span><span class="tag"><?= e($g['format']) ?></span></div>
            <h2 class="h3" style="font-size:22px"><?= e($g['title']) ?></h2>
            <p class="mut" style="font-size:15px"><?= e($g['description']) ?></p>
            <p class="hint"><?= plural((int)$g['players'], 'игрок', 'игрока', 'игроков') ?> · ищут сейчас: <?= (int)$g['looking'] ?> · событий: <?= (int)$g['events'] ?></p>
            <div class="acts">
              <a class="pill g sm" href="<?= url('players.php', ['game' => $g['id']]) ?>">Найти игроков</a>
              <a class="pill out sm" href="<?= url('events.php', ['game' => $g['id']]) ?>">События</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php';
