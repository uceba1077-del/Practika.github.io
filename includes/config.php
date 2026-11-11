<?php
// Настройки подключения. Для XAMPP/OpenServer обычно root без пароля.
return [
    'db_host' => getenv('GH_DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('GH_DB_NAME') ?: 'gamehub',
    'db_user' => getenv('GH_DB_USER') ?: 'root',
    'db_pass' => getenv('GH_DB_PASS') !== false ? getenv('GH_DB_PASS') : '',
    'upload_max' => 2 * 1024 * 1024,           // 2 МБ
    'upload_types' => ['image/png' => 'png', 'image/jpeg' => 'jpg'],
    'debug' => false,
];
