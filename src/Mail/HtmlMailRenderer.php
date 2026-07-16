<?php

namespace Barua\Mail;

/**
 * Renders an email's HTML body as a standalone document for the reader iframe.
 *
 * Defence in depth — three independent layers:
 *   1. The reader <iframe sandbox> (no allow-scripts) — browser-enforced, primary.
 *   2. A strict CSP (both as HTTP header and <meta>) — protects even direct navigation
 *      to the endpoint, where no sandbox applies. default-src 'none', no scripts ever.
 *   3. This server-side sanitizer — strips script tags, event handlers, dangerous URLs
 *      and embedding elements before the markup leaves the server.
 *
 * Remote images are blocked by default via CSP (tracking-pixel privacy); pass
 * $remoteImages = true to allow http(s) images for one render.
 */
class HtmlMailRenderer
{
    public static function csp(bool $remoteImages): string
    {
        $img = $remoteImages ? "img-src https: http: data: cid:" : "img-src data: cid:";
        return "default-src 'none'; {$img}; style-src 'unsafe-inline'; font-src data:; form-action 'none'; base-uri 'none'";
    }

    public static function document(string $html, bool $remoteImages): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<meta http-equiv="Content-Security-Policy" content="' . htmlspecialchars(self::csp($remoteImages), ENT_QUOTES) . '">'
            . '<base target="_blank">'
            . '<style>'
            . "body{margin:14px;font-family:-apple-system,'Segoe UI',sans-serif;font-size:14px;"
            . 'line-height:1.55;color:#1d1f21;background:#ffffff;word-wrap:break-word;}'
            . 'img{max-width:100%;height:auto;}'
            . 'table{max-width:100%;}'
            . '</style>'
            . '</head><body>' . self::sanitize($html) . '</body></html>';
    }

    /** Belt-and-braces markup scrub — the sandbox + CSP are the primary defences. */
    public static function sanitize(string $html): string
    {
        // script blocks and stray script tags
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#</?script\b[^>]*>#i', '', $html);

        // inline event handlers (onclick=, onload=, …)
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        // javascript:/vbscript:/data:text/html URLs in URL-bearing attributes
        $html = preg_replace(
            '/\b(href|src|action|formaction|background)\s*=\s*(["\']?)\s*(javascript:|vbscript:|data:\s*text\/html)[^"\'>\s]*\2/i',
            '$1=$2#$2',
            $html
        );

        // embedding / navigation-hijacking elements (keep inner text where any)
        $html = preg_replace('#</?(base|object|embed|applet|iframe|frame|frameset|form)\b[^>]*>#i', '', $html);
        $html = preg_replace('#<meta[^>]+http-equiv[^>]*>#i', '', $html);

        return $html;
    }
}
