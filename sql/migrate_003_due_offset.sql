-- Migration 003: User-configurable due-date offset on recurring task templates.
-- Each instance's due_date = instance_date + due_offset_days.
-- Idempotent. Apply via /migrate.php or phpMyAdmin → Import.

DROP PROCEDURE IF EXISTS pr_lgtm_migrate_003;
DELIMITER //
CREATE PROCEDURE pr_lgtm_migrate_003()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'task_recurrences'
          AND COLUMN_NAME = 'due_offset_days'
    ) THEN
        ALTER TABLE task_recurrences
            ADD COLUMN due_offset_days INT NOT NULL DEFAULT 0 AFTER day_of_month;
    END IF;
END //
DELIMITER ;
CALL pr_lgtm_migrate_003();
DROP PROCEDURE pr_lgtm_migrate_003;
