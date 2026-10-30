-- ========================================================================
-- PRODUCTION RECOVERY SCRIPT: xqtrqexj_portfolio_db
-- Target Engine: MySQL 8.0.41+
-- Safe, Idempotent, Non-Destructive Schema Reconciliation
-- ========================================================================

-- 1. Add status column to projects (Defaults to 'published')
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'status'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `status` ENUM(\'draft\', \'published\') NOT NULL DEFAULT \'published\' AFTER `featured`'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add composite index for status, featured, created_at
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'idx_projects_status_featured_created'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD INDEX `idx_projects_status_featured_created` (`status`, `featured`, `created_at`)'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Add base index for featured, created_at (Migration 001 reconciliation)
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND INDEX_NAME = 'idx_projects_featured_created'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD INDEX `idx_projects_featured_created` (`featured`, `created_at`)'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Add github_url column
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'github_url'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `github_url` VARCHAR(500) NULL AFTER `project_url`'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Add technologies column
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'technologies'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `technologies` VARCHAR(500) NULL AFTER `category`'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Add problem column
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'problem'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `problem` TEXT NULL AFTER `description`'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Add contribution column
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'contribution'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `contribution` TEXT NULL AFTER `problem`'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8. Add features column
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'features'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `features` TEXT NULL AFTER `contribution`'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 9. Add outcomes column
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'outcomes'
  ),
  'SELECT 1',
  'ALTER TABLE `projects` ADD COLUMN `outcomes` TEXT NULL AFTER `features`'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 10. Add contacts performance index (Migration 001 reconciliation)
SET @statement = IF(
  EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts' AND INDEX_NAME = 'idx_contacts_read_created'
  ),
  'SELECT 1',
  'ALTER TABLE `contacts` ADD INDEX `idx_contacts_read_created` (`is_read`, `created_at`)'
);
PREPARE stmt FROM @statement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
