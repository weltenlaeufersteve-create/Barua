<?php
use Barua\Mail\MessageRepository;

require_once __DIR__ . '/helpers.php';

$activeAccountId = $activeAccountId ?? null;
$view = $view ?? 'inbox';                      // folder axis
$type = $type ?? '';                           // type axis (inbox only)
$filterPinned = $filterPinned ?? false;        // filter toggles (inbox only)
$filterAttachments = $filterAttachments ?? false;
$inInbox = $view === 'inbox';                  // where type + filters apply
$accounts = MessageRepository::accountsWithUnread();
$totalUnread = MessageRepository::totalUnread();
$sentCount = MessageRepository::sentCount($activeAccountId);

// Resolve the active account (scope) regardless of which folder is shown.
$activeAccount = null;
if ($activeAccountId !== null) {
    foreach ($accounts as $a) {
        if ((int) $a['id'] === $activeAccountId) { $activeAccount = $a; break; }
    }
}

// One URL builder for all three axes: scope (account) × folder × type × filter toggles.
// Only non-default values land in the query string, so the plain inbox stays a bare "/".
$url = function (?int $account, string $folderView, string $typeVal, bool $pin, bool $att): string {
    $params = [];
    if ($account !== null)   { $params['account'] = $account; }
    if ($folderView !== 'inbox') { $params['view'] = $folderView; }
    if ($typeVal !== '')     { $params['type'] = $typeVal; }
    if ($pin)                { $params['pinned'] = 1; }
    if ($att)                { $params['attachments'] = 1; }
    return '/' . (empty($params) ? '' : '?' . http_build_query($params));
};

// Switching account keeps whatever narrowing is active; switching folder drops the
// inbox-only axes, since type/filters don't apply to Sent/Archive/Trash/Spam/Drafts.
$urlAccount = fn(?int $account) => $url($account, $view, $type, $filterPinned, $filterAttachments);
$urlType    = fn(string $t) => $url($activeAccountId, 'inbox', $t, $filterPinned, $filterAttachments);
$urlFolder  = fn(string $f) => $url($activeAccountId, $f, '', false, false);
// A toggle flips its own axis and leaves everything else alone. From a folder view it
// returns to the inbox, where filters have meaning.
$urlPinned  = fn() => $url($activeAccountId, 'inbox', $inInbox ? $type : '', !$filterPinned, $filterAttachments);
$urlAttach  = fn() => $url($activeAccountId, 'inbox', $inInbox ? $type : '', $filterPinned, !$filterAttachments);

// Kept for the folder links further down, which still think in "view" terms.
$buildUrl = fn(?int $account, string $folderView) => $folderView === 'inbox'
    ? $url($account, 'inbox', $type, $filterPinned, $filterAttachments)
    : $url($account, $folderView, '', false, false);

// Human labels for the type axis, reused by the sidebar, the pills and the list header.
$typeLabels = [
    ''             => 'Inbox',
    'clean'        => 'Clean Inbox',
    'people'       => 'Correspondents',
    'newsletter'   => 'Newsletters',
    'notification' => 'Notifications',
];

if ($view === 'sent') {
    $rows = MessageRepository::sentMessages(100, $activeAccountId);
    $listTitle = $activeAccount ? $activeAccount['label'] : 'Sent';
    $listSubtitle = $activeAccount ? 'Sent' : 'All accounts';
} elseif ($view === 'archive' || $view === 'trash' || $view === 'spam') {
    $rows = MessageRepository::roleMessages($view, 100, $activeAccountId);
    $roleLabel = ucfirst($view);
    $listTitle = $activeAccount ? $activeAccount['label'] : $roleLabel;
    $listSubtitle = $activeAccount ? $roleLabel : 'All accounts';
} elseif ($view === 'drafts') {
    $rows = \Barua\Mail\DraftRepository::forDisplay($activeAccountId);
    $listTitle = $activeAccount ? $activeAccount['label'] : 'Drafts';
    $listSubtitle = $activeAccount ? 'Drafts' : 'All accounts';
} else {
    // Inbox: one query for base × type × filters.
    $rows = MessageRepository::inboxMessages($type, $filterPinned, $filterAttachments, 100, $activeAccountId);
    $listTitle = $activeAccount ? $activeAccount['label'] : $typeLabels[$type];
    // Spell the active narrowing out in the subtitle — with combinable filters the list
    // alone can't explain why it's short.
    $activeBits = [];
    if ($activeAccount) { $activeBits[] = $typeLabels[$type]; }
    if ($filterPinned) { $activeBits[] = 'Pinned'; }
    if ($filterAttachments) { $activeBits[] = 'Attachments'; }
    if (empty($activeBits)) {
        $listSubtitle = $activeAccount ? $activeAccount['email'] : 'Unified';
    } else {
        $listSubtitle = implode(' · ', $activeBits);
    }
}

