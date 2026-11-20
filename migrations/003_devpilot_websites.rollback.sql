-- Rollback only after an explicit data-retention review and verified backup.
-- This removes DevPilot website registry/audit data. It does NOT erase copies
-- held in database snapshots, exports, or backups. Preserve the vault key until
-- all encrypted backup copies expire or are separately retired.
DROP TABLE IF EXISTS `devpilot_website_credentials`;
DROP TABLE IF EXISTS `devpilot_website_scopes`;
DROP TABLE IF EXISTS `devpilot_security_audit`;
DROP TABLE IF EXISTS `devpilot_websites`;
