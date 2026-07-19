<?php

namespace Barua\Mail;

use Barua\Accounts\AccountRepository;
use Barua\Database;

/**
 * Single-message actions that write back to the IMAP server (pin, archive, trash)
 * and keep the local cache in step.
 */
class MessageActions
{
    /**
     * Mark a message read on the IMAP server (\Seen) and in the cache. Must write \Seen to
     * the server, not just the DB — otherwise the next sync's flag mirror sees the mail is
     * still unread on the server and flips is_read back to 0.
     */
    public static function markRead(int $messageId): array
    {
        [$row, $account] = self::load($messageId);
        if (!$row) {
            return ['ok' => false, 'error' => 'Unknown message'];
        }
        if ((int) $row['is_read'] === 1) {
            return ['ok' => true, 'already' => true]; // nothing to do
        }

        try {
            $client = SyncService::makeClient($account);
            $client->connect();
            $folder = $client->getFolderByPath($row['folder']);
            $message = $folder->query()->setFetchBody(false)->getMessageByUid((int) $row['imap_uid']);
            $message->setFlag('Seen');
            $client->disconnect();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'IMAP: ' . $e->getMessage()];
        }

        Database::connection()
            ->prepare('UPDATE messages SET is_read = 1 WHERE id = ?')
            ->execute([$messageId]);

        return ['ok' => true];
    }

    /** Toggle the IMAP \Flagged flag ("Pinned"). Returns the new state. */
    public static function setPin(int $messageId, bool $pinned): array
    {
        [$row, $account] = self::load($messageId);
        if (!$row) {
            return ['ok' => false, 'error' => 'Unknown message'];
        }

        try {
            $client = SyncService::makeClient($account);
            $client->connect();
            $folder = $client->getFolderByPath($row['folder']);
            $message = $folder->query()->setFetchBody(false)->getMessageByUid((int) $row['imap_uid']);
            $result = $pinned ? $message->setFlag('Flagged') : $message->unsetFlag('Flagged');
            $client->disconnect();
            if (!$result) {
                return ['ok' => false, 'error' => 'Server refused the flag change'];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'IMAP: ' . $e->getMessage()];
        }

        Database::connection()
            ->prepare('UPDATE messages SET is_starred = ? WHERE id = ?')
            ->execute([$pinned ? 1 : 0, $messageId]);

        return ['ok' => true, 'pinned' => $pinned];
    }

    /** Move the message to the account's Archive folder and drop it from the cache. */
    public static function archive(int $messageId): array
    {
        return self::moveToRole($messageId, 'archive');
    }

    /** Move the message to the account's Trash folder and drop it from the cache. */
    public static function trash(int $messageId): array
    {
        return self::moveToRole($messageId, 'trash');
    }

    /**
     * File a message into a smart group by hand and pin that choice, so the sync's
     * classifier stops second-guessing it. Purely local — groups are Barua's own concept,
     * not an IMAP folder, so no server round-trip is needed.
     */
    public static function setGroup(int $messageId, string $group): array
    {
        $allowed = ['people', 'newsletter', 'notification', 'other'];
        if (!in_array($group, $allowed, true)) {
            return ['ok' => false, 'error' => 'Unknown group'];
        }

        $stmt = Database::connection()
            ->prepare('UPDATE messages SET group_type = ?, group_locked = 1 WHERE id = ?');
        $stmt->execute([$group, $messageId]);

        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'error' => 'Unknown message'];
        }
        return ['ok' => true, 'group' => $group];
    }

    /** Move the message to the account's Spam/Junk folder and drop it from the cache. */
    public static function spam(int $messageId): array
    {
        return self::moveToRole($messageId, 'spam');
    }

    private static function moveToRole(int $messageId, string $role): array
    {
        [$row, $account] = self::load($messageId);
        if (!$row) {
            return ['ok' => false, 'error' => 'Unknown message'];
        }

        try {
            $client = SyncService::makeClient($account);
            $client->connect();
            $target = FolderResolver::find($client, $role);
            if ($target === null) {
                $client->disconnect();
                return ['ok' => false, 'error' => "No {$role} folder on this account"];
            }
            if ($target->path === $row['folder']) {
                $client->disconnect();
                return ['ok' => false, 'error' => "Message is already in {$role}"];
            }
            $folder = $client->getFolderByPath($row['folder']);
            $message = $folder->query()->setFetchBody(false)->getMessageByUid((int) $row['imap_uid']);
            $moved = $message->move($target->path, true);
            $client->disconnect();
            if ($moved === null) {
                return ['ok' => false, 'error' => 'Server refused the move'];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'IMAP: ' . $e->getMessage()];
        }

        // The message left its cached folder (it gets a new UID in the target folder;
        // an Archive/Trash view will pick it up once those folders are synced).
        Database::connection()
            ->prepare('DELETE FROM messages WHERE id = ?')
            ->execute([$messageId]);

        return ['ok' => true];
    }

    /** @return array{0: ?array, 1: ?array} message row + its account row */
    private static function load(int $messageId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM messages WHERE id = ?');
        $stmt->execute([$messageId]);
        $row = $stmt->fetch();
        if (!$row) {
            return [null, null];
        }
        $account = AccountRepository::find((int) $row['account_id']);
        return $account ? [$row, $account] : [null, null];
    }
}
