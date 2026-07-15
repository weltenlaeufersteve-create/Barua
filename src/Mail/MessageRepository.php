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
        $where = 'm.is_archived = 0';
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
        $sql = 'SELECT a.id, a.label, a.colour, a.email,
                       COALESCE(SUM(CASE WHEN m.is_read = 0 AND m.is_archived = 0 THEN 1 ELSE 0 END), 0) AS unread
                FROM accounts a
                LEFT JOIN messages m ON m.account_id = a.id
                GROUP BY a.id, a.label, a.colour, a.email
                ORDER BY a.created_at ASC';
        return Database::connection()->query($sql)->fetchAll();
    }

    public static function totalUnread(): int
    {
        $sql = 'SELECT COUNT(*) FROM messages WHERE is_read = 0 AND is_archived = 0';
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
}