// Group rows by human date label, preserving date-desc order.
$groups = [];
foreach ($rows as $row) {
    $groups[MessageRepository::dateGroup($row['date_sent'])][] = $row;
}

// The reader always starts empty (no auto-opened message) — JS populates it
// from a row click. Drafts open in the composer, not the reader, either way.
$isDraftView = $view === 'drafts';
$selected = null;

// Drafts view: a compact map so a row click can reopen the draft in the composer.
$jsDrafts = [];
if ($isDraftView) {
    foreach ($rows as $row) {
        $jsDrafts[(int) $row['id']] = [
            'accountId'   => (int) $row['account_id'],
            'to'          => $row['to_addresses'],
            'cc'          => $row['cc_addresses'],
            'bcc'         => $row['bcc_addresses'],
            'subject'     => $row['subject'],
            'body'        => $row['body_plain'],
            'attachments' => \Barua\Mail\DraftAttachmentRepository::forDraft((int) $row['id']),
        ];
    }
}

// Compact JSON map for the reader pane (client-side swap on row click).
$jsMessages = [];
foreach ($isDraftView ? [] : $rows as $row) {
    $jsMessages[(int) $row['id']] = mailRowData($row);
}

// One batched lookup for every visible row's attachments (not one query per row).
$attachmentsByMessage = $isDraftView ? [] : MessageRepository::attachmentsForMessages(array_keys($jsMessages));
foreach ($attachmentsByMessage as $mid => $atts) {
    if (isset($jsMessages[$mid])) {
        $jsMessages[$mid]['attachments'] = $atts;
    }
}
$selectedAttachments = $selected ? ($attachmentsByMessage[(int) $selected['id']] ?? []) : [];

