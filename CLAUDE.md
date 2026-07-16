# Barua — Project Guide

## What Barua is

**Barua** (Swahili for "letter / mail") is a self-hosted, single-user **unified IMAP email
client** for the browser — a fast, lean alternative to Spark Mail (no Electron, no desktop app,
no team features). It merges up to ~8 IMAP accounts into one colour-coded inbox and is designed
to be deployed on plain PHP/MariaDB shared hosting.

Design ethos: **snappy and a bit smarter than the usual clients.** The local message cache makes
the UI instant; account colours are the only vivid elements; everything else is calm.

- **Live:** https://barua.weltenlaeufer.de
- **Repo:** https://github.com/weltenlaeufersteve-create/Barua (public)
- **Full task spec:** `barua-task.md`
- **Design reference:** `design_handoff_unified_inbox/` (v2 "Farbstreifen" is canonical; a
  React prototype for visual reference only — Barua is vanilla JS/PHP)

## Conventions

- **GUI language: English only.** (The working conversation with the maintainer is in German,
  but all product/UI copy is English.)
- **Code comments** match the surrounding style; keep them purposeful.
- **Deploy discipline:** always test locally first, then **ask permission before every
  `git push` / server deploy**. Local commits are fine without asking.
- **Secrets** never go in the repo or this file. Local dev secrets live in `config/config.php`
  (gitignored); production/deploy details live in `.deploy-secrets` (gitignored).

## Tech stack

- **PHP 8.1+** (local dev 8.3 via Laragon; production 8.4 — watch for 8.4 deprecations)
- **MariaDB / MySQL** (utf8mb4). Write portable SQL — prod is MariaDB.
- **Composer deps:** `webklex/php-imap` (IMAP — the native `imap_*` ext is deprecated/removed in
  8.4 and intentionally NOT used), `phpmailer/phpmailer` (SMTP send).
- **Frontend:** vanilla JS + `fetch()` against small JSON/redirect endpoints. No framework.
  CSS uses oklch design tokens. Fonts: Inter with system fallback (no external font request).

## Architecture

```
public/            web root
  index.php        front controller / manual router (all routes live here)
  css/             theme.css (8 palettes) + app.css + inbox.css
  favicon.png
src/               PSR-4 "Barua\" autoload
  Config.php       reads config/config.php (dotted keys)
  Database.php     PDO singleton (utf8mb4 DSN)
  Crypto.php       AES-256-GCM for stored IMAP/SMTP passwords
  Auth/Auth.php    session auth, CSRF tokens, IP-based login rate-limiting
  Accounts/        AccountRepository (CRUD + encrypt/decrypt), ColorPalette (20-colour set)
  Mail/            SyncService (IMAP → cache), MailSender (SMTP + APPEND to Sent),
                   FolderResolver (map folders → roles), MessageRepository (queries)
views/             login, dashboard (the 3-column app), settings_modal, compose, accounts
cron/sync.php      IMAP sync entry point: `php cron/sync.php [limit]`
config/            config.php (secrets, gitignored), config.php.example, schema.sql
bin/generate-key.php   prints a fresh 32-byte base64 app_key
```

**Request flow:** browser → `public/index.php` (router) → `src/` → MariaDB. IMAP/SMTP happen in
`cron/sync.php` and on send. The message list/reader render from the **local cache**, not live
IMAP, which is why it's fast.

### Data model (see `config/schema.sql`)
- `accounts` — per-account IMAP/SMTP config; passwords AES-256-GCM encrypted; auto-assigned colour.
- `messages` — the local cache. Key columns: `folder` + `folder_role` (`inbox`/`sent`/…),
  unique on `(account_id, folder, imap_uid)`. `FULLTEXT` index for search (not wired yet).
- `groups_` / `message_groups` — smart & custom filter groups (planned).
- `scheduled_send`, `drafts`, `attachments`, `users`, `login_attempts`.

### Folder / view model
Messages are synced from multiple IMAP folders and tagged with a normalized `folder_role`.
`FolderResolver` maps each account's real folders (e.g. `INBOX.Sent`) to roles by name. The UI
uses a **scope × folder** model:
- **Scope** = "all accounts" (unified) or a single account (sidebar selection persists across
  folders, and vice-versa).
- **Folder** = Inbox / Sent / (Archive, Drafts… planned).
- URLs: `/`, `/?account=<id>`, `/?view=sent`, `/?account=<id>&view=sent`.

