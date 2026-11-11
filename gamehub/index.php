<?php
require __DIR__ . '/includes/bootstrap.php';

$games = all("SELECT g.*, (SELECT COUNT(*) FROM player_games pg JOIN player_profiles p ON p.user_id = pg.user_id
                          WHERE pg.game_id = g.id AND p.looking_now = 1) AS looking
              FROM games g ORDER BY g.id LIMIT 6");

$players = all("SELECT u.id, u.nickname, u.avatar, p.looking_for, p.days, p.time_from, p.time_to,
                       g.title, g.cover, " . RELIABILITY_SQL . " AS rel
                FROM users u
                JOIN player_profiles p ON p.user_id = u.id
                LEFT JOIN player_games pg ON pg.user_id = u.id AND pg.is_main = 1
                LEFT JOIN games g ON g.id = pg.game_id
                WHERE p.looking_for <> '' AND u.role = 'user'
                ORDER BY p.looking_now DESC, rel DESC
                LIMIT 5");

$events = all("SELECT e.*, g.title AS game_title, g.cover AS game_cover, COALESCE(g.banner, g.cover) AS img,
                      (SELECT COUNT(*) FROM event_participants ep WHERE ep.event_id = e.id AND ep.status = 'accepted') AS taken
               FROM events e JOIN games g ON g.id = e.game_id
               WHERE e.starts_at > NOW()
               ORDER BY e.starts_at LIMIT 3");

$active = 'home';
require __DIR__ . '/includes/header.php';
?>
<main>
  <section class="hero dark">
    <img src="<?= url('assets/img/photos/photo-lan.jpg') ?>" alt="Игроки в наушниках за компьютерами">
    <div class="wrap">
      <div class="hero-in">
        <p class="kicker">Поиск тиммейтов · кооператив и командные игры</p>
        <h1>Собери команду на вечер<br>с теми, кто будет на связи</h1>
        <p>Ищи игроков по свободным часам, режиму игры и микрофону. После каждой встречи участники оценивают друг друга — отзывы можно посмотреть в профиле.</p>
        <div class="btns">
          <?php if (current_user()): ?>
            <a class="pill g lg" href="<?= url('players.php') ?>">Найти игроков</a>
          <?php else: ?>
            <a class="pill g lg" href="<?= url('register.php') ?>">Начать бесплатно</a>
          <?php endif; ?>
          <a class="pill ghost lg" href="#how">Как это работает</a>
        </div>
      </div>
    </div>
  </section>

  <section class="sec">
    <div class="wrap">
      <div class="sec-head">
        <div><h2 class="h2">Выбери игру</h2><p class="sub">Сначала игра, потом люди — так проще найти своих.</p></div>
        <a class="more" href="<?= url('games.php') ?>">весь каталог →</a>
      </div>
      <div class="game-strip">
        <?php foreach ($games as $g): ?>
          <a class="gtile" href="<?= url('players.php', ['game' => $g['id']]) ?>">
            <?= cover($g, 300, 450) ?>
            <b><?= e($g['title']) ?></b>
            <span><?= e($g['genre']) ?> · <?= e($g['format']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sec soft" id="how">
    <div class="wrap how">
      <img src="<?= url('assets/img/photos/photo-hero.jpg') ?>" alt="Двое играют в наушниках" loading="lazy">
      <div>
        <h2 class="h2">Как это работает</h2>
        <p class="sub">Четыре шага от регистрации до первой игры.</p>
        <div class="steps">
          <div class="step"><i>1</i><b>Заполни анкету</b><p>Укажи игры, дни и часы, когда свободен, и есть ли микрофон.</p></div>
          <div class="step"><i>2</i><b>Найди своих</b><p>Фильтруй игроков по игре, времени и режиму: спокойно или на результат.</p></div>
          <div class="step"><i>3</i><b>Собери команду</b><p>Создай команду и отправь приглашения тем, кто подошёл.</p></div>
          <div class="step"><i>4</i><b>Оцени игру</b><p>После события участники ставят друг другу оценки — они влияют на надёжность.</p></div>
        </div>
      </div>
    </div>
  </section>

  <section class="sec dark">
    <div class="wrap">
      <div class="sec-head">
        <div><h2 class="h2">Игроки ищут компанию</h2><p class="sub">Открытые анкеты: кто, во что и когда хочет играть.</p></div>
        <a class="more" href="<?= url('players.php') ?>">все игроки →</a>
      </div>
      <div class="panel">
        <?php if (!$players): ?>
          <p class="empty" style="background:none">Пока никто не ищет компанию. Будь первым — заполни анкету в профиле.</p>
        <?php else: ?>
        <table class="tbl">
          <thead><tr><th>Игрок</th><th>Игра</th><th>Кого ищет</th><th class="hide-t">Когда</th><th>Надёжность</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($players as $p): ?>
            <tr>
              <td><span class="player-cell"><?= ava($p) ?><b><?= e($p['nickname']) ?></b></span></td>
              <td class="m-hide"><?php if ($p['title']): ?><span class="game-cell"><?= cover($p, 28, 42) ?><?= e($p['title']) ?></span><?php endif; ?></td>
              <td class="m-full" style="color:#C9CED3"><?= e($p['looking_for']) ?></td>
              <td class="hide-t m-hide"><?= e(days_label($p['days'])) ?>, <?= substr($p['time_from'], 0, 5) ?>–<?= substr($p['time_to'], 0, 5) ?></td>
              <td class="m-side"><?= lvl($p['rel']) ?></td>
              <td class="r m-full"><a class="pill g sm" href="<?= url('player.php', ['id' => $p['id']]) ?>">Профиль</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
      <p class="hint" style="margin-top:16px">Надёжность от 1 до 10 считается по оценкам после событий: пришёл ли игрок вовремя и доиграл ли до конца. «new» — у игрока ещё нет оценок.</p>
    </div>
  </section>

  <section class="sec">
    <div class="wrap">
      <div class="sec-head">
        <div><h2 class="h2">Ближайшие события</h2><p class="sub">Подай заявку один или сразу командой.</p></div>
        <a class="more" href="<?= url('events.php') ?>">все события →</a>
      </div>
      <div class="ev-cards">
        <?php foreach ($events as $ev): $pct = $ev['slots'] ? min(100, round($ev['taken'] / $ev['slots'] * 100)) : 0; ?>
          <article class="card ev-card">
            <img src="<?= e(url($ev['img'])) ?>" alt="<?= e($ev['game_title']) ?>" loading="lazy">
            <div class="body">
              <div class="when"><?= e(fmt_dt($ev['starts_at'])) ?></div>
              <h3 class="h3" style="font-size:21px;margin-top:6px"><?= e($ev['title']) ?></h3>
              <div class="mut" style="font-size:15px"><?= e($ev['game_title']) ?></div>
              <div class="tags" style="margin-top:14px"><span class="tag"><?= plural((int)$ev['slots'], 'игрок', 'игрока', 'игроков') ?></span><span class="tag"><?= MODES[$ev['play_mode']] ?></span></div>
              <div class="bar" style="margin-top:18px"><i style="width:<?= $pct ?>%"></i></div>
              <div class="row" style="justify-content:space-between;margin-top:16px"><span class="hint">занято <?= $ev['taken'] ?> из <?= $ev['slots'] ?></span><a class="pill dk sm" href="<?= url('event.php', ['id' => $ev['id']]) ?>">Подать заявку</a></div>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if (!$events): ?><p class="empty">Ближайших событий пока нет.</p><?php endif; ?>
      </div>
    </div>
  </section>

  <section class="cta">
    <img src="<?= url('assets/img/photos/photo-couch.jpg') ?>" alt="Друзья играют вместе" loading="lazy">
    <div>
      <h2 class="h2" style="font-size:40px">Свободен сегодня вечером?</h2>
      <p>Заполни анкету за пару минут: игры, дни и часы, есть ли микрофон. Дальше GameHub покажет тех, у кого время совпадает с твоим.</p>
      <a class="pill lg" href="<?= url(current_user() ? 'profile.php' : 'register.php') ?>"><?= current_user() ? 'Заполнить анкету' : 'Создать аккаунт' ?></a>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php';
