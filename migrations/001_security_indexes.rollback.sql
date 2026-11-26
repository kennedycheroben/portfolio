-- Review and run manually only when rolling back migration 001.
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'idx_projects_featured_created'
  ),
  'ALTER TABLE `projects` DROP INDEX `idx_projects_featured_created`',
  'SELECT 1'
);
PREPARE rollback_statement FROM @statement;
EXECUTE rollback_statement;
DEALLOCATE PREPARE rollback_statement;

SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' AND INDEX_NAME = 'idx_contacts_read_created'
  ),
  'ALTER TABLE `contacts` DROP INDEX `idx_contacts_read_created`',
  'SELECT 1'
);
PREPARE rollback_statement FROM @statement;
EXECUTE rollback_statement;
DEALLOCATE PREPARE rollback_statement;
