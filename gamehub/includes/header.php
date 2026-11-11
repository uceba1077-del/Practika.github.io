<?php
/** @var string|null $page_title  заголовок вкладки
 *  @var string|null $active      активный пункт меню: home|games|players|teams|events */
$hu = current_user();
$active = $active ?? '';
$hnav = [
    'home' => ['главная', 'index.php'],
    'games' => ['игры', 'games.php'],
    'players' => ['игроки', 'players.php'],
    'teams' => ['команды', 'teams.php'],
    'events' => ['события', 'events.php'],
];
$hicons = [
    'home' => '<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path d="M2 8l7-6 7 6v8H11v-5H7v5H2z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
    'games' => '<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path d="M2 2h6v6H2zM10 2h6v6h-6zM2 10h6v6H2zM10 10h6v6h-6z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
    'players' => '<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 12l4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    'teams' => '<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><circle cx="6" cy="7" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12.5" cy="7" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M1 16c0-3 2.5-4.5 5-4.5s5 1.5 5 4.5M11 11.6c2.6-.3 6 .9 6 4.4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
    'events' => '<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><rect x="2" y="3.5" width="14" height="12.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M2 7.5h14M6 1.5v4M12 1.5v4" stroke="currentColor" stroke-width="1.8"/></svg>',
];
$hpending = 0;
if ($hu) {
    // приглашения в команды + заявки в мои команды/события, ждущие решения
    $hpending = (int)val("SELECT
        (SELECT COUNT(*) FROM team_members WHERE user_id = ? AND status = 'invited') +
        (SELECT COUNT(*) FROM team_members tm JOIN teams t ON t.id = tm.team_id WHERE t.captain_id = ? AND tm.status = 'requested') +
        (SELECT COUNT(*) FROM event_participants ep JOIN events ev ON ev.id = ep.event_id WHERE ev.organizer_id = ? AND ep.status = 'pending')",
        [$hu['id'], $hu['id'], $hu['id']]);
}
?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($page_title ?? '') ? $page_title . ' — GameHub' : 'GameHub — поиск тиммейтов') ?></title>
<meta name="description" content="GameHub — поиск игроков по свободным часам, команды и игровые события.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
<link rel="icon" href="<?= url('assets/img/logo.svg') ?>" type="image/svg+xml">
<script src="<?= url('assets/js/app.js') ?>" defer></script>
</head>
<body>
<header class="hd">
  <a class="logo" href="<?= url('index.php') ?>"><img src="<?= url('assets/img/logo.svg') ?>" alt="" width="28" height="28">GameHub</a>
  <nav class="nav" aria-label="Главное меню">
    <?php foreach ($hnav as $hk => [$hlabel, $hhref]): ?>
      <a class="<?= $active === $hk ? 'on' : '' ?>" href="<?= url($hhref) ?>"><?= $hlabel ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="hd-right">
    <?php if ($hu): ?>
      <?php if ($hu['role'] === 'admin'): ?><a class="pill lt sm hide-m" href="<?= url('admin/games.php') ?>">админка</a><?php endif; ?>
      <a class="pill lt sm hide-m" href="<?= url('team_edit.php') ?>">+ команда</a>
      <a class="me" href="<?= url('profile.php') ?>" title="Профиль"><?= ava($hu, 40) ?><span class="hide-m"><?= e($hu['nickname']) ?></span><?php if ($hpending): ?><b class="badge"><?= $hpending ?></b><?php endif; ?></a>
    <?php else: ?>
      <a class="pill out" href="<?= url('login.php') ?>">войти</a>
      <a class="pill g hide-m" href="<?= url('register.php') ?>">Регистрация</a>
    <?php endif; ?>
  </div>
</header>
<?php foreach (take_flash() as [$type, $msg]): ?>
  <div class="flash flash-<?= e($type) ?>" role="status"><span><?= e($msg) ?></span><button type="button" class="flash-x" aria-label="Закрыть">×</button></div>
<?php endforeach; ?>
<nav class="bn" aria-label="Меню">
  <?php foreach ($hnav as $hk => [$hlabel, $hhref]): ?>
    <a class="<?= $active === $hk ? 'on' : '' ?>" href="<?= url($hhref) ?>"><i><?= $hicons[$hk] ?></i><?= $hlabel ?></a>
  <?php endforeach; ?>
</nav>
