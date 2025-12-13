-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 13, 2025 at 08:23 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `meplay`
--

-- --------------------------------------------------------

--
-- Table structure for table `albums`
--

CREATE TABLE `albums` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `artist_id` int(11) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT 'default-cover.png',
  `release_year` year(4) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `albums`
--

INSERT INTO `albums` (`id`, `title`, `artist_id`, `cover_image`, `release_year`, `is_active`, `created_at`, `updated_at`) VALUES
(11, 'U-Turn', 14, 'album_692ad25f45f88_1764414047.jpeg', '2017', 1, '2025-11-29 11:00:47', '2025-11-29 11:00:47'),
(12, 'Supersonic', 15, 'album_692ad27078edb_1764414064.jpg', '2024', 1, '2025-11-29 11:01:04', '2025-11-30 00:12:30'),
(13, 'LP1', 16, 'album_693541fa264ba_1765097978.jpeg', '2019', 1, '2025-12-07 08:59:38', '2025-12-13 04:59:34');

-- --------------------------------------------------------

--
-- Table structure for table `artists`
--

CREATE TABLE `artists` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `bio` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `artists`
--

INSERT INTO `artists` (`id`, `name`, `bio`, `profile_picture`, `is_active`, `created_at`, `updated_at`) VALUES
(14, 'Mardial', NULL, 'artist_692ad0e95085f_1764413673.jpg', 1, '2025-11-29 10:54:33', '2025-11-29 10:54:33'),
(15, 'Fromis_9', NULL, 'artist_692ad0f34a8d6_1764413683.jpg', 1, '2025-11-29 10:54:43', '2025-12-07 08:57:33'),
(16, 'Liam Payne', NULL, 'default-artist.png', 1, '2025-12-07 08:58:38', '2025-12-13 07:18:48');

-- --------------------------------------------------------

--
-- Table structure for table `genres`
--

CREATE TABLE `genres` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `genres`
--

INSERT INTO `genres` (`id`, `name`, `description`, `color`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'Pop', 'Popular music', '#667eea', 1, '2025-11-26 15:01:58', '2025-11-26 15:01:58'),
(4, 'Rock', 'Rock music', '#e53e3e', 1, '2025-11-26 15:01:58', '2025-11-26 15:01:58'),
(6, 'Classical', 'Classical music', '#d69e2e', 1, '2025-11-26 15:01:58', '2025-12-13 07:16:00'),
(7, 'Hip Hop', 'Hip Hop music', '#805ad5', 1, '2025-11-26 15:01:58', '2025-11-26 15:01:58'),
(8, 'Electronic', 'Electronic music', '#3182ce', 1, '2025-11-26 15:01:58', '2025-11-26 15:01:58'),
(9, 'R&B', 'Rhythm and Blues', '#dd6b20', 1, '2025-11-26 15:01:58', '2025-11-26 15:01:58'),
(10, 'Country', 'Country music', '#38b2ac', 1, '2025-11-26 15:01:58', '2025-11-26 15:01:58'),
(11, 'EDM', 'edm', '#092086', 1, '2025-11-26 15:02:41', '2025-11-26 15:02:41'),
(12, 'Kpop', 'korean pop', '#ffe0f2', 1, '2025-11-26 15:03:06', '2025-11-26 15:03:06'),
(13, 'Jazz', 'jazz music', '#009926', 1, '2025-11-29 00:58:14', '2025-11-29 00:58:14'),
(14, 'Phonk', 'Phonk', '#b30000', 1, '2025-12-13 07:09:34', '2025-12-13 07:13:57');

-- --------------------------------------------------------

--
-- Table structure for table `playlists`
--

CREATE TABLE `playlists` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `playlists`
--

INSERT INTO `playlists` (`id`, `user_id`, `title`, `description`, `cover_image`, `is_public`, `created_at`) VALUES
(14, 14, 'ddsa', '', NULL, 0, '2025-12-05 17:04:40'),
(15, 14, 'sda', '', NULL, 0, '2025-12-05 17:04:43'),
(33, 14, 'a', '', NULL, 0, '2025-12-11 18:38:02');

-- --------------------------------------------------------

--
-- Table structure for table `playlist_songs`
--

