-- DevPilot project connector: pairing codes are one-time; access tokens are SHA-256 hashes only.
CREATE TABLE IF NOT EXISTS `devpilot_connector_pairings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `code_hash` BINARY(32) NOT NULL, `project_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `project_name` VARCHAR(160) NOT NULL, `origin` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, `scopes_json` JSON NOT NULL,
  `expires_at` TIMESTAMP NOT NULL, `used_at` TIMESTAMP NULL DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_connector_pairing_hash` (`code_hash`), KEY `idx_connector_pairing_expiry` (`expires_at`,`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `devpilot_connector_connections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `connection_id` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, `project_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `project_name` VARCHAR(160) NOT NULL, `origin` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, `scopes_json` JSON NOT NULL, `token_hash` BINARY(32) NOT NULL,
  `status` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active', `connected_at` TIMESTAMP NOT NULL, `last_seen_at` TIMESTAMP NULL,
  `expires_at` TIMESTAMP NOT NULL, `revoked_at` TIMESTAMP NULL DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_connector_connection_id` (`connection_id`), UNIQUE KEY `uq_connector_token_hash` (`token_hash`), KEY `idx_connector_project` (`project_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
