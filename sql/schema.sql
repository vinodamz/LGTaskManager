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

CREATE TABLE IF NOT EXISTS tasks (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title               VARCHAR(200) NOT NULL,
    description         TEXT         NULL,
    status              ENUM('todo','in_progress','done') NOT NULL DEFAULT 'todo',
    priority            ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    due_date            DATE         NULL,
    assigned_to_user_id INT UNSIGNED NULL,
    created_by_user_id  INT UNSIGNED NOT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to_user_id),
    INDEX idx_due (due_date),
    CONSTRAINT fk_tasks_assigned FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_creator  FOREIGN KEY (created_by_user_id)  REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- After running this schema, open /install.php in your browser
-- to create the first admin user with the PIN of your choice.
