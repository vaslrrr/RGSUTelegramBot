-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Май 16 2023 г., 00:07
-- Версия сервера: 8.0.30-22
-- Версия PHP: 7.2.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `p511269_rgsu`
--
CREATE DATABASE IF NOT EXISTS `p511269_rgsu` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `p511269_rgsu`;

-- --------------------------------------------------------

--
-- Структура таблицы `facultets`
--

CREATE TABLE `facultets` (
                             `id` bigint NOT NULL,
                             `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `groups`
--

CREATE TABLE `groups` (
                          `id` bigint NOT NULL,
                          `f_id` bigint NOT NULL,
                          `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `lectors`
--

CREATE TABLE `lectors` (
                           `id` bigint NOT NULL,
                           `f_id` bigint DEFAULT NULL,
                           `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `notifications`
--

CREATE TABLE `notifications` (
                                 `id` bigint NOT NULL,
                                 `chat_id` bigint NOT NULL,
                                 `data_mode` enum('student','lector') COLLATE utf8mb4_general_ci NOT NULL,
                                 `data_id` bigint NOT NULL,
                                 `mode` enum('day','schedule','change') COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `pages`
--

CREATE TABLE `pages` (
                         `id` bigint NOT NULL,
                         `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
                         `content` longtext COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `schedule`
--

CREATE TABLE `schedule` (
                            `id` bigint NOT NULL,
                            `g_id` bigint NOT NULL,
                            `starttime` int NOT NULL,
                            `endtime` int NOT NULL,
                            `day` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') COLLATE utf8mb4_general_ci NOT NULL,
                            `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
                            `aud` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
                            `lector` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
                            `week` enum('even','odd') COLLATE utf8mb4_general_ci NOT NULL,
                            `date` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
                            `update_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
                         `chat_id` bigint NOT NULL,
                         `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
                         `surname` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
                         `username` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `facultets`
--
ALTER TABLE `facultets`
    ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `groups`
--
ALTER TABLE `groups`
    ADD PRIMARY KEY (`id`),
    ADD KEY `f_id` (`f_id`);

--
-- Индексы таблицы `lectors`
--
ALTER TABLE `lectors`
    ADD PRIMARY KEY (`id`),
    ADD KEY `f_id` (`f_id`);

--
-- Индексы таблицы `notifications`
--
ALTER TABLE `notifications`
    ADD PRIMARY KEY (`id`),
    ADD KEY `chat_id` (`chat_id`),
    ADD KEY `data_id` (`data_id`);

--
-- Индексы таблицы `pages`
--
ALTER TABLE `pages`
    ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `schedule`
--
ALTER TABLE `schedule`
    ADD PRIMARY KEY (`id`),
    ADD KEY `g_id` (`g_id`),
    ADD KEY `day` (`day`,`week`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
    ADD PRIMARY KEY (`chat_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `facultets`
--
ALTER TABLE `facultets`
    MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `groups`
--
ALTER TABLE `groups`
    MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `lectors`
--
ALTER TABLE `lectors`
    MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `notifications`
--
ALTER TABLE `notifications`
    MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `pages`
--
ALTER TABLE `pages`
    MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `schedule`
--
ALTER TABLE `schedule`
    MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `groups`
--
ALTER TABLE `groups`
    ADD CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`f_id`) REFERENCES `facultets` (`id`);

--
-- Ограничения внешнего ключа таблицы `notifications`
--
ALTER TABLE `notifications`
    ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `users` (`chat_id`);

--
-- Ограничения внешнего ключа таблицы `schedule`
--
ALTER TABLE `schedule`
    ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`g_id`) REFERENCES `groups` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
