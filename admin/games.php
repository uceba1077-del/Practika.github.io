<?php
// Админка: CRUD каталога игр. Доступна только роли admin (проверка на сервере).
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$editId = get_int('edit') ?: (int)post('id');
$edit = $editId ? one('SELECT * FROM games WHERE id = ?', [$editId]) : null;
$data = $edit ?: ['title' => '', 'genre' => '', 'format' => '', 'max_players' => 4, 'description' => ''];
$errors = [];

if (is_post()) {
    check_csrf();
    $action = post('action');
    if ($action === 'delete') {
        $g = one('SELECT * FROM games WHERE id = ?', [(int)post('id')]);
        if (!$g) {
            not_found('Игра');
        }
        $used = (int)val('SELECT (SELECT COUNT(*) FROM teams WHERE game_id = ?) + (SELECT COUNT(*) FROM events WHERE game_id = ?)', [$g['id'], $g['id']]);
        if ($used) {
            flash('err', 'Нельзя удалить «' . $g['title'] . '»: по ней есть команды или события (' . $used . ').');
        } else {
            q('DELETE FROM games WHERE id = ?', [$g['id']]);
            flash('info', 'Игра «' . $g['title'] . '» удалена.');
        }
        redirect('admin/games.php');
    }

    $data = ['title' => post('title'), 'genre' => post('genre'), 'format' => post('format'), 'max_players' => (int)post('max_players'), 'description' => post('description')];
    if (!len_between($data['title'], 2, 80)) {
        $errors['title'] = 'Название: 2–80 символов';
    } elseif (val('SELECT 1 FROM games WHERE title = ? AND id <> ?', [$data['title'], $editId])) {
        $errors['title'] = 'Такая игра уже есть';
    }
    if (!len_between($data['genre'], 2, 40)) {
        $errors['genre'] = 'Жанр: 2–40 символов';
    }
    if (!len_between($data['format'], 1, 20)) {
        $errors['format'] = 'Формат: 1–20 символов';
    }
    if ($data['max_players'] < 1 || $data['max_players'] > 100) {
        $errors['max_players'] = 'От 1 до 100';
    }
    if (mb_strlen($data['description']) > 300) {
        $errors['description'] = 'Не больше 300 символов';
    }
    [$coverPath, $upErr] = $errors ? [null, null] : upload_image('cover', 'games');
    if ($upErr) {
        $errors['cover'] = $upErr;
    }
    if (!$errors) {
        if ($edit) {
            q('UPDATE games SET title = ?, genre = ?, format = ?, max_players = ?, description = ?, cover = COALESCE(?, cover) WHERE id = ?',
                [$data['title'], $data['genre'], $data['format'], $data['max_players'], $data['description'], $coverPath, $editId]);
            flash('ok', 'Игра обновлена.');
        } else {
            q('INSERT INTO games (title, genre, format, max_players, description, cover) VALUES (?, ?, ?, ?, ?, ?)',
                [$data['title'], $data['genre'], $data['format'], $data['max_players'], $data['description'], $coverPath]);
            flash('ok', 'Игра добавлена в каталог.');
        }
        redirect('admin/games.php');
    }
}
$games = all('SELECT g.*, (SELECT COUNT(*) FROM teams t WHERE t.game_id = g.id) AS teams, (SELECT COUNT(*) FROM events e WHERE e.game_id = g.id) AS events FROM games g ORDER BY g.title');
$stats = one("SELECT (SELECT COUNT(*) FROM users) u, (SELECT COUNT(*) FROM teams) t, (SELECT COUNT(*) FROM events) e, (SELECT COUNT(*) FROM event_participants WHERE status = 'pending') p");

$page_title = 'Админка · игры';
require __DIR__ . '/../includes/header.php';
?>
<main class="wrap" style="padding-bottom:72px">
  <section class="page-head" style="padding-left:0;padding-right:0">
    <div><div class="crumb">админка / игры</div><h1 class="h1">Каталог игр</h1><p class="sub">Пользователей: <?= $stats['u'] ?> · команд: <?= $stats['t'] ?> · событий: <?= $stats['e'] ?> · заявок ждут: <?= $stats['p'] ?></p></div>
  </section>
  <form class="card box form" method="post" enctype="multipart/form-data" action="<?= url('admin/games.php') ?>" data-validate>
    <?= csrf_field() ?><?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= config()['upload_max'] ?>">
    <h2 class="h3"><?= $edit ? 'Изменить «' . e($edit['title']) . '»' : 'Добавить игру' ?></h2>
    <div class="admin-form">
      <div class="fl"><label class="lab req" for="t">Название</label><input class="inp<?= err_class($errors, 'title') ?>" id="t" name="title" value="<?= e($data['title']) ?>" maxlength="80" data-required><?= field_error($errors, 'title') ?></div>
      <div class="fl"><label class="lab req" for="gn">Жанр</label><input class="inp<?= err_class($errors, 'genre') ?>" id="gn" name="genre" value="<?= e($data['genre']) ?>" maxlength="40" data-required placeholder="кооператив"><?= field_error($errors, 'genre') ?></div>
      <div class="fl"><label class="lab req" for="f">Формат</label><input class="inp<?= err_class($errors, 'format') ?>" id="f" name="format" value="<?= e($data['format']) ?>" maxlength="20" data-required placeholder="5×5"><?= field_error($errors, 'format') ?></div>
      <div class="fl"><label class="lab" for="mp">Макс. игроков</label><input class="inp<?= err_class($errors, 'max_players') ?>" id="mp" name="max_players" type="number" min="1" max="100" value="<?= (int)$data['max_players'] ?>"><?= field_error($errors, 'max_players') ?></div>
      <div></div>
    </div>
    <div class="two">
      <div class="fl"><label class="lab" for="d">Описание</label><input class="inp<?= err_class($errors, 'description') ?>" id="d" name="description" value="<?= e($data['description']) ?>" maxlength="300" data-max="300"><?= field_error($errors, 'description') ?></div>
      <div class="fl"><label class="lab" for="c">Обложка 2:3</label><div class="file-box<?= err_class($errors, 'cover') ?>"><input id="c" name="cover" type="file" accept="image/png,image/jpeg" style="flex:1;min-width:0"></div><?= field_error($errors, 'cover') ?></div>
    </div>
    <div class="row"><button class="pill g" type="submit"><?= $edit ? 'Сохранить' : 'Добавить' ?></button><?php if ($edit): ?><a class="pill out" href="<?= url('admin/games.php') ?>">Отмена</a><?php endif; ?></div>
  </form>
  <div class="card tcard" style="margin-top:24px">
    <table class="tbl"><thead><tr><th>Игра</th><th>Жанр</th><th>Формат</th><th>Команд</th><th>Событий</th><th></th></tr></thead><tbody>
      <?php foreach ($games as $g): ?>
        <tr><td class="m-full"><span class="game-cell"><?= cover($g, 28, 42) ?><b><?= e($g['title']) ?></b></span></td><td class="mut"><?= e($g['genre']) ?></td><td><?= e($g['format']) ?></td><td><?= $g['teams'] ?></td><td><?= $g['events'] ?></td>
          <td class="r m-full"><span class="row" style="justify-content:flex-end;gap:6px"><a class="pill out xs" href="<?= url('admin/games.php', ['edit' => $g['id']]) ?>">изменить</a>
            <form method="post" action="<?= url('admin/games.php') ?>" data-confirm="Удалить игру «<?= e($g['title']) ?>»?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="pill danger xs" type="submit">удалить</button></form></span></td></tr>
      <?php endforeach; ?></tbody></table>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php';
