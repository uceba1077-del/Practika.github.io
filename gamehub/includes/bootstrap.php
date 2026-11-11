<?php
// Общий файл: подключается первой строкой на каждой странице.
declare(strict_types=1);

date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

function config(): array
{
    static $c = null;
    return $c ??= require __DIR__ . '/config.php';
}

// --- ошибки: страница не «падает», а показывает понятное сообщение -------------
function fatal_page(string $title, string $text, ?Throwable $e = null, int $code = 500): void
{
    if ($e) {
        error_log('[GameHub] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }
    if (!headers_sent()) {
        http_response_code($code);
    }
    $debug = config()['debug'] && $e ? '<pre style="white-space:pre-wrap;font-size:12px">' . htmlspecialchars((string)$e) . '</pre>' : '';
    echo '<!doctype html><meta charset="utf-8"><title>Ошибка — GameHub</title>'
        . '<div style="font-family:system-ui;max-width:560px;margin:80px auto;padding:0 16px">'
        . '<h1 style="font-size:28px">' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($text) . '</p>'
        . '<p><a href="' . url('index.php') . '">На главную</a></p>' . $debug . '</div>';
    exit;
}
set_exception_handler(function (Throwable $e) {
    fatal_page('Что-то пошло не так', 'Мы уже знаем об ошибке. Попробуйте обновить страницу или вернуться позже.', $e);
});

require __DIR__ . '/db.php';

// --- сессия ---------------------------------------------------------------------
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_name('GHSESSID');
session_start();

// --- адреса ---------------------------------------------------------------------
function base_url(): string
{
    static $b = null;
    if ($b !== null) {
        return $b;
    }
    $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $app = realpath(__DIR__ . '/..') ?: '';
    $b = ($root && str_starts_with($app, $root)) ? str_replace('\\', '/', substr($app, strlen($root))) : '';
    return rtrim($b, '/');
}
function url(string $path = '', array $query = []): string
{
    $u = base_url() . '/' . ltrim($path, '/');
    return $query ? $u . '?' . http_build_query($query) : $u;
}
function redirect(string $path, array $query = []): never
{
    header('Location: ' . (str_starts_with($path, 'http') || str_starts_with($path, '/') ? $path : url($path, $query)));
    exit;
}
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

// --- flash-сообщения ------------------------------------------------------------
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}
function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// --- CSRF -----------------------------------------------------------------------
function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function check_csrf(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        fatal_page('Форма устарела', 'Обновите страницу и отправьте форму ещё раз.', null, 400);
    }
}

// --- авторизация и права ----------------------------------------------------------
function current_user(): ?array
{
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    $id = $_SESSION['user_id'] ?? null;
    $u = $id ? one('SELECT id, nickname, email, role, avatar FROM users WHERE id = ?', [$id]) : null;
    if ($id && !$u) {
        unset($_SESSION['user_id']);
    }
    return $u;
}
function uid(): ?int
{
    return current_user()['id'] ?? null;
}
function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}
function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
}
function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('info', 'Войдите, чтобы продолжить.');
        redirect('login.php', ['next' => $_SERVER['REQUEST_URI'] ?? '']);
    }
    return $u;
}
function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        forbidden();
    }
    return $u;
}
function forbidden(string $text = 'У вас нет прав на это действие.'): never
{
    http_response_code(403);
    $page_title = 'Нет доступа';
    require __DIR__ . '/header.php';
    echo '<main class="wrap narrow empty-page"><h1 class="h1">Нет доступа</h1><p class="sub">' . e($text) . '</p><a class="pill dk" href="' . url('index.php') . '">На главную</a></main>';
    require __DIR__ . '/footer.php';
    exit;
}
function not_found(string $what = 'Страница'): never
{
    http_response_code(404);
    $page_title = 'Не найдено';
    require __DIR__ . '/header.php';
    echo '<main class="wrap narrow empty-page"><div class="big404">404</div><h1 class="h2">' . e($what) . ' не найдена</h1><p class="sub">Возможно, её удалили или адрес набран с ошибкой.</p><a class="pill dk" href="' . url('index.php') . '">На главную</a></main>';
    require __DIR__ . '/footer.php';
    exit;
}

// --- валидация --------------------------------------------------------------------
function post(string $k, string $def = ''): string
{
    return trim((string)($_POST[$k] ?? $def));
}
function get(string $k, string $def = ''): string
{
    return trim((string)($_GET[$k] ?? $def));
}
function get_int(string $k, int $def = 0): int
{
    return filter_var($_GET[$k] ?? null, FILTER_VALIDATE_INT) ?: $def;
}
function len_between(string $s, int $min, int $max): bool
{
    $l = mb_strlen($s);
    return $l >= $min && $l <= $max;
}
function field_error(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<span class="err-t">' . e($errors[$k]) . '</span>' : '';
}
function err_class(array $errors, string $k): string
{
    return isset($errors[$k]) ? ' err' : '';
}

