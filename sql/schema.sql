-- LG Task Manager schema
-- Run once in cPanel → phpMyAdmin after creating the database.

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    pin_hash      VARCHAR(255)  NOT NULL,                       -- bcrypt of the PIN
    role          ENUM('staff','admin') NOT NULL DEFAULT 'staff',
    active        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

INSERT IGNORE INTO task_columns (name, position, color, is_done) VALUES
    ('To do',       1, '#EC407A', 0),
    ('In progress', 2, '#F5B342', 0),
    ('Done',        3, '#5BA547', 1);

CREATE TABLE IF NOT EXISTS tasks (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title               VARCHAR(200) NOT NULL,
    description         TEXT         NULL,
    status              ENUM('todo','in_progress','done') NOT NULL DEFAULT 'todo',
    column_id           INT UNSIGNED NULL,
    board_position      INT UNSIGNED NOT NULL DEFAULT 0,
    priority            ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    due_date            DATE         NULL,
    assigned_to_user_id INT UNSIGNED NULL,
    created_by_user_id  INT UNSIGNED NOT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_col_pos (column_id, board_position),
    INDEX idx_assigned (assigned_to_user_id),
    INDEX idx_due (due_date),
    CONSTRAINT fk_tasks_column   FOREIGN KEY (column_id)           REFERENCES task_columns(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tasks_assigned FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_creator  FOREIGN KEY (created_by_user_id)  REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- After running this schema, open /install.php in your browser
-- to create the first admin user with the PIN of your choice.
