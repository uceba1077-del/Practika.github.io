<?php
require __DIR__ . '/includes/bootstrap.php';

// ---- фильтры из адресной строки ----
$nick = get('q');
$now = !empty($_GET['now']);
$gameIds = array_values(array_filter(array_map('intval', (array)($_GET['game'] ?? [])), fn($v) => $v > 0));
$days = array_values(array_filter(array_map('intval', (array)($_GET['day'] ?? [])), fn($v) => $v >= 0 && $v <= 6));
$from = preg_match('/^\d{2}:\d{2}$/', get('from')) ? get('from') : '';
$to = preg_match('/^\d{2}:\d{2}$/', get('to')) ? get('to') : '';
$mode = in_array(get('mode'), ['chill', 'tryhard'], true) ? get('mode') : '';
$mic = !empty($_GET['mic']);
$minRel = in_array(get_int('rel'), [5, 8], true) ? get_int('rel') : 0;
$sort = in_array(get('sort'), ['rel', 'new', 'nick'], true) ? get('sort') : 'default';
$page = max(1, get_int('page', 1));
$perPage = 10;

$where = ["u.role = 'user'"];
$params = [];
if ($nick !== '') {
    $where[] = 'u.nickname LIKE ?';
    $params[] = '%' . $nick . '%';
}
if ($now) {
    $where[] = 'p.looking_now = 1';
}
if ($gameIds) {
    $where[] = 'EXISTS (SELECT 1 FROM player_games x WHERE x.user_id = u.id AND x.game_id IN (' . implode(',', array_fill(0, count($gameIds), '?')) . '))';
    array_push($params, ...$gameIds);
}
if ($days) {
    $or = [];
    foreach ($days as $d) {
        $or[] = 'SUBSTRING(p.days, ?, 1) = \'1\'';
        $params[] = $d + 1;
    }
    $where[] = '(' . implode(' OR ', $or) . ')';
}
if ($from !== '' && $to !== '') {
    $where[] = 'p.time_from < ? AND p.time_to > ?';
    array_push($params, $to . ':00', $from . ':00');
}
if ($mode) {
    $where[] = 'p.play_mode = ?';
    $params[] = $mode;
}
if ($mic) {
    $where[] = 'p.has_mic = 1';
}
$having = $minRel ? 'HAVING rel >= ' . (int)$minRel : '';
$order = match ($sort) {
    'rel' => 'rel IS NULL, rel DESC, u.nickname',
    'new' => 'u.created_at DESC',
    'nick' => 'u.nickname',
    default => 'p.looking_now DESC, rel IS NULL, rel DESC',
};

