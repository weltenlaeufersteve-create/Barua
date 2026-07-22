<?php
// Idempotent migration for outbound (compose) attachments. Safe to run more than once.
// Creates the draft_attachments table. Run: php bin/migrate-draft-attachments.php
require __DIR__ . '/../vendor/autoload.php';

use Barua\Database;

Database::connection()->exec(
    "CREATE TABLE IF NOT EXISTS draft_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        draft_id INT NOT NULL,
        filename VARCHAR(500),
        mime_type VARCHAR(255),
        size_bytes INT,
        storage_path VARCHAR(1000),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_da_draft FOREIGN KEY (draft_id) REFERENCES drafts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
echo "draft_attachments table ready\n";
