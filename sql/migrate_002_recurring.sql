-- Migration 002: Recurring task templates
-- Idempotent. Apply via /migrate.php (admin login) or phpMyAdmin → Import.

CREATE TABLE IF NOT EXISTS task_recurrences (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title                VARCHAR(200)   NOT NULL,
    description          TEXT           NULL,
    priority             ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    column_id            INT UNSIGNED   NULL,
    assigned_to_user_id  INT UNSIGNED   NULL,
    frequency            ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
    -- days_mask is a 7-bit bitmask of weekdays. bit 0 = Sunday, bit 6 = Saturday.
    --   Mon–Fri  = 62  (0b0111110)
    --   Weekends = 65  (0b1000001)
    --   All days = 127 (0b1111111)
    days_mask            TINYINT UNSIGNED NOT NULL DEFAULT 127,
    day_of_month         TINYINT UNSIGNED NULL,
    start_date           DATE           NOT NULL,
    end_date             DATE           NULL,
    is_active            TINYINT(1)     NOT NULL DEFAULT 1,
    created_by_user_id   INT UNSIGNED   NOT NULL,
    created_at           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active, start_date),
    CONSTRAINT fk_rec_column   FOREIGN KEY (column_id)           REFERENCES task_columns(id) ON DELETE SET NULL,
    CONSTRAINT fk_rec_assignee FOREIGN KEY (assigned_to_user_id) REFERENCES users(id)        ON DELETE SET NULL,
    CONSTRAINT fk_rec_creator  FOREIGN KEY (created_by_user_id)  REFERENCES users(id)        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS pr_lgtm_migrate_002;
DELIMITER //
CREATE PROCEDURE pr_lgtm_migrate_002()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'recurrence_id'
    ) THEN
        ALTER TABLE tasks
            ADD COLUMN recurrence_id INT UNSIGNED NULL AFTER created_by_user_id,
            ADD COLUMN instance_date DATE         NULL AFTER recurrence_id,
            ADD INDEX idx_instance_date (instance_date),
            ADD UNIQUE KEY uq_recurrence_date (recurrence_id, instance_date),
            ADD CONSTRAINT fk_tasks_recurrence FOREIGN KEY (recurrence_id)
                REFERENCES task_recurrences(id) ON DELETE SET NULL;
    END IF;
END //
DELIMITER ;
CALL pr_lgtm_migrate_002();
DROP PROCEDURE pr_lgtm_migrate_002;
