-- Migration 002 Rollback
ALTER TABLE `projects` DROP COLUMN IF EXISTS `outcomes`;
ALTER TABLE `projects` DROP COLUMN IF EXISTS `features`;
ALTER TABLE `projects` DROP COLUMN IF EXISTS `contribution`;
ALTER TABLE `projects` DROP COLUMN IF EXISTS `problem`;
ALTER TABLE `projects` DROP COLUMN IF EXISTS `technologies`;
ALTER TABLE `projects` DROP COLUMN IF EXISTS `github_url`;
ALTER TABLE `projects` DROP INDEX IF EXISTS `idx_projects_status_featured_created`;
ALTER TABLE `projects` DROP COLUMN IF EXISTS `status`;
