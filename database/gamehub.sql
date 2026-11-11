-- GameHub · база данных (MySQL 8 / MariaDB 10.4+)
SET NAMES utf8mb4;
-- Команда 1, МДК 09.01. Запуск: импортировать файл целиком в phpMyAdmin или
--   mysql -u root -p < database/gamehub.sql

DROP DATABASE IF EXISTS gamehub;
CREATE DATABASE gamehub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gamehub;

-- 1. Пользователи и роли
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nickname      VARCHAR(20)  NOT NULL UNIQUE,
  email         VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  avatar        VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Игры (каталог, правит администратор)
CREATE TABLE games (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(80)  NOT NULL UNIQUE,
  genre       VARCHAR(40)  NOT NULL,
  format      VARCHAR(20)  NOT NULL,
  max_players TINYINT UNSIGNED NOT NULL DEFAULT 4,
  description VARCHAR(300) NOT NULL DEFAULT '',
  cover       VARCHAR(255) NULL,
  banner      VARCHAR(255) NULL
) ENGINE=InnoDB;

-- 3. Игровой профиль (анкета) — 1:1 с users
CREATE TABLE player_profiles (
  user_id     INT UNSIGNED PRIMARY KEY,
  about       VARCHAR(300) NOT NULL DEFAULT '',
  timezone    VARCHAR(10)  NOT NULL DEFAULT 'UTC+3',
  play_mode   ENUM('chill','tryhard') NOT NULL DEFAULT 'chill',
  has_mic     TINYINT(1) NOT NULL DEFAULT 0,
  looking_now TINYINT(1) NOT NULL DEFAULT 0,
  looking_for VARCHAR(80) NOT NULL DEFAULT '',
  days        CHAR(7) NOT NULL DEFAULT '0000000',   -- пн..вс, 1 = свободен
  time_from   TIME NOT NULL DEFAULT '19:00:00',
  time_to     TIME NOT NULL DEFAULT '23:00:00',
  CONSTRAINT fk_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Игры игрока (многие ко многим)
CREATE TABLE player_games (
  user_id INT UNSIGNED NOT NULL,
  game_id INT UNSIGNED NOT NULL,
  hours   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_main TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, game_id),
  CONSTRAINT fk_pg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_pg_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Команды (основная сущность с полным CRUD)
CREATE TABLE teams (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(30)  NOT NULL UNIQUE,
  game_id    INT UNSIGNED NOT NULL,
  captain_id INT UNSIGNED NOT NULL,
  size       TINYINT UNSIGNED NOT NULL DEFAULT 4,
  play_mode  ENUM('chill','tryhard') NOT NULL DEFAULT 'chill',
  schedule   VARCHAR(60)  NOT NULL DEFAULT '',
  about      VARCHAR(300) NOT NULL DEFAULT '',
  emblem     VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_team_game    FOREIGN KEY (game_id)    REFERENCES games(id),
  CONSTRAINT fk_team_captain FOREIGN KEY (captain_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Участники команды + приглашения и заявки в команду
CREATE TABLE team_members (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  team_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  status     ENUM('member','invited','requested') NOT NULL DEFAULT 'member',
  game_role  VARCHAR(30) NOT NULL DEFAULT '',
  message    VARCHAR(200) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_team_user (team_id, user_id),
  CONSTRAINT fk_tm_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_tm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Игровые события
CREATE TABLE events (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(60) NOT NULL,
  game_id         INT UNSIGNED NOT NULL,
  organizer_id    INT UNSIGNED NOT NULL,
  team_id         INT UNSIGNED NULL,
  starts_at       DATETIME NOT NULL,
  duration_min    SMALLINT UNSIGNED NOT NULL DEFAULT 180,
  slots           TINYINT UNSIGNED NOT NULL DEFAULT 4,
  play_mode       ENUM('chill','tryhard','newbies') NOT NULL DEFAULT 'chill',
  need_mic        TINYINT(1) NOT NULL DEFAULT 1,
  min_reliability TINYINT UNSIGNED NOT NULL DEFAULT 0,
  description     VARCHAR(500) NOT NULL DEFAULT '',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ev_game  FOREIGN KEY (game_id)      REFERENCES games(id),
  CONSTRAINT fk_ev_org   FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ev_team  FOREIGN KEY (team_id)      REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 8. Заявки и участники событий
CREATE TABLE event_participants (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id   INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  team_id    INT UNSIGNED NULL,
  comment    VARCHAR(200) NOT NULL DEFAULT '',
  status     ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_user (event_id, user_id),
  CONSTRAINT fk_ep_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_ep_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ep_team  FOREIGN KEY (team_id)  REFERENCES teams(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- 9. Оценки после событий (из них считается надёжность)
CREATE TABLE ratings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id     INT UNSIGNED NOT NULL,
  from_user_id INT UNSIGNED NOT NULL,
  to_user_id   INT UNSIGNED NOT NULL,
  score        TINYINT UNSIGNED NOT NULL,          -- 1..5
  came_on_time TINYINT(1) NOT NULL DEFAULT 1,
  comment      VARCHAR(200) NOT NULL DEFAULT '',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rating (event_id, from_user_id, to_user_id),
  CONSTRAINT fk_r_event FOREIGN KEY (event_id)     REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_r_from  FOREIGN KEY (from_user_id) REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_r_to    FOREIGN KEY (to_user_id)   REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT chk_score CHECK (score BETWEEN 1 AND 5),
  CONSTRAINT chk_self  CHECK (from_user_id <> to_user_id)
) ENGINE=InnoDB;

-- ===================== Тестовые данные =====================
-- Пароль у всех игроков: password123, у администратора: admin123
INSERT INTO users (id, nickname, email, password_hash, role, created_at) VALUES
 (1, 'admin',        'admin@gamehub.local',     '$2y$10$qDVYG/TRq1mSVJVgHHc4xOYqrKoPLvBW7eh/VkNUEmjUlDIAVKtQu', 'admin', '2026-09-01 10:00:00'),
 (2, 'nightowl',     'nightowl@gamehub.local',  '$2y$10$kMEd4BakQmmXge09I565Ce07f3ROxUsauGHkDCvaPiNYUZ7rh8cC.', 'user', '2026-09-03 21:10:00'),
 (3, 'kr1sp',        'kr1sp@gamehub.local',     '$2y$10$kMEd4BakQmmXge09I565Ce07f3ROxUsauGHkDCvaPiNYUZ7rh8cC.', 'user', '2026-09-05 18:00:00'),
 (4, 'zhuk_dmg',     'zhuk@gamehub.local',      '$2y$10$kMEd4BakQmmXge09I565Ce07f3ROxUsauGHkDCvaPiNYUZ7rh8cC.', 'user', '2026-09-06 19:30:00'),
 (5, 'pelmen4ik',    'pelmen@gamehub.local',    '$2y$10$kMEd4BakQmmXge09I565Ce07f3ROxUsauGHkDCvaPiNYUZ7rh8cC.', 'user', '2026-09-09 20:00:00'),
 (6, 'lowping_',     'lowping@gamehub.local',   '$2y$10$kMEd4BakQmmXge09I565Ce07f3ROxUsauGHkDCvaPiNYUZ7rh8cC.', 'user', '2026-09-12 22:15:00'),
 (7, 'sova_v_shleme','sova@gamehub.local',      '$2y$10$kMEd4BakQmmXge09I565Ce07f3ROxUsauGHkDCvaPiNYUZ7rh8cC.', 'user', '2026-09-14 17:40:00'),
 (8, 'miner_tolya',  'tolya@gamehub.local',     '$2y$10$kMEd4BakQmmXge09I565Ce07f3ROxUsauGHkDCvaPiNYUZ7rh8cC.', 'user', '2026-09-15 20:05:00'),
 (9, 'dedushka_ak',  'ded@gamehub.local',       '$2y$10$kMEd4BakQmmXge09I565Ce07f3ROxUsauGHkDCvaPiNYUZ7rh8cC.', 'user', '2026-09-20 16:20:00');

INSERT INTO games (id, title, genre, format, max_players, description, cover, banner) VALUES
 (1, 'Deep Rock Galactic', 'кооператив',        'до 4',    4,  'Добывайте ресурсы и проходите миссии в команде до четырёх игроков.', 'assets/img/games/cover-drg.jpg', 'assets/img/games/banner-drg.jpg'),
 (2, 'Counter-Strike 2',   'командный шутер',   '5×5',     5,  'Матчи 5×5. Найди пятого в пати для обычных или рейтинговых матчей.', 'assets/img/games/cover-cs2.jpg', 'assets/img/games/banner-cs2.jpg'),
 (3, 'Dota 2',             'MOBA',              '5×5',     5,  'Матчи 5×5. Найди игроков на нужные роли и договорись о режиме.', 'assets/img/games/cover-dota2.jpg', NULL),
 (4, 'Lethal Company',     'кооператив',        'до 4',    4,  'Собирайте металлолом на заброшенных лунах. С голосовой связью проще координироваться.', 'assets/img/games/cover-lethal.jpg', 'assets/img/games/banner-lethal.jpg'),
 (5, 'Apex Legends',       'королевская битва', '3 игрока', 3, 'Игра втроём. Ищут третьего в рейтинговые и обычные матчи.', 'assets/img/games/cover-apex.jpg', NULL),
 (6, 'Phasmophobia',       'кооп-хоррор',       'до 4',    4,  'Исследуйте дома и определяйте тип призрака. До четырёх игроков, нужна голосовая связь.', 'assets/img/games/cover-phasmo.jpg', NULL);

INSERT INTO player_profiles (user_id, about, timezone, play_mode, has_mic, looking_now, looking_for, days, time_from, time_to) VALUES
 (1, 'Администратор GameHub.', 'UTC+4', 'chill', 1, 0, '', '0000000', '19:00', '23:00'),
 (2, 'Играю по вечерам после работы, в основном DRG на высоких сложностях. Не токсичу, люблю, когда игра все на связи.', 'UTC+3', 'chill', 1, 1, 'одного в пати, без спешки', '0101110', '20:00', '23:00'),
 (3, 'Играю в CS2 по вечерам, нужен пятый с микрофоном.', 'UTC+3', 'tryhard', 1, 1, 'пятого в пати, с микрофоном', '0011100', '21:00', '23:59'),
 (4, 'Саппорт со стажем, Dota 2 по будням.', 'UTC+3', 'tryhard', 1, 0, 'саппорта на вечер', '0110000', '20:30', '23:30'),
 (5, 'Объясняю новичкам, как не умереть на первой луне.', 'UTC+4', 'chill', 1, 1, 'троих новичков, объясню всё', '0000011', '18:00', '22:00'),
 (6, 'Apex, обычно в пятницу ночью.', 'UTC+5', 'tryhard', 0, 0, 'третьего в пати', '0000100', '22:00', '23:59'),
 (7, 'Спокойная охота на призраков вчетвером.', 'UTC+3', 'chill', 1, 0, 'спокойную охоту вчетвером', '0000001', '19:00', '23:00'),
 (8, 'Бурильщик, хожу на Elite Deep Dive.', 'UTC+3', 'chill', 1, 1, 'стрелка в пати на Elite Deep Dive', '0010100', '21:00', '23:30'),
 (9, 'Ищу двоих на фейсит-уровень 5–7.', 'UTC+3', 'tryhard', 1, 0, 'двоих на фейсит-уровень 5–7', '0000100', '20:00', '23:00');

INSERT INTO player_games (user_id, game_id, hours, is_main) VALUES
 (2,1,340,1),(2,4,120,0),(2,6,45,0),
 (3,2,900,1),(4,3,2100,1),(5,4,210,1),(5,6,60,0),(6,5,400,1),(7,6,150,1),(8,1,520,1),(9,2,1300,1);

INSERT INTO teams (id, name, game_id, captain_id, size, play_mode, schedule, about) VALUES
 (1, 'Тихий час',       1, 2, 4, 'chill',   'вт, чт · 20:00–23:00', 'Играем в Deep Rock Galactic по вторникам и четвергам. Проходим Elite Deep Dive. Ищем инженера с микрофоном.'),
 (2, 'Пятничный микс',  2, 3, 5, 'tryhard', 'пт · 21:00',            'Собираемся по пятницам на обычные и рейтинговые матчи.'),
 (3, 'Ночная смена',    4, 5, 4, 'chill',   'сб · 22:00',            'Учим новичков собирать лут и не бояться темноты.'),
 (4, 'Ластхит',         3, 4, 5, 'tryhard', 'вт, ср · 21:00',        'Пятёрка на вечерние катки, нужен мид.'),
 (5, 'Без пинга',       5, 6, 3, 'tryhard', 'пт · 22:00',            'Ранкед втроём, ищем третьего.'),
 (6, 'Охотники за ЭМП', 6, 7, 4, 'chill',   'вс · 19:00',            'Спокойные вечера в Phasmophobia, без скримеров в голосе.');

INSERT INTO team_members (team_id, user_id, status, game_role, message) VALUES
 (1,2,'member','разведчик',''),(1,8,'member','бурильщик',''),(1,7,'member','стрелок',''),
 (1,9,'requested','','хочу инженером, есть микрофон'),(1,6,'requested','','свободен по четвергам'),
 (2,3,'member','',''),(2,9,'member','',''),(2,4,'member','',''),(2,6,'member','',''),
 (3,5,'member','',''),(3,2,'invited','','Зайдёшь к нам в субботу?'),
 (4,4,'member','мид',''),(5,6,'member','',''),(5,3,'member','',''),
 (6,7,'member','',''),(6,8,'member','',''),(6,5,'member','','');

-- даты событий считаются от текущего дня, чтобы в демо всегда были ближайшие события
INSERT INTO events (id, title, game_id, organizer_id, team_id, starts_at, duration_min, slots, play_mode, need_mic, min_reliability, description) VALUES
 (1, 'Elite Deep Dive',             1, 2, 1,    TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '20:00:00'), 180, 4,  'chill',   1, 5, 'Проходим недельный Elite Deep Dive. Нужен опыт на Hazard 4 или выше. Собираемся в Discord за 10 минут до начала. Ищем инженера и стрелка.'),
 (2, 'Пятничный микс 5×5',          2, 3, 2,    TIMESTAMP(CURDATE() + INTERVAL 2 DAY, '21:00:00'), 120, 10, 'tryhard', 1, 0, 'Две пятёрки, обычные матчи на результат.'),
 (3, 'Lethal Company вчетвером',    4, 5, 3,    TIMESTAMP(CURDATE() + INTERVAL 3 DAY, '22:00:00'), 150, 4,  'newbies', 1, 0, 'Для новичков: объясняем, как не умереть на первой луне.'),
 (4, 'Хэллоуин в Phasmophobia',     6, 7, 6,    TIMESTAMP(CURDATE() + INTERVAL 3 DAY, '19:00:00'), 120, 4,  'chill',   1, 0, 'Праздничная охота на призраков.'),
 (5, 'Ранкед на троих',             5, 6, 5,    TIMESTAMP(CURDATE() + INTERVAL 4 DAY, '21:00:00'), 120, 3,  'tryhard', 0, 4, 'Поднимаем ранг втроём.'),
 (6, 'Dota 2: турбо-вечер',         3, 4, 4,    TIMESTAMP(CURDATE() + INTERVAL 6 DAY, '20:30:00'), 150, 10, 'chill',   1, 0, 'Турбо-режим, без напряга.'),
 (7, 'Прошлый вылет в шахты',       1, 2, 1,    TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '20:00:00'), 180, 4,  'chill',   1, 0, 'Прошедшее событие для примера оценок.');

INSERT INTO event_participants (event_id, user_id, team_id, comment, status) VALUES
 (1,2,1,'организатор','accepted'),(1,8,1,'','accepted'),
 (2,3,2,'','accepted'),(2,9,2,'','accepted'),(2,4,NULL,'','accepted'),(2,6,NULL,'','accepted'),(2,5,NULL,'','accepted'),(2,7,NULL,'','accepted'),(2,8,NULL,'','pending'),
 (3,5,3,'','accepted'),
 (4,7,6,'','accepted'),(4,8,6,'','accepted'),(4,5,6,'','accepted'),(4,2,NULL,'','rejected'),
 (5,6,5,'','accepted'),
 (6,4,4,'','accepted'),(6,3,NULL,'','accepted'),(6,9,NULL,'','accepted'),(6,6,NULL,'','accepted'),(6,7,NULL,'','accepted'),(6,8,NULL,'','accepted'),
 (7,2,1,'','accepted'),(7,8,1,'','accepted'),(7,7,1,'','accepted'),(7,5,NULL,'','accepted');

INSERT INTO ratings (event_id, from_user_id, to_user_id, score, came_on_time, comment) VALUES
 (7,8,2,5,1,'Пришёл вовремя, был на связи. Сыграл бы ещё.'),
 (7,5,2,5,1,'Терпеливо объяснял новичкам, не уходил после первой смерти.'),
 (7,7,2,4,1,'Всё хорошо, немного спешил.'),
 (7,2,8,5,1,'Отличный бурильщик.'),
 (7,2,7,4,0,'Опоздал минут на 10, но предупредил в чате.'),
 (7,2,5,5,1,'Спасибо за игру!'),
 (7,7,8,5,1,''),(7,5,8,4,1,''),(7,8,7,4,0,''),(7,5,7,4,1,'');
