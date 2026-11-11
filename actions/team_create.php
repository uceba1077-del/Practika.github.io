<?php
require_once '../includes/bootstrap.php'; // Подключаем сессии и БД

// 1. Проверяем, что пользователь авторизован (гость не может создать команду)
$user = require_login(); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf(); // Проверка токена безопасности

    $name = trim($_POST['name'] ?? '');
    $game_id = (int)($_POST['game_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $errors = [];

    // 2. Валидация полей
    if (mb_strlen($name) < 3 || mb_strlen($name) > 30) {
        $errors['name'] = 'Название команды должно быть от 3 до 30 символов.';
    }
    if ($game_id <= 0) {
        $errors['game_id'] = 'Выберите игру.';
    }

    // 3. Обработка логотипа (если загружен)
    $logo_path = null;
    if (!empty($_FILES['logo']['name'])) {
        $file = $_FILES['logo'];
        if ($file['size'] > 2 * 1024 * 1024) {
            $errors['logo'] = 'Логотип не должен превышать 2 МБ.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $errors['logo'] = 'Только JPG или PNG.';
            } else {
                // Сохраняем файл в папку uploads (создай её, если нет)
                $new_name = uniqid('team_') . '.' . $ext;
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
                    $logo_path = 'uploads/' . $new_name;
                } else {
                    $errors['logo'] = 'Ошибка при загрузке файла.';
                }
            }
        }
    }

    // 4. Если ошибок нет — сохраняем в БД
    if (empty($errors)) {
        try {
            db()->beginTransaction(); // Начинаем транзакцию (чтобы не было "полу-команд")

            // Создаем команду
            $stmt = db()->prepare("INSERT INTO teams (name, game_id, description, captain_id, logo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $game_id, $description, $user['id'], $logo_path]);
            $team_id = db()->lastInsertId();

            // Сразу добавляем создателя в состав как капитана
            $stmt_member = db()->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'captain')");
            $stmt_member->execute([$team_id, $user['id']]);

            db()->commit(); // Подтверждаем изменения

            flash('ok', 'Команда успешно создана!');
            redirect("team.php?id=" . $team_id); // Перекидываем на страницу команды

        } catch (Exception $e) {
            db()->rollBack();
            $errors['form'] = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }

    // Если есть ошибки — возвращаем обратно на форму (сохраняя введенные данные)
    $_SESSION['old_team'] = $_POST;
    $_SESSION['team_errors'] = $errors;
    redirect('team_create.php'); 
}
?>