### Themes
`public/css/theme.css` defines **8 palettes** via `[data-theme="<mode>-<tint>"]` where
mode ∈ {light, dark} and tint ∈ {neutral, sand, aubergine, steel}. Each theme also defines
`--accent` (+ `--accent-text`) derived from its own hue — buttons follow the theme, not a fixed
blue. Chosen in Settings → Appearance; stored per-browser in `localStorage['barua_theme']` and
applied pre-paint to avoid a flash. Account accent colours (the 20-colour palette) are
theme-independent.

### UI notes
- **Buttons** are all pill-shaped (text buttons); icon buttons stay rounded-square.
- **Account-context buttons** (compose Send, settings Edit/Remove/Save/Cancel) use
  `border = account colour, no solid fill, light theme fill`.
- **Composer** has a fullscreen mode (default, with a left account column) and a minimized
  bottom-right panel; the minimized mode switches sender via a dropdown (no fullscreen jump).

## Security decisions

- Passwords: **AES-256-GCM** (`iv:tag:ciphertext`, base64), key = base64-decoded `app_key`.
- **CSRF** token on every state-changing endpoint; **login rate-limiting** is IP-based (DB
  table, not session — a session lockout is trivially bypassed).
- App cloned **outside** the web root; docroot holds only symlinks so `config/`, `src/`,
  `vendor/`, `.git` are not web-reachable.
- Sent mail is **APPENDed to the IMAP Sent folder** (visible in every client), then cached.

## Local dev (Laragon, Windows)

- PHP: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` (needs `extension=zip` for
  webklex's deps — already enabled).
- MySQL 8.4.3 (`C:\laragon\bin\mysql\...`), started manually as `mysqld` (root / no password).
  DBs: `barua`, `barua_test`. Import schema: `mysql -u root barua < config/schema.sql`.
- Serve: `php -S 127.0.0.1:8000 -t public` → http://127.0.0.1:8000
- Local login: `admin` / `barua-dev` (in gitignored `config/config.php`; different from prod).
- Composer: `C:\laragon\bin\composer\composer.phar`.

## Deploy (git + SSH, mirrors LIPA Web 26)

Host `195.30.85.70` (Serverprofis), user `weltenla`, key `id_ed25519` (passphrase-free).
App at `~/barua_app` (outside webroot); docroot `~/barua.weltenlaeufer.de` holds symlinks
(`index.php`, `.htaccess`, `css`, `favicon.png` → `barua_app/public/*`). Prod DB `weltenla_barua`
(localhost). See `.deploy-secrets` for exact credentials.

**Update flow:** `cd ~/barua_app && git pull && composer install --no-dev` (symlinks persist).

**Schema changes:** there is no migration runner yet — mirror local `ALTER`s to the live DB by
hand, and **back up first** (`mysqldump`). Static files added to `public/` need their own docroot
symlink.

## Status — what's built (2026-07-16)

Working end-to-end, live with 3 real accounts:
- Auth (login/logout, CSRF, rate-limit), account CRUD + edit + live colour change.
- Multi-account IMAP sync (INBOX + Sent), MIME-decoded, UTC-stored / Berlin-displayed
  (DST-aware), self-healing upsert.
- Unified inbox + per-account filter; date grouping; reader pane.
- Compose / send (SMTP) with per-account signature; reply / forward with quoting + threading
  headers; sent mail APPENDed to IMAP Sent; unified/per-account Sent view.
- 8 themes (Appearance tab), theme-derived accents, pill buttons, sidebar folder icons,
  settings modal, favicon.

## Backlog / next up

1. **Archive flow** (move to `INBOX.Archive`, scope-aware like Sent — `FolderResolver` +
   `Message::move()` groundwork is in place).
2. **Read-sync** (`\Seen`) and **star** (`\Flagged`) mirrored to IMAP.
3. **HTML email rendering** with sanitization (currently plain-text only — XSS-safe by default).
4. **Draft autosave** + Drafts view.
5. Smart groups (People/Newsletters/Notifications/Starred) + custom filter groups.
6. Cross-account full-text search (FULLTEXT index exists).
7. Scheduled send (table exists; cron dispatcher planned).
8. Gravatar in avatars; move "Add account" into the settings modal; retire standalone
   `/accounts` page.
9. Eventually: a proper installer / DB-migration story.
