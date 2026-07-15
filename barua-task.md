# Barua — Claude Code Task Document

## Project Overview

Build **Barua** (Swahili: "letter / mail"), a self-hosted PHP web email client backed by MariaDB. It serves as a personal unified inbox for up to 8 IMAP accounts, replacing Spark Mail with a fast, browser-based alternative deployable on a standard PHP/MariaDB server (serverprofis shared hosting or VPS with SSH access).

No Electron, no desktop app, no team features. Just a snappy, well-designed personal mail client in the browser.

---

## Target Environment

- **Server:** Serverprofis (shared hosting or VPS) with SSH access
- **PHP:** 8.1+ preferred
- **Database:** MariaDB 10.6+
- **Required PHP extensions:** `php-mbstring`, `php-pdo`, `php-pdo_mysql`, `php-openssl`
- **Cron jobs:** available (for background sync and scheduled send) — confirm minimum interval with Serverprofis before relying on 1-minute scheduling (many shared plans enforce a 5-minute floor)
- **SMTP:** via PHPMailer for outgoing mail
- **No Docker required** — plain PHP deployment

> **Note:** `imap_open()` / the native `php-imap` extension is deprecated as of PHP 8.1 and removed entirely in PHP 8.4. Do not build on it. Use `webklex/php-imap` (pure PHP, IMAP over sockets) instead — see Dependencies section.

---

## Core Features (MVP)

### 1. Multi-Account IMAP Management
- Up to 8 IMAP accounts configurable via settings UI
- Each account assigned a **colour automatically at creation**, randomly picked (without repetition among active accounts) from a fixed 20-colour palette (5×4 grid) — not manually chosen from a small swatch set, since the account count is variable
- Account credentials stored encrypted in MariaDB (AES-256 via PHP `openssl_encrypt`)
- Supports SSL/TLS and STARTTLS connections
- All 8 accounts are on custom/own domains (no Gmail/Outlook) — plain IMAP/SMTP username+password auth is sufficient; no OAuth2 flow needed in v1

### 2. Colour-Coded Unified Inbox
- All accounts merged into a single inbox view
- Each message row shows a **colour strip/dot** indicating its account
- Account colour is consistent throughout the UI (list, thread view, compose)
- Account name + colour shown as a pill/badge

### 3. Threaded Conversations
- Messages grouped by thread using `Message-ID` / `In-Reply-To` / `References` headers
- Thread view shows all messages in a conversation collapsed/expandable
- Replying keeps the thread context visible

### 4. Smart Groups (Pinnable)
- Default groups auto-sorted by heuristic detection:
  - **People** — direct human senders (not bulk)
  - **Newsletters** — List-Unsubscribe header present
  - **Notifications** — automated/transactional
  - **Starred** — manually starred by user
- Groups displayed as pinned tabs/sidebar items
- User can pin/unpin and rename groups
- Groups work across all accounts

### 5. Custom Filter Groups (Rule-Based)

User-created groups with configurable conditions, pinnable in the sidebar alongside smart groups.

**Available filter fields:**
- `sender_email` — contains / equals / domain equals
- `sender_name` — contains
- `subject` — contains / starts with
- `has_attachments` — true / false
- `attachment_size_mb` — greater than / less than
- `account_id` — is / is not (specific account)
- `recipient` — To or CC contains
- `is_starred` — true / false
- `body_snippet` — contains (keyword in body)

**Condition logic:** AND / OR between rules (flat, no nesting needed for v1)

**Example groups a user might create:**
- "Invoices" → subject contains "invoice" OR "rechnung" AND has_attachments = true
- "Pina" → sender_email equals pina@example.com
- "Moyo Leads" → account_id is Moyo Reisen AND sender_email not contains "@known-newsletter.com"
- "Heavy Files" → has_attachments = true AND attachment_size_mb > 2

**Filter JSON schema** (stored in `groups.filter_json`):
```json
{
  "operator": "OR",
  "rules": [
    { "field": "subject", "op": "contains", "value": "invoice" },
    { "field": "subject", "op": "contains", "value": "rechnung" },
    {
      "operator": "AND",
      "rules": [
        { "field": "has_attachments", "op": "equals", "value": true },
        { "field": "account_id", "op": "equals", "value": 3 }
      ]
    }
  ]
}
```

**Implementation:**
- Filter rules evaluated at sync time — messages tagged with matching group IDs in a `message_groups` join table
- Re-evaluated on demand when rules are edited
- Filter builder UI: simple condition rows with field/operator/value dropdowns — no code required
- Groups show unread count badge in sidebar

### `message_groups` (join table)
```sql
CREATE TABLE message_groups (
  message_id BIGINT NOT NULL,
  group_id INT NOT NULL,
  PRIMARY KEY (message_id, group_id)
);
```

