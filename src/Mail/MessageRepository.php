<?php

namespace Barua\Mail;

use Barua\Database;

class MessageRepository
{
    /**
     * Newest non-archived messages. If $accountId is given, restrict to that account
     * (single-mailbox view); otherwise the unified inbox across all accounts.
     */
    public static function unifiedInbox(int $limit = 100, ?int $accountId = null): array
    {
        $where = "m.folder_role = 'inbox' AND m.is_archived = 0";
        $params = [];
        if ($accountId !== null) {
            $where .= ' AND m.account_id = ?';
            $params[] = $accountId;
        }
        $sql = 'SELECT m.*, a.label AS account_label, a.colour AS account_colour
                FROM messages m
                JOIN accounts a ON a.id = m.account_id
                WHERE ' . $where . '
                ORDER BY m.date_sent DESC
                LIMIT ' . (int) $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Unified Sent view from the synced IMAP Sent folders (folder_role='sent'), across all
     * accounts. Mapped so the inbox list/reader markup renders it — the "sender" slot shows
     * the recipient; the account colour stripe marks the sending account.
     */
    public static function sentMessages(int $limit = 100, ?int $accountId = null): array
    {
        $where = "m.folder_role = 'sent'";
        $params = [];
        if ($accountId !== null) {
            $where .= ' AND m.account_id = ?';
            $params[] = $accountId;
        }
        $sql = "SELECT m.*, a.label AS account_label, a.colour AS account_colour
                FROM messages m
                JOIN accounts a ON a.id = m.account_id
                WHERE $where
                ORDER BY m.date_sent DESC
                LIMIT " . (int) $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(function (array $r) {
            $recipient = $r['recipients'] !== '' ? $r['recipients'] : '(unknown recipient)';
            $r['sender_name'] = 'To: ' . $recipient;
            $r['sender_email'] = $r['recipients'];
            return $r;
        }, $rows);
    }

    /** Messages of one folder role (archive, trash, …), newest first, optionally scoped. */
    public static function roleMessages(string $role, int $limit = 100, ?int $accountId = null): array
    {
        $where = 'm.folder_role = ?';
        $params = [$role];
        if ($accountId !== null) {
            $where .= ' AND m.account_id = ?';
            $params[] = $accountId;
        }
        $sql = "SELECT m.*, a.label AS account_label, a.colour AS account_colour
                FROM messages m
                JOIN accounts a ON a.id = m.account_id
                WHERE $where
                ORDER BY m.date_sent DESC
                LIMIT " . (int) $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function roleCount(string $role, ?int $accountId = null): int
    {
        $where = 'folder_role = ?';
        $params = [$role];
        if ($accountId !== null) {
            $where .= ' AND account_id = ?';
            $params[] = $accountId;
        }
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM messages WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Smart-group view: inbox messages of one group_type (newsletter/notification/people). */
    /**
     * People = non-bulk mail from senders the user has WRITTEN TO (correspondents table,
     * harvested from Sent folders). Computed dynamically: replying to someone new makes all
     * their mail retroactively "People" — no reclassification pass needed.
     */
    /**
     * "Clean Inbox" — the daily-driver filtered view: everything except bulk mail that
     * already has a permanent home elsewhere (Newsletters/Notifications) and mail that's
     * already flagged as handled (Pinned has its own one-click group, no need to repeat it
     * here). Unclassified senders stay in — "not proven noise" wins until shown otherwise.
     */
    public static function cleanInboxMessages(int $limit = 100, ?int $accountId = null): array
    {
        $where = "m.folder_role = 'inbox' AND m.is_archived = 0 AND m.is_starred = 0
                  AND m.group_type NOT IN ('newsletter', 'notification')";
        $params = [];
        if ($accountId !== null) {
            $where .= ' AND m.account_id = ?';
            $params[] = $accountId;
        }
        $sql = "SELECT m.*, a.label AS account_label, a.colour AS account_colour
                FROM messages m
                JOIN accounts a ON a.id = m.account_id
                WHERE $where
                ORDER BY m.date_sent DESC
                LIMIT " . (int) $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function cleanInboxUnread(?int $accountId = null): int
    {
        $where = "folder_role = 'inbox' AND is_archived = 0 AND is_starred = 0 AND is_read = 0
                  AND group_type NOT IN ('newsletter', 'notification')";
        $params = [];
        if ($accountId !== null) {
            $where .= ' AND account_id = ?';
            $params[] = $accountId;
        }
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM messages WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Mail that actually carries a file. Spans inbox + archive like Pinned, not inbox
     * alone: this is a "where was that invoice?" tool, and the invoice is usually already
     * archived. Matches on the attachments table (excluding inline images) rather than the
     * has_attachments flag — that flag counts a newsletter's social icons as attachments.
     */
    private const REAL_ATTACHMENT_EXISTS =
        "EXISTS (SELECT 1 FROM attachments a WHERE a.message_id = %s
                 AND (a.disposition IS NULL OR a.disposition <> 'inline'))";

    public static function attachmentMessages(int $limit = 100, ?int $accountId = null): array
    {
        $where = "m.folder_role IN ('inbox','archive') AND m.is_archived = 0
                  AND " . sprintf(self::REAL_ATTACHMENT_EXISTS, 'm.id');
        $params = [];
        if ($accountId !== null) {
            $where .= ' AND m.account_id = ?';
            $params[] = $accountId;
        }
        $sql = "SELECT m.*, a.label AS account_label, a.colour AS account_colour
                FROM messages m
                JOIN accounts a ON a.id = m.account_id
                WHERE $where
                ORDER BY m.date_sent DESC
                LIMIT " . (int) $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Total, not unread — consistent with Pinned/Archive, where the count is the size. */
    public static function attachmentCount(?int $accountId = null): int
    {
        $where = "folder_role IN ('inbox','archive') AND is_archived = 0
                  AND " . sprintf(self::REAL_ATTACHMENT_EXISTS, 'messages.id');
        $params = [];
        if ($accountId !== null) {
            $where .= ' AND account_id = ?';
            $params[] = $accountId;
        }
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM messages WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function peopleMessages(int $limit = 100, ?int $accountId = null): array
    {
        // Either explicitly filed here by hand, or the computed rule: not bulk mail and
        // from someone the user has written to before.
        $where = "m.folder_role = 'inbox' AND m.is_archived = 0
                  AND (m.group_type = 'people'
                       OR (m.group_type = 'other'
                           AND LOWER(m.sender_email) IN (SELECT email FROM correspondents)))";
        $params = [];
        if ($accountId !== null) {
            $where .= ' AND m.account_id = ?';
            $params[] = $accountId;
        }
        $sql = "SELECT m.*, a.label AS account_label, a.colour AS account_colour
                FROM messages m
                JOIN accounts a ON a.id = m.account_id
                WHERE $where
                ORDER BY m.date_sent DESC
                LIMIT " . (int) $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function peopleUnread(?int $accountId = null): int
    {
        $where = "folder_role = 'inbox' AND is_archived = 0 AND is_read = 0
                  AND (group_type = 'people'
                       OR (group_type = 'other'
                           AND LOWER(sender_email) IN (SELECT email FROM correspondents)))";
        $params = [];
        if ($accountId !== null) {
            $where .= ' AND account_id = ?';
            $params[] = $accountId;
        }
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM messages WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function groupMessages(string $type, int $limit = 100, ?int $accountId = null): array
    {
        $where = "m.folder_role = 'inbox' AND m.group_type = ? AND m.is_archived = 0";
        $params = [$type];
        if ($accountId !== null) {
            $where .= ' AND m.account_id = ?';
            $params[] = $accountId;
        }
        $sql = "SELECT m.*, a.label AS account_label, a.colour AS account_colour
                FROM messages m
                JOIN accounts a ON a.id = m.account_id
                WHERE $where
                ORDER BY m.date_sent DESC
                LIMIT " . (int) $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Unread count for one smart group (sidebar badge). */
    public static function groupUnread(string $type, ?int $accountId = null): int
    {
        $where = "folder_role = 'inbox' AND group_type = ? AND is_read = 0 AND is_archived = 0";
        $params = [$type];
        if ($accountId !== null) {
            $where .= ' AND account_id = ?';
            $params[] = $accountId;
        }
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM messages WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Pinned = IMAP \Flagged (stored as is_starred). Spark-style naming — no stars in the UI.
     * Includes archived pins (Spark's pin list spans folders); trash pins stay out.
     */
    public static function pinnedMessages(int $limit = 100, ?int $accountId = null): array
    {
        $where = "m.folder_role IN ('inbox','archive') AND m.is_starred = 1 AND m.is_archived = 0";
        $params = [];
        if ($accountId !== null) {
            $where .= ' AND m.account_id = ?';
            $params[] = $accountId;
        }
        $sql = "SELECT m.*, a.label AS account_label, a.colour AS account_colour
                FROM messages m
                JOIN accounts a ON a.id = m.account_id
                WHERE $where
                ORDER BY m.date_sent DESC
                LIMIT " . (int) $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function pinnedCount(?int $accountId = null): int
    {
        $where = "folder_role IN ('inbox','archive') AND is_starred = 1 AND is_archived = 0";
        $params = [];
        if ($accountId !== null) {
            $where .= ' AND account_id = ?';
            $params[] = $accountId;
        }
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM messages WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function sentCount(?int $accountId = null): int
    {
        $where = "folder_role = 'sent'";
        $params = [];
        if ($accountId !== null) {
            $where .= ' AND account_id = ?';
            $params[] = $accountId;
        }
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM messages WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT m.*, a.label AS account_label, a.colour AS account_colour
             FROM messages m JOIN accounts a ON a.id = m.account_id
             WHERE m.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Accounts with their unread (non-archived) counts, for the sidebar. */
    public static function accountsWithUnread(): array
    {
        $sql = "SELECT a.id, a.label, a.colour, a.email,
                       COALESCE(SUM(CASE WHEN m.is_read = 0 AND m.is_archived = 0 AND m.folder_role = 'inbox' THEN 1 ELSE 0 END), 0) AS unread
                FROM accounts a
                LEFT JOIN messages m ON m.account_id = a.id
                GROUP BY a.id, a.label, a.colour, a.email, a.sort_order
                ORDER BY a.sort_order ASC, a.id ASC";
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function totalUnread(): int
    {
        $sql = "SELECT COUNT(*) FROM messages WHERE is_read = 0 AND is_archived = 0 AND folder_role = 'inbox'";
        return (int) Database::connection()->query($sql)->fetchColumn();
    }

    /** Configured display timezone (messages are stored in UTC). */
    private static function displayTz(): \DateTimeZone
    {
        return new \DateTimeZone(\Barua\Config::get('timezone', 'Europe/Berlin') ?: 'Europe/Berlin');
    }

    /** Parse a stored UTC datetime string into a DateTime in the display zone. */
    private static function toLocal(?string $utc): ?\DateTime
    {
        if (!$utc) {
            return null;
        }
        $dt = new \DateTime($utc, new \DateTimeZone('UTC'));
        $dt->setTimezone(self::displayTz());
        return $dt;
    }

    /** Human date-group label (Today / Yesterday / Last week / month name). */
    public static function dateGroup(?string $dateSent): string
    {
        $dt = self::toLocal($dateSent);
        if (!$dt) {
            return 'Earlier';
        }
        $today = (new \DateTime('now', self::displayTz()))->setTime(0, 0);
        $msgDay = (clone $dt)->setTime(0, 0);
        $diffDays = (int) $today->diff($msgDay)->format('%r%a');

        if ($diffDays === 0) {
            return 'Today';
        }
        if ($diffDays === -1) {
            return 'Yesterday';
        }
        if ($diffDays < 0 && $diffDays >= -7) {
            return 'Last week';
        }
        return $dt->format('F Y');
    }

    /** Short time label for the list (HH:MM today, else "M j"). */
    public static function timeLabel(?string $dateSent): string
    {
        $dt = self::toLocal($dateSent);
        if (!$dt) {
            return '';
        }
        $now = new \DateTime('now', self::displayTz());
        if ($dt->format('Y-m-d') === $now->format('Y-m-d')) {
            return $dt->format('H:i');
        }
        return $dt->format('M j');
    }

    /** Full date + 24h time for the reader header, e.g. "16 Jul 2026, 14:30". */
    public static function fullTimeLabel(?string $dateSent): string
    {
        $dt = self::toLocal($dateSent);
        return $dt ? $dt->format('j M Y, H:i') : '';
    }

    /**
     * Mime types safe to render inline in the browser ("Preview"). Deliberately narrow:
     * images render as pure pixels (no script execution), and PDFs get the browser's
     * sandboxed built-in viewer. SVG is excluded — it can carry <script> and executes when
     * navigated to directly. Everything else (HTML, text, Office docs, archives, ...) stays
     * download-only, since an attachment's content is attacker-controlled (the sender chose
     * it) and rendering it same-origin could read the session/CSRF token.
     * Enforced server-side in the /attachments/{id}?preview=1 route — never trust the
     * client's request alone.
     */
    private const PREVIEWABLE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/x-icon',
        'application/pdf',
    ];

    public static function isPreviewableMime(?string $mime): bool
    {
        return in_array(strtolower(trim((string) $mime)), self::PREVIEWABLE_MIMES, true);
    }

    /**
     * Batched attachment lookup for a set of messages — one query instead of one per row,
     * used to enrich both the server-rendered reader and the JS row map.
     * @return array<int, array<int, array{id:int, filename:string, mimeType:string, size:int}>>
     */
    public static function attachmentsForMessages(array $messageIds): array
    {
        $messageIds = array_values(array_unique(array_map('intval', $messageIds)));
        if (empty($messageIds)) {
            return [];
        }
        // Hide decorative inline images (signature logos, newsletter social icons) —
        // everything else shows, including a NULL disposition: better to show real content
        // a sender's client didn't label than to hide it on an ambiguous signal.
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT id, message_id, filename, mime_type, size_bytes FROM attachments
             WHERE message_id IN ($placeholders) AND (disposition IS NULL OR disposition <> 'inline')
             ORDER BY id"
        );
        $stmt->execute($messageIds);

        $byMessage = [];
        foreach ($stmt->fetchAll() as $r) {
            $byMessage[(int) $r['message_id']][] = [
                'id'          => (int) $r['id'],
                'filename'    => $r['filename'],
                'mimeType'    => $r['mime_type'],
                'size'        => (int) $r['size_bytes'],
                'previewable' => self::isPreviewableMime($r['mime_type']),
            ];
        }
        return $byMessage;
    }
}
