<?php
// Создание и редактирование события
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
if ($me['role'] === 'admin' && !get_int('id') && !post('id')) {
    flash('info', 'Администратор управляет событиями, но не создаёт их от своего имени.');
    redirect('events.php');
}

$id = get_int('id') ?: (int)post('id');
$ev = null;
if ($id) {
    $ev = one('SELECT * FROM events WHERE id = ?', [$id]);
    if (!$ev) {
        not_found('Событие');
    }
    if (!can_edit_event($ev)) {
        forbidden('Редактировать событие может только организатор.');
    }
    if (strtotime($ev['starts_at']) < time()) {
        flash('err', 'Прошедшее событие изменить нельзя.');
        redirect('event.php', ['id' => $id]);
    }
}
$games = all('SELECT id, title FROM games ORDER BY title');
$myTeams = all('SELECT t.id, t.name, t.game_id FROM teams t WHERE t.captain_id = ? ORDER BY t.name', [$ev['organizer_id'] ?? $me['id']]);
$taken = $ev ? (int)val("SELECT COUNT(*) FROM event_participants WHERE event_id = ? AND status = 'accepted'", [$id]) : 1;
$presetTeam = get_int('team');
$presetGame = 0;
foreach ($myTeams as $mt) {
    if ((int)$mt['id'] === $presetTeam) {
        $presetGame = (int)$mt['game_id'];
    }
}

$start = $ev ? strtotime($ev['starts_at']) : strtotime('tomorrow 20:00');
$data = [
    'title' => $ev['title'] ?? '',
    'game_id' => (int)($ev['game_id'] ?? $presetGame),
    'team_id' => (int)($ev['team_id'] ?? $presetTeam),
    'date' => date('Y-m-d', $start),
    'time' => date('H:i', $start),
    'duration' => (int)($ev['duration_min'] ?? 180),
    'slots' => (int)($ev['slots'] ?? 4),
    'play_mode' => $ev['play_mode'] ?? 'chill',
    'need_mic' => (int)($ev['need_mic'] ?? 1),
    'min_reliability' => (int)($ev['min_reliability'] ?? 0),
    'description' => $ev['description'] ?? '',
];
$errors = [];