### 6. Read / Reply / Archive Flow
- Primary actions: **Read → Reply → Archive**
- Archive removes from inbox (moves to IMAP Archive folder or adds `\Archive` flag)
- No folder browser — folders are hidden; Archive is the exit
- Keyboard shortcuts: `R` reply, `E` archive, `S` star, `F` forward

### 7. Starred Emails
- Star toggles a local flag in MariaDB AND syncs IMAP `\Flagged` flag
- Starred group always accessible in sidebar

### 8. Scheduled Send
- Compose window has "Send Later" option with datetime picker
- Scheduled emails stored in MariaDB `scheduled_send` table
- Cron job (every minute) checks and dispatches due emails via PHPMailer
- Scheduled drafts visible/editable/cancellable in a "Scheduled" view

### 9. Cross-Account Search
- Full-text search across locally cached message subjects, senders, and body snippets
- MariaDB `FULLTEXT` index on the messages cache table
- Search results show account colour and origin

### 10. Compose
- Full compose with To / CC / BCC / Subject
- Reply-all, forward
- Choose sending account (from dropdown, colour-coded)
- HTML + plain text (simple rich text editor — Trix or Quill, lightweight)
- Attachments (upload + send)
- **Per-account signature** — configurable in account settings, auto-inserted on new compose (not on reply/forward quoted text) based on the selected sending account
- **Draft autosave** — compose content saved periodically (e.g. every few seconds of inactivity) to a drafts store so an accidental tab close/crash doesn't lose the message; drafts listed/resumable, similar to Gmail

### 11. Notifications
- **In-app only** — unread counters (sidebar groups, tab title, browser tab favicon badge if feasible)
- No browser Web Push / Service Worker in v1 (explicitly out of scope — user only needs to see new mail when the app is open)

---

## Architecture

### Folder Structure

```
barua/
├── public/              # Web root (index.php, assets)
│   ├── index.php
│   ├── css/
│   ├── js/
│   └── assets/
├── src/
│   ├── Auth/            # Login, session management
│   ├── Mail/            # IMAP sync, threading, parsing
│   ├── Accounts/        # Account CRUD, credential encryption
│   ├── Groups/          # Smart group detection logic
│   ├── Search/          # Full-text search queries
│   ├── Compose/         # Outgoing mail, scheduled send
│   └── Api/             # JSON API endpoints (for JS frontend calls)
├── cron/
│   ├── sync.php         # IMAP sync — run every 2 min
│   └── send_scheduled.php  # Scheduled send dispatcher — run every 1 min
├── config/
│   └── config.php       # DB credentials, encryption key, app settings
├── vendor/              # Composer dependencies
├── composer.json
└── README.md
```

### Request Flow

```
Browser → public/index.php (router) → src/Api/*.php → MariaDB
                                                    ↕
                                              cron/sync.php ← IMAP servers
```

The frontend is a **single-page feel** achieved with vanilla JS + fetch() calls to JSON API endpoints. No heavy framework. Fast, minimal JS.

---

## Database Schema

