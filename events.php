<?php
require __DIR__ . '/includes/bootstrap.php';

$tab = in_array(get('tab'), ['mine', 'past'], true) ? get('tab') : 'soon';
$game = get_int('game');
$period = in_array(get('period'), ['week', 'month'], true) ? get('period') : '';
$freeOnly = !empty($_GET['free']);
$newbies = !empty($_GET['newbies']);
if ($tab === 'mine') {
    require_login();
}

$where = [];
$params = [];
if ($tab === 'past') {
    $where[] = 'e.starts_at < NOW()';
} else {
    $where[] = 'e.starts_at >= NOW()';
}
if ($tab === 'mine') {
    $where[] = '(e.organizer_id = ? OR EXISTS (SELECT 1 FROM event_participants x WHERE x.event_id = e.id AND x.user_id = ?))';
    array_push($params, uid(), uid());
}
if ($game) {
    $where[] = 'e.game_id = ?';
    $params[] = $game;
}
if ($period === 'week') {
    $where[] = 'e.starts_at < NOW() + INTERVAL 7 DAY';
} elseif ($period === 'month') {
    $where[] = 'e.starts_at < NOW() + INTERVAL 30 DAY';
}
if ($newbies) {
    $where[] = "e.play_mode = 'newbies'";
}
$having = $freeOnly ? 'HAVING taken < e.slots' : '';
$events = all('SELECT e.*, g.title AS game_title, g.cover, COALESCE(g.banner, g.cover) AS img,
        (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.status = \'accepted\') AS taken,
        (SELECT status FROM event_participants ep WHERE ep.event_id = e.id AND ep.user_id = ?) AS my_status
    FROM events e JOIN games g ON g.id = e.game_id
    WHERE ' . implode(' AND ', $where) . " $having ORDER BY e.starts_at " . ($tab === 'past' ? 'DESC' : 'ASC'), array_merge([uid() ?? 0], $params));
$games = all('SELECT id, title FROM games ORDER BY title');
$feat = ($tab === 'soon' && $events) ? array_shift($events) : null;
$statusLabel = ['pending' => ['на рассмотрении', 'am'], 'accepted' => ['ты участвуешь', 'gr'], 'rejected' => ['заявка отклонена', 'rd']];

function tab_url(string $tab): string
{
    return url('events.php', array_filter(['tab' => $tab === 'soon' ? null : $tab, 'game' => get_int('game') ?: null]));
}

$active = 'events';
$page_title = 'События';
require __DIR__ . '/includes/header.php';
?>
<main>
  <section class="wrap page-head">
    <div>
      <div class="crumb"><a href="<?= url('index.php') ?>">главная</a> / события</div>
      <h1 class="h1">События</h1>
      <p class="sub">Игровые вечера с местами. Подай заявку один или командой.</p>
    </div>
    <a class="pill g lg" href="<?= url('event_edit.php') ?>">+ Создать событие</a>
  </section>
  <section class="wrap" style="padding-bottom:72px">
    <form class="toolbar" method="get" action="<?= url('events.php') ?>">
      <div class="seg-links" style="width:380px;max-width:100%">
        <a class="<?= $tab === 'soon' ? 'on' : '' ?>" href="<?= tab_url('soon') ?>">ближайшие</a>
        <a class="<?= $tab === 'mine' ? 'on' : '' ?>" href="<?= tab_url('mine') ?>">мои</a>
        <a class="<?= $tab === 'past' ? 'on' : '' ?>" href="<?= tab_url('past') ?>">прошедшие</a>
      </div>
      <?php if ($tab !== 'soon'): ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><?php endif; ?>
      <select class="inp" name="game" data-autosubmit aria-label="Игра">
        <option value="">Все игры</option>
        <?php foreach ($games as $g): ?><option value="<?= $g['id'] ?>" <?= $game === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['title']) ?></option><?php endforeach; ?>
      </select>
      <?php if ($tab !== 'past'): ?>
      <select class="inp" name="period" data-autosubmit aria-label="Период" style="width:200px">
        <option value="">Любая дата</option>
        <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Эта неделя</option>
        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Этот месяц</option>
      </select>
      <?php endif; ?>
      <label class="chk tag" style="height:40px;padding:0 16px"><input type="checkbox" name="free" value="1" data-autosubmit <?= $freeOnly ? 'checked' : '' ?> style="width:16px;height:16px">есть места</label>
      <label class="chk tag" style="height:40px;padding:0 16px"><input type="checkbox" name="newbies" value="1" data-autosubmit <?= $newbies ? 'checked' : '' ?> style="width:16px;height:16px">для новичков</label>
    </form>

    <?php if ($feat): ?>
      <a class="feat dark" href="<?= url('event.php', ['id' => $feat['id']]) ?>">
        <img src="<?= e(url($feat['img'])) ?>" alt="">
        <div>
          <span class="tag gr">ближайшее · <?= e(fmt_dt($feat['starts_at'])) ?></span>
          <h2 class="h1" style="margin-top:16px"><?= e($feat['title']) ?></h2>
          <p><?= e($feat['game_title']) ?> · <?= plural((int)$feat['slots'], 'игрок', 'игрока', 'игроков') ?> · <?= MODES[$feat['play_mode']] ?>. <?= e(mb_strimwidth($feat['description'], 0, 110, '…')) ?></p>
          <div class="row" style="gap:20px;margin-top:26px;flex-wrap:wrap"><span class="pill g lg">Подать заявку</span><span style="color:#C9CED3">занято <?= $feat['taken'] ?> из <?= $feat['slots'] ?></span></div>
        </div>
      </a>
    <?php endif; ?>

    <?php if (!$events && !$feat): ?>
      <div class="empty" style="margin-top:28px"><?= $tab === 'mine' ? 'Ты пока не подавал заявок и не создавал событий.' : 'Событий по фильтрам нет.' ?> <a class="more" href="<?= url('events.php') ?>">Все события</a></div>
    <?php endif; ?>
    <div class="stack" style="gap:14px;margin-top:20px">
      <?php foreach ($events as $ev): $t = strtotime($ev['starts_at']); $pct = min(100, round($ev['taken'] / max(1, $ev['slots']) * 100)); $full = $ev['taken'] >= $ev['slots']; ?>
        <article class="card ev-row">
          <?= cover($ev, 80, 120) ?>
          <div class="date"><b><?= date('j', $t) ?></b><span class="hint"><?= MONTHS[(int)date('n', $t) - 1] ?> · <?= DAYS[(int)date('N', $t) - 1] ?></span></div>
          <div class="info">
            <h3 class="h3"><a href="<?= url('event.php', ['id' => $ev['id']]) ?>"><?= e($ev['title']) ?></a></h3>
            <div class="mut" style="font-size:15px;margin-top:4px"><?= e($ev['game_title']) ?> · начало в <?= date('H:i', $t) ?></div>
            <div class="tags" style="margin-top:10px"><span class="tag sm"><?= plural((int)$ev['slots'], 'игрок', 'игрока', 'игроков') ?></span><span class="tag sm"><?= MODES[$ev['play_mode']] ?></span>
              <?php if ($ev['my_status']): ?><span class="tag sm <?= $statusLabel[$ev['my_status']][1] ?>"><?= $statusLabel[$ev['my_status']][0] ?></span><?php endif; ?>
              <?php if ((int)$ev['organizer_id'] === uid()): ?><span class="tag sm on">ты организатор</span><?php endif; ?></div>
          </div>
          <div class="fill"><div class="bar"><i style="width:<?= $pct ?>%"></i></div><div class="hint" style="margin-top:8px"><?= $full ? 'мест нет' : 'занято ' . $ev['taken'] . ' из ' . $ev['slots'] ?></div></div>
          <a class="pill <?= $tab === 'past' ? 'out' : 'dk' ?>" href="<?= url('event.php', ['id' => $ev['id']]) ?>"><?= $tab === 'past' ? 'Открыть' : ($full ? 'Подробнее' : 'Подать заявку') ?></a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php';
