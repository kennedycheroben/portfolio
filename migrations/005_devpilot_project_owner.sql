-- Customer-project-side authorization ownership and pairing failure rate limits.
ALTER TABLE `devpilot_connector_pairings` ADD COLUMN `owner_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `used_at`;
ALTER TABLE `devpilot_connector_connections` ADD COLUMN `owner_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `revoked_at`;
ALTER TABLE `devpilot_connector_connections` ADD KEY `idx_connector_owner_project` (`owner_id`,`project_id`,`status`);

CREATE TABLE `devpilot_connector_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_hash` BINARY(32) NOT NULL,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_connector_attempt_window` (`ip_hash`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
