-- Review and run manually against a backup. This migration is idempotent and
-- avoids stored procedures so it remains compatible with upgraded MariaDB data directories.
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'idx_projects_featured_created'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD INDEX `idx_projects_featured_created` (`featured`, `created_at`)'
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' AND INDEX_NAME = 'idx_contacts_read_created'
  ),
  'SELECT 1',
  'ALTER TABLE `contacts` ADD INDEX `idx_contacts_read_created` (`is_read`, `created_at`)'
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
