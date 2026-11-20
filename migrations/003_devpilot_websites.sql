-- DevPilot Phase 1 website registration and credential vault.
-- Review and apply manually to a BACKED UP local/staging database only.
-- This migration is additive and intentionally does not alter existing tables.
-- MySQL/MariaDB prepared statements are used so a failed preflight blocks DDL.

SET @devpilot_users_ok := (
  SELECT COUNT(*) = 1
  FROM information_schema.COLUMNS c
  JOIN information_schema.TABLES t
    ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
  WHERE c.TABLE_SCHEMA = DATABASE()
    AND c.TABLE_NAME = 'users'
    AND c.COLUMN_NAME = 'id'
    AND c.DATA_TYPE = 'int'
    AND c.COLUMN_TYPE NOT LIKE '%unsigned%'
    AND c.IS_NULLABLE = 'NO'
    AND t.ENGINE = 'InnoDB'
);
SET @devpilot_existing_tables_ok := (
  NOT EXISTS (
    SELECT 1 FROM information_schema.TABLES t
    WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = 'devpilot_websites'
      AND (IFNULL(t.ENGINE, '') <> 'InnoDB' OR (SELECT COUNT(*) FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND c.COLUMN_NAME IN ('id','website_uuid','owner_user_id','name','admin_url','origin_host','origin_port','base_path','origin_fingerprint','active_origin_fingerprint','environment','mfa_expected','status','created_at','updated_at','revoked_at')) <> 16)
  )
  AND NOT EXISTS (
    SELECT 1 FROM information_schema.TABLES t
    WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = 'devpilot_website_scopes'
      AND (IFNULL(t.ENGINE, '') <> 'InnoDB' OR (SELECT COUNT(*) FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND c.COLUMN_NAME IN ('grant_id','website_id','permission_code','constraints_json','granted_by','granted_at','revoked_at')) <> 7)
  )
  AND NOT EXISTS (
    SELECT 1 FROM information_schema.TABLES t
    WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = 'devpilot_website_credentials'
      AND (IFNULL(t.ENGINE, '') <> 'InnoDB' OR (SELECT COUNT(*) FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND c.COLUMN_NAME IN ('credential_uuid','website_id','credential_version','cipher_algorithm','data_key_version','master_key_version','payload_nonce','payload_ciphertext','wrapped_dek_nonce','wrapped_dek','status')) <> 11)
  )
  AND NOT EXISTS (
    SELECT 1 FROM information_schema.TABLES t
    WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = 'devpilot_security_audit'
      AND (IFNULL(t.ENGINE, '') <> 'InnoDB' OR (SELECT COUNT(*) FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND c.COLUMN_NAME IN ('id','actor_user_id','website_id','event_code','outcome','request_uuid','details_json','created_at')) <> 8)
  )
);
SET @devpilot_preflight_sql := IF(
  @devpilot_users_ok AND @devpilot_existing_tables_ok,
  'SELECT 1',
  'SELECT * FROM `__devpilot_migration_003_prerequisite_failed__`'
);
PREPARE devpilot_preflight FROM @devpilot_preflight_sql;
EXECUTE devpilot_preflight;
DEALLOCATE PREPARE devpilot_preflight;

