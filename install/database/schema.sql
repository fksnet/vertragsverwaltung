-- Vertragsverwaltung Database Schema
-- Version 1.0.0
-- Compatible with MySQL 8.0+ and MariaDB 10.5+

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Benutzer-Tabelle
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled_at` timestamp NULL DEFAULT NULL,
  `two_factor_backup_codes_generated_at` timestamp NULL DEFAULT NULL,
  `remember_2fa_device` tinyint(1) NOT NULL DEFAULT 0,
  `trusted_devices` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `force_two_factor` tinyint(1) NOT NULL DEFAULT 0,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `login_count` int(11) NOT NULL DEFAULT 0,
  `emergency_mode_active` tinyint(1) NOT NULL DEFAULT 0,
  `account_expires_at` timestamp NULL DEFAULT NULL,
  `created_by_admin` bigint(20) UNSIGNED DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_email_verified_at_index` (`email_verified_at`),
  KEY `users_is_active_index` (`is_active`),
  KEY `users_created_by_admin_foreign` (`created_by_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollen-Tabelle
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#6366f1',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`),
  KEY `roles_created_by_foreign` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Berechtigungen-Tabelle
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`),
  KEY `permissions_category_index` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Benutzer-Rollen-Zuordnung
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_roles_user_id_role_id_unique` (`user_id`,`role_id`),
  KEY `user_roles_role_id_foreign` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollen-Berechtigungen-Zuordnung
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permissions_role_id_permission_id_unique` (`role_id`,`permission_id`),
  KEY `role_permissions_permission_id_foreign` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Korrespondenten-Tabelle
CREATE TABLE IF NOT EXISTS `korrespondenten` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `typ` enum('unternehmen','person','behoerde','sonstiges') NOT NULL DEFAULT 'unternehmen',
  `adresse` text DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `korrespondenten_user_id_foreign` (`user_id`),
  KEY `korrespondenten_typ_index` (`typ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verträge-Tabelle
CREATE TABLE IF NOT EXISTS `vertraege` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `korrespondent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `korrespondent_name` varchar(150) NOT NULL,
  `titel` varchar(200) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `kategorie` varchar(50) DEFAULT NULL,
  `vertragsnummer` varchar(100) DEFAULT NULL,
  `start_datum` date DEFAULT NULL,
  `ende_datum` date DEFAULT NULL,
  `kuendigungsfrist` varchar(100) DEFAULT NULL,
  `kuendigungsdatum` date DEFAULT NULL,
  `automatische_verlaengerung` tinyint(1) NOT NULL DEFAULT 0,
  `verlaengerung_zeitraum` varchar(50) DEFAULT NULL,
  `monatliche_kosten` decimal(10,2) DEFAULT NULL,
  `jaehrliche_kosten` decimal(10,2) DEFAULT NULL,
  `einmalige_kosten` decimal(10,2) DEFAULT NULL,
  `waehrung` varchar(3) DEFAULT 'EUR',
  `zahlungsintervall` enum('einmalig','monatlich','quartalsweise','halbjaehrlich','jaehrlich') DEFAULT 'monatlich',
  `zahlungsmethode` varchar(50) DEFAULT NULL,
  `status` enum('aktiv','gekuendigt','abgelaufen','pausiert') NOT NULL DEFAULT 'aktiv',
  `wichtigkeit` enum('niedrig','mittel','hoch','kritisch') NOT NULL DEFAULT 'mittel',
  `erinnerungen_aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `tags` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vertraege_user_id_foreign` (`user_id`),
  KEY `vertraege_korrespondent_id_foreign` (`korrespondent_id`),
  KEY `vertraege_status_index` (`status`),
  KEY `vertraege_ende_datum_index` (`ende_datum`),
  KEY `vertraege_kuendigungsdatum_index` (`kuendigungsdatum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zugangsdaten-Tabelle
