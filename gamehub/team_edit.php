<?php
// Создание и редактирование команды (C и U из CRUD). Удаление — actions/team_delete.php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();

$id = get_int('id') ?: (int)post('id');
$team = null;
if ($id) {
    $team = one('SELECT * FROM teams WHERE id = ?', [$id]);
    if (!$team) {
        not_found('Команда');
    }
    if (!can_edit_team($team)) {
        forbidden('Редактировать команду может только её капитан.');
    }
}
if (!$team && $me['role'] === 'admin') {
    flash('info', 'Администратор управляет командами, но не создаёт их от своего имени.');
    redirect('teams.php');
}
$inviteId = get_int('invite') ?: (int)post('invite');
$invitee = $inviteId ? one("SELECT id, nickname, avatar FROM users WHERE id = ? AND id <> ? AND role = 'user'", [$inviteId, $me['id']]) : null;

$games = all('SELECT id, title, cover, max_players FROM games ORDER BY id');
$membersNow = $team ? (int)val("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND status = 'member'", [$id]) : 1;

$data = [
    'name' => $team['name'] ?? '',
    'game_id' => (int)($team['game_id'] ?? 0),
    'size' => (int)($team['size'] ?? 4),
    'play_mode' => $team['play_mode'] ?? 'chill',
    'schedule' => $team['schedule'] ?? '',
    'about' => $team['about'] ?? '',
];
$errors = [];