CREATE TABLE IF NOT EXISTS `devpilot_websites` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `website_uuid` BINARY(16) NOT NULL,
  `owner_user_id` INT NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `admin_url` VARCHAR(2048) NOT NULL,
  `origin_host` VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `origin_port` SMALLINT UNSIGNED NOT NULL DEFAULT 443,
  `base_path` VARCHAR(1024) NOT NULL DEFAULT '/',
  `origin_fingerprint` BINARY(32) NOT NULL,
  `active_origin_fingerprint` BINARY(32) NULL,
  `environment` VARCHAR(24) NOT NULL,
  `mfa_expected` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `revoked_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_devpilot_websites_uuid` (`website_uuid`),
  UNIQUE KEY `uq_devpilot_websites_owner_active_origin` (`owner_user_id`, `active_origin_fingerprint`),
  KEY `idx_devpilot_websites_owner_status` (`owner_user_id`, `status`, `created_at`),
  CONSTRAINT `fk_devpilot_websites_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `devpilot_website_scopes` (
  `grant_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `website_id` BIGINT UNSIGNED NOT NULL,
  `permission_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `constraints_json` LONGTEXT NOT NULL,
  `granted_by` INT NOT NULL,
  `granted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`grant_id`),
  KEY `idx_devpilot_scopes_website_active` (`website_id`, `revoked_at`, `permission_code`),
  KEY `idx_devpilot_scopes_granted_by` (`granted_by`, `granted_at`),
  CONSTRAINT `fk_devpilot_scopes_website` FOREIGN KEY (`website_id`) REFERENCES `devpilot_websites` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_devpilot_scopes_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `devpilot_website_credentials` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `credential_uuid` BINARY(16) NOT NULL,
  `website_id` BIGINT UNSIGNED NOT NULL,
  `credential_version` INT UNSIGNED NOT NULL,
  `credential_kind` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `cipher_algorithm` VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `data_key_version` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `master_key_version` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload_nonce` VARBINARY(24) NULL,
  `payload_ciphertext` MEDIUMBLOB NULL,
  `wrapped_dek_nonce` VARBINARY(24) NULL,
  `wrapped_dek` VARBINARY(64) NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` TIMESTAMP NULL DEFAULT NULL,
  `replaced_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_devpilot_credential_uuid` (`credential_uuid`),
  UNIQUE KEY `uq_devpilot_credential_version` (`website_id`, `credential_version`),
  KEY `idx_devpilot_credentials_website_status` (`website_id`, `status`),
  CONSTRAINT `fk_devpilot_credentials_website` FOREIGN KEY (`website_id`) REFERENCES `devpilot_websites` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_devpilot_credentials_replaced_by` FOREIGN KEY (`replaced_by`) REFERENCES `devpilot_website_credentials` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `devpilot_security_audit` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` INT NULL,
  `website_id` BIGINT UNSIGNED NULL,
  `event_code` VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `outcome` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `request_uuid` BINARY(16) NOT NULL,
  `details_json` LONGTEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_devpilot_audit_website_created` (`website_id`, `created_at`),
  KEY `idx_devpilot_audit_actor_created` (`actor_user_id`, `created_at`),
  CONSTRAINT `fk_devpilot_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_devpilot_audit_website` FOREIGN KEY (`website_id`) REFERENCES `devpilot_websites` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detect pre-existing objects that CREATE TABLE IF NOT EXISTS would otherwise
-- silently accept with an incompatible schema or engine.
SET @devpilot_tables_ok := (
  SELECT COUNT(DISTINCT t.TABLE_NAME) = 4
  FROM information_schema.TABLES t
  WHERE t.TABLE_SCHEMA = DATABASE()
    AND t.ENGINE = 'InnoDB'
    AND t.TABLE_NAME IN ('devpilot_websites', 'devpilot_website_scopes', 'devpilot_website_credentials', 'devpilot_security_audit')
);
SET @devpilot_columns_ok := (
  SELECT COUNT(*) = 42
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND ((TABLE_NAME = 'devpilot_websites' AND COLUMN_NAME IN ('id','website_uuid','owner_user_id','name','admin_url','origin_host','origin_port','base_path','origin_fingerprint','active_origin_fingerprint','environment','mfa_expected','status','created_at','updated_at','revoked_at'))
      OR (TABLE_NAME = 'devpilot_website_scopes' AND COLUMN_NAME IN ('grant_id','website_id','permission_code','constraints_json','granted_by','granted_at','revoked_at'))
      OR (TABLE_NAME = 'devpilot_website_credentials' AND COLUMN_NAME IN ('credential_uuid','website_id','credential_version','cipher_algorithm','data_key_version','master_key_version','payload_nonce','payload_ciphertext','wrapped_dek_nonce','wrapped_dek','status'))
      OR (TABLE_NAME = 'devpilot_security_audit' AND COLUMN_NAME IN ('id','actor_user_id','website_id','event_code','outcome','request_uuid','details_json','created_at')))
);
SET @devpilot_final_sql := IF(
  @devpilot_users_ok AND @devpilot_tables_ok AND @devpilot_columns_ok,
  'SELECT 1',
  'SELECT * FROM `__devpilot_migration_003_schema_verification_failed__`'
);
PREPARE devpilot_final_check FROM @devpilot_final_sql;
EXECUTE devpilot_final_check;
DEALLOCATE PREPARE devpilot_final_check;