$base = 'FROM users u JOIN player_profiles p ON p.user_id = u.id WHERE ' . implode(' AND ', $where);
$total = (int)val('SELECT COUNT(*) FROM (SELECT u.id, ' . RELIABILITY_SQL . ' AS rel ' . $base . ' ' . $having . ') t', $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$players = all('SELECT u.id, u.nickname, u.avatar, u.created_at, p.*, ' . RELIABILITY_SQL . ' AS rel ' . $base . ' ' . $having .
    ' ORDER BY ' . $order . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);

// игра, которую показываем в строке: из фильтра или основная
$gameOf = [];
if ($players) {
    $ids = array_column($players, 'user_id');
    $rows = all('SELECT pg.user_id, pg.game_id, pg.is_main, g.title, g.cover FROM player_games pg JOIN games g ON g.id = pg.game_id
                 WHERE pg.user_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY pg.is_main DESC', $ids);
    foreach ($rows as $r) {
        $prefer = $gameIds ? in_array((int)$r['game_id'], $gameIds, true) : (bool)$r['is_main'];
        if (!isset($gameOf[$r['user_id']]) || ($prefer && empty($gameOf[$r['user_id']]['_p']))) {
            $gameOf[$r['user_id']] = $r + ['_p' => $prefer];
        }
    }
}
$games = all('SELECT id, title FROM games ORDER BY title');
$activeFilters = ($now ? 1 : 0) + count($gameIds) + ($days ? 1 : 0) + ($from && $to ? 1 : 0) + ($mode ? 1 : 0) + ($mic ? 1 : 0) + ($minRel ? 1 : 0);

function page_link(int $p): string
{
    return url('players.php', array_merge($_GET, ['page' => $p]));
}

$active = 'players';
$page_title = 'Поиск игроков';
require __DIR__ . '/includes/header.php';
?>
<main>
  <section class="wrap page-head">
    <div>
      <div class="crumb"><a href="<?= url('index.php') ?>">главная</a> / игроки</div>
      <h1 class="h1">Поиск игроков</h1>
      <p class="sub">Подбери тех, у кого совпадают игра и свободное время.</p>
    </div>
  </section>
  <div class="wrap search">
    <form class="card filters" method="get" action="<?= url('players.php') ?>" id="filters">
      <div class="row" style="justify-content:space-between"><b class="h3">Фильтры</b><span class="row"><a class="more" style="font-size:14px" href="<?= url('players.php') ?>">сбросить</a><button type="button" class="pill lt xs filters-close" aria-label="Закрыть фильтры">×</button></span></div>
      <?php if ($nick !== ''): ?><input type="hidden" name="q" value="<?= e($nick) ?>"><?php endif; ?>
      <label class="now-box tog"><b>Ищу игроков сейчас</b><input type="checkbox" name="now" value="1" <?= $now ? 'checked' : '' ?>><i></i></label>
      <fieldset class="fl" style="border:0;padding:0;margin:0"><legend class="lab" style="margin-bottom:8px">Игра</legend>
        <div class="games-list">
          <?php foreach ($games as $g): ?>
            <label class="chk"><input type="checkbox" name="game[]" value="<?= $g['id'] ?>" <?= in_array((int)$g['id'], $gameIds, true) ? 'checked' : '' ?>><?= e($g['title']) ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <fieldset class="fl" style="border:0;padding:0;margin:0"><legend class="lab" style="margin-bottom:8px">Дни</legend>
        <div class="days">
          <?php foreach (DAYS as $i => $d): ?>
            <label><input type="checkbox" name="day[]" value="<?= $i ?>" <?= in_array($i, $days, true) ? 'checked' : '' ?>><span style="width:34px;height:34px;font-size:13px"><?= $d ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <div class="fl"><span class="lab">Время</span>
        <div class="row" style="gap:8px"><input class="inp" type="time" name="from" value="<?= e($from) ?>" aria-label="С" style="padding:0 12px">—<input class="inp" type="time" name="to" value="<?= e($to) ?>" aria-label="До" style="padding:0 12px"></div>
      </div>
      <div class="fl"><span class="lab">Режим</span>
        <div class="seg">
          <label><input type="radio" name="mode" value="" <?= $mode === '' ? 'checked' : '' ?>><span>любой</span></label>
          <label><input type="radio" name="mode" value="chill" <?= $mode === 'chill' ? 'checked' : '' ?>><span>спокойно</span></label>
          <label><input type="radio" name="mode" value="tryhard" <?= $mode === 'tryhard' ? 'checked' : '' ?>><span>результат</span></label>
        </div>
      </div>
      <label class="tog" style="justify-content:space-between"><span class="lab">Только с микрофоном</span><input type="checkbox" name="mic" value="1" <?= $mic ? 'checked' : '' ?>><i></i></label>
      <div class="fl"><span class="lab">Надёжность от</span>
        <div class="seg">
          <label><input type="radio" name="rel" value="0" <?= !$minRel ? 'checked' : '' ?>><span>любая</span></label>
          <label><input type="radio" name="rel" value="5" <?= $minRel === 5 ? 'checked' : '' ?>><span>5+</span></label>
          <label><input type="radio" name="rel" value="8" <?= $minRel === 8 ? 'checked' : '' ?>><span>8+</span></label>
        </div>
      </div>
      <input type="hidden" name="sort" value="<?= e($sort) ?>">
      <button class="pill g wide" type="submit">Показать</button>
    </form>

    <div>
      <form class="toolbar" method="get" action="<?= url('players.php') ?>" role="search">
        <?php foreach ($_GET as $k => $v): if (in_array($k, ['q', 'page', 'sort'], true)) continue; foreach ((array)$v as $vv): ?>
          <input type="hidden" name="<?= e($k) . (is_array($v) ? '[]' : '') ?>" value="<?= e((string)$vv) ?>">
        <?php endforeach; endforeach; ?>
        <input class="inp" style="flex:1;min-width:200px" type="search" name="q" value="<?= e($nick) ?>" placeholder="⌕  Ник игрока" aria-label="Ник игрока">
        <button type="button" class="pill dk filters-open">Фильтры<?= $activeFilters ? ' · ' . $activeFilters : '' ?></button>
        <label class="sort">сортировка:
          <select name="sort" data-autosubmit>
            <option value="default" <?= $sort === 'default' ? 'selected' : '' ?>>по умолчанию</option>
            <option value="rel" <?= $sort === 'rel' ? 'selected' : '' ?>>по надёжности</option>
            <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>сначала новые</option>
            <option value="nick" <?= $sort === 'nick' ? 'selected' : '' ?>>по нику</option>
          </select>
        </label>
      </form>
      <p class="hint" style="margin:14px 0 0">Найдено: <?= plural($total, 'игрок', 'игрока', 'игроков') ?></p>
      <div class="card tcard" style="margin-top:12px">
        <?php if (!$players): ?>
          <div class="empty" style="margin:16px 8px 20px">По фильтрам никого нет. <a class="more" href="<?= url('players.php') ?>">Сбросить фильтры</a></div>
        <?php else: ?>
        <table class="tbl">
          <thead><tr><th>Игрок</th><th>Игра</th><th>Кого ищет</th><th class="hide-t">Когда</th><th>Микрофон</th><th>Над.</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($players as $p): $g = $gameOf[$p['user_id']] ?? null; ?>
            <tr>
              <td><span class="player-cell"><?= ava($p) ?><span><b><?= e($p['nickname']) ?></b><?= $p['looking_now'] ? '<span class="now">ищет сейчас</span>' : '<span class="hint">в ' . e(days_label($p['days'])) . '</span>' ?></span></span></td>
              <td class="m-hide"><?php if ($g): ?><span class="game-cell"><?= cover($g, 28, 42) ?><?= e($g['title']) ?></span><?php else: ?><span class="hint">—</span><?php endif; ?></td>
              <td class="m-full mut"><?= e($p['looking_for'] ?: 'анкета не заполнена') ?></td>
              <td class="hide-t m-hide"><?= substr($p['time_from'], 0, 5) ?>–<?= substr($p['time_to'], 0, 5) ?></td>
              <td class="m-hide"><?= $p['has_mic'] ? '<span class="tag gr sm">есть</span>' : '<span class="tag sm">нет</span>' ?></td>
              <td class="m-side"><?= lvl($p['rel']) ?></td>
              <td class="r m-full"><a class="pill dk sm" href="<?= url('player.php', ['id' => $p['user_id']]) ?>">Профиль</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
      <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Страницы">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <?= $i === $page ? '<span class="on">' . $i . '</span>' : '<a href="' . e(page_link($i)) . '">' . $i . '</a>' ?>
          <?php endfor; ?>
          <?php if ($page < $pages): ?><a href="<?= e(page_link($page + 1)) ?>">дальше →</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php';