### `accounts`
```sql
CREATE TABLE accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  colour CHAR(7) NOT NULL DEFAULT '#4A90D9',
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
  is_active TINYINT(1) DEFAULT 1,
  last_synced_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### `messages`
```sql
CREATE TABLE messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
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
  is_read TINYINT(1) DEFAULT 0,
  is_starred TINYINT(1) DEFAULT 0,
  is_archived TINYINT(1) DEFAULT 0,
  group_type ENUM('people','newsletter','notification','other') DEFAULT 'other',
  has_attachments TINYINT(1) DEFAULT 0,
  raw_headers TEXT,
  synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_account_uid (account_id, imap_uid),
  FULLTEXT KEY ft_search (subject, sender_name, sender_email, body_snippet),
  INDEX idx_thread (thread_id),
  INDEX idx_account_date (account_id, date_sent),
  INDEX idx_archived (is_archived, date_sent)
);
```

### `groups`
```sql
CREATE TABLE groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('smart','manual') DEFAULT 'smart',
  filter_json TEXT,
  is_pinned TINYINT(1) DEFAULT 0,
  sort_order INT DEFAULT 0,
  icon VARCHAR(50),
  colour CHAR(7)
);
```

### `scheduled_send`
```sql
CREATE TABLE scheduled_send (
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
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### `attachments`
```sql
CREATE TABLE attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id BIGINT NOT NULL,
  filename VARCHAR(500),
  mime_type VARCHAR(255),
  size_bytes INT,
  storage_path VARCHAR(1000),
  content_id VARCHAR(500)
);
```
`storage_path` is relative to a dedicated attachments directory **outside the web root** (not under `public/`), so files aren't directly downloadable by URL — served only through an authenticated API endpoint. Needs writable permissions set up during deployment (documented in the README/setup guide) and a cleanup strategy for attachments belonging to deleted/archived-and-expired messages.

---

## Key Implementation Notes

### IMAP Sync (`cron/sync.php`)
- Use `webklex/php-imap` to connect to each active account (native `imap_open()` is deprecated/removed as of PHP 8.4 — do not use it)
- Fetch only new UIDs since `last_synced_at` via a `SINCE`/UID search
- Parse headers and body structure via the library's message API
- Wrap each account's connection in its own try/catch with a connect timeout — one hanging/unreachable account must not block the sync of the other 7. Log and skip on failure, retry next run.
- Detect group type heuristically:
  - `List-Unsubscribe` header present → `newsletter`
  - `X-Mailer` contains known automation tools → `notification`
  - Precedence: bulk/list → `newsletter`
  - Otherwise → `people`
- Build `thread_id` by normalising `Message-ID`/`In-Reply-To`/`References` chains. **Fallback when no chain matches** (common with newsletters/notifications that omit or vary these headers): normalise the subject (strip `Re:`/`Fwd:` prefixes, lowercase, trim) and group with same sender + same normalised subject within a rolling time window (e.g. 30 days) as a synthetic thread.
- Store body snippet as first 300 chars of plain text
- **Body caching policy** (storage budget guard for 8 accounts × 10k+ messages): cache `body_html`/`body_plain` in full only for messages within a configurable recent window (e.g. last 90 days) or while unread/starred. Older read messages keep headers + snippet locally and fetch full body from IMAP on demand when opened. Prevents the local cache from ballooning on shared hosting with limited DB storage.
- Update `last_synced_at` on success

### Encryption
- Use a server-side `APP_KEY` in `config.php` (never in DB)
- `openssl_encrypt($password, 'AES-256-GCM', APP_KEY, 0, $iv, $tag)` — GCM instead of CBC, since it provides a built-in authentication tag and prevents undetected ciphertext tampering (CBC alone has no integrity check)
- Store as `base64(iv):base64(tag):base64(ciphertext)`

### Auth
- Single-user app — one master login (username + bcrypt password) stored in `config.php` or a `users` table
- PHP session-based authentication
- HTTPS enforced (server-level, Caddy or Apache)
- Rate-limit login attempts (e.g. lockout/backoff after 5 failed tries per IP) to prevent brute-forcing the master password
- CSRF protection on all state-changing `src/Api/*.php` endpoints (token issued to the session, verified on POST/PUT/DELETE) — the JSON API is otherwise reachable by any page the browser has open while the session cookie is valid

### Frontend Design Direction
- **Dark UI by default** — deep charcoal background (`#1A1C1E`), not pure black.
- **Theme selection lives in Settings only** — no theme switcher in the main GUI (sidebar/header). Choices: Dark, Light, plus a handful of dark pastel variants (working names: Aubergine, Sand, Steel Blue) as alternative dark palettes, not just accent swaps — same structural layout/tokens as the default Dark theme, different base hues. Theme is per-browser (`localStorage`), not a server-side setting — same pattern as LIPA Web 26's theme toggle.
- Account colours are the only vivid elements — everything else is neutral
- Message list: compact rows, colour strip on left edge (4px), sender bold, subject regular, snippet muted
- Thread view: card-style bubbles per message, account colour as subtle left border
- Sidebar: Groups as icon+label rows, pinned at top, account list below with colour dots
- Typography: system font stack (`-apple-system, 'Segoe UI', sans-serif`) — no web font loading delay
- Compose: slides up as a panel from bottom-right (like Spark/Gmail), doesn't replace the view
- Fully responsive — usable on phone

### Dependencies (Composer)
```json
{
  "require": {
    "phpmailer/phpmailer": "^6.8",
    "webklex/php-imap": "^5.5",
    "php": ">=8.1"
  }
}
```
`webklex/php-imap` replaces the deprecated native `imap_*` extension for IMAP connectivity/parsing.

---

## Out of Scope (v1)

- Team / shared inbox features
- Push/real-time sync (cron polling is fine)
- Calendar / contacts integration
- End-to-end encryption
- Mobile native app
- Folder browser (deliberately excluded)
- Multiple user accounts
- **Snooze** (deferred — a design handoff mocked up a "Snoozed" folder, but it's not part of v1; revisit as a later addition alongside Read/Reply/Archive)

---

## Deliverables

1. Full working PHP application deployable by uploading to server + running `composer install` + importing SQL schema
2. Cron job instructions (`crontab -e` entries)
3. `config.php.example` with all required settings documented
4. Setup / README with step-by-step deployment guide
5. Functional UI covering all MVP features above

---

## Success Criteria

- Can add 8 IMAP accounts with different colours
- Unified inbox loads within 1 second (from cache)
- Threads group correctly
- Archive removes from inbox permanently
- Scheduled send fires within one cron cycle of due time (1 minute if the host allows it, otherwise the host's minimum interval, e.g. 5 minutes)
- Search returns results across all accounts
- Works on mobile browser
- No crashes on accounts with 10,000+ cached messages
