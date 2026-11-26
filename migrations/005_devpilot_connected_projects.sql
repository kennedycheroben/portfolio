-- Owner-bound DevPilot project registry and secure-pairing rate limit.
ALTER TABLE `devpilot_connector_pairings` ADD COLUMN `owner_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `used_at`;
ALTER TABLE `devpilot_connector_connections` ADD COLUMN `owner_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `revoked_at`;
ALTER TABLE `devpilot_connector_connections` ADD KEY `idx_connector_owner_project` (`owner_id`,`project_id`,`status`);

CREATE TABLE `devpilot_connector_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_hash` BINARY(32) NOT NULL,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_connector_attempt_window` (`ip_hash`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `devpilot_connected_projects` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id` BIGINT UNSIGNED NOT NULL,
  `project_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `connection_id` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `project_name` VARCHAR(160) NOT NULL,
  `origin` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `scopes_json` JSON NOT NULL,
  `status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
  `credential_uuid` BINARY(16) NOT NULL,
  `cipher_algorithm` VARCHAR(64) NOT NULL,
  `data_key_version` VARCHAR(32) NOT NULL,
  `master_key_version` VARCHAR(32) NOT NULL,
  `payload_nonce` VARBINARY(64) NOT NULL,
  `payload_ciphertext` MEDIUMBLOB NOT NULL,
  `wrapped_dek_nonce` VARBINARY(64) NOT NULL,
  `wrapped_dek` VARBINARY(128) NOT NULL,
  `connected_at` TIMESTAMP NOT NULL,
  `last_seen_at` TIMESTAMP NULL,
  `last_inspected_at` TIMESTAMP NULL,
  `inspection_json` JSON NULL,
  `last_error` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_connected_project_owner` (`owner_id`,`project_id`),
  UNIQUE KEY `uq_connected_project_connection` (`connection_id`), KEY `idx_connected_project_status` (`owner_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
