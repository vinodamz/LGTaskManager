-- Migration 001: Configurable Kanban columns
-- Idempotent — safe to run multiple times.
-- Apply via phpMyAdmin → Import, or via /migrate.php in a browser.

CREATE TABLE IF NOT EXISTS task_columns (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(50)    NOT NULL,
    position      INT UNSIGNED   NOT NULL DEFAULT 0,
    color         VARCHAR(7)     NOT NULL DEFAULT '#EC407A',
    is_done       TINYINT(1)     NOT NULL DEFAULT 0,
    created_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_name (name),
    INDEX idx_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default columns matching the old status enum (idempotent via UNIQUE KEY).
INSERT IGNORE INTO task_columns (name, position, color, is_done) VALUES
    ('To do',       1, '#EC407A', 0),
    ('In progress', 2, '#F5B342', 0),
    ('Done',        3, '#5BA547', 1);

-- Add column_id + board_position to tasks. Wrapped in a stored procedure so
-- the migration is idempotent even though MySQL has no `ALTER TABLE IF NOT
-- EXISTS COLUMN` syntax.
DROP PROCEDURE IF EXISTS pr_lgtm_migrate_001;
DELIMITER //
CREATE PROCEDURE pr_lgtm_migrate_001()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'column_id'
    ) THEN
        ALTER TABLE tasks
            ADD COLUMN column_id      INT UNSIGNED NULL AFTER status,
            ADD COLUMN board_position INT UNSIGNED NOT NULL DEFAULT 0 AFTER column_id,
            ADD INDEX idx_col_pos (column_id, board_position);
    END IF;

    -- Backfill column_id from the old status column wherever still NULL.
    UPDATE tasks SET column_id = (
        SELECT id FROM task_columns WHERE name = 'To do' LIMIT 1
    ) WHERE column_id IS NULL AND status = 'todo';

    UPDATE tasks SET column_id = (
        SELECT id FROM task_columns WHERE name = 'In progress' LIMIT 1
    ) WHERE column_id IS NULL AND status = 'in_progress';

    UPDATE tasks SET column_id = (
        SELECT id FROM task_columns WHERE name = 'Done' LIMIT 1
    ) WHERE column_id IS NULL AND status = 'done';

    -- Add FK if not already there.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks'
          AND CONSTRAINT_NAME = 'fk_tasks_column'
    ) THEN
        ALTER TABLE tasks
            ADD CONSTRAINT fk_tasks_column FOREIGN KEY (column_id)
            REFERENCES task_columns(id) ON DELETE RESTRICT;
    END IF;
END //
DELIMITER ;
CALL pr_lgtm_migrate_001();
DROP PROCEDURE pr_lgtm_migrate_001;
