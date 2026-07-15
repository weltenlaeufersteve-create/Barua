<?php

namespace Barua\Mail;

use Barua\Accounts\AccountRepository;
use Barua\Database;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class SyncService
{
    /**
     * Sync the newest messages for every active account.
     * Stage 1: fetch the latest N messages from INBOX, store headers + body.
     * One failing account never blocks the others.
     *
     * @return array<int,array> per-account result rows
     */
    public static function syncAll(int $limit = 50): array
    {
        $results = [];
        foreach (AccountRepository::all() as $account) {
            if ((int) $account['is_active'] !== 1) {
                continue;
            }
            $results[] = self::syncAccount($account, $limit);
        }
        return $results;
    }

    /**
     * Build a connected IMAP client for an account.
     * The native imap extension is intentionally not loaded, so webklex's default 'utf-8'
     * header decoder (imap_utf8/imap_mime_header_decode) silently fails on RFC 2047
     * encoded-words. 'mimeheader' uses mb_decode_mimeheader() — pure PHP via mbstring.
     * Header::__construct reads this from the GLOBAL ClientManager options, so it must be
     * set on the manager, not passed to make().
     */
    public static function makeClient(array $account): Client
    {
        $cm = new ClientManager([
            'options' => [
                'decoder' => [
                    'message'    => 'mimeheader',
                    'attachment' => 'mimeheader',
                ],
            ],
        ]);
        return $cm->make([
            'host'          => $account['imap_host'],
            'port'          => (int) $account['imap_port'],
            'encryption'    => $account['imap_encryption'] === 'none' ? false : $account['imap_encryption'],
            'validate_cert' => true,
            'username'      => $account['imap_username'],
            'password'      => AccountRepository::decryptImapPassword($account),
            'protocol'      => 'imap',
            'timeout'       => 30,
        ]);
    }

    public static function syncAccount(array $account, int $limit = 50): array
    {
        $label = $account['label'];
        try {
            $client = self::makeClient($account);
            $client->connect();

            // Sync the account's INBOX and Sent folders (Archive/Trash/Drafts follow later).
            $roles = FolderResolver::map($client);
            $toSync = ['inbox' => $roles['inbox'] ?? $client->getFolder('INBOX')];
            if (!empty($roles['sent'])) {
                $toSync['sent'] = $roles['sent'];
            }

            $fetched = 0;
            $stored = 0;
            foreach ($toSync as $role => $folder) {
                $messages = $folder->messages()->all()->setFetchOrder('desc')->limit($limit)->get();
                foreach ($messages as $message) {
                    if (self::store((int) $account['id'], $folder->path, $role, $message)) {
                        $stored++;
                    }
                }
                $fetched += count($messages);
            }

            $client->disconnect();

            $db = Database::connection();
            $db->prepare('UPDATE accounts SET last_synced_at = NOW() WHERE id = ?')
               ->execute([$account['id']]);

            return ['account' => $label, 'ok' => true, 'fetched' => $fetched, 'stored' => $stored];
        } catch (\Throwable $e) {
            return ['account' => $label, 'ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Refresh just the Sent-folder cache for one account (called right after sending). */
    public static function syncSentFolder(array $account, int $limit = 30): void
    {
        try {
            $client = self::makeClient($account);
            $client->connect();
            $sent = FolderResolver::find($client, 'sent');
            if ($sent) {
                $messages = $sent->messages()->all()->setFetchOrder('desc')->limit($limit)->get();
                foreach ($messages as $message) {
                    self::store((int) $account['id'], $sent->path, 'sent', $message);
                }
            }
            $client->disconnect();
        } catch (\Throwable $e) {
            // Non-fatal: the message was already sent; the Sent cache will catch up next sync.
        }
    }

    private static function store(int $accountId, string $folderPath, string $folderRole, $message): bool
    {
        $uid = (int) self::scalar($message->getUid());
        if ($uid === 0) {
            return false;
        }

        $from = self::firstFrom($message);
        $recipients = self::recipientList($message);
        $messageId = self::scalar($message->getMessageId());
        $inReplyTo = self::scalar($message->getInReplyTo());
        $subject   = self::scalar($message->getSubject());

        $plain = self::bodyText($message, false);
        $html  = self::bodyText($message, true);
        $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', $plain !== '' ? $plain : strip_tags($html))), 0, 300);

        $isRead = 0;
        try {
            $isRead = $message->getFlags()->contains('Seen') ? 1 : 0;
        } catch (\Throwable $e) {
        }

        $isStarred = 0;
        try {
            $isStarred = $message->getFlags()->contains('Flagged') ? 1 : 0;
        } catch (\Throwable $e) {
        }

        $hasAttachments = 0;
        try {
            $hasAttachments = $message->hasAttachments() ? 1 : 0;
        } catch (\Throwable $e) {
        }

        $dateSent = null;
        try {
            $date = $message->getDate()->first();
            if ($date) {
                // Store canonically in UTC regardless of the email's own offset;
                // display converts to the configured zone. $date is a Carbon instance.
                $dateSent = $date->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
            }
        } catch (\Throwable $e) {
        }

        // Stage 1: thread_id falls back to the message's own id; proper threading is Stage 2.
        $threadId = $inReplyTo !== '' ? $inReplyTo : $messageId;

        $sql = 'INSERT INTO messages
                    (account_id, folder, folder_role, imap_uid, message_id, in_reply_to, thread_id, subject,
                     sender_name, sender_email, recipients, date_sent, body_snippet, body_html, body_plain,
                     body_cached, is_read, is_starred, has_attachments)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    folder_role = VALUES(folder_role),
                    is_read = VALUES(is_read),
                    is_starred = VALUES(is_starred),
                    has_attachments = VALUES(has_attachments),
                    subject = VALUES(subject),
                    sender_name = VALUES(sender_name),
                    sender_email = VALUES(sender_email),
                    recipients = VALUES(recipients),
                    date_sent = VALUES(date_sent),
                    message_id = VALUES(message_id),
                    in_reply_to = VALUES(in_reply_to),
                    thread_id = VALUES(thread_id),
                    body_snippet = VALUES(body_snippet),
                    body_html = VALUES(body_html),
                    body_plain = VALUES(body_plain)';

        Database::connection()->prepare($sql)->execute([
            $accountId,
            $folderPath,
            $folderRole,
            $uid,
            $messageId ?: null,
            $inReplyTo ?: null,
            $threadId ?: null,
            $subject,
            $from['name'],
            $from['email'],
            $recipients,
            $dateSent,
            $snippet,
            $html,
            $plain,
            $isRead,
            $isStarred,
            $hasAttachments,
        ]);

        return true;
    }

    /** Comma-joined "To" recipients (display name or address), for the Sent view. */
    private static function recipientList($message): string
    {
        try {
            $to = $message->getTo();
            $list = is_array($to) ? $to : ($to !== null ? $to->all() : []);
            $out = [];
            foreach ($list as $addr) {
                $name = self::decodeMime(trim((string) ($addr->personal ?: $addr->mail)));
                $out[] = trim($name, "\"'");
            }
            return implode(', ', array_filter($out));
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function firstFrom($message): array
    {
        try {
            $from = $message->getFrom();
            $addr = is_array($from) ? ($from[0] ?? null) : ($from->first() ?? null);
            if ($addr) {
                $name = self::decodeMime(trim((string) ($addr->personal ?: $addr->mail)));
                $name = trim($name, "\"'"); // strip literal quotes some display-names carry
                return [
                    'name'  => $name,
                    'email' => (string) $addr->mail,
                ];
            }
        } catch (\Throwable $e) {
        }
        return ['name' => '', 'email' => ''];
    }

    private static function bodyText($message, bool $html): string
    {
        try {
            $body = $html ? $message->getHTMLBody() : $message->getTextBody();
            return is_string($body) ? $body : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function scalar($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_object($value) && method_exists($value, 'first')) {
            $value = $value->first();
        }
        return self::decodeMime(trim((string) $value));
    }

    /**
     * Defensive net for RFC 2047 encoded-words that webklex's imap-extension-dependent
     * decode paths (e.g. address personal names) leave untouched when the native imap
     * extension isn't loaded. A no-op on already-decoded strings.
     */
    private static function decodeMime(string $value): string
    {
        if ($value !== '' && str_contains($value, '=?')) {
            $decoded = @mb_decode_mimeheader($value);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }
        return $value;
    }
}
