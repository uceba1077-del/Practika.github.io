<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('profile.php');
}

$errors = [];
$old = ['nickname' => '', 'email' => ''];

if (is_post()) {
    check_csrf();
    $old['nickname'] = $nick = post('nickname');
    $old['email'] = $email = mb_strtolower(post('email'));
    $pass = (string)($_POST['password'] ?? '');

    // серверная валидация — дублирует клиентскую, но доверяем только ей
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $nick)) {
        $errors['nickname'] = 'Ник: 3–20 символов, латиница, цифры и _';
    } elseif (val('SELECT 1 FROM users WHERE nickname = ?', [$nick])) {
        $errors['nickname'] = 'Такой ник уже занят';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
        $errors['email'] = 'Укажи полный адрес, например name@mail.ru';
    } elseif (val('SELECT 1 FROM users WHERE email = ?', [$email])) {
        $errors['email'] = 'Эта почта уже зарегистрирована. Попробуй войти';
    }
    if (mb_strlen($pass) < 8 || !preg_match('/\d/', $pass) || !preg_match('/\p{L}/u', $pass)) {
        $errors['password'] = 'Не меньше 8 символов, буквы и цифры';
    } elseif (mb_strlen($pass) > 72) {
        $errors['password'] = 'Слишком длинный пароль (максимум 72 символа)';
    }
    if (empty($_POST['agree'])) {
        $errors['agree'] = 'Нужно согласие с правилами сервиса';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        q('INSERT INTO users (nickname, email, password_hash) VALUES (?, ?, ?)', [$nick, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        $id = (int)$pdo->lastInsertId();
        q('INSERT INTO player_profiles (user_id) VALUES (?)', [$id]);
        $pdo->commit();
        login_user($id);
        flash('ok', 'Аккаунт создан! Заполни анкету, чтобы тебя находили в поиске.');
        redirect('profile.php');
    }
}

$page_title = 'Регистрация';
require __DIR__ . '/includes/header.php';
?>
<main class="auth">
  <div class="auth-photo dark">
    <img src="<?= url('assets/img/photos/photo-club.jpg') ?>" alt="Игроки в компьютерном клубе">
    <div><h2 class="h1">Найди тех, с кем<br>приятно играть</h2><p>Анкета занимает пару минут. После неё GameHub покажет игроков с похожим расписанием.</p></div>
  </div>
  <div class="auth-side">
    <form class="card auth-card form" method="post" action="<?= url('register.php') ?>" data-validate>
      <?= csrf_field() ?>
      <div class="seg-links"><a href="<?= url('login.php') ?>">Вход</a><a class="on" href="<?= url('register.php') ?>">Регистрация</a></div>
      <h1 class="h2">Создать аккаунт</h1>
      <?php if ($errors): ?><div class="form-err" role="alert">Проверь поля, отмеченные красным.</div><?php endif; ?>
      <div class="fl">
        <label class="lab req" for="nickname">Ник</label>
        <input class="inp<?= err_class($errors, 'nickname') ?>" id="nickname" name="nickname" value="<?= e($old['nickname']) ?>" autocomplete="username" maxlength="20" data-required data-type="nick" data-ok-mark>
        <span class="hint">3–20 символов, латиница, цифры и _</span>
        <?= field_error($errors, 'nickname') ?>
      </div>
      <div class="fl">
        <label class="lab req" for="email">Почта</label>
        <input class="inp<?= err_class($errors, 'email') ?>" id="email" name="email" type="email" value="<?= e($old['email']) ?>" autocomplete="email" maxlength="120" data-required data-type="email">
        <?= field_error($errors, 'email') ?>
      </div>
      <div class="fl">
        <label class="lab req" for="password">Пароль</label>
        <div style="position:relative">
          <input class="inp<?= err_class($errors, 'password') ?>" id="password" name="password" type="password" autocomplete="new-password" maxlength="72" data-required data-type="password" data-strength="#strength" style="padding-right:96px">
          <button type="button" class="linkbtn" data-toggle-pass="#password" style="position:absolute;right:20px;top:14px;font-size:13px;color:var(--hint)">показать</button>
        </div>
        <div class="strength" id="strength" data-s="0"><i></i><i></i><i></i></div>
        <span class="hint">не меньше 8 символов, буквы и цифры</span>
        <?= field_error($errors, 'password') ?>
      </div>
      <div class="fl">
        <label class="chk"><input type="checkbox" name="agree" value="1" data-required data-msg-required="Нужно согласие с правилами сервиса" <?= !empty($_POST['agree']) ? 'checked' : '' ?>>Мне есть 14 лет, согласен с правилами сервиса</label>
        <?= field_error($errors, 'agree') ?>
      </div>
      <button class="pill g lg" type="submit">Зарегистрироваться</button>
      <p class="mut" style="text-align:center;font-size:15px">Уже есть аккаунт? <a class="more" href="<?= url('login.php') ?>">Войти</a></p>
    </form>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php';
