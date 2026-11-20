-- DevPilot Phase 1 website registration and credential vault.
-- Review and apply manually to a BACKED UP local/staging database only.
-- This migration is additive and intentionally does not alter existing tables.
-- MySQL/MariaDB prepared statements are used so a failed preflight blocks DDL.
SET SESSION group_concat_max_len = 16384;

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
    AND c.COLUMN_KEY = 'PRI'
    AND c.EXTRA LIKE '%auto_increment%'
    AND t.ENGINE = 'InnoDB'
);
-- Existing objects must match the complete expected columns, indexes, foreign
-- keys, and engine before CREATE TABLE IF NOT EXISTS can accept them.
SET @devpilot_existing_tables_ok := (
  (NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_websites') OR (
    (SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_websites')='InnoDB'
    AND (SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_websites')='utf8mb4_unicode_ci'
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_websites' AND COLUMN_NAME='origin_host' AND CHARACTER_SET_NAME='ascii' AND COLLATION_NAME='ascii_bin')=1
    AND (SELECT GROUP_CONCAT(CONCAT(COLUMN_NAME,'=',CASE WHEN DATA_TYPE IN ('int','bigint','smallint','tinyint') THEN CONCAT(DATA_TYPE,IF(COLUMN_TYPE LIKE '%unsigned%',' unsigned','')) ELSE COLUMN_TYPE END,':',IS_NULLABLE,':',IFNULL(COLUMN_DEFAULT,'<NULL>'),':',EXTRA) ORDER BY COLUMN_NAME SEPARATOR '|') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_websites')='active_origin_fingerprint=binary(32):YES:NULL:|admin_url=varchar(2048):NO:<NULL>:|base_path=varchar(1024):NO:\'/\':|created_at=timestamp:NO:current_timestamp():|environment=varchar(24):NO:<NULL>:|id=bigint unsigned:NO:<NULL>:auto_increment|mfa_expected=tinyint:NO:0:|name=varchar(120):NO:<NULL>:|origin_fingerprint=binary(32):NO:<NULL>:|origin_host=varchar(253):NO:<NULL>:|origin_port=smallint unsigned:NO:443:|owner_user_id=int:NO:<NULL>:|revoked_at=timestamp:YES:NULL:|status=varchar(16):NO:\'active\':|updated_at=timestamp:NO:current_timestamp():on update current_timestamp()|website_uuid=binary(16):NO:<NULL>:'
    AND (SELECT GROUP_CONCAT(CONCAT(INDEX_NAME,':',NON_UNIQUE,':',SEQ_IN_INDEX,':',COLUMN_NAME) ORDER BY INDEX_NAME,SEQ_IN_INDEX SEPARATOR '|') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_websites')='idx_devpilot_websites_owner_status:1:1:owner_user_id|idx_devpilot_websites_owner_status:1:2:status|idx_devpilot_websites_owner_status:1:3:created_at|PRIMARY:0:1:id|uq_devpilot_websites_owner_active_origin:0:1:owner_user_id|uq_devpilot_websites_owner_active_origin:0:2:active_origin_fingerprint|uq_devpilot_websites_uuid:0:1:website_uuid'
    AND (SELECT GROUP_CONCAT(CONCAT(k.CONSTRAINT_NAME,':',k.COLUMN_NAME,'>',k.REFERENCED_TABLE_NAME,'.',k.REFERENCED_COLUMN_NAME,':',r.DELETE_RULE,':',r.UPDATE_RULE) ORDER BY k.CONSTRAINT_NAME SEPARATOR '|') FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='devpilot_websites' AND k.REFERENCED_TABLE_NAME IS NOT NULL)='fk_devpilot_websites_owner:owner_user_id>users.id:RESTRICT:RESTRICT'
  ))
  AND (NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_scopes') OR (
    (SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_scopes')='InnoDB'
    AND (SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_scopes')='utf8mb4_unicode_ci'
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_scopes' AND COLUMN_NAME='permission_code' AND CHARACTER_SET_NAME='ascii' AND COLLATION_NAME='ascii_bin')=1
    AND (SELECT GROUP_CONCAT(CONCAT(COLUMN_NAME,'=',CASE WHEN DATA_TYPE IN ('int','bigint','smallint','tinyint') THEN CONCAT(DATA_TYPE,IF(COLUMN_TYPE LIKE '%unsigned%',' unsigned','')) ELSE COLUMN_TYPE END,':',IS_NULLABLE,':',IFNULL(COLUMN_DEFAULT,'<NULL>'),':',EXTRA) ORDER BY COLUMN_NAME SEPARATOR '|') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_scopes')='constraints_json=longtext:NO:<NULL>:|granted_at=timestamp:NO:current_timestamp():|granted_by=int:NO:<NULL>:|grant_id=bigint unsigned:NO:<NULL>:auto_increment|permission_code=varchar(64):NO:<NULL>:|revoked_at=timestamp:YES:NULL:|website_id=bigint unsigned:NO:<NULL>:'
    AND (SELECT GROUP_CONCAT(CONCAT(INDEX_NAME,':',NON_UNIQUE,':',SEQ_IN_INDEX,':',COLUMN_NAME) ORDER BY INDEX_NAME,SEQ_IN_INDEX SEPARATOR '|') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_scopes')='idx_devpilot_scopes_granted_by:1:1:granted_by|idx_devpilot_scopes_granted_by:1:2:granted_at|idx_devpilot_scopes_website_active:1:1:website_id|idx_devpilot_scopes_website_active:1:2:revoked_at|idx_devpilot_scopes_website_active:1:3:permission_code|PRIMARY:0:1:grant_id'
    AND (SELECT GROUP_CONCAT(CONCAT(k.CONSTRAINT_NAME,':',k.COLUMN_NAME,'>',k.REFERENCED_TABLE_NAME,'.',k.REFERENCED_COLUMN_NAME,':',r.DELETE_RULE,':',r.UPDATE_RULE) ORDER BY k.CONSTRAINT_NAME SEPARATOR '|') FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='devpilot_website_scopes' AND k.REFERENCED_TABLE_NAME IS NOT NULL)='fk_devpilot_scopes_granted_by:granted_by>users.id:RESTRICT:RESTRICT|fk_devpilot_scopes_website:website_id>devpilot_websites.id:RESTRICT:RESTRICT'
  ))
  AND (NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_credentials') OR (
    (SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_credentials')='InnoDB'
    AND (SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_credentials')='utf8mb4_unicode_ci'
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_credentials' AND COLUMN_NAME IN ('credential_kind','cipher_algorithm','data_key_version','master_key_version') AND CHARACTER_SET_NAME='ascii' AND COLLATION_NAME='ascii_bin')=4
    AND (SELECT GROUP_CONCAT(CONCAT(COLUMN_NAME,'=',CASE WHEN DATA_TYPE IN ('int','bigint','smallint','tinyint') THEN CONCAT(DATA_TYPE,IF(COLUMN_TYPE LIKE '%unsigned%',' unsigned','')) ELSE COLUMN_TYPE END,':',IS_NULLABLE,':',IFNULL(COLUMN_DEFAULT,'<NULL>'),':',EXTRA) ORDER BY COLUMN_NAME SEPARATOR '|') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_credentials')='cipher_algorithm=varchar(48):NO:<NULL>:|created_at=timestamp:NO:current_timestamp():|credential_kind=varchar(32):NO:<NULL>:|credential_uuid=binary(16):NO:<NULL>:|credential_version=int unsigned:NO:<NULL>:|data_key_version=varchar(32):NO:<NULL>:|id=bigint unsigned:NO:<NULL>:auto_increment|master_key_version=varchar(32):NO:<NULL>:|payload_ciphertext=mediumblob:YES:NULL:|payload_nonce=varbinary(24):YES:NULL:|replaced_by=bigint unsigned:YES:NULL:|revoked_at=timestamp:YES:NULL:|status=varchar(16):NO:\'active\':|website_id=bigint unsigned:NO:<NULL>:|wrapped_dek=varbinary(64):YES:NULL:|wrapped_dek_nonce=varbinary(24):YES:NULL:'
    AND (SELECT GROUP_CONCAT(CONCAT(INDEX_NAME,':',NON_UNIQUE,':',SEQ_IN_INDEX,':',COLUMN_NAME) ORDER BY INDEX_NAME,SEQ_IN_INDEX SEPARATOR '|') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_website_credentials')='fk_devpilot_credentials_replaced_by:1:1:replaced_by|idx_devpilot_credentials_website_status:1:1:website_id|idx_devpilot_credentials_website_status:1:2:status|PRIMARY:0:1:id|uq_devpilot_credential_uuid:0:1:credential_uuid|uq_devpilot_credential_version:0:1:website_id|uq_devpilot_credential_version:0:2:credential_version'
    AND (SELECT GROUP_CONCAT(CONCAT(k.CONSTRAINT_NAME,':',k.COLUMN_NAME,'>',k.REFERENCED_TABLE_NAME,'.',k.REFERENCED_COLUMN_NAME,':',r.DELETE_RULE,':',r.UPDATE_RULE) ORDER BY k.CONSTRAINT_NAME SEPARATOR '|') FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='devpilot_website_credentials' AND k.REFERENCED_TABLE_NAME IS NOT NULL)='fk_devpilot_credentials_replaced_by:replaced_by>devpilot_website_credentials.id:RESTRICT:RESTRICT|fk_devpilot_credentials_website:website_id>devpilot_websites.id:RESTRICT:RESTRICT'
  ))
  AND (NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_security_audit') OR (
    (SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_security_audit')='InnoDB'
    AND (SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_security_audit')='utf8mb4_unicode_ci'
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_security_audit' AND COLUMN_NAME IN ('event_code','outcome') AND CHARACTER_SET_NAME='ascii' AND COLLATION_NAME='ascii_bin')=2
    AND (SELECT GROUP_CONCAT(CONCAT(COLUMN_NAME,'=',CASE WHEN DATA_TYPE IN ('int','bigint','smallint','tinyint') THEN CONCAT(DATA_TYPE,IF(COLUMN_TYPE LIKE '%unsigned%',' unsigned','')) ELSE COLUMN_TYPE END,':',IS_NULLABLE,':',IFNULL(COLUMN_DEFAULT,'<NULL>'),':',EXTRA) ORDER BY COLUMN_NAME SEPARATOR '|') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_security_audit')='actor_user_id=int:YES:NULL:|created_at=timestamp:NO:current_timestamp():|details_json=longtext:YES:NULL:|event_code=varchar(48):NO:<NULL>:|id=bigint unsigned:NO:<NULL>:auto_increment|outcome=varchar(16):NO:<NULL>:|request_uuid=binary(16):NO:<NULL>:|website_id=bigint unsigned:YES:NULL:'
    AND (SELECT GROUP_CONCAT(CONCAT(INDEX_NAME,':',NON_UNIQUE,':',SEQ_IN_INDEX,':',COLUMN_NAME) ORDER BY INDEX_NAME,SEQ_IN_INDEX SEPARATOR '|') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='devpilot_security_audit')='idx_devpilot_audit_actor_created:1:1:actor_user_id|idx_devpilot_audit_actor_created:1:2:created_at|idx_devpilot_audit_website_created:1:1:website_id|idx_devpilot_audit_website_created:1:2:created_at|PRIMARY:0:1:id'
    AND (SELECT GROUP_CONCAT(CONCAT(k.CONSTRAINT_NAME,':',k.COLUMN_NAME,'>',k.REFERENCED_TABLE_NAME,'.',k.REFERENCED_COLUMN_NAME,':',r.DELETE_RULE,':',r.UPDATE_RULE) ORDER BY k.CONSTRAINT_NAME SEPARATOR '|') FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='devpilot_security_audit' AND k.REFERENCED_TABLE_NAME IS NOT NULL)='fk_devpilot_audit_actor:actor_user_id>users.id:SET NULL:RESTRICT|fk_devpilot_audit_website:website_id>devpilot_websites.id:SET NULL:RESTRICT'
  ))
);
SET @devpilot_preflight_sql := IF(
  @devpilot_users_ok AND @devpilot_existing_tables_ok,
  'SELECT 1',
  'SELECT * FROM `__devpilot_migration_003_schema_incompatible__`'
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

-- Verify actual resulting structure, including exact ordered definitions, keys,
-- constraints, collations, defaults, and engines (not just table/column counts).
SET @devpilot_final_schema_ok := (
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('devpilot_websites','devpilot_website_scopes','devpilot_website_credentials','devpilot_security_audit') AND ENGINE='InnoDB' AND TABLE_COLLATION='utf8mb4_unicode_ci')=4
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('devpilot_websites','devpilot_website_scopes','devpilot_website_credentials','devpilot_security_audit'))=47
  AND (SELECT SHA2(GROUP_CONCAT(CONCAT(TABLE_NAME,'=',COLUMN_NAME,'=',CASE WHEN DATA_TYPE IN ('int','bigint','smallint','tinyint') THEN CONCAT(DATA_TYPE,IF(COLUMN_TYPE LIKE '%unsigned%',' unsigned','')) ELSE COLUMN_TYPE END,':',IS_NULLABLE,':',IFNULL(COLUMN_DEFAULT,'<NULL>'),':',EXTRA,':',IFNULL(CHARACTER_SET_NAME,'<NULL>'),':',IFNULL(COLLATION_NAME,'<NULL>')) ORDER BY TABLE_NAME,COLUMN_NAME SEPARATOR '|'),256) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('devpilot_websites','devpilot_website_scopes','devpilot_website_credentials','devpilot_security_audit'))='557157a50a17f225df29091b398d49a9b61c3ac412f07add83228f47a29641df'
  AND (SELECT SHA2(GROUP_CONCAT(CONCAT(TABLE_NAME,'=',INDEX_NAME,':',NON_UNIQUE,':',SEQ_IN_INDEX,':',COLUMN_NAME) ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX SEPARATOR '|'),256) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('devpilot_websites','devpilot_website_scopes','devpilot_website_credentials','devpilot_security_audit'))='3697cde673b2b5038b18c81b2c7b1aba08a29413f1e08d56dfbc0fb90a05825a'
  AND (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME IN ('devpilot_websites','devpilot_website_scopes','devpilot_website_credentials','devpilot_security_audit') AND REFERENCED_TABLE_NAME IS NOT NULL)=7
  AND (SELECT SHA2(GROUP_CONCAT(CONCAT(k.TABLE_NAME,'=',k.CONSTRAINT_NAME,':',k.COLUMN_NAME,'>',k.REFERENCED_TABLE_NAME,'.',k.REFERENCED_COLUMN_NAME,':',r.DELETE_RULE,':',r.UPDATE_RULE) ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME SEPARATOR '|'),256) FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME IN ('devpilot_websites','devpilot_website_scopes','devpilot_website_credentials','devpilot_security_audit') AND k.REFERENCED_TABLE_NAME IS NOT NULL)='9866e6ff9cf49227f9aed969f53b8e3b65cbc9a591c7719f0e38ea446f43d4b7'
  AND (SELECT COUNT(*) FROM information_schema.COLUMNS c JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME WHERE c.TABLE_SCHEMA=DATABASE() AND c.TABLE_NAME='users' AND c.COLUMN_NAME='id' AND c.DATA_TYPE='int' AND c.COLUMN_TYPE NOT LIKE '%unsigned%' AND c.IS_NULLABLE='NO' AND c.COLUMN_KEY='PRI' AND c.EXTRA LIKE '%auto_increment%' AND t.ENGINE='InnoDB')=1
);
SET @devpilot_final_sql := IF(
  @devpilot_users_ok AND @devpilot_final_schema_ok,
  'SELECT 1',
  'SELECT * FROM `__devpilot_migration_003_schema_verification_failed__`'
);
PREPARE devpilot_final_check FROM @devpilot_final_sql;
EXECUTE devpilot_final_check;
DEALLOCATE PREPARE devpilot_final_check;