/** Загрузка картинки: только PNG/JPG до 2 МБ. Возвращает [путь|null, ошибка|null] */
function upload_image(string $field, string $dir): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return [null, $f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE ? 'Файл больше 2 МБ' : 'Файл не загрузился, попробуйте ещё раз'];
    }
    $c = config();
    if ($f['size'] > $c['upload_max']) {
        return [null, 'Файл больше 2 МБ'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($c['upload_types'][$mime])) {
        return [null, 'Можно только PNG или JPG до 2 МБ'];
    }
    $name = bin2hex(random_bytes(8)) . '.' . $c['upload_types'][$mime];
    $rel = 'uploads/' . $dir . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/../' . $rel)) {
        return [null, 'Не удалось сохранить файл'];
    }
    return [$rel, null];
}

// --- предметная область -----------------------------------------------------------
const MODES = ['chill' => 'спокойно', 'tryhard' => 'на результат', 'newbies' => 'новички'];
const DAYS = ['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'];
const MONTHS = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

/** SQL-выражение надёжности игрока (1..10) по оценкам после событий */
const RELIABILITY_SQL = '(SELECT ROUND(AVG(r.score) * 2) FROM ratings r WHERE r.to_user_id = u.id)';

function reliability(int $userId): ?int
{
    $v = val('SELECT ROUND(AVG(score) * 2) FROM ratings WHERE to_user_id = ?', [$userId]);
    return $v === null || $v === false ? null : (int)$v;
}

function lvl($n, int $size = 38): string
{
    $fs = (int)round($size * 0.4);
    if ($n === null || $n === '') {
        return '<span class="lvl new" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . max(9, $fs - 4) . 'px" title="Пока нет оценок">new</span>';
    }
    $n = (int)$n;
    $c = $n >= 8 ? 'hi' : ($n >= 5 ? 'mid' : 'low');
    return '<span class="lvl ' . $c . '" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . $fs . 'px" title="Надёжность ' . $n . ' из 10">' . $n . '</span>';
}

function ava(array $user, int $size = 40): string
{
    $nick = $user['nickname'] ?? '?';
    $fs = (int)round($size * 0.4);
    if (!empty($user['avatar'])) {
        return '<img class="ava" src="' . e(url($user['avatar'])) . '" alt="" style="width:' . $size . 'px;height:' . $size . 'px">';
    }
    $colors = ['#3A6EA5', '#B5563B', '#6B4FA0', '#2E8B6E', '#8A8F3C', '#A0466B', '#3C7F8A', '#7A5B3A', '#4A5BA8', '#9A6B2E'];
    $c = $colors[crc32($nick) % count($colors)];
    return '<span class="ava" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . $fs . 'px;background:' . $c . '">' . e(mb_strtoupper(mb_substr($nick, 0, 1))) . '</span>';
}

function cover(array $game, int $w, int $h, string $cls = ''): string
{
    $src = $game['cover'] ?? $game['game_cover'] ?? null;
    $title = $game['title'] ?? $game['game_title'] ?? '';
    if (!$src) {
        return '<span class="cover-ph ' . $cls . '" style="width:' . $w . 'px;height:' . $h . 'px">' . e(mb_substr($title, 0, 1)) . '</span>';
    }
    return '<img class="cover ' . $cls . '" src="' . e(url($src)) . '" alt="' . e($title) . '" width="' . $w . '" height="' . $h . '" loading="lazy">';
}

function fmt_dt(string $dt, bool $withTime = true): string
{
    $t = strtotime($dt);
    $dow = DAYS[(int)date('N', $t) - 1];
    $s = $dow . ', ' . date('j', $t) . ' ' . MONTHS[(int)date('n', $t) - 1];
    return $withTime ? $s . ' · ' . date('H:i', $t) : $s;
}
function days_label(string $mask): string
{
    $out = [];
    foreach (DAYS as $i => $d) {
        if (($mask[$i] ?? '0') === '1') {
            $out[] = $d;
        }
    }
    return $out ? implode(', ', $out) : 'не указаны';
}
function plural(int $n, string $one, string $few, string $many): string
{
    $n10 = $n % 10;
    $n100 = $n % 100;
    $w = ($n10 === 1 && $n100 !== 11) ? $one : (($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) ? $few : $many);
    return $n . ' ' . $w;
}
function team_role(int $teamId, ?int $userId): ?string
{
    if (!$userId) {
        return null;
    }
    $t = one('SELECT captain_id FROM teams WHERE id = ?', [$teamId]);
    if (!$t) {
        return null;
    }
    if ((int)$t['captain_id'] === $userId) {
        return 'captain';
    }
    return val('SELECT status FROM team_members WHERE team_id = ? AND user_id = ?', [$teamId, $userId]) ?: null;
}
function can_edit_team(array $team): bool
{
    return is_admin() || (int)$team['captain_id'] === uid();
}
function can_edit_event(array $ev): bool
{
    return is_admin() || (int)$ev['organizer_id'] === uid();
}
