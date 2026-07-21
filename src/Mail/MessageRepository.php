<?php

namespace Barua\Mail;

use Barua\Database;

class MessageRepository
{
    /** Mail carrying a real (non-inline) attachment. %s is the message-id expression. */
    private const REAL_ATTACHMENT_EXISTS =
        "EXISTS (SELECT 1 FROM attachments a WHERE a.message_id = %s
                 AND (a.disposition IS NULL OR a.disposition <> 'inline'))";

    /**
     * The inbox view model, in one composable query: a base list (inbox, optionally scoped
     * to one account) narrowed by at most one TYPE, then narrowed further by any number of
     * independent FILTER toggles. Each axis only ever appends another AND, so a filter can
     * never widen the list beyond what the type already narrowed it to.
     *
     *   type   — 'clean' | 'people' | 'newsletter' | 'notification' | '' (no narrowing)
     *   pinned — only \Flagged mail
     *   attach — only mail carrying a real (non-inline) attachment
     *
     * Everything is scoped to folder_role='inbox' on purpose: filters apply to the account
     * inboxes, not Sent/Archive/Trash/Spam/Drafts. One scope for every axis is what keeps
     * the combinations explainable.
     *
     * @return array{0: string, 1: array} SQL predicate (alias `m`) + bound params
     */
    private static function inboxPredicate(string $type, bool $pinned, bool $attach, ?int $accountId): array
    {
        $where = "m.folder_role = 'inbox' AND m.is_archived = 0";
        $params = [];

        switch ($type) {
            case 'clean':
                // Bulk mail has a permanent home of its own, so it leaves the daily list.
                // Pinned is NOT excluded (it used to be): now that Pinned is an independent
                // toggle, excluding it here would contradict "Clean Inbox + Pinned on".
                $where .= " AND m.group_type NOT IN ('newsletter', 'notification')";
                break;
            case 'people':
                $where .= " AND (m.group_type = 'people'
                                 OR (m.group_type = 'other'
                                     AND LOWER(m.sender_email) IN (SELECT email FROM correspondents)))";
                break;
            case 'newsletter':
            case 'notification':
                $where .= ' AND m.group_type = ?';
                $params[] = $type;
                break;
        }

        if ($pinned) {
            $where .= ' AND m.is_starred = 1';
        }
        if ($attach) {
            $where .= ' AND ' . sprintf(self::REAL_ATTACHMENT_EXISTS, 'm.id');
        }
        if ($accountId !== null) {
            $where .= ' AND m.account_id = ?';
            $params[] = $accountId;
        }

        return [$where, $params];
    }

    public static function inboxMessages(string $type = '', bool $pinned = false, bool $attach = false, int $limit = 100, ?int $accountId = null): array
    {
        [$where, $params] = self::inboxPredicate($type, $pinned, $attach, $accountId);
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

    /**
     * Unread count for a type, honouring the active filter toggles — so a sidebar badge
     * always answers "how many would I get if I clicked this", never a number from some
     * other combination. Same predicate as the list, so the two can't disagree.
     */
    public static function inboxUnread(string $type = '', bool $pinned = false, bool $attach = false, ?int $accountId = null): int
    {
        [$where, $params] = self::inboxPredicate($type, $pinned, $attach, $accountId);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM messages m WHERE $where AND m.is_read = 0"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
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
     * The other messages of an opened mail's conversation, for the reader stack — the whole
     * exchange in one place (incoming AND your own Sent replies + archived mail), so you never
     * have to jump to Sent. Scoped to the same account (a conversation lives in one mailbox);
     * trash/spam/drafts are excluded as noise. Newest-first; the reader reverses for the
     * chronological display option. The opened message itself is excluded.
     */
    public static function threadMessages(int $messageId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT thread_id, account_id FROM messages WHERE id = ?');
        $stmt->execute([$messageId]);
        $me = $stmt->fetch();
        if (!$me || ($me['thread_id'] ?? '') === '') {
            return [];
        }

        $stmt = $db->prepare(
            "SELECT id, folder_role, sender_name, sender_email, date_sent, body_plain, body_html
             FROM messages
             WHERE thread_id = ? AND account_id = ? AND id <> ?
               AND folder_role IN ('inbox', 'sent', 'archive')
             ORDER BY date_sent DESC"
        );
        $stmt->execute([$me['thread_id'], (int) $me['account_id'], $messageId]);

        return array_map(function (array $r): array {
            $body = trim((string) ($r['body_plain'] ?? ''));
            if ($body === '') {
                $body = HtmlMailRenderer::toText((string) ($r['body_html'] ?? ''));
            }
            $body = trim(preg_replace('/[ \t]+/', ' ', $body));
            return [
                'id'      => (int) $r['id'],
                'folder'  => $r['folder_role'],                 // 'sent'/'archive' get a badge
                'sender'  => ($r['sender_name'] ?? '') !== '' ? $r['sender_name'] : $r['sender_email'],
                'time'    => self::fullTimeLabel($r['date_sent']),
                'snippet' => mb_substr(preg_replace('/\s+/', ' ', $body), 0, 140),
                'body'    => $body,
            ];
        }, $stmt->fetchAll());
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
