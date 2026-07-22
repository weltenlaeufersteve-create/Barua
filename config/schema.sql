CREATE TABLE IF NOT EXISTS accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  colour CHAR(7) NOT NULL DEFAULT '#4A90D9',
  sort_order INT NOT NULL DEFAULT 0,
  imap_host VARCHAR(255) NOT NULL,
  imap_port SMALLINT NOT NULL DEFAULT 993,
  imap_encryption ENUM('ssl','tls','none') DEFAULT 'ssl',
  imap_username VARCHAR(255) NOT NULL,
  imap_password_enc TEXT NOT NULL,
  smtp_host VARCHAR(255) NOT NULL,
  smtp_port SMALLINT NOT NULL DEFAULT 587,
  smtp_encryption ENUM('ssl','tls','none') DEFAULT 'tls',
  smtp_username VARCHAR(255) NOT NULL,
  smtp_password_enc TEXT NOT NULL,
  signature TEXT,
  signature_id INT NULL,
  is_active TINYINT(1) DEFAULT 1,
  last_synced_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reusable signatures, assigned to accounts via accounts.signature_id.
-- format 'plain' → inserted verbatim into the plain-text body; 'html' → sent as HTML.
CREATE TABLE IF NOT EXISTS signatures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  format ENUM('plain','html') NOT NULL DEFAULT 'plain',
  body MEDIUMTEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  folder VARCHAR(255) NOT NULL DEFAULT 'INBOX',
  folder_role VARCHAR(20) NOT NULL DEFAULT 'inbox',
  imap_uid INT NOT NULL,
  message_id VARCHAR(500),
  in_reply_to VARCHAR(500),
  thread_id VARCHAR(500),
  subject VARCHAR(1000),
  sender_name VARCHAR(255),
  sender_email VARCHAR(255),
  recipients TEXT,
  date_sent DATETIME,
  body_snippet TEXT,
  body_html MEDIUMTEXT,
  body_plain MEDIUMTEXT,
  body_cached TINYINT(1) DEFAULT 1,
  is_read TINYINT(1) DEFAULT 0,
  is_starred TINYINT(1) DEFAULT 0,
  is_archived TINYINT(1) DEFAULT 0,
  group_type ENUM('people','newsletter','notification','other') DEFAULT 'other',
  -- 1 = the user picked this group by hand; the classifier must not overwrite it
  group_locked TINYINT(1) NOT NULL DEFAULT 0,
  has_attachments TINYINT(1) DEFAULT 0,
  raw_headers TEXT,
  synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_account_folder_uid (account_id, folder, imap_uid),
  FULLTEXT KEY ft_search (subject, sender_name, sender_email, body_snippet),
  INDEX idx_thread (thread_id),
  INDEX idx_account_date (account_id, date_sent),
  INDEX idx_archived (is_archived, date_sent),
  INDEX idx_role_date (folder_role, date_sent),
  CONSTRAINT fk_messages_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS groups_ (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('smart','manual') DEFAULT 'smart',
  filter_json TEXT,
  is_pinned TINYINT(1) DEFAULT 0,
  sort_order INT DEFAULT 0,
  icon VARCHAR(50),
  colour CHAR(7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_groups (
  message_id BIGINT NOT NULL,
  group_id INT NOT NULL,
  PRIMARY KEY (message_id, group_id),
  CONSTRAINT fk_mg_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_mg_group FOREIGN KEY (group_id) REFERENCES groups_(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scheduled_send (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  to_addresses TEXT NOT NULL,
  cc_addresses TEXT,
  bcc_addresses TEXT,
  subject VARCHAR(1000),
  body_html MEDIUMTEXT,
  body_plain TEXT,
  attachments_json TEXT,
  send_at DATETIME NOT NULL,
  status ENUM('pending','sent','failed') DEFAULT 'pending',
  error_message TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_scheduled_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS drafts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  to_addresses TEXT,
  cc_addresses TEXT,
  bcc_addresses TEXT,
  subject VARCHAR(1000),
  body_html MEDIUMTEXT,
  body_plain TEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_drafts_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Outbound attachments a draft carries; files live in storage/outbox/{draft_id}/.
-- ON DELETE CASCADE clears the rows when a draft is sent or discarded (files are removed
-- explicitly in DraftRepository::delete, since cascade doesn't touch the filesystem).
CREATE TABLE IF NOT EXISTS draft_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  draft_id INT NOT NULL,
  filename VARCHAR(500),
  mime_type VARCHAR(255),
  size_bytes INT,
  storage_path VARCHAR(1000),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_da_draft FOREIGN KEY (draft_id) REFERENCES drafts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id BIGINT NOT NULL,
  filename VARCHAR(500),
  mime_type VARCHAR(255),
  size_bytes INT,
  storage_path VARCHAR(1000),
  content_id VARCHAR(500),
  -- MIME disposition ('attachment' vs 'inline'). Distinguishes a real attachment (PDF
  -- invoice, forwarded file) from a decorative inline image (signature logo, newsletter
  -- social icons) — content_id alone doesn't: webklex sets it on both.
  disposition VARCHAR(20) NULL,
  CONSTRAINT fk_attachments_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- People you have written to (fed by Sent-folder sync + the send flow).
-- Powers the "People" smart group; later also composer autocomplete.
CREATE TABLE IF NOT EXISTS correspondents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  name VARCHAR(255) DEFAULT '',
  last_used DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  theme VARCHAR(20) DEFAULT 'dark',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
