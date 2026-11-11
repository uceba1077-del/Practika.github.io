<?php
require_once '../includes/bootstrap.php';

$user = require_login(); // Создавать события может только авторизованный игрок

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $title = trim($_POST['title'] ?? '');
    $game_id = (int)($_POST['game_id'] ?? 0);
    $event_date = $_POST['event_date'] ?? '';
    $format = trim($_POST['format'] ?? '');
    $max_teams = (int)($_POST['max_teams'] ?? 8);
    $description = trim($_POST['description'] ?? '');
    
    $errors = [];

    // 1. Валидация
    if (mb_strlen($title) < 5) {
        $errors['title'] = 'Название должно быть не менее 5 символов.';
    }
    if ($game_id <= 0) {
        $errors['game_id'] = 'Выберите игру.';
    }
    
    // Проверка даты (не в прошлом)
    $now = new DateTime();
    $date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $event_date);
    if (!$date_obj || $date_obj < $now) {
        $errors['event_date'] = 'Дата события не может быть в прошлом.';
    }

    if (empty($errors)) {
        try {
            // Сохраняем событие
            $stmt = db()->prepare("INSERT INTO events (title, game_id, organizer_id, event_date, format, max_teams, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $game_id, $user['id'], $date_obj->format('Y-m-d H:i:s'), $format, $max_teams, $description]);
            
            $event_id = db()->lastInsertId();

            flash('ok', 'Событие успешно создано!');
            redirect("event.php?id=" . $event_id); // Перекидываем на страницу события

        } catch (Exception $e) {
            $errors['form'] = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }

    $_SESSION['old_event'] = $_POST;
    $_SESSION['event_errors'] = $errors;
    redirect('event_create.php'); 
}
?>