CREATE TABLE IF NOT EXISTS `zugangsdaten` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `vertrag_id` bigint(20) UNSIGNED DEFAULT NULL,
  `korrespondent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `titel` varchar(200) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `benutzername` text DEFAULT NULL,
  `passwort` text DEFAULT NULL,
  `email` text DEFAULT NULL,
  `pin` text DEFAULT NULL,
  `sicherheitsfragen` json DEFAULT NULL,
  `zwei_faktor_secret` text DEFAULT NULL,
  `recovery_codes` json DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `kategorie` varchar(50) DEFAULT NULL,
  `passwort_staerke` int(11) DEFAULT NULL,
  `letzter_passwort_wechsel` timestamp NULL DEFAULT NULL,
  `passwort_ablauf` date DEFAULT NULL,
  `zugriff_protokoll` json DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `zugangsdaten_user_id_foreign` (`user_id`),
  KEY `zugangsdaten_vertrag_id_foreign` (`vertrag_id`),
  KEY `zugangsdaten_korrespondent_id_foreign` (`korrespondent_id`),
  KEY `zugangsdaten_kategorie_index` (`kategorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dokumente-Tabelle
CREATE TABLE IF NOT EXISTS `dokumente` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `vertrag_id` bigint(20) UNSIGNED DEFAULT NULL,
  `korrespondent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `titel` varchar(200) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `dateiname` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `dateipfad` varchar(500) NOT NULL,
  `dateityp` varchar(50) NOT NULL,
  `groesse` bigint(20) UNSIGNED NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `hash` varchar(64) DEFAULT NULL,
  `thumbnail_pfad` varchar(500) DEFAULT NULL,
  `ocr_text` text DEFAULT NULL,
  `kategorie` varchar(50) DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `download_count` int(11) NOT NULL DEFAULT 0,
  `last_downloaded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dokumente_user_id_foreign` (`user_id`),
  KEY `dokumente_vertrag_id_foreign` (`vertrag_id`),
  KEY `dokumente_korrespondent_id_foreign` (`korrespondent_id`),
  KEY `dokumente_dateityp_index` (`dateityp`),
  KEY `dokumente_kategorie_index` (`kategorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Freigaben-Tabelle
CREATE TABLE IF NOT EXISTS `freigaben` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_user_id` bigint(20) UNSIGNED NOT NULL,
  `friend_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `friend_name` varchar(100) NOT NULL,
  `friend_email` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` json NOT NULL,
  `vertrag_ids` json DEFAULT NULL,
  `korrespondent_ids` json DEFAULT NULL,
  `status` enum('active','pending','expired','revoked') NOT NULL DEFAULT 'active',
  `expires_at` timestamp NOT NULL,
  `access_token` varchar(64) NOT NULL,
  `confirmation_token` varchar(64) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_reason` text DEFAULT NULL,
  `access_count` int(11) NOT NULL DEFAULT 0,
  `last_accessed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `freigaben_access_token_unique` (`access_token`),
  KEY `freigaben_owner_user_id_foreign` (`owner_user_id`),
  KEY `freigaben_friend_user_id_foreign` (`friend_user_id`),
  KEY `freigaben_status_index` (`status`),
  KEY `freigaben_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notfallkontakte-Tabelle
CREATE TABLE IF NOT EXISTS `notfall_kontakte` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `relationship` varchar(100) NOT NULL,
  `priority` tinyint(4) NOT NULL,
  `can_access_contracts` tinyint(1) NOT NULL DEFAULT 0,
  `can_access_credentials` tinyint(1) NOT NULL DEFAULT 0,
  `can_access_documents` tinyint(1) NOT NULL DEFAULT 0,
  `notification_preference` enum('email','phone','both') NOT NULL DEFAULT 'email',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notfall_kontakte_user_id_priority_unique` (`user_id`,`priority`),
  KEY `notfall_kontakte_user_id_foreign` (`user_id`),
  KEY `notfall_kontakte_is_verified_index` (`is_verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notfallzugriffe-Tabelle
