-- Migration Script: Update MePlay Database Schema
-- Execute this in phpMyAdmin or MySQL client to update existing database

USE meplay;

-- 1. Add is_active column to users table if it doesn't exist
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) DEFAULT 1 AFTER `role`;

-- 2. Update existing users to set is_active = 1 (active by default)
UPDATE `users` SET `is_active` = 1 WHERE `is_active` IS NULL;

-- 3. Rename plays_count to play_count in songs table
ALTER TABLE `songs` 
CHANGE COLUMN `plays_count` `play_count` INT(11) DEFAULT 0;

-- 4. Drop and recreate the song_details view with corrected column name
DROP VIEW IF EXISTS `song_details`;

CREATE VIEW `song_details` AS 
SELECT 
  `s`.`id` AS `id`,
  `s`.`title` AS `title`,
  `s`.`artist_id` AS `artist_id`,
  `s`.`album_id` AS `album_id`,
  `s`.`album_name` AS `album_name`,
  `s`.`genre_id` AS `genre_id`,
  `s`.`genre_name` AS `genre_name`,
  `s`.`release_year` AS `release_year`,
  `s`.`audio_file` AS `audio_file`,
  `s`.`cover_image` AS `cover_image`,
  `s`.`duration` AS `duration`,
  `s`.`lyrics` AS `lyrics`,
  `s`.`play_count` AS `play_count`,
  `s`.`created_at` AS `created_at`,
  `s`.`updated_at` AS `updated_at`,
  `ar`.`name` AS `artist_name`,
  `a`.`title` AS `album_title`,
  `g`.`name` AS `genre_name_display`,
  `g`.`color` AS `genre_color`,
  COALESCE(`s`.`album_name`,`a`.`title`,'Unknown Album') AS `display_album`,
  COALESCE(`s`.`genre_name`,`g`.`name`,'Unknown Genre') AS `display_genre` 
FROM (((`songs` `s` 
  LEFT JOIN `artists` `ar` ON(`s`.`artist_id` = `ar`.`id`)) 
  LEFT JOIN `albums` `a` ON(`s`.`album_id` = `a`.`id`)) 
  LEFT JOIN `genres` `g` ON(`s`.`genre_id` = `g`.`id`));

-- 5. Create default admin user if not exists (for testing)
INSERT IGNORE INTO `users` (`id`, `username`, `email`, `password`, `role`, `is_active`, `created_at`) 
VALUES (1, 'admin', 'admin@meplay.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NOW());
-- Password: password

-- Migration completed
SELECT 'Migration completed successfully!' AS status;