CREATE TABLE `playlist_songs` (
  `id` int(11) NOT NULL,
  `playlist_id` int(11) NOT NULL,
  `song_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `playlist_songs`
--

INSERT INTO `playlist_songs` (`id`, `playlist_id`, `song_id`, `added_at`) VALUES
(23, 14, 26, '2025-12-05 17:04:47'),
(25, 14, 25, '2025-12-05 17:04:53'),
(26, 15, 25, '2025-12-05 17:04:55'),
(27, 15, 26, '2025-12-05 17:11:13'),
(28, 33, 27, '2025-12-11 18:38:21'),
(29, 33, 26, '2025-12-11 18:38:51'),
(30, 33, 25, '2025-12-11 18:39:09');

-- --------------------------------------------------------

--
-- Table structure for table `recently_played`
--

CREATE TABLE `recently_played` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `song_id` int(11) NOT NULL,
  `played_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recently_played`
--

INSERT INTO `recently_played` (`id`, `user_id`, `song_id`, `played_at`) VALUES
(713, 13, 25, '2025-12-07 09:00:56'),
(717, 13, 26, '2025-12-07 09:00:56'),
(719, 13, 27, '2025-12-07 09:01:08'),
(1317, 14, 26, '2025-12-10 17:47:36'),
(1319, 14, 25, '2025-12-10 18:25:31'),
(1323, 14, 27, '2025-12-11 01:14:06'),
(1324, 14, 27, '2025-12-11 01:40:50'),
(1325, 14, 27, '2025-12-11 01:41:12'),
(1326, 14, 25, '2025-12-11 05:39:02'),
(1327, 14, 26, '2025-12-11 05:39:25'),
(1328, 14, 27, '2025-12-11 05:39:27'),
(1329, 14, 26, '2025-12-11 05:39:29'),
(1330, 14, 25, '2025-12-11 05:39:29'),
(1331, 14, 27, '2025-12-11 05:39:30'),
(1332, 14, 26, '2025-12-11 05:49:38'),
(1333, 14, 27, '2025-12-11 05:50:06'),
(1334, 14, 25, '2025-12-11 05:52:01'),
(1335, 14, 26, '2025-12-11 11:30:25'),
(1336, 14, 25, '2025-12-11 11:36:49'),
(1337, 14, 26, '2025-12-11 11:52:34'),
(1338, 14, 25, '2025-12-11 13:09:18'),
(1339, 14, 27, '2025-12-11 13:19:23'),
(1340, 14, 25, '2025-12-11 13:21:46'),
(1341, 14, 26, '2025-12-11 13:22:28'),
(1342, 14, 25, '2025-12-11 13:25:25'),
(1343, 14, 27, '2025-12-11 13:34:50'),
(1344, 14, 25, '2025-12-11 13:37:47'),
(1345, 14, 26, '2025-12-11 13:37:53'),
(1346, 14, 25, '2025-12-11 13:48:10'),
(1347, 14, 27, '2025-12-11 13:51:43'),
(1348, 14, 25, '2025-12-11 13:56:08'),
(1349, 14, 27, '2025-12-11 14:04:03'),
(1350, 14, 25, '2025-12-11 14:11:08'),
(1351, 14, 26, '2025-12-11 18:25:41'),
(1352, 14, 27, '2025-12-11 18:26:19'),
(1353, 14, 25, '2025-12-11 18:32:41'),
(1354, 14, 27, '2025-12-12 05:35:53'),
(1355, 14, 25, '2025-12-12 05:37:16'),
(1356, 14, 27, '2025-12-12 05:37:17'),
(1357, 14, 26, '2025-12-12 05:37:17'),
(1358, 14, 25, '2025-12-12 05:37:18'),
(1359, 14, 26, '2025-12-12 05:37:18'),
(1360, 14, 27, '2025-12-12 05:37:18'),
(1361, 14, 25, '2025-12-12 05:37:18'),
(1362, 14, 27, '2025-12-12 05:37:18'),
(1363, 14, 26, '2025-12-12 05:37:19'),
(1364, 14, 27, '2025-12-12 05:39:19'),
(1365, 14, 26, '2025-12-12 05:40:02'),
(1366, 14, 27, '2025-12-12 05:40:18'),
(1367, 14, 26, '2025-12-12 08:21:07'),
(1368, 14, 25, '2025-12-12 08:21:09'),
(1369, 14, 27, '2025-12-12 09:09:27'),
(1370, 14, 26, '2025-12-12 09:09:33'),
(1371, 14, 25, '2025-12-12 09:13:46'),
(1372, 14, 26, '2025-12-12 09:13:47'),
(1373, 14, 25, '2025-12-12 09:13:48'),
(1374, 14, 26, '2025-12-12 09:15:24'),
(1375, 14, 25, '2025-12-12 10:34:17'),
(1376, 14, 26, '2025-12-12 10:50:45'),
(1377, 14, 25, '2025-12-12 10:53:42'),
(1378, 14, 26, '2025-12-12 10:56:14'),
(1379, 14, 25, '2025-12-12 10:58:03'),
(1380, 14, 27, '2025-12-12 10:58:24'),
(1381, 14, 26, '2025-12-12 10:58:54'),
(1382, 14, 25, '2025-12-12 10:59:37'),
(1383, 14, 26, '2025-12-12 10:59:38'),
(1384, 14, 25, '2025-12-12 10:59:38'),
(1385, 14, 26, '2025-12-12 10:59:38'),
(1386, 14, 25, '2025-12-12 10:59:39'),
(1387, 14, 26, '2025-12-12 10:59:39'),
(1388, 14, 25, '2025-12-12 10:59:39'),
(1389, 14, 26, '2025-12-12 10:59:39'),
(1390, 14, 25, '2025-12-12 10:59:39'),
(1391, 14, 26, '2025-12-12 10:59:39'),
(1392, 14, 25, '2025-12-12 10:59:40'),
(1393, 14, 26, '2025-12-12 10:59:40'),
(1394, 14, 25, '2025-12-12 10:59:40'),
(1395, 14, 26, '2025-12-12 10:59:40'),
(1396, 14, 26, '2025-12-12 10:59:42'),
(1397, 14, 27, '2025-12-12 10:59:42'),
(1398, 14, 26, '2025-12-12 10:59:42'),
(1399, 14, 25, '2025-12-12 10:59:43'),
(1400, 14, 27, '2025-12-12 10:59:43'),
(1401, 14, 26, '2025-12-12 10:59:43'),
(1402, 14, 27, '2025-12-12 10:59:43'),
(1403, 14, 25, '2025-12-12 10:59:43'),
(1404, 14, 26, '2025-12-12 10:59:44'),
(1405, 14, 27, '2025-12-12 10:59:44'),
(1406, 14, 25, '2025-12-12 10:59:44'),
(1407, 14, 27, '2025-12-12 10:59:44'),
(1408, 14, 26, '2025-12-12 10:59:44'),
(1409, 14, 25, '2025-12-12 10:59:44'),
(1410, 14, 27, '2025-12-12 10:59:45'),
(1411, 14, 25, '2025-12-12 10:59:45'),
(1412, 14, 26, '2025-12-12 10:59:45'),
(1413, 14, 27, '2025-12-12 11:00:54'),
(1414, 14, 25, '2025-12-12 11:08:49'),
(1415, 14, 26, '2025-12-12 11:09:57'),
(1416, 14, 25, '2025-12-12 11:09:57'),
(1417, 14, 26, '2025-12-12 11:09:58'),
(1418, 14, 25, '2025-12-12 11:09:58'),
(1419, 14, 26, '2025-12-12 11:09:59'),
(1420, 14, 25, '2025-12-12 11:09:59'),
(1421, 14, 26, '2025-12-12 11:09:59'),
(1422, 14, 25, '2025-12-12 11:09:59'),
(1423, 14, 27, '2025-12-12 11:21:57'),
(1424, 14, 25, '2025-12-12 11:22:13'),
(1425, 14, 26, '2025-12-12 11:22:18'),
(1426, 14, 27, '2025-12-12 11:27:58'),
(1427, 14, 26, '2025-12-12 11:28:10'),
(1428, 14, 25, '2025-12-12 11:31:23'),
(1429, 14, 27, '2025-12-12 12:39:22'),
(1430, 14, 26, '2025-12-13 03:22:28'),
(1431, 14, 25, '2025-12-13 03:26:35'),
(1432, 14, 26, '2025-12-13 03:26:36'),
(1433, 14, 25, '2025-12-13 03:26:38'),
(1434, 13, 27, '2025-12-13 03:50:47'),
(1435, 13, 25, '2025-12-13 03:58:57'),
(1436, 13, 26, '2025-12-13 04:01:04'),
(1437, 13, 27, '2025-12-13 04:58:01');

-- --------------------------------------------------------

--
-- Table structure for table `songs`
--

CREATE TABLE `songs` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `artist_id` int(11) DEFAULT NULL,
  `album_id` int(11) DEFAULT NULL,
  `album_name` varchar(255) NOT NULL,
  `genre_id` int(11) DEFAULT NULL,
  `genre_name` varchar(100) NOT NULL,
  `release_year` int(11) DEFAULT NULL,
  `audio_file` varchar(255) NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `duration` int(11) NOT NULL,
  `lyrics` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `play_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `likes_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `songs`
--

INSERT INTO `songs` (`id`, `title`, `artist_id`, `album_id`, `album_name`, `genre_id`, `genre_name`, `release_year`, `audio_file`, `cover_image`, `duration`, `lyrics`, `is_active`, `play_count`, `created_at`, `updated_at`, `likes_count`) VALUES
(25, 'U-Turn', 14, 11, '', 8, '', NULL, 'song_692ad2b3ac855_1764414131.mp3', 'song_cover_692ad2b3acd00_1764414131.jpeg', 191, NULL, 1, 327, '2025-11-29 11:02:11', '2025-12-13 03:58:57', 1),
(26, 'Supersonic', 15, 12, '', 12, '', NULL, 'song_692ad4a7c954c_1764414631.mp3', 'song_cover_692ad4a7c9997_1764414631.jpg', 174, NULL, 1, 413, '2025-11-29 11:10:31', '2025-12-13 05:22:52', 1),
(27, 'For you', 16, 13, '', 3, '', NULL, 'song_6935422e800c7_1765098030.mp3', 'song_cover_6935422e804e2_1765098030.jpeg', 242, NULL, 1, 145, '2025-12-07 09:00:30', '2025-12-13 06:18:40', 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `song_details`
-- (See below for the actual view)
--
CREATE TABLE `song_details` (
`id` int(11)
,`title` varchar(100)
,`artist_id` int(11)
,`album_id` int(11)
,`album_name` varchar(255)
,`genre_id` int(11)
,`genre_name` varchar(100)
,`release_year` int(11)
,`audio_file` varchar(255)
,`cover_image` varchar(255)
,`duration` int(11)
,`lyrics` text
,`play_count` int(11)
,`created_at` timestamp
,`updated_at` timestamp
,`artist_name` varchar(100)
,`album_title` varchar(100)
,`genre_name_display` varchar(50)
,`genre_color` varchar(7)
,`display_album` varchar(255)
,`display_genre` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT 'default-avatar.png',
  `role` enum('user','admin') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `profile_picture`, `role`, `is_active`, `created_at`) VALUES
(13, 'admin', 'admin@meplay.com', '$2y$10$XuHgb3qPns3K1KDBpQMQAu.EkTaTSUZm6Lrfymdagp6a/6DR5WScy', 'Administrator', 'default-avatar.png', 'admin', 1, '2025-11-26 03:42:06'),
(14, 'asa', 'aswa@gmail.com', '$2y$10$GwsUDzQglpKBH00QotHY1.KsGxN3KllpXgEuM31V1KJizZH1CTtRO', 'aswas', 'default-avatar.png', 'user', 1, '2025-11-26 03:44:23'),
(19, 'sss', 'ss@gmail.com', '$2y$10$ROXNwi48P/CGaMKZHWBRR.uFolUnvGjoftvDRo/e2vtq9zgnOsAf.', NULL, 'default-avatar.png', 'user', 1, '2025-11-29 08:26:38');

-- --------------------------------------------------------

--
-- Table structure for table `user_likes`
--

CREATE TABLE `user_likes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `song_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_likes`
--

INSERT INTO `user_likes` (`id`, `user_id`, `song_id`, `created_at`, `updated_at`) VALUES
(144, 14, 25, '2025-12-12 11:00:08', '2025-12-12 11:00:08'),
(147, 14, 26, '2025-12-13 02:57:41', '2025-12-13 02:57:41'),
(148, 14, 27, '2025-12-13 03:12:05', '2025-12-13 03:12:05');

-- --------------------------------------------------------

--
-- Structure for view `song_details`
--
DROP TABLE IF EXISTS `song_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `song_details`  AS SELECT `s`.`id` AS `id`, `s`.`title` AS `title`, `s`.`artist_id` AS `artist_id`, `s`.`album_id` AS `album_id`, `s`.`album_name` AS `album_name`, `s`.`genre_id` AS `genre_id`, `s`.`genre_name` AS `genre_name`, `s`.`release_year` AS `release_year`, `s`.`audio_file` AS `audio_file`, `s`.`cover_image` AS `cover_image`, `s`.`duration` AS `duration`, `s`.`lyrics` AS `lyrics`, `s`.`play_count` AS `play_count`, `s`.`created_at` AS `created_at`, `s`.`updated_at` AS `updated_at`, `ar`.`name` AS `artist_name`, `a`.`title` AS `album_title`, `g`.`name` AS `genre_name_display`, `g`.`color` AS `genre_color`, coalesce(`s`.`album_name`,`a`.`title`,'Unknown Album') AS `display_album`, coalesce(`s`.`genre_name`,`g`.`name`,'Unknown Genre') AS `display_genre` FROM (((`songs` `s` left join `artists` `ar` on(`s`.`artist_id` = `ar`.`id`)) left join `albums` `a` on(`s`.`album_id` = `a`.`id`)) left join `genres` `g` on(`s`.`genre_id` = `g`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `albums`
--
ALTER TABLE `albums`
  ADD PRIMARY KEY (`id`),
  ADD KEY `artist_id` (`artist_id`);

--
-- Indexes for table `artists`
--
ALTER TABLE `artists`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `genres`
--
ALTER TABLE `genres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `playlists`
--
ALTER TABLE `playlists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `playlist_songs`
--
ALTER TABLE `playlist_songs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_playlist_song` (`playlist_id`,`song_id`),
  ADD KEY `song_id` (`song_id`);

--
-- Indexes for table `recently_played`
--
ALTER TABLE `recently_played`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `song_id` (`song_id`);

--
-- Indexes for table `songs`
--
ALTER TABLE `songs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `album_id` (`album_id`),
  ADD KEY `genre_id` (`genre_id`),
  ADD KEY `idx_album_name` (`album_name`),
  ADD KEY `idx_genre_name` (`genre_name`),
  ADD KEY `idx_songs_album_name` (`album_name`),
  ADD KEY `idx_songs_genre_name` (`genre_name`),
  ADD KEY `idx_songs_release_year` (`release_year`),
  ADD KEY `idx_songs_album` (`album_name`),
  ADD KEY `idx_songs_genre` (`genre_name`),
  ADD KEY `idx_songs_artist` (`artist_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_likes`
--
ALTER TABLE `user_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_song` (`user_id`,`song_id`),
  ADD KEY `song_id` (`song_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `albums`
--
ALTER TABLE `albums`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `artists`
--
ALTER TABLE `artists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `genres`
--
ALTER TABLE `genres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `playlists`
--
ALTER TABLE `playlists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `playlist_songs`
--
ALTER TABLE `playlist_songs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `recently_played`
--
ALTER TABLE `recently_played`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1438;

--
-- AUTO_INCREMENT for table `songs`
--
ALTER TABLE `songs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_likes`
--
ALTER TABLE `user_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `albums`
--
ALTER TABLE `albums`
  ADD CONSTRAINT `albums_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `albums_ibfk_2` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `playlists`
--
ALTER TABLE `playlists`
  ADD CONSTRAINT `playlists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `playlist_songs`
--
ALTER TABLE `playlist_songs`
  ADD CONSTRAINT `playlist_songs_ibfk_1` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `playlist_songs_ibfk_2` FOREIGN KEY (`song_id`) REFERENCES `songs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recently_played`
--
ALTER TABLE `recently_played`
  ADD CONSTRAINT `recently_played_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recently_played_ibfk_2` FOREIGN KEY (`song_id`) REFERENCES `songs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `songs`
--
ALTER TABLE `songs`
  ADD CONSTRAINT `songs_ibfk_1` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `songs_ibfk_4` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `songs_ibfk_5` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_likes`
--
ALTER TABLE `user_likes`
  ADD CONSTRAINT `user_likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_likes_ibfk_2` FOREIGN KEY (`song_id`) REFERENCES `songs` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
