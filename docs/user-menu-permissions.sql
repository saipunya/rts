-- Optional manual setup. The application also creates this table automatically.
-- This does not modify user accounts or collection records.
CREATE TABLE IF NOT EXISTS tbl_user_menu_permissions (
    user_id INT NOT NULL PRIMARY KEY,
    permissions TEXT NOT NULL,
    updated_by INT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
