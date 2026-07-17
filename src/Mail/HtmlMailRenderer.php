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

    public static function document(string $html, bool $remoteImages, bool $dark = false): string
    {
        // Email HTML is authored for a light background. For dark mode we use the well-known
        // "dark reader" trick: invert + hue-rotate the body (whites → dark, dark text → light,
        // hues roughly preserved), then re-invert media so images/videos look normal.
        // In dark mode the document is TRANSPARENT so the reader's own theme background
        // (--reader-bg, the darkest theme surface) shows through — no hard black, no frame.
        $bg = $dark ? 'transparent' : '#ffffff';
        $darkCss = $dark
            ? 'body{filter:invert(1) hue-rotate(180deg);}'
              . 'img,video,picture,[style*="background-image"]{filter:invert(1) hue-rotate(180deg);}'
            : '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<meta http-equiv="Content-Security-Policy" content="' . htmlspecialchars(self::csp($remoteImages), ENT_QUOTES) . '">'
            . '<base target="_blank">'
            . '<style>'
            . "html{background:{$bg};}"
            // 32px side margin aligns the mail text with the reader's content column.
            . "body{margin:24px 32px;font-family:-apple-system,'Segoe UI',sans-serif;font-size:14px;"
            . "line-height:1.55;color:#1d1f21;background:{$bg};word-wrap:break-word;}"
            . 'img{max-width:100%;height:auto;}'
            . 'table{max-width:100%;}'
            . $darkCss
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
