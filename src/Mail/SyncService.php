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

            // Sync the account's INBOX, Sent, Archive and Trash folders (Drafts/Spam later).
            $roles = FolderResolver::map($client);
            $toSync = ['inbox' => $roles['inbox'] ?? $client->getFolder('INBOX')];
            foreach (['sent', 'archive', 'trash', 'spam'] as $role) {
                if (!empty($roles[$role])) {
                    $toSync[$role] = $roles[$role];
                }
            }

            $stats = ['checked' => 0, 'new' => 0, 'flags' => 0, 'removed' => 0];
            foreach ($toSync as $role => $folder) {
                $s = self::syncFolder((int) $account['id'], $folder, $role, $limit);
                foreach ($s as $k => $v) {
                    $stats[$k] += $v;
                }
            }

            $client->disconnect();

            $db = Database::connection();
            $db->prepare('UPDATE accounts SET last_synced_at = NOW() WHERE id = ?')
               ->execute([$account['id']]);

            return ['account' => $label, 'ok' => true] + $stats;
        } catch (\Throwable $e) {
            return ['account' => $label, 'ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Two-phase folder sync, so routine runs are cheap and server-side changes propagate:
     *   1. Light pass — UIDs + flags only (no bodies): detects new mail, read/star changes
     *      made in other clients, and deletions.
     *   2. Full fetch (with bodies) only for messages we have never cached. New messages
     *      always carry the highest UIDs in an IMAP folder, so fetching the newest
     *      count(new) covers exactly them.
     * Deletions: cached rows whose UID vanished from the server are removed — limited to the
     * synced window (>= lowest seen UID) when the folder holds more than $limit messages, so
     * older cached mail below the window is never wrongly purged.
     */
    private static function syncFolder(int $accountId, $folder, string $role, int $limit): array
    {
        $db = Database::connection();

        // Pass 1: cheap — no bodies.
        $light = $folder->messages()->all()->setFetchBody(false)->setFetchOrder('desc')->limit($limit)->get();
        $server = []; // uid => ['read' => 0|1, 'star' => 0|1, 'group' => string]
        foreach ($light as $m) {
            $uid = (int) self::scalar($m->getUid());
            if ($uid === 0) {
                continue;
            }
            $read = 0;
            $star = 0;
            try {
                $read = $m->getFlags()->contains('Seen') ? 1 : 0;
                $star = $m->getFlags()->contains('Flagged') ? 1 : 0;
            } catch (\Throwable $e) {
            }
            $server[$uid] = [
                'read'  => $read,
                'star'  => $star,
                'group' => self::classify($m, self::firstFrom($m)['email']),
            ];
        }

        // Cached state for this folder.
        $stmt = $db->prepare('SELECT imap_uid, is_read, is_starred, group_type, group_locked FROM messages WHERE account_id = ? AND folder = ?');
        $stmt->execute([$accountId, $folder->path]);
        $cached = [];
        foreach ($stmt->fetchAll() as $row) {
            $cached[(int) $row['imap_uid']] = $row;
        }

        // Pass 2: full fetch only for never-cached messages.
        $newUids = array_diff(array_keys($server), array_keys($cached));
        $new = 0;
        if (!empty($newUids)) {
            $full = $folder->messages()->all()->setFetchOrder('desc')->limit(count($newUids))->get();
            foreach ($full as $message) {
                if (self::store($accountId, $folder->path, $role, $message)) {
                    $new++;
                }
            }
        }

        // Mirror flag changes (read on the phone, starred elsewhere, …) and keep the
        // smart-group classification of the window current.
        $flags = 0;
        $upd = $db->prepare('UPDATE messages SET is_read = ?, is_starred = ?, group_type = ? WHERE account_id = ? AND folder = ? AND imap_uid = ?');
        foreach ($server as $uid => $f) {
            if (!isset($cached[$uid])) {
                continue;
            }
            // A group the user set by hand wins over the classifier — keep it as cached.
            $locked = (int) ($cached[$uid]['group_locked'] ?? 0) === 1;
            $group = $locked ? $cached[$uid]['group_type'] : $f['group'];
            if ((int) $cached[$uid]['is_read'] !== $f['read']
                || (int) $cached[$uid]['is_starred'] !== $f['star']
                || $cached[$uid]['group_type'] !== $group) {
                $upd->execute([$f['read'], $f['star'], $group, $accountId, $folder->path, $uid]);
                $flags++;
            }
        }

        // Remove cached rows whose message is gone server-side (deleted/moved in another client).
        if (empty($server)) {
            $del = $db->prepare('DELETE FROM messages WHERE account_id = ? AND folder = ?');
            $del->execute([$accountId, $folder->path]);
            $removed = $del->rowCount();
        } else {
            $uids = array_keys($server);
            $ph = implode(',', array_fill(0, count($uids), '?'));
            $sql = 'DELETE FROM messages WHERE account_id = ? AND folder = ? AND imap_uid NOT IN (' . $ph . ')';
            $params = array_merge([$accountId, $folder->path], $uids);
            if (count($uids) >= $limit) {
                // Partial window: leave older cached mail below the window untouched.
                $sql .= ' AND imap_uid >= ?';
                $params[] = min($uids);
            }
            $del = $db->prepare($sql);
            $del->execute($params);
            $removed = $del->rowCount();
        }

        // Pinned sweep: pins are few but often OLD (far below the sync window), so mirror
        // IMAP \Flagged across the WHOLE folder via a targeted FLAGGED search. Ensures the
        // Pinned view matches other clients (Spark, phone) even for years-old mail.
        try {
            // NB: use where('FLAGGED') — webklex's whereFlagged() convenience method
            // wrongly demands a value argument.
            $flaggedLight = $folder->messages()->where('FLAGGED')->setFetchBody(false)->get();
            $flaggedUids = [];
            foreach ($flaggedLight as $m) {
                $fuid = (int) self::scalar($m->getUid());
                if ($fuid) {
                    $flaggedUids[] = $fuid;
                }
            }

            if (!empty($flaggedUids)) {
                $ph = implode(',', array_fill(0, count($flaggedUids), '?'));
                $stmt = $db->prepare("SELECT imap_uid FROM messages WHERE account_id = ? AND folder = ? AND imap_uid IN ($ph)");
                $stmt->execute(array_merge([$accountId, $folder->path], $flaggedUids));
                $have = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
                $missing = array_diff($flaggedUids, $have);

                // Fetch bodies only for pinned mails we have never cached.
                if (!empty($missing)) {
                    $flaggedFull = $folder->messages()->where('FLAGGED')->get();
                    foreach ($flaggedFull as $message) {
                        $fuid = (int) self::scalar($message->getUid());
                        if (in_array($fuid, $missing, true) && self::store($accountId, $folder->path, $role, $message)) {
                            $new++;
                        }
                    }
                }

                // Cache flag state mirrors the server exactly (pin + unpin).
                $upd1 = $db->prepare("UPDATE messages SET is_starred = 1 WHERE account_id = ? AND folder = ? AND is_starred = 0 AND imap_uid IN ($ph)");
                $upd1->execute(array_merge([$accountId, $folder->path], $flaggedUids));
                $flags += $upd1->rowCount();
                $upd0 = $db->prepare("UPDATE messages SET is_starred = 0 WHERE account_id = ? AND folder = ? AND is_starred = 1 AND imap_uid NOT IN ($ph)");
                $upd0->execute(array_merge([$accountId, $folder->path], $flaggedUids));
                $flags += $upd0->rowCount();
            } else {
                $upd0 = $db->prepare('UPDATE messages SET is_starred = 0 WHERE account_id = ? AND folder = ? AND is_starred = 1');
                $upd0->execute([$accountId, $folder->path]);
                $flags += $upd0->rowCount();
            }
        } catch (\Throwable $e) {
            // Non-fatal: a failed pin sweep must not break the folder sync.
        }

        return ['checked' => count($server), 'new' => $new, 'flags' => $flags, 'removed' => $removed];
    }

    /** Refresh just the Sent-folder cache for one account (called right after sending). */
    public static function syncSentFolder(array $account, int $limit = 30): void
    {
        try {
            $client = self::makeClient($account);
            $client->connect();
            $sent = FolderResolver::find($client, 'sent');
            if ($sent) {
                self::syncFolder((int) $account['id'], $sent, 'sent', $limit);
            }
            $client->disconnect();
        } catch (\Throwable $e) {
            // Non-fatal: the message was already sent; the Sent cache will catch up next sync.
        }
    }

    /**
     * Smart-group classification. webklex's parsed header attributes are unreliable
     * (folded headers get mangled), so we regex the RAW header text instead.
     *   newsletter   — subscribed bulk content: List-Unsubscribe / List-Id / Precedence bulk|list
     *   notification — event-triggered automation: Auto-Submitted, or a no-reply-style sender
     *   other        — everything else ("People" refinement comes later)
     */
    public static function classify($message, string $fromEmail): string
    {
        $raw = '';
        try {
            $raw = (string) $message->getHeader()->raw;
        } catch (\Throwable $e) {
        }

        if ($raw !== '') {
            if (preg_match('/^list-(unsubscribe|id):/im', $raw)) {
                return 'newsletter';
            }
            if (preg_match('/^precedence:\s*(bulk|list)/im', $raw)) {
                return 'newsletter';
            }
            if (preg_match('/^auto-submitted:\s*auto/im', $raw)) {
                return 'notification';
            }
        }

        $local = strtolower(strstr($fromEmail, '@', true) ?: $fromEmail);
        if (preg_match('/^(no-?reply|do-?not-?reply|notifications?|notify|mailer(-daemon)?|postmaster|alerts?|auto(mail(er)?)?|system|robot|daemon|bounces?)([.\-_+]|$)/', $local)
            || preg_match('/([.\-_+]|^)no-?reply([.\-_+]|$)/', $local)) {
            return 'notification';
        }

        return 'other';
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
        $snippet = mb_substr($plain !== '' ? trim(preg_replace('/\s+/', ' ', $plain)) : HtmlMailRenderer::toText($html), 0, 300);

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

        $groupType = self::classify($message, $from['email']);

        $sql = 'INSERT INTO messages
                    (account_id, folder, folder_role, imap_uid, message_id, in_reply_to, thread_id, subject,
                     sender_name, sender_email, recipients, date_sent, body_snippet, body_html, body_plain,
                     body_cached, is_read, is_starred, has_attachments, group_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    -- a hand-picked group survives re-syncs; the classifier only fills the rest
                    group_type = IF(group_locked = 1, group_type, VALUES(group_type)),
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
            $groupType,
        ]);

        // Sent mail = proof of correspondence: harvest recipient addresses for People.
        if ($folderRole === 'sent') {
            self::recordCorrespondents($message, $dateSent);
        }

        return true;
    }

    /** Upsert every To/Cc address of an outgoing message into correspondents. */
    private static function recordCorrespondents($message, ?string $dateSent): void
    {
        try {
            $all = [];
            foreach (['getTo', 'getCc'] as $getter) {
                try {
                    $list = $message->$getter();
                    $list = is_array($list) ? $list : ($list !== null ? $list->all() : []);
                    $all = array_merge($all, $list);
                } catch (\Throwable $e) {
                }
            }
            foreach ($all as $addr) {
                $email = (string) ($addr->mail ?? '');
                $name = trim(self::decodeMime(trim((string) ($addr->personal ?? ''))), "\"'");
                CorrespondentRepository::upsert($email, $name, $dateSent);
            }
        } catch (\Throwable $e) {
            // Non-fatal: correspondent harvesting must never break a sync.
        }
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
