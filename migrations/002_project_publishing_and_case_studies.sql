-- Migration 002: Project Publishing Status and Case Study Detail Fields
-- Safe and backward-compatible for existing database records.

SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'status'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `status` ENUM(\'draft\', \'published\') NOT NULL DEFAULT \'published\' AFTER `featured`'
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'idx_projects_status_featured_created'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD INDEX `idx_projects_status_featured_created` (`status`, `featured`, `created_at`)'
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'github_url'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `github_url` VARCHAR(500) NULL AFTER `project_url`'
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'technologies'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `technologies` VARCHAR(500) NULL AFTER `category`'
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'problem'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `problem` TEXT NULL AFTER `description`'
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'contribution'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `contribution` TEXT NULL AFTER `problem`'
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'features'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `features` TEXT NULL AFTER `contribution`'
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'outcomes'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `outcomes` TEXT NULL AFTER `features`'
);
PREPARE migration_statement FROM @statement;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
