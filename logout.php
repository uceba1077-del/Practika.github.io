<?php
require __DIR__ . '/includes/bootstrap.php';
// Выход только через POST с CSRF-токеном, чтобы чужая ссылка не разлогинила пользователя
if (is_post()) {
    check_csrf();
    $_SESSION = [];
    session_regenerate_id(true);
    flash('info', 'Ты вышел из аккаунта.');
}
redirect('index.php');