CREATE TABLE IF NOT EXISTS `notfall_zugriffe` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `kontakt_id` bigint(20) UNSIGNED NOT NULL,
  `reason` text NOT NULL,
  `urgency` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('pending','verification_sent','active','denied','expired','deactivated') NOT NULL DEFAULT 'pending',
  `verification_code` varchar(6) NOT NULL,
  `access_token` varchar(64) NOT NULL,
  `request_ip` varchar(45) DEFAULT NULL,
  `request_user_agent` text DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `denied_at` timestamp NULL DEFAULT NULL,
  `denied_by` bigint(20) UNSIGNED DEFAULT NULL,
  `denial_reason` text DEFAULT NULL,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `access_count` int(11) NOT NULL DEFAULT 0,
  `last_accessed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notfall_zugriffe_access_token_unique` (`access_token`),
  KEY `notfall_zugriffe_user_id_foreign` (`user_id`),
  KEY `notfall_zugriffe_kontakt_id_foreign` (`kontakt_id`),
  KEY `notfall_zugriffe_status_index` (`status`),
  KEY `notfall_zugriffe_urgency_index` (`urgency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zwei-Faktor Backup-Codes
CREATE TABLE IF NOT EXISTS `two_factor_backup_codes` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `encrypted_code` text NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `used_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `two_factor_backup_codes_user_id_foreign` (`user_id`),
  KEY `two_factor_backup_codes_code_hash_index` (`code_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit-Log-Tabelle
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `resource` varchar(50) DEFAULT NULL,
  `resource_id` bigint(20) UNSIGNED DEFAULT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_resource_index` (`resource`),
  KEY `audit_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Einstellungen-Tabelle
CREATE TABLE IF NOT EXISTS `settings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type` enum('string','integer','boolean','json','email','url') NOT NULL DEFAULT 'string',
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `default_value` text DEFAULT NULL,
  `validation_rules` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `cache_key` varchar(100) DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`),
  KEY `settings_category_index` (`category`),
  KEY `settings_updated_by_foreign` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Foreign Key Constraints
ALTER TABLE `users`
  ADD CONSTRAINT `users_created_by_admin_foreign` FOREIGN KEY (`created_by_admin`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `roles`
  ADD CONSTRAINT `roles_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

ALTER TABLE `korrespondenten`
  ADD CONSTRAINT `korrespondenten_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `vertraege`
  ADD CONSTRAINT `vertraege_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vertraege_korrespondent_id_foreign` FOREIGN KEY (`korrespondent_id`) REFERENCES `korrespondenten` (`id`) ON DELETE SET NULL;

ALTER TABLE `zugangsdaten`
  ADD CONSTRAINT `zugangsdaten_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `zugangsdaten_vertrag_id_foreign` FOREIGN KEY (`vertrag_id`) REFERENCES `vertraege` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `zugangsdaten_korrespondent_id_foreign` FOREIGN KEY (`korrespondent_id`) REFERENCES `korrespondenten` (`id`) ON DELETE SET NULL;

ALTER TABLE `dokumente`
  ADD CONSTRAINT `dokumente_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dokumente_vertrag_id_foreign` FOREIGN KEY (`vertrag_id`) REFERENCES `vertraege` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dokumente_korrespondent_id_foreign` FOREIGN KEY (`korrespondent_id`) REFERENCES `korrespondenten` (`id`) ON DELETE SET NULL;

ALTER TABLE `freigaben`
  ADD CONSTRAINT `freigaben_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `freigaben_friend_user_id_foreign` FOREIGN KEY (`friend_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `notfall_kontakte`
  ADD CONSTRAINT `notfall_kontakte_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `notfall_zugriffe`
  ADD CONSTRAINT `notfall_zugriffe_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notfall_zugriffe_kontakt_id_foreign` FOREIGN KEY (`kontakt_id`) REFERENCES `notfall_kontakte` (`id`) ON DELETE CASCADE;

ALTER TABLE `two_factor_backup_codes`
  ADD CONSTRAINT `two_factor_backup_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `settings`
  ADD CONSTRAINT `settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;