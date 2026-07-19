<?php
// Shared view helpers, used by both the full dashboard render and the live-stream API,
// so a message row looks identical whether it's server-rendered on load or slid in later.

use Barua\Mail\MessageRepository;

/** Inline stroke SVG icons for the sidebar / row actions (currentColor, 16px). */
function sidebarIcon(string $name): string
{
    static $paths = [
        'inbox'         => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'sent'          => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'pinned'        => '<line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24z"/>',
        'drafts'        => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'archive'       => '<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>',
        'spam'          => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'trash'         => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'people'        => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'newsletters'   => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'notifications' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'back'          => '<polyline points="15 18 9 12 15 6"/>',
        'filter'        => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'reply'         => '<polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/>',
        'forward'       => '<polyline points="15 14 20 9 15 4"/><path d="M4 20v-7a4 4 0 0 1 4-4h12"/>',
        'settings'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'logout'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    ];
    $inner = $paths[$name] ?? '';
    return '<svg class="sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
}

function initial(array $row): string
{
    $base = ($row['sender_name'] ?? '') !== '' ? $row['sender_name'] : ($row['sender_email'] ?? '');
    return mb_strtoupper(mb_substr($base, 0, 1)) ?: '?';
}

/**
 * Row-action icons drawn as CLOSED silhouettes, so CSS can toggle them between
 * outline (fill:none) and filled (fill:currentColor). No inline fill — the .ra-icon
 * CSS controls it per state (idle = outline, hover / pinned = filled).
 */
function rowActionIcon(string $name): string
{
    static $paths = [
        // vertical thumbtack silhouette
        'pin'     => '<path d="M9 3h6l-1 6 3 3v2h-4v5l-1 1-1-1v-5H6v-2l3-3-1-6z"/>',
        // archive box: lid bar + body + slot (slot carved via evenodd)
        'archive' => '<path fill-rule="evenodd" d="M3.5 4h17v3.6h-17z M5.3 8.4h13.4l-1 11.6H6.3z M9.4 11.5h5.2v1.7H9.4z"/>',
        // trash can: handle + lid bar + body
        'trash'   => '<path fill-rule="evenodd" d="M9.4 3h5.2v1.6H9.4z M4 5.2h16v2.2H4z M6.4 8.2h11.2l-1 11.8H7.4z"/>',
    ];
    return '<svg class="ra-icon" viewBox="0 0 24 24">' . ($paths[$name] ?? '') . '</svg>';
}

/** One message-list row, identical markup for full render and live insert. */
function renderMailRow(array $row, bool $isDraftView = false, bool $isSelected = false): string
{
    $e = fn($v) => htmlspecialchars((string) $v);
    $isUnread = (int) ($row['is_read'] ?? 1) === 0;
    $id = (int) $row['id'];
    $sender = ($row['sender_name'] ?? '') !== '' ? $row['sender_name'] : ($row['sender_email'] ?? '');
    $subject = ($row['subject'] ?? '') !== '' ? $row['subject'] : '(no subject)';

    $cls = 'mail-row' . ($isUnread ? ' is-unread' : '') . ($isSelected ? ' is-selected' : '');
    $idAttr = $isDraftView ? 'data-draft="' . $id . '"' : 'data-msg="' . $id . '"';

    $actions = $isDraftView
        ? '<span class="row-action" title="Delete draft">' . rowActionIcon('trash') . '</span>'
        : '<span class="row-action row-action--pin' . ((int) ($row['is_starred'] ?? 0) === 1 ? ' is-pinned' : '') . '" title="Pin">' . rowActionIcon('pin') . '</span>'
          . '<span class="row-action" title="Archive">' . rowActionIcon('archive') . '</span>'
          . '<span class="row-action" title="Delete">' . rowActionIcon('trash') . '</span>';

    return '<div class="' . $cls . '" ' . $idAttr . ' data-account="' . (int) $row['account_id'] . '">'
        . '<div class="mail-row__actions">' . $actions . '</div>'
        . '<span class="mail-row__stripe" style="background: ' . $e($row['account_colour']) . '"></span>'
        . '<div class="mail-row__body">'
        . '<div class="mail-row__top">'
        . '<span class="mail-row__sender">' . $e($sender) . '</span>'
        . '<span class="mail-row__time">' . $e(MessageRepository::timeLabel($row['date_sent'])) . '</span>'
        . '</div>'
        . '<div class="mail-row__subject">' . $e($subject) . '</div>'
        . '<div class="mail-row__preview">' . $e($row['body_snippet'] ?? '') . '</div>'
        . '</div></div>';
}

/** The JS reader-map entry for a message row. */
function mailRowData(array $row): array
{
    $body = ($row['body_plain'] ?? '') !== '' ? $row['body_plain'] : \Barua\Mail\HtmlMailRenderer::toText($row['body_html'] ?? '');
    return [
        'subject'       => $row['subject'],
        'sender'        => ($row['sender_name'] ?? '') !== '' ? $row['sender_name'] : $row['sender_email'],
        'email'         => $row['sender_email'],
        'accountId'     => (int) $row['account_id'],
        'accountLabel'  => $row['account_label'],
        'accountColour' => $row['account_colour'],
        'messageId'     => $row['message_id'] ?? '',
        'time'          => MessageRepository::timeLabel($row['date_sent']),
        'fullTime'      => MessageRepository::fullTimeLabel($row['date_sent']),
        'initial'       => initial($row),
        'hasHtml'       => trim($row['body_html'] ?? '') !== '',
        'body'          => $body !== '' ? $body : '(No text content)',
    ];
}
