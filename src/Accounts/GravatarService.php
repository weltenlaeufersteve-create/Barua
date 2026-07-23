<?php

namespace Barua\Accounts;

use Barua\Database;

class GravatarService
{
    private const TIMEOUT_SECONDS = 4;

    /** Where cached avatar images live — outside the webroot, served via GET /avatars/{id}. */
    public static function storageDir(): string
    {
        return __DIR__ . '/../../storage/avatars';
    }

    public static function cachedPath(int $accountId): string
    {
        // Extension is nominal — the actual bytes can be PNG or JPEG (see ensure()); the
        // serve route detects the real type rather than trusting this name.
        return self::storageDir() . '/' . $accountId . '.img';
    }

    /**
     * Look up a Gravatar for this account's email once and cache the result — either the
     * image on disk (avatar_state='has') or a "nothing there" marker (avatar_state='none')
     * so a stranger's mailbox with no Gravatar isn't re-requested on every page load.
     * Call this right after create/update (when the email is known to have changed), not
     * from the request path that renders the dashboard.
     */
    public static function ensure(int $accountId, string $email): void
    {
        $hash = md5(strtolower(trim($email)));
        // Gravatar ignores the extension for the actual image format (a hit can come back
        // PNG or JPEG regardless) — the serve route detects the real type from the cached
        // bytes rather than assuming one from this URL.
        $url = "https://www.gravatar.com/avatar/{$hash}?s=128&d=404";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body !== false && $status === 200 && $body !== '') {
            if (!is_dir(self::storageDir())) {
                mkdir(self::storageDir(), 0775, true);
            }
            file_put_contents(self::cachedPath($accountId), $body);
            AccountRepository::setAvatarState($accountId, 'has');
        } else {
            // 404 (no Gravatar) or a network hiccup — either way, don't hold up the
            // account save on it. A stale 'none' from a transient failure just means the
            // next re-save (or a manual bin/backfill-avatars.php run) retries it.
            AccountRepository::setAvatarState($accountId, 'none');
        }
    }
}
