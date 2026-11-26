DROP TABLE IF EXISTS `devpilot_connected_projects`;
DROP TABLE IF EXISTS `devpilot_connector_attempts`;
ALTER TABLE `devpilot_connector_connections` DROP KEY `idx_connector_owner_project`;
ALTER TABLE `devpilot_connector_connections` DROP COLUMN `owner_id`;
ALTER TABLE `devpilot_connector_pairings` DROP COLUMN `owner_id`;
