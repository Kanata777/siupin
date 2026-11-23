-- =====================================================================
-- SIUPIN: fitur Rapat Saya + pembatasan data rapat per user
-- Jalankan di phpMyAdmin (tab SQL) pada database siupin_nh.
-- Aman dijalankan berulang kali (setiap perubahan dicek dulu).
-- Tidak ada data rapat/undangan yang dihapus atau diubah.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Tabel users (login). Dibuat hanya bila belum ada.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('super_admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bila tabel users sudah ada tetapi belum punya kolom role, tambahkan:
SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `users` ADD COLUMN `role` ENUM(''super_admin'',''user'') NOT NULL DEFAULT ''user'' AFTER `password`',
  'SELECT ''users.role sudah ada''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2) Tabel pendukung login (sesi + cache untuk pembatas percobaan login).
--    Tidak berbahaya bila .env memakai driver "file": tabelnya hanya tidak terpakai.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) rapat.user_id = pemilik rapat (user yang membuat).
--    NULL = data lama tanpa pemilik -> hanya terlihat oleh Super Admin.
--    rapat_undangan tidak perlu kolom baru: pemiliknya mengikuti rapat.user_id.
-- ---------------------------------------------------------------------
SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rapat' AND COLUMN_NAME = 'user_id');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `rapat` ADD COLUMN `user_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `pengundang`, ADD KEY `rapat_user_id_index` (`user_id`)',
  'SELECT ''rapat.user_id sudah ada''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Relasi ke users (bila user dihapus, rapatnya tetap ada dan menjadi "tanpa pemilik").
-- Bila langkah ini gagal karena tipe kolom users.id berbeda, langkah 3 di atas tetap sudah
-- berfungsi; relasi ini hanya tambahan integritas data.
SET @ada := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rapat'
               AND CONSTRAINT_NAME = 'rapat_user_id_foreign' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `rapat` ADD CONSTRAINT `rapat_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT ''relasi rapat_user_id_foreign sudah ada''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- OPSIONAL (hapus tanda "-- " di depan baris bila diperlukan)
-- =====================================================================

-- A) Memberi pemilik pada rapat lama: cocokkan kolom pengundang dengan users.name,
--    hanya bila nama itu milik TEPAT SATU user. Jalankan SETELAH akun user dibuat.
--    Rapat yang tidak cocok tetap tanpa pemilik (hanya terlihat Super Admin).
--
-- UPDATE `rapat` r
-- JOIN (SELECT `name`, MIN(`id`) AS id FROM `users` GROUP BY `name` HAVING COUNT(*) = 1) u
--   ON u.`name` COLLATE utf8mb4_general_ci = r.`pengundang`
-- SET r.`user_id` = u.`id`
-- WHERE r.`user_id` IS NULL;

-- B) Dibutuhkan fitur "Tempat di luar kantor" (sudah diminta oleh RapatController lama):
--
-- ALTER TABLE `rapat` MODIFY `id_tempat` INT NULL DEFAULT NULL,
--                     MODIFY `tempat_rapat` VARCHAR(255) NOT NULL;