if (is_post()) {
    check_csrf();
    $data = [
        'name' => post('name'),
        'game_id' => (int)post('game_id'),
        'size' => (int)post('size'),
        'play_mode' => post('play_mode'),
        'schedule' => post('schedule'),
        'about' => post('about'),
    ];
    if (!len_between($data['name'], 3, 30)) {
        $errors['name'] = 'Название: от 3 до 30 символов';
    } elseif (val('SELECT 1 FROM teams WHERE name = ? AND id <> ?', [$data['name'], $id])) {
        $errors['name'] = 'Команда с таким названием уже есть';
    }
    if (!in_array($data['game_id'], array_map('intval', array_column($games, 'id')), true)) {
        $errors['game_id'] = 'Выбери игру';
    }
    if ($data['size'] < 2 || $data['size'] > 10) {
        $errors['size'] = 'Размер команды — от 2 до 10 игроков';
    } elseif ($data['size'] < $membersNow) {
        $errors['size'] = 'В команде уже ' . $membersNow . ' игроков — размер не может быть меньше';
    }
    if (!isset(MODES[$data['play_mode']]) || $data['play_mode'] === 'newbies') {
        $errors['play_mode'] = 'Выбери режим';
    }
    if (mb_strlen($data['schedule']) > 60) {
        $errors['schedule'] = 'Не больше 60 символов';
    }
    if (mb_strlen($data['about']) > 300) {
        $errors['about'] = 'Не больше 300 символов';
    }
    [$emblem, $upErr] = $errors ? [null, null] : upload_image('emblem', 'emblems');
    if ($upErr) {
        $errors['emblem'] = $upErr;
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        if ($team) {
            q('UPDATE teams SET name = ?, game_id = ?, size = ?, play_mode = ?, schedule = ?, about = ?, emblem = COALESCE(?, emblem) WHERE id = ?',
                [$data['name'], $data['game_id'], $data['size'], $data['play_mode'], $data['schedule'], $data['about'], $emblem, $id]);
            if ($emblem && $team['emblem'] && is_file(__DIR__ . '/' . $team['emblem'])) {
                @unlink(__DIR__ . '/' . $team['emblem']);
            }
            $msg = 'Изменения сохранены.';
        } else {
            q('INSERT INTO teams (name, game_id, captain_id, size, play_mode, schedule, about, emblem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$data['name'], $data['game_id'], $me['id'], $data['size'], $data['play_mode'], $data['schedule'], $data['about'], $emblem]);
            $id = (int)$pdo->lastInsertId();
            q("INSERT INTO team_members (team_id, user_id, status) VALUES (?, ?, 'member')", [$id, $me['id']]);
            $msg = 'Команда создана!';
            if ($invitee) {
                q("INSERT INTO team_members (team_id, user_id, status, message) VALUES (?, ?, 'invited', ?)", [$id, $invitee['id'], 'Приглашаю в «' . $data['name'] . '»']);
                $msg = 'Команда создана, приглашение отправлено ' . $invitee['nickname'] . '.';
            }
        }
        $pdo->commit();
        flash('ok', $msg);
        redirect('team.php', ['id' => $id]);
    }
}

$active = 'teams';
$page_title = $team ? 'Редактирование команды' : 'Новая команда';
require __DIR__ . '/includes/header.php';
?>
<main>
  <section class="wrap page-head">
    <div>
      <div class="crumb"><a href="<?= url('teams.php') ?>">команды</a> / <?= $team ? e($team['name']) . ' / редактирование' : 'новая команда' ?></div>
      <h1 class="h1"><?= $team ? 'Редактирование команды' : 'Новая команда' ?></h1>
      <p class="sub"><?= $team ? 'Поменяй данные и сохрани.' : 'Заполни поля и пригласи игроков. Всё можно поменять потом.' ?></p>
    </div>
  </section>
  <div class="wrap grid-side" style="padding-bottom:72px">
    <form class="card box form" method="post" enctype="multipart/form-data" action="<?= url('team_edit.php', array_filter(['id' => $id ?: null])) ?>" data-validate>
      <?= csrf_field() ?>
      <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
      <?php if ($invitee): ?><input type="hidden" name="invite" value="<?= $invitee['id'] ?>"><?php endif; ?>
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= config()['upload_max'] ?>">
      <?php if ($errors): ?><div class="form-err" role="alert">Не получилось сохранить — проверь поля, отмеченные красным.</div><?php endif; ?>

      <div class="fl">
        <label class="lab req" for="name">Название</label>
        <input class="inp<?= err_class($errors, 'name') ?>" id="name" name="name" value="<?= e($data['name']) ?>" maxlength="30" data-required data-min="3" data-max="30" data-msg-len="Название: от 3 до 30 символов" data-ok-mark>
        <span class="hint">3–30 символов</span>
        <?= field_error($errors, 'name') ?>
      </div>
      <div class="fl">
        <span class="lab req">Игра</span>
        <div class="game-pick" data-group-required="Выбери игру">
          <?php foreach ($games as $g): ?>
            <label><input type="radio" name="game_id" value="<?= $g['id'] ?>" <?= $data['game_id'] === (int)$g['id'] ? 'checked' : '' ?>><?= cover($g, 88, 132) ?><?= e($g['title']) ?></label>
          <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'game_id') ?: '<span class="err-t"></span>' ?>
      </div>
      <div class="two">
        <div class="fl">
          <label class="lab" for="size">Размер команды</label>
          <select class="inp<?= err_class($errors, 'size') ?>" id="size" name="size">
            <?php for ($i = 2; $i <= 10; $i++): ?><option value="<?= $i ?>" <?= $data['size'] === $i ? 'selected' : '' ?>><?= plural($i, 'игрок', 'игрока', 'игроков') ?></option><?php endfor; ?>
          </select>
          <?= field_error($errors, 'size') ?>
        </div>
        <div class="fl">
          <span class="lab">Режим</span>
          <div class="seg">
            <label><input type="radio" name="play_mode" value="chill" <?= $data['play_mode'] === 'chill' ? 'checked' : '' ?>><span>спокойно</span></label>
            <label><input type="radio" name="play_mode" value="tryhard" <?= $data['play_mode'] === 'tryhard' ? 'checked' : '' ?>><span>на результат</span></label>
          </div>
          <?= field_error($errors, 'play_mode') ?>
        </div>
      </div>
      <div class="fl">
        <label class="lab" for="schedule">Когда играете</label>
        <input class="inp<?= err_class($errors, 'schedule') ?>" id="schedule" name="schedule" value="<?= e($data['schedule']) ?>" maxlength="60" data-max="60" placeholder="Например: вт, чт · 20:00–23:00">
        <?= field_error($errors, 'schedule') ?>
      </div>
      <div class="fl">
        <label class="lab" for="about">О команде</label>
        <textarea class="inp<?= err_class($errors, 'about') ?>" id="about" name="about" maxlength="300" data-max="300" data-counter="#about-c" placeholder="Когда играете, кого ищете, какие правила"><?= e($data['about']) ?></textarea>
        <span class="counter" id="about-c">0 / 300</span>
        <?= field_error($errors, 'about') ?>
      </div>
      <div class="fl">
        <label class="lab" for="emblem">Эмблема</label>
        <div class="file-box<?= err_class($errors, 'emblem') ?>">
          <input id="emblem" name="emblem" type="file" accept="image/png,image/jpeg" data-max-size="<?= config()['upload_max'] ?>" style="flex:1;min-width:0">
        </div>
        <span class="hint">PNG или JPG до 2 МБ<?= !empty($team['emblem']) ? ' · сейчас загружена своя эмблема' : '' ?></span>
        <?= field_error($errors, 'emblem') ?>
      </div>
      <div class="row" style="flex-wrap:wrap">
        <button class="pill g lg" type="submit"><?= $team ? 'Сохранить' : 'Создать команду' ?></button>
        <a class="pill out lg" href="<?= $team ? url('team.php', ['id' => $id]) : url('teams.php') ?>">Отмена</a>
      </div>
    </form>
    <aside class="stack">
      <div class="card box">
        <h2 class="h3">Состав</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px">
          <div class="soft" style="padding:18px;display:flex;flex-direction:column;align-items:center;gap:8px"><?= ava($me, 56) ?><b style="font-size:15px"><?= e($me['nickname']) ?></b><span class="tag on sm">капитан</span></div>
          <?php if ($invitee): ?>
            <div class="soft" style="padding:18px;display:flex;flex-direction:column;align-items:center;gap:8px"><?= ava($invitee, 56) ?><b style="font-size:15px"><?= e($invitee['nickname']) ?></b><span class="tag am sm">получит приглашение</span></div>
          <?php endif; ?>
          <div style="padding:18px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;box-shadow:inset 0 0 0 1.5px #C9CED3"><span class="slot-free" style="width:56px;height:56px;font-size:22px">+</span><span class="hint">остальных — из поиска</span></div>
        </div>
        <a class="pill lt wide" style="margin-top:18px" href="<?= url('players.php') ?>" target="_blank">⌕ Найти игроков</a>
      </div>
      <img src="<?= url('assets/img/photos/photo-couch.jpg') ?>" alt="Друзья играют вместе" style="width:100%;height:320px;object-fit:cover" loading="lazy">
    </aside>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php';