if (is_post()) {
    check_csrf();
    $data = [
        'title' => post('title'),
        'game_id' => (int)post('game_id'),
        'team_id' => (int)post('team_id'),
        'date' => post('date'),
        'time' => post('time'),
        'duration' => (int)post('duration'),
        'slots' => (int)post('slots'),
        'play_mode' => post('play_mode'),
        'need_mic' => empty($_POST['need_mic']) ? 0 : 1,
        'min_reliability' => (int)post('min_reliability'),
        'description' => post('description'),
    ];
    if (!len_between($data['title'], 3, 60)) {
        $errors['title'] = 'Название: от 3 до 60 символов';
    }
    if (!in_array($data['game_id'], array_map('intval', array_column($games, 'id')), true)) {
        $errors['game_id'] = 'Выбери игру';
    }
    if ($data['team_id'] && !in_array($data['team_id'], array_map('intval', array_column($myTeams, 'id')), true)) {
        $errors['team_id'] = 'Можно указать только свою команду';
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['time']);
    if (!$dt || $dt->format('Y-m-d H:i') !== $data['date'] . ' ' . $data['time']) {
        $errors['date'] = 'Укажи дату и время';
    } elseif ($dt->getTimestamp() < time() + 30 * 60) {
        $errors['date'] = 'Событие должно начинаться не раньше чем через 30 минут';
    } elseif ($dt->getTimestamp() > time() + 180 * 86400) {
        $errors['date'] = 'Слишком далеко — не позже чем через полгода';
    }
    if ($data['duration'] < 30 || $data['duration'] > 600) {
        $errors['duration'] = 'Длительность от 30 до 600 минут';
    }
    if ($data['slots'] < 2 || $data['slots'] > 20) {
        $errors['slots'] = 'Мест — от 2 до 20';
    } elseif ($data['slots'] < $taken) {
        $errors['slots'] = 'Уже участвуют ' . $taken . ' — мест не может быть меньше';
    }
    if (!isset(MODES[$data['play_mode']])) {
        $errors['play_mode'] = 'Выбери режим';
    }
    if ($data['min_reliability'] < 0 || $data['min_reliability'] > 10) {
        $errors['min_reliability'] = 'От 0 до 10';
    }
    if (mb_strlen($data['description']) > 500) {
        $errors['description'] = 'Не больше 500 символов';
    }

    if (!$errors) {
        $vals = [$data['title'], $data['game_id'], $data['team_id'] ?: null, $dt->format('Y-m-d H:i:00'), $data['duration'], $data['slots'],
            $data['play_mode'], $data['need_mic'], $data['min_reliability'], $data['description']];
        $pdo = db();
        $pdo->beginTransaction();
        if ($ev) {
            q('UPDATE events SET title = ?, game_id = ?, team_id = ?, starts_at = ?, duration_min = ?, slots = ?, play_mode = ?, need_mic = ?, min_reliability = ?, description = ? WHERE id = ?', [...$vals, $id]);
            $msg = 'Событие обновлено.';
        } else {
            q('INSERT INTO events (title, game_id, team_id, starts_at, duration_min, slots, play_mode, need_mic, min_reliability, description, organizer_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$vals, $me['id']]);
            $id = (int)$pdo->lastInsertId();
            q("INSERT INTO event_participants (event_id, user_id, team_id, comment, status) VALUES (?, ?, ?, 'организатор', 'accepted')", [$id, $me['id'], $data['team_id'] ?: null]);
            $msg = 'Событие создано! Ссылкой можно поделиться с командой.';
        }
        $pdo->commit();
        flash('ok', $msg);
        redirect('event.php', ['id' => $id]);
    }
}

$active = 'events';
$page_title = $ev ? 'Редактирование события' : 'Новое событие';
require __DIR__ . '/includes/header.php';
?>
<main>
  <section class="wrap page-head">
    <div>
      <div class="crumb"><a href="<?= url('events.php') ?>">события</a> / <?= $ev ? e($ev['title']) . ' / редактирование' : 'новое событие' ?></div>
      <h1 class="h1"><?= $ev ? 'Редактирование события' : 'Новое событие' ?></h1>
      <p class="sub">Игроки увидят событие в ленте и смогут подать заявку.</p>
    </div>
  </section>
  <div class="wrap grid-side" style="padding-bottom:72px">
    <form class="card box form" method="post" action="<?= url('event_edit.php', array_filter(['id' => $id ?: null])) ?>" data-validate>
      <?= csrf_field() ?>
      <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
      <?php if ($errors): ?><div class="form-err" role="alert">Не получилось сохранить — проверь поля, отмеченные красным.</div><?php endif; ?>
      <div class="fl">
        <label class="lab req" for="title">Название</label>
        <input class="inp<?= err_class($errors, 'title') ?>" id="title" name="title" value="<?= e($data['title']) ?>" maxlength="60" data-required data-min="3" data-max="60" data-msg-len="Название: от 3 до 60 символов" placeholder="Например: Elite Deep Dive">
        <?= field_error($errors, 'title') ?>
      </div>
      <div class="two">
        <div class="fl">
          <label class="lab req" for="game_id">Игра</label>
          <select class="inp<?= err_class($errors, 'game_id') ?>" id="game_id" name="game_id" data-required data-msg-required="Выбери игру">
            <option value="">Выбери игру</option>
            <?php foreach ($games as $g): ?><option value="<?= $g['id'] ?>" <?= $data['game_id'] === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['title']) ?></option><?php endforeach; ?>
          </select>
          <?= field_error($errors, 'game_id') ?>
        </div>
        <div class="fl">
          <label class="lab" for="team_id">От команды</label>
          <select class="inp<?= err_class($errors, 'team_id') ?>" id="team_id" name="team_id">
            <option value="0">без команды</option>
            <?php foreach ($myTeams as $mt): ?><option value="<?= $mt['id'] ?>" <?= $data['team_id'] === (int)$mt['id'] ? 'selected' : '' ?>><?= e($mt['name']) ?></option><?php endforeach; ?>
          </select>
          <?= field_error($errors, 'team_id') ?>
        </div>
      </div>
      <div class="two">
        <div class="fl">
          <label class="lab req" for="date">Дата</label>
          <input class="inp<?= err_class($errors, 'date') ?>" id="date" name="date" type="date" value="<?= e($data['date']) ?>" min="<?= date('Y-m-d') ?>" data-required>
          <?= field_error($errors, 'date') ?>
        </div>
        <div class="fl">
          <label class="lab req" for="time">Начало (МСК)</label>
          <input class="inp" id="time" name="time" type="time" value="<?= e($data['time']) ?>" data-required data-type="time">
        </div>
      </div>
      <div class="two">
        <div class="fl">
          <label class="lab" for="duration">Длительность, мин</label>
          <input class="inp<?= err_class($errors, 'duration') ?>" id="duration" name="duration" type="number" min="30" max="600" step="10" value="<?= $data['duration'] ?>">
          <?= field_error($errors, 'duration') ?>
        </div>
        <div class="fl">
          <label class="lab" for="slots">Мест</label>
          <input class="inp<?= err_class($errors, 'slots') ?>" id="slots" name="slots" type="number" min="2" max="20" value="<?= $data['slots'] ?>">
          <?= field_error($errors, 'slots') ?>
        </div>
      </div>
      <div class="fl">
        <span class="lab">Режим</span>
        <div class="seg">
          <?php foreach (MODES as $k => $label): ?><label><input type="radio" name="play_mode" value="<?= $k ?>" <?= $data['play_mode'] === $k ? 'checked' : '' ?>><span><?= $label ?></span></label><?php endforeach; ?>
        </div>
        <?= field_error($errors, 'play_mode') ?>
      </div>
      <div class="two">
        <label class="tog"><input type="checkbox" name="need_mic" value="1" <?= $data['need_mic'] ? 'checked' : '' ?>><i></i>Нужен микрофон</label>
        <div class="fl">
          <label class="lab" for="min_rel">Надёжность от (0 — любая)</label>
          <input class="inp<?= err_class($errors, 'min_reliability') ?>" id="min_rel" name="min_reliability" type="number" min="0" max="10" value="<?= $data['min_reliability'] ?>">
          <?= field_error($errors, 'min_reliability') ?>
        </div>
      </div>
      <div class="fl">
        <label class="lab" for="description">Описание</label>
        <textarea class="inp<?= err_class($errors, 'description') ?>" id="description" name="description" maxlength="500" data-max="500" data-counter="#d-c" placeholder="Что проходите, какой нужен опыт, где собираетесь"><?= e($data['description']) ?></textarea>
        <span class="counter" id="d-c">0 / 500</span>
        <?= field_error($errors, 'description') ?>
      </div>
      <div class="row" style="flex-wrap:wrap">
        <button class="pill g lg" type="submit"><?= $ev ? 'Сохранить' : 'Создать событие' ?></button>
        <a class="pill out lg" href="<?= $ev ? url('event.php', ['id' => $id]) : url('events.php') ?>">Отмена</a>
      </div>
    </form>
    <aside class="stack">
      <div class="card box"><h2 class="h3">Как это работает</h2>
        <p class="mut" style="margin-top:10px;font-size:15px">Ты станешь организатором и сразу займёшь одно место. Заявки других игроков будут приходить на страницу события — принимай подходящих.</p>
        <p class="mut" style="margin-top:10px;font-size:15px">После события участники оценят друг друга, и это повлияет на их надёжность.</p></div>
      <img src="<?= url('assets/img/photos/photo-hero.jpg') ?>" alt="" style="width:100%;height:320px;object-fit:cover" loading="lazy">
    </aside>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php';
