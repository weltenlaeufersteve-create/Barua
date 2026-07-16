<?php

namespace Barua\Mail;

use Barua\Database;

class DraftRepository
{
    /** Insert or update a draft. Returns the draft id. */
    public static function save(?int $id, int $accountId, array $data): int
    {
        $db = Database::connection();
        if ($id !== null && self::find($id)) {
            $db->prepare(
                'UPDATE drafts SET account_id = ?, to_addresses = ?, cc_addresses = ?, bcc_addresses = ?,
                        subject = ?, body_plain = ?, updated_at = ? WHERE id = ?'
            )->execute([
                $accountId,
                $data['to'] ?? '',
                $data['cc'] ?? '',
                $data['bcc'] ?? '',
                $data['subject'] ?? '',
                $data['body_plain'] ?? '',
                gmdate('Y-m-d H:i:s'), // UTC, like every stored timestamp
                $id,
            ]);
            return $id;
        }

        $db->prepare(
            'INSERT INTO drafts (account_id, to_addresses, cc_addresses, bcc_addresses, subject, body_plain, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $accountId,
            $data['to'] ?? '',
            $data['cc'] ?? '',
            $data['bcc'] ?? '',
            $data['subject'] ?? '',
            $data['body_plain'] ?? '',
            gmdate('Y-m-d H:i:s'),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM drafts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM drafts WHERE id = ?')->execute([$id]);
    }

    /** Drafts newest first, mapped into the mail-list row shape, optionally scoped. */
    public static function forDisplay(?int $accountId = null): array
    {
        $where = '1=1';
        $params = [];
        if ($accountId !== null) {
            $where = 'd.account_id = ?';
            $params[] = $accountId;
        }
        $sql = "SELECT d.*, a.label AS account_label, a.colour AS account_colour
                FROM drafts d
                JOIN accounts a ON a.id = d.account_id
                WHERE $where
                ORDER BY d.updated_at DESC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return array_map(function (array $d) {
            $recipient = trim($d['to_addresses'] ?? '');
            return [
                'id'             => (int) $d['id'],
                'account_id'     => (int) $d['account_id'],
                'account_label'  => $d['account_label'],
                'account_colour' => $d['account_colour'],
                'sender_name'    => 'Draft — ' . ($recipient !== '' ? 'To: ' . $recipient : '(no recipient)'),
                'sender_email'   => $recipient,
                'subject'        => $d['subject'] ?? '',
                'body_snippet'   => mb_substr(trim(preg_replace('/\s+/', ' ', $d['body_plain'] ?? '')), 0, 300),
                'body_plain'     => $d['body_plain'] ?? '',
                'body_html'      => '',
                'to_addresses'   => $d['to_addresses'] ?? '',
                'cc_addresses'   => $d['cc_addresses'] ?? '',
                'bcc_addresses'  => $d['bcc_addresses'] ?? '',
                'date_sent'      => $d['updated_at'],
                'message_id'     => '',
                'is_read'        => 1,
                'is_starred'     => 0,
                'is_draft'       => true,
            ];
        }, $stmt->fetchAll());
    }

    public static function count(?int $accountId = null): int
    {
        $where = '1=1';
        $params = [];
        if ($accountId !== null) {
            $where = 'account_id = ?';
            $params[] = $accountId;
        }
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM drafts WHERE $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