// The reader's "filled" markup is always rendered (JS needs its element ids to exist
// for the very first row click) but starts hidden behind the empty state below —
// $sel is a blank fallback so the template can render without a real message.
$sel = $selected ?? [
    'id' => 0, 'subject' => '', 'account_id' => 0, 'account_colour' => 'transparent',
    'sender_name' => '', 'sender_email' => '', 'account_label' => '', 'date_sent' => null,
    'is_starred' => 0, 'body_plain' => '', 'body_html' => '',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barua</title>
<link rel="icon" type="image/png" href="/favicon.png">
<link rel="apple-touch-icon" href="/favicon.png">
<script>
  (function () {
    var theme = localStorage.getItem('barua_theme');
    if (theme) document.documentElement.setAttribute('data-theme', theme);
  })();
</script>
<link rel="stylesheet" href="<?= asset('/css/theme.css') ?>">
<link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('/css/inbox.css') ?>">
<link rel="stylesheet" href="<?= asset('/css/compose.css') ?>">
<link rel="stylesheet" href="<?= asset('/css/settings.css') ?>">
</head>
<body data-mobile-view="list">
  <div class="app">
    <!-- Dims the app behind the mobile sidebar drawer; tap to close. -->
    <div class="sidebar-scrim" id="sidebar-scrim" data-go="list"></div>

    <!-- Far-left module rail (Outlook-style): Mail is the app today; Calendar (CalDAV) and
         Contacts (CardDAV) are groundwork for future modules — inert for now. -->
    <nav class="app-rail" aria-label="Modules">
      <a href="/" class="app-rail__item is-active" title="Mail" aria-current="page">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="m3.5 6.5 8.5 7 8.5-7"/></svg>
        <span class="app-rail__label">Mail</span>
      </a>
      <button type="button" class="app-rail__item is-soon" title="Calendar — coming soon" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="16" rx="2.5"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2.5" x2="8" y2="6"/><line x1="16" y1="2.5" x2="16" y2="6"/><circle cx="8" cy="13" r="1.15" fill="currentColor" stroke="none"/><circle cx="12" cy="13" r="1.15" fill="currentColor" stroke="none"/><circle cx="16" cy="13" r="1.15" fill="currentColor" stroke="none"/><circle cx="8" cy="17" r="1.15" fill="currentColor" stroke="none"/><circle cx="12" cy="17" r="1.15" fill="currentColor" stroke="none"/><circle cx="16" cy="17" r="1.15" fill="currentColor" stroke="none"/></svg>
        <span class="app-rail__label">Calendar</span>
      </button>
      <button type="button" class="app-rail__item is-soon" title="Contacts — coming soon" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span class="app-rail__label">Contacts</span>
      </button>
      <button type="button" class="app-rail__item is-soon" title="Files — coming soon" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        <span class="app-rail__label">Files</span>
      </button>

      <span class="app-rail__spacer"></span>

      <button type="button" class="app-rail__item js-open-settings" title="Settings">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        <span class="app-rail__label">Settings</span>
      </button>
      <a href="/logout" class="app-rail__item" title="Sign out">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span class="app-rail__label">Sign out</span>
      </a>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar">
      <div class="sidebar__scroll">
      <div class="mobile-back" data-go="list"><?= sidebarIcon('back') ?> Inbox</div>
      <div class="sidebar__title"><strong>BARUA</strong> MAIL</div>

      <a href="<?= htmlspecialchars($url(null, 'inbox', $type, $filterPinned, $filterAttachments)) ?>" class="sidebar__item<?= $activeAccount === null && $inInbox ? ' is-active' : '' ?>"><?= sidebarIcon('inbox') ?> Inbox <span class="sidebar__count" id="inbox-count"><?= $totalUnread ?: '' ?></span></a>

      <div class="sidebar__section-header">Accounts</div>

      <?php foreach ($accounts as $acc): ?>
        <a href="<?= htmlspecialchars($urlAccount((int) $acc['id'])) ?>" data-account="<?= (int) $acc['id'] ?>" class="sidebar__item<?= $activeAccount && (int) $activeAccount['id'] === (int) $acc['id'] ? ' is-active' : '' ?>">
          <span class="account-avatar" style="background: <?= htmlspecialchars($acc['colour']) ?>; border-color: <?= htmlspecialchars($acc['colour']) ?>"><?php if (($acc['avatar_state'] ?? 'unknown') === 'has'): ?><img src="/avatars/<?= (int) $acc['id'] ?>" alt=""><?php else: ?><?= htmlspecialchars(mb_strtoupper(mb_substr($acc['label'], 0, 1))) ?><?php endif; ?></span>
          <?= htmlspecialchars($acc['label']) ?>
          <?php if ((int) $acc['unread'] > 0): ?>
            <span class="sidebar__count"><?= (int) $acc['unread'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <?php
        // TYPE narrows the base list — pick one. Counts honour the active filter toggles,
        // so each badge answers "how many would I get if I clicked this".
        $typeItems = [
            ['clean',        'clean',         'Clean Inbox'],
            ['people',       'people',        'Correspondents'],
            ['newsletter',   'newsletters',   'Newsletters'],
            ['notification', 'notifications', 'Notifications'],
        ];
      ?>
      <div class="sidebar__section-header">Type</div>
      <?php foreach ($typeItems as [$tVal, $tIcon, $tLabel]): ?>
        <a href="<?= htmlspecialchars($urlType($tVal)) ?>" class="sidebar__item<?= $inInbox && $type === $tVal ? ' is-active' : '' ?>"><?= sidebarIcon($tIcon) ?> <?= $tLabel ?> <span class="sidebar__count"><?= MessageRepository::inboxUnread($tVal, $filterPinned, $filterAttachments, $activeAccountId) ?: '' ?></span></a>
      <?php endforeach; ?>

      <?php
        // FILTER narrows further — independent switches, combinable with each other and
        // with any type. No counts here: a toggle only needs a visible on/off state.
        $toggleItems = [
            ['pinned',      $urlPinned(), $inInbox && $filterPinned,      'Pinned'],
            ['attachment',  $urlAttach(), $inInbox && $filterAttachments, 'Attachments'],
        ];
      ?>
      <div class="sidebar__section-header">Filter</div>
      <?php foreach ($toggleItems as [$fIcon, $fUrl, $fOn, $fLabel]): ?>
        <a href="<?= htmlspecialchars($fUrl) ?>" class="sidebar__item sidebar__toggle<?= $fOn ? ' is-on' : '' ?>" role="switch" aria-checked="<?= $fOn ? 'true' : 'false' ?>"><?= sidebarIcon($fIcon) ?> <?= $fLabel ?> <span class="switch" aria-hidden="true"><span class="switch__knob"></span></span></a>
      <?php endforeach; ?>

      <div class="sidebar__section-header">Folder</div>
      <a href="<?= htmlspecialchars($buildUrl($activeAccountId, 'drafts')) ?>" class="sidebar__item<?= $view === 'drafts' ? ' is-active' : '' ?>"><?= sidebarIcon('drafts') ?> Drafts <span class="sidebar__count" id="drafts-count"><?= \Barua\Mail\DraftRepository::count($activeAccountId) ?: '' ?></span></a>
      <a href="<?= htmlspecialchars($buildUrl($activeAccountId, 'sent')) ?>" class="sidebar__item<?= $view === 'sent' ? ' is-active' : '' ?>"><?= sidebarIcon('sent') ?> Sent <span class="sidebar__count"><?= $sentCount ?: '' ?></span></a>
      <a href="<?= htmlspecialchars($buildUrl($activeAccountId, 'archive')) ?>" class="sidebar__item<?= $view === 'archive' ? ' is-active' : '' ?>"><?= sidebarIcon('archive') ?> Archive <span class="sidebar__count"><?= MessageRepository::roleCount('archive', $activeAccountId) ?: '' ?></span></a>
      <a href="<?= htmlspecialchars($buildUrl($activeAccountId, 'spam')) ?>" class="sidebar__item<?= $view === 'spam' ? ' is-active' : '' ?>"><?= sidebarIcon('spam') ?> Spam <span class="sidebar__count"><?= MessageRepository::roleCount('spam', $activeAccountId) ?: '' ?></span></a>
      <a href="<?= htmlspecialchars($buildUrl($activeAccountId, 'trash')) ?>" class="sidebar__item<?= $view === 'trash' ? ' is-active' : '' ?>"><?= sidebarIcon('trash') ?> Trash <span class="sidebar__count"><?= MessageRepository::roleCount('trash', $activeAccountId) ?: '' ?></span></a>
      </div>

    </div>

    <!-- Mail list -->
    <div class="mail-list-col">
      <div class="mail-list__header">
        <div class="mail-list__heading">
          <div class="icon-btn mobile-menu" data-go="sidebar" title="Menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></div>
          <div>
            <span class="mail-list__title"><?= htmlspecialchars($listTitle) ?></span>
            <span class="mail-list__subtitle"><?= htmlspecialchars($listSubtitle) ?></span>
          </div>
        </div>
        <div class="mail-list__icons">
          <?php if ($view === 'trash' || $view === 'spam'):
            $emptyCount = MessageRepository::roleCount($view, $activeAccountId);
            $emptyScope = $activeAccount ? $activeAccount['label'] : 'all accounts';
          ?>
          <button type="button" class="icon-btn icon-btn--danger" id="empty-folder"
                  title="Empty <?= ucfirst($view) ?>"
                  data-role="<?= htmlspecialchars($view) ?>"
                  data-account="<?= $activeAccountId !== null ? (int) $activeAccountId : '' ?>"
                  data-count="<?= (int) $emptyCount ?>"
                  data-label="<?= htmlspecialchars(ucfirst($view)) ?>"
                  data-scope="<?= htmlspecialchars($emptyScope) ?>"
                  <?= $emptyCount === 0 ? 'disabled' : '' ?>>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
          </button>
          <?php endif; ?>
          <div class="icon-btn" title="Search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
          <button type="button" id="sync-now" class="icon-btn" title="Sync now"><svg class="sync-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg></button>
          <div class="icon-btn" title="Compose"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg></div>
        </div>
      </div>

      <!-- Same markup serves both layouts: a wrapping row under the header on desktop, and
           on mobile a stack that pops out of the filter button. Mirrors the sidebar's two
           axes — the types select one at a time, the toggles stack on top. -->
      <div class="filter-pills" id="filter-pills">
        <a href="<?= htmlspecialchars($urlType('')) ?>" class="filter-pill<?= $inInbox && $type === '' ? ' is-active' : '' ?>"><?= sidebarIcon('inbox') ?> Inbox<?php if ($totalUnread): ?><span class="filter-pill__count"><?= (int) $totalUnread ?></span><?php endif; ?></a>
        <?php foreach ($typeItems as [$tVal, $tIcon, $tLabel]): ?>
          <?php $pcount = MessageRepository::inboxUnread($tVal, $filterPinned, $filterAttachments, $activeAccountId); ?>
          <a href="<?= htmlspecialchars($urlType($tVal)) ?>" class="filter-pill<?= $inInbox && $type === $tVal ? ' is-active' : '' ?>"><?= sidebarIcon($tIcon) ?> <?= $tLabel ?><?php if ($pcount): ?><span class="filter-pill__count"><?= (int) $pcount ?></span><?php endif; ?></a>
        <?php endforeach; ?>
        <!-- Toggles get their own row on desktop, so the two axes read as two groups
             instead of one long chain. On mobile this wrapper becomes display:contents,
             so the pop-out keeps one flat vertical stack. -->
        <div class="filter-pills__toggles">
          <?php foreach ($toggleItems as [$fIcon, $fUrl, $fOn, $fLabel]): ?>
            <a href="<?= htmlspecialchars($fUrl) ?>" class="filter-pill filter-pill--toggle<?= $fOn ? ' is-on' : '' ?>" role="switch" aria-checked="<?= $fOn ? 'true' : 'false' ?>"><?= sidebarIcon($fIcon) ?> <?= $fLabel ?><span class="switch" aria-hidden="true"><span class="switch__knob"></span></span></a>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="button" class="compose-fab" title="Compose" aria-label="Compose"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg></button>
      <button type="button" class="filter-fab<?= $inInbox && ($filterPinned || $filterAttachments) ? ' has-filters' : '' ?>" id="filter-fab" aria-label="Filter"><?= sidebarIcon('filter') ?></button>

      <?php if (empty($rows)): ?>
        <div class="list-empty">
          <?php if ($view === 'sent'): ?>
            No sent messages yet.
          <?php elseif ($view === 'archive'): ?>
            Nothing archived yet (within the sync window).
          <?php elseif ($view === 'trash'): ?>
            Trash is empty (within the sync window).
          <?php elseif ($view === 'spam'): ?>
            No spam (within the sync window).
          <?php elseif ($view === 'drafts'): ?>
            No drafts. Start writing in the composer — it autosaves here.
          <?php elseif ($type === '' && !$filterPinned && !$filterAttachments): ?>
            No messages yet. Click ⟳ to sync your accounts.
          <?php else: ?>
            <?php
              // With combinable narrowing, an empty list needs to name what emptied it —
              // otherwise it reads as a bug rather than a filter.
              $bits = [];
              if ($type !== '') { $bits[] = $typeLabels[$type]; }
              if ($filterPinned) { $bits[] = 'Pinned'; }
              if ($filterAttachments) { $bits[] = 'Attachments'; }
            ?>
            Nothing matches <strong><?= htmlspecialchars(implode(' + ', $bits)) ?></strong>.
            <a href="<?= htmlspecialchars($url($activeAccountId, 'inbox', '', false, false)) ?>">Clear all filters</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="mail-list__scroll" id="mail-list-scroll">
      <?php foreach ($groups as $groupName => $groupRows): ?>
        <div class="mail-list__date-group" data-group="<?= htmlspecialchars($groupName) ?>"><?= htmlspecialchars($groupName) ?></div>
        <?php foreach ($groupRows as $row):
          $isSelected = $selected && $row['id'] === $selected['id'];
          // Reuse the batched lookup above — its keys are exactly the messages that have
          // a real (non-inline) attachment.
          $row['has_real_attachments'] = isset($attachmentsByMessage[(int) $row['id']]);
          echo renderMailRow($row, $isDraftView, (bool) $isSelected);
        endforeach; ?>
      <?php endforeach; ?>
      </div>

      <!-- Live notification strip (new mail, later other events) -->
      <div class="list-notify" id="list-notify"></div>
    </div>

    <!-- Reader -->
    <div class="reader-col">
      <div class="mobile-back" data-go="list"><?= sidebarIcon('back') ?> Inbox</div>

      <!-- Empty state: shown until a message is opened, and again whenever the open
           message is deleted/archived/refiled out from under the reader. -->
      <div class="reader__empty" id="reader-empty"<?= $selected ? ' style="display:none"' : '' ?>>
        <svg class="reader__empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="m3.5 6.5 8.5 7 8.5-7"/></svg>
        <p class="reader__empty-text">Somewhere out there, a thousand words are being typed that no one needed to read.<br>This one can wait.</p>
      </div>

      <div id="reader-filled"<?= $selected ? '' : ' style="display:none"' ?>>
      <div class="reader__content">
        <h1 class="reader__subject" id="reader-subject"><?= htmlspecialchars($sel['subject'] !== '' ? $sel['subject'] : '(no subject)') ?></h1>
        <div class="reader__meta" id="reader-meta">
          <div class="reader__avatar" data-account="<?= (int) $sel['account_id'] ?>" style="background: <?= htmlspecialchars($sel['account_colour']) ?>">
            <?= htmlspecialchars(initial($sel)) ?>
          </div>
          <div>
            <div class="reader__from-name"><?= htmlspecialchars($sel['sender_name'] !== '' ? $sel['sender_name'] : $sel['sender_email']) ?></div>
            <div class="reader__from-email"><?= htmlspecialchars($sel['sender_email']) ?> · <?= htmlspecialchars($sel['account_label']) ?></div>
          </div>
          <div class="reader__time"><?= htmlspecialchars(MessageRepository::fullTimeLabel($sel['date_sent'])) ?></div>
        </div>
        <?php $selHasHtml = trim($sel['body_html'] ?? '') !== ''; ?>
        <!-- Action bar sits above the mail body and is ALWAYS visible — plain-text mail
             gets the same print / light-dark / ⋮ actions as HTML mail. Only the
             remote-image notice is HTML-specific and hides itself. -->
        <div class="reader__topbar">
          <div class="reader__imgbar" id="reader-imgbar"<?= $selHasHtml ? '' : ' style="display:none"' ?>>Remote images blocked · <span id="load-images">Load images</span></div>
          <div class="reader__floatbar" id="reader-floatbar">
            <button type="button" class="icon-btn reader-pin<?= (int) ($sel['is_starred'] ?? 0) === 1 ? ' is-pinned' : '' ?>" id="reader-pin" title="Pin"><?= rowActionIcon('pin') ?></button>
            <button type="button" class="icon-btn" id="reader-theme" title="Toggle light/dark"></button>
            <button type="button" class="icon-btn" id="reader-print" title="Print"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></button>
            <div class="reader__more">
              <button type="button" class="icon-btn" id="reader-more" title="More actions"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg></button>
              <div class="reader__menu" id="reader-menu">
                <button type="button" class="reader__menu-item" data-compose="reply"><?= sidebarIcon('reply') ?> Reply</button>
                <button type="button" class="reader__menu-item" data-compose="forward"><?= sidebarIcon('forward') ?> Forward</button>
                <div class="reader__menu-sep"></div>
                <button type="button" class="reader__menu-item" data-action="trash"><?= sidebarIcon('trash') ?> Delete</button>
                <button type="button" class="reader__menu-item" data-action="archive"><?= sidebarIcon('archive') ?> Archive</button>
                <button type="button" class="reader__menu-item" data-action="spam"><?= sidebarIcon('spam') ?> Mark as spam</button>
                <div class="reader__menu-sep"></div>
                <div class="reader__menu-label">Move to group</div>
                <button type="button" class="reader__menu-item" data-group="people"><?= sidebarIcon('people') ?> Correspondents</button>
                <button type="button" class="reader__menu-item" data-group="newsletter"><?= sidebarIcon('newsletters') ?> Newsletters</button>
                <button type="button" class="reader__menu-item" data-group="notification"><?= sidebarIcon('notifications') ?> Notifications</button>
              </div>
            </div>
          </div>
        </div>
        <div class="reader__body" id="reader-body"<?= $selHasHtml ? ' style="display:none"' : '' ?>><?php
          $selBody = $sel['body_plain'] !== '' ? $sel['body_plain'] : \Barua\Mail\HtmlMailRenderer::toText($sel['body_html'] ?? '');
          echo htmlspecialchars($selBody !== '' ? $selBody : '(No text content)');
        ?></div>
        <div class="reader__htmlwrap" id="reader-html"<?= $selHasHtml ? '' : ' style="display:none"' ?>>
          <!-- allow-same-origin lets the parent read the document height to size the frame
               to its content (see sizeReaderFrame). Deliberately WITHOUT allow-scripts —
               that pairing is the dangerous one. Scripts stay blocked three ways: no
               allow-scripts, the endpoint's CSP default-src 'none', and the sanitizer. -->
          <iframe class="reader__frame" id="reader-frame" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-modals"></iframe>
        </div>
        <!-- Below the mail body/iframe, not above — reachable via the reader's own
             scroll (.reader__content already scrolls as a whole). -->
        <div class="reader__attachments" id="reader-attachments"<?= empty($selectedAttachments) ? ' style="display:none"' : '' ?>>
          <?php foreach ($selectedAttachments as $att): ?>
            <div class="attachment-chip" data-att-id="<?= (int) $att['id'] ?>">
              <?= sidebarIcon('attachment') ?>
              <div class="attachment-chip__info">
                <span class="attachment-chip__name"><?= htmlspecialchars($att['filename']) ?></span>
                <span class="attachment-chip__size"><?= htmlspecialchars(formatBytes($att['size'])) ?></span>
              </div>
              <button type="button" class="attachment-chip__more" title="More"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg></button>
              <div class="attachment-chip__menu">
                <a class="attachment-chip__menu-item" href="/attachments/<?= (int) $att['id'] ?>">Download</a>
                <?php if ($att['previewable']): ?>
                  <a class="attachment-chip__menu-item" href="/attachments/<?= (int) $att['id'] ?>?preview=1" target="_blank" rel="noopener">Preview</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <!-- Conversation stack: the rest of this thread (incoming + your own Sent replies),
             newest-first by default. Filled client-side from /messages/{id}/thread. -->
        <div class="reader__thread" id="reader-thread" style="display:none">
          <div class="reader__thread-sep">
            <span class="reader__thread-title">Conversation · <span id="thread-count">0</span></span>
            <button type="button" class="reader__thread-order" id="thread-order"></button>
          </div>
          <div class="reader__thread-list" id="thread-list"></div>
        </div>
      </div>
      <div class="reader__toolbar">
        <button class="pill" id="reader-reply"><?= sidebarIcon('reply') ?> Reply</button>
        <button class="pill" id="reader-forward"><?= sidebarIcon('forward') ?> Forward</button>
        <button class="pill" id="reader-archive"><?= sidebarIcon('archive') ?> Archive</button>
      </div>
      </div>
    </div>
  </div>


  <?php require __DIR__ . '/settings_modal.php'; ?>
  <?php require __DIR__ . '/compose.php'; ?>

  <!-- Generic confirm dialog (replaces the browser's native confirm()). Text + the
       action button's label/danger styling are filled in by app.js per use. -->
  <div class="confirm-overlay" id="confirm-overlay" aria-hidden="true">
    <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
      <h2 class="confirm-modal__title" id="confirm-title"></h2>
      <p class="confirm-modal__text" id="confirm-text"></p>
      <div class="confirm-modal__actions">
        <button type="button" class="pill" id="confirm-cancel">Cancel</button>
        <button type="button" class="pill" id="confirm-ok"></button>
      </div>
    </div>
  </div>

  <?php // ---- JS bootstrap: all PHP-interpolated state lives here; external modules read window.Barua ---- ?>
  <script>
    window.Barua = {
      csrf:           <?= json_encode($csrfToken) ?>,
      view:           <?= json_encode($view) ?>,              // folder axis
      type:           <?= json_encode($type) ?>,              // type axis
      account:        <?= $activeAccountId !== null ? (int) $activeAccountId : 'null' ?>,
      filterPinned:   <?= $filterPinned ? 'true' : 'false' ?>,
      filterAttach:   <?= $filterAttachments ? 'true' : 'false' ?>,
      isDraftView:    <?= $isDraftView ? 'true' : 'false' ?>,
      totalUnread:    <?= (int) $totalUnread ?>,
      selectedId:     <?= $selected ? (int) $selected['id'] : 'null' ?>,
      selectedHasHtml:<?= $selected && trim($selected['body_html'] ?? '') !== '' ? 'true' : 'false' ?>,
      messages:       <?= json_encode($jsMessages, JSON_UNESCAPED_UNICODE) ?>,
      drafts:         <?= json_encode($jsDrafts, JSON_UNESCAPED_UNICODE) ?>,
      signatures:     <?= json_encode($composeSignatures, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="<?= asset('/js/app.js') ?>"></script>
  <script src="<?= asset('/js/compose.js') ?>"></script>
  <script src="<?= asset('/js/settings.js') ?>"></script>
</body>
</html>
