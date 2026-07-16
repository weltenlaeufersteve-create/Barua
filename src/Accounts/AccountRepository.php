<?php

namespace Barua\Accounts;

use Barua\Crypto;
use Barua\Database;

class AccountRepository
{
    public static function all(): array
    {
        // User-defined order (drag & drop in Settings); id as deterministic tiebreaker.
        $stmt = Database::connection()->query('SELECT * FROM accounts ORDER BY sort_order ASC, id ASC');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM accounts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $usedColours = array_column(self::all(), 'colour');
        $colour = ColorPalette::pickUnused($usedColours);

        $db = Database::connection();
        $nextOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM accounts')->fetchColumn();
        $stmt = $db->prepare(
            'INSERT INTO accounts
                (label, email, colour, sort_order, imap_host, imap_port, imap_encryption, imap_username, imap_password_enc,
                 smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password_enc, signature)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['label'],
            $data['email'],
            $colour,
            $nextOrder,
            $data['imap_host'],
            $data['imap_port'],
            $data['imap_encryption'],
            $data['imap_username'],
            Crypto::encrypt($data['imap_password']),
            $data['smtp_host'],
            $data['smtp_port'],
            $data['smtp_encryption'],
            $data['smtp_username'],
            Crypto::encrypt($data['smtp_password']),
            $data['signature'] ?? '',
        ]);

        return (int) $db->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM accounts WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Persist a full drag-and-drop ordering: position in $ids becomes sort_order. */
    public static function reorder(array $ids): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE accounts SET sort_order = ? WHERE id = ?');
        foreach (array_values($ids) as $pos => $id) {
            $stmt->execute([$pos + 1, (int) $id]);
        }
    }

    public static function updateColour(int $id, string $colour): void
    {
        $stmt = Database::connection()->prepare('UPDATE accounts SET colour = ? WHERE id = ?');
        $stmt->execute([$colour, $id]);
    }

    /**
     * Update connection details. Passwords are only re-encrypted when a non-empty
     * new value is supplied; blank password fields leave the stored secret untouched.
     */
    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE accounts SET
                    label = ?, email = ?, signature = ?,
                    imap_host = ?, imap_port = ?, imap_encryption = ?, imap_username = ?,
                    smtp_host = ?, smtp_port = ?, smtp_encryption = ?, smtp_username = ?';
        $params = [
            $data['label'],
            $data['email'],
            $data['signature'] ?? '',
            $data['imap_host'],
            $data['imap_port'],
            $data['imap_encryption'],
            $data['imap_username'],
            $data['smtp_host'],
            $data['smtp_port'],
            $data['smtp_encryption'],
            $data['smtp_username'],
        ];

        if (($data['imap_password'] ?? '') !== '') {
            $sql .= ', imap_password_enc = ?';
            $params[] = Crypto::encrypt($data['imap_password']);
        }
        if (($data['smtp_password'] ?? '') !== '') {
            $sql .= ', smtp_password_enc = ?';
            $params[] = Crypto::encrypt($data['smtp_password']);
        }

        $sql .= ' WHERE id = ?';
        $params[] = $id;

        Database::connection()->prepare($sql)->execute($params);
    }

    public static function decryptImapPassword(array $account): string
    {
        return Crypto::decrypt($account['imap_password_enc']);
    }

    public static function decryptSmtpPassword(array $account): string
    {
        return Crypto::decrypt($account['smtp_password_enc']);
    }
}
