<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('profile.php');
}

const MAX_TRIES = 5;
const LOCK_SECONDS = 60;

$errors = [];
$login = '';
$next = get('next') ?: post('next');
// разрешаем возвращать только на страницы нашего сайта
if (!preg_match('~^/[^/]~', $next)) {
    $next = '';
}

$tries = $_SESSION['login_tries'] ?? 0;
$lockedUntil = $_SESSION['login_lock'] ?? 0;

if (is_post()) {
    check_csrf();
    $login = post('login');
    $pass = (string)($_POST['password'] ?? '');

    if ($lockedUntil > time()) {
        $errors['form'] = 'Слишком много попыток. Подожди ' . ($lockedUntil - time()) . ' сек. и попробуй снова.';
    } else {
        if ($login === '') {
            $errors['login'] = 'Введи почту или ник';
        }
        if ($pass === '') {
            $errors['password'] = 'Введи пароль';
        }
        if (!$errors) {
            $u = one('SELECT id, password_hash FROM users WHERE email = ? OR nickname = ?', [mb_strtolower($login), $login]);
            if ($u && password_verify($pass, $u['password_hash'])) {
                unset($_SESSION['login_tries'], $_SESSION['login_lock']);
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
                }
                login_user((int)$u['id']);
                flash('ok', 'С возвращением!');
                redirect($next ?: url('index.php'));
            }
            $tries++;
            $_SESSION['login_tries'] = $tries;
            if ($tries >= MAX_TRIES) {
                $_SESSION['login_lock'] = time() + LOCK_SECONDS;
                $_SESSION['login_tries'] = 0;
                $errors['form'] = 'Слишком много попыток. Подожди минуту и попробуй снова.';
            } else {
                $left = MAX_TRIES - $tries;
                $errors['password'] = 'Неверная почта или пароль. ' . plural($left, 'попытка', 'попытки', 'попыток') . ' до паузы';
            }
        }
    }
}

$page_title = 'Вход';
require __DIR__ . '/includes/header.php';
?>
<main class="auth">
  <div class="auth-photo dark">
    <img src="<?= url('assets/img/photos/photo-club.jpg') ?>" alt="Игроки в компьютерном клубе">
    <div><h2 class="h1">С возвращением</h2><p>Твоя команда, заявки и приглашения ждут в профиле.</p></div>
  </div>
  <div class="auth-side">
    <form class="card auth-card form" method="post" action="<?= url('login.php') ?>" data-validate>
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="seg-links"><a class="on" href="<?= url('login.php') ?>">Вход</a><a href="<?= url('register.php') ?>">Регистрация</a></div>
      <h1 class="h2">Войти в GameHub</h1>
      <?php if (isset($errors['form'])): ?><div class="form-err" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
      <div class="fl">
        <label class="lab" for="login">Почта или ник</label>
        <input class="inp<?= err_class($errors, 'login') ?>" id="login" name="login" value="<?= e($login) ?>" autocomplete="username" data-required data-msg-required="Введи почту или ник">
        <?= field_error($errors, 'login') ?>
      </div>
      <div class="fl">
        <label class="lab" for="password">Пароль</label>
        <div style="position:relative">
          <input class="inp<?= err_class($errors, 'password') ?>" id="password" name="password" type="password" autocomplete="current-password" data-required data-msg-required="Введи пароль" style="padding-right:96px">
          <button type="button" class="linkbtn" data-toggle-pass="#password" style="position:absolute;right:20px;top:14px;font-size:13px;color:var(--hint)">показать</button>
        </div>
        <?= field_error($errors, 'password') ?>
      </div>
      <button class="pill g lg" type="submit">Войти</button>
      <p class="mut" style="text-align:center;font-size:15px">Нет аккаунта? <a class="more" href="<?= url('register.php') ?>">Зарегистрироваться</a></p>
      <p class="hint" style="text-align:center">Тестовый вход: nightowl / password123</p>
    </form>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php';
