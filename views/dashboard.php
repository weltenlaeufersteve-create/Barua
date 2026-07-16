<?php
use Barua\Mail\MessageRepository;

$activeAccountId = $activeAccountId ?? null;
$view = $view ?? 'inbox';
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

// Build a sidebar URL for a given scope (account or null=all) + folder view.
$buildUrl = function (?int $account, string $folderView): string {
    $params = [];
    if ($account !== null) { $params['account'] = $account; }
    if ($folderView !== 'inbox') { $params['view'] = $folderView; }
    return '/' . (empty($params) ? '' : '?' . http_build_query($params));
};

// Folder view = scope (all / account) × folder (inbox / sent / pinned).
$pinnedCount = MessageRepository::pinnedCount($activeAccountId);
if ($view === 'sent') {
    $rows = MessageRepository::sentMessages(100, $activeAccountId);
    $listTitle = $activeAccount ? $activeAccount['label'] : 'Sent';
    $listSubtitle = $activeAccount ? 'Sent' : 'All accounts';
} elseif ($view === 'pinned') {
    $rows = MessageRepository::pinnedMessages(100, $activeAccountId);
    $listTitle = $activeAccount ? $activeAccount['label'] : 'Pinned';
    $listSubtitle = $activeAccount ? 'Pinned' : 'All accounts';
} elseif ($view === 'archive' || $view === 'trash') {
    $rows = MessageRepository::roleMessages($view, 100, $activeAccountId);
    $roleLabel = $view === 'archive' ? 'Archive' : 'Trash';
    $listTitle = $activeAccount ? $activeAccount['label'] : $roleLabel;
    $listSubtitle = $activeAccount ? $roleLabel : 'All accounts';
} else {
    $rows = MessageRepository::unifiedInbox(100, $activeAccountId);
    $listTitle = $activeAccount ? $activeAccount['label'] : 'Inbox';
    $listSubtitle = $activeAccount ? $activeAccount['email'] : 'Unified';
}

// Group rows by human date label, preserving date-desc order.
$groups = [];
foreach ($rows as $row) {
    $groups[MessageRepository::dateGroup($row['date_sent'])][] = $row;
}

$selected = $rows[0] ?? null;

function initial(array $row): string
{
    $base = $row['sender_name'] !== '' ? $row['sender_name'] : $row['sender_email'];
    return mb_strtoupper(mb_substr($base, 0, 1)) ?: '?';
}

/** Inline stroke SVG icons for the sidebar (currentColor, 16px). */
function sidebarIcon(string $name): string
{
    $paths = [
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
        'starred'       => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'settings'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'logout'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    ];
    $inner = $paths[$name] ?? '';
    return '<svg class="sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
}

// Compact JSON map for the reader pane (client-side swap on row click).
$jsMessages = [];
foreach ($rows as $row) {
    $body = $row['body_plain'] !== '' ? $row['body_plain'] : trim(strip_tags($row['body_html'] ?? ''));
    $jsMessages[(int) $row['id']] = [
        'subject'       => $row['subject'],
        'sender'        => $row['sender_name'] !== '' ? $row['sender_name'] : $row['sender_email'],
        'email'         => $row['sender_email'],
        'accountId'     => (int) $row['account_id'],
        'accountLabel'  => $row['account_label'],
        'accountColour' => $row['account_colour'],
        'messageId'     => $row['message_id'] ?? '',
        'time'          => MessageRepository::timeLabel($row['date_sent']),
        'initial'       => initial($row),
        'body'          => $body !== '' ? $body : '(No text content)',
    ];
}
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
<link rel="stylesheet" href="/css/theme.css">
<link rel="stylesheet" href="/css/app.css">
<link rel="stylesheet" href="/css/inbox.css">
</head>
<body data-mobile-view="list">
  <div class="app">
    <!-- Sidebar -->
    <div class="sidebar">
      <div class="mobile-back" data-go="list">‹ Inbox</div>
      <div class="sidebar__title">Barua</div>

      <a href="/" class="sidebar__item<?= $activeAccount === null ? ' is-active' : '' ?>"><?= sidebarIcon('inbox') ?> Inbox <span class="sidebar__count"><?= $totalUnread ?: '' ?></span></a>

      <div class="sidebar__divider"></div>

      <?php foreach ($accounts as $acc): ?>
        <a href="<?= htmlspecialchars($buildUrl((int) $acc['id'], $view)) ?>" data-account="<?= (int) $acc['id'] ?>" class="sidebar__item<?= $activeAccount && (int) $activeAccount['id'] === (int) $acc['id'] ? ' is-active' : '' ?>">
          <span class="account-avatar" style="background: <?= htmlspecialchars($acc['colour']) ?>"><?= htmlspecialchars(mb_strtoupper(mb_substr($acc['label'], 0, 1))) ?></span>
          <?= htmlspecialchars($acc['label']) ?>
          <?php if ((int) $acc['unread'] > 0): ?>
            <span class="sidebar__count"><?= (int) $acc['unread'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <div class="sidebar__divider"></div>

      <a href="<?= htmlspecialchars($buildUrl($activeAccountId, 'pinned')) ?>" class="sidebar__item<?= $view === 'pinned' ? ' is-active' : '' ?>"><?= sidebarIcon('pinned') ?> Pinned <span class="sidebar__count" id="pinned-count"><?= $pinnedCount ?: '' ?></span></a>
      <div class="sidebar__item"><?= sidebarIcon('drafts') ?> Drafts</div>
      <a href="<?= htmlspecialchars($buildUrl($activeAccountId, 'sent')) ?>" class="sidebar__item<?= $view === 'sent' ? ' is-active' : '' ?>"><?= sidebarIcon('sent') ?> Sent <span class="sidebar__count"><?= $sentCount ?: '' ?></span></a>
      <a href="<?= htmlspecialchars($buildUrl($activeAccountId, 'archive')) ?>" class="sidebar__item<?= $view === 'archive' ? ' is-active' : '' ?>"><?= sidebarIcon('archive') ?> Archive <span class="sidebar__count"><?= MessageRepository::roleCount('archive', $activeAccountId) ?: '' ?></span></a>
      <div class="sidebar__item"><?= sidebarIcon('spam') ?> Spam</div>
      <a href="<?= htmlspecialchars($buildUrl($activeAccountId, 'trash')) ?>" class="sidebar__item<?= $view === 'trash' ? ' is-active' : '' ?>"><?= sidebarIcon('trash') ?> Trash <span class="sidebar__count"><?= MessageRepository::roleCount('trash', $activeAccountId) ?: '' ?></span></a>

      <div class="sidebar__section-header">Groups</div>
      <div class="sidebar__item"><?= sidebarIcon('people') ?> People</div>
      <div class="sidebar__item"><?= sidebarIcon('newsletters') ?> Newsletters</div>
      <div class="sidebar__item"><?= sidebarIcon('notifications') ?> Notifications</div>

      <div class="sidebar__spacer"></div>
      <div class="sidebar__divider"></div>
      <div class="sidebar__item" id="open-settings"><?= sidebarIcon('settings') ?> Settings</div>
      <a href="/logout" class="sidebar__item"><?= sidebarIcon('logout') ?> Sign out</a>
    </div>

    <!-- Mail list -->
    <div class="mail-list-col">
      <div class="mail-list__header">
        <div>
          <span class="mail-list__title"><?= htmlspecialchars($listTitle) ?></span>
          <span class="mail-list__subtitle"><?= htmlspecialchars($listSubtitle) ?></span>
        </div>
        <div class="mail-list__icons">
          <div class="icon-btn mobile-menu" data-go="sidebar" title="Menu">☰</div>
          <form method="post" action="/sync" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <?php if ($activeAccount): ?>
              <input type="hidden" name="return_account" value="<?= (int) $activeAccount['id'] ?>">
            <?php endif; ?>
            <button type="submit" class="icon-btn" title="Sync now" style="border:none;background:transparent;cursor:pointer;">⟳</button>
          </form>
          <div class="icon-btn" title="Compose">✎</div>
        </div>
      </div>

      <?php if (empty($rows)): ?>
        <div style="padding: 24px 20px; color: var(--text-tertiary); font-size: 13.5px;">
          <?php if ($view === 'sent'): ?>
            No sent messages yet.
          <?php elseif ($view === 'pinned'): ?>
            No pinned messages. Pin mail on any device (IMAP flag) and it shows up here.
          <?php elseif ($view === 'archive'): ?>
            Nothing archived yet (within the sync window).
          <?php elseif ($view === 'trash'): ?>
            Trash is empty (within the sync window).
          <?php else: ?>
            No messages yet. Click ⟳ to sync your accounts.
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php foreach ($groups as $groupName => $groupRows): ?>
        <div class="mail-list__date-group"><?= htmlspecialchars($groupName) ?></div>
        <?php foreach ($groupRows as $row):
          $isUnread = (int) $row['is_read'] === 0;
          $isSelected = $selected && $row['id'] === $selected['id'];
        ?>
          <div class="mail-row<?= $isUnread ? ' is-unread' : '' ?><?= $isSelected ? ' is-selected' : '' ?>" data-msg="<?= (int) $row['id'] ?>" data-account="<?= (int) $row['account_id'] ?>">
            <div class="mail-row__actions">
              <span class="row-action row-action--pin<?= (int) $row['is_starred'] === 1 ? ' is-pinned' : '' ?>" title="Pin"><?= sidebarIcon('pinned') ?></span>
              <span class="row-action" title="Archive"><?= sidebarIcon('archive') ?></span>
              <span class="row-action" title="Delete"><?= sidebarIcon('trash') ?></span>
            </div>
            <span class="mail-row__stripe" style="background: <?= htmlspecialchars($row['account_colour']) ?>"></span>
            <div class="mail-row__body">
              <div class="mail-row__top">
                <span class="mail-row__sender"><?= htmlspecialchars($row['sender_name'] !== '' ? $row['sender_name'] : $row['sender_email']) ?></span>
                <span class="mail-row__time"><?= htmlspecialchars(MessageRepository::timeLabel($row['date_sent'])) ?></span>
              </div>
              <div class="mail-row__subject"><?= htmlspecialchars($row['subject'] !== '' ? $row['subject'] : '(no subject)') ?></div>
              <div class="mail-row__preview"><?= htmlspecialchars($row['body_snippet'] ?? '') ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>

    <!-- Reader -->
    <div class="reader-col">
      <div class="mobile-back" data-go="list">‹ Inbox</div>
      <?php if ($selected): ?>
      <div class="reader__content">
        <h1 class="reader__subject" id="reader-subject"><?= htmlspecialchars($selected['subject'] !== '' ? $selected['subject'] : '(no subject)') ?></h1>
        <div class="reader__meta" id="reader-meta">
          <div class="reader__avatar" data-account="<?= (int) $selected['account_id'] ?>" style="background: <?= htmlspecialchars($selected['account_colour']) ?>">
            <?= htmlspecialchars(initial($selected)) ?>
          </div>
          <div>
            <div class="reader__from-name"><?= htmlspecialchars($selected['sender_name'] !== '' ? $selected['sender_name'] : $selected['sender_email']) ?></div>
            <div class="reader__from-email"><?= htmlspecialchars($selected['sender_email']) ?> · <?= htmlspecialchars($selected['account_label']) ?></div>
          </div>
          <div class="reader__time"><?= htmlspecialchars(MessageRepository::timeLabel($selected['date_sent'])) ?></div>
        </div>
        <div class="reader__body" id="reader-body"><?php
          $selBody = $selected['body_plain'] !== '' ? $selected['body_plain'] : trim(strip_tags($selected['body_html'] ?? ''));
          echo htmlspecialchars($selBody !== '' ? $selBody : '(No text content)');
        ?></div>
      </div>
      <div class="reader__toolbar">
        <button class="pill" id="reader-reply">↩ Reply</button>
        <button class="pill" id="reader-forward">↪ Forward</button>
        <button class="pill">🗄 Archive</button>
      </div>
      <?php else: ?>
      <div class="reader__content" style="color: var(--text-tertiary); font-size: 14px;">
        Select a message to read it.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    var messages = <?= json_encode($jsMessages, JSON_UNESCAPED_UNICODE) ?>;
    var currentMsgId = <?= $selected ? (int) $selected['id'] : 'null' ?>;

    document.querySelectorAll('.mail-row').forEach(function (row) {
      row.addEventListener('click', function () {
        document.querySelectorAll('.mail-row').forEach(function (r) { r.classList.remove('is-selected'); });
        row.classList.add('is-selected');
        row.classList.remove('is-unread');

        var msg = messages[row.dataset.msg];
        if (!msg) return;
        currentMsgId = parseInt(row.dataset.msg, 10);
        document.getElementById('reader-subject').textContent = msg.subject || '(no subject)';
        document.getElementById('reader-body').textContent = msg.body;
        var meta = document.getElementById('reader-meta');
        var avatar = meta.querySelector('.reader__avatar');
        avatar.style.background = msg.accountColour;
        avatar.textContent = msg.initial;
        avatar.setAttribute('data-account', msg.accountId);
        meta.querySelector('.reader__from-name').textContent = msg.sender;
        meta.querySelector('.reader__from-email').textContent = msg.email + ' · ' + msg.accountLabel;
        meta.querySelector('.reader__time').textContent = msg.time;

        document.body.setAttribute('data-mobile-view', 'reader');
      });
    });

    // Row actions: pin toggle (IMAP \Flagged), archive, trash — server write + cache + UI.
    var mainCsrf = <?= json_encode($csrfToken) ?>;
    var currentView = <?= json_encode($view) ?>;

    function msgAction(id, action, data, cb) {
      var body = new URLSearchParams();
      body.set('csrf_token', mainCsrf);
      Object.keys(data || {}).forEach(function (k) { body.set(k, data[k]); });
      fetch('/messages/' + id + '/' + action, { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(cb)
        .catch(function () { cb({ ok: false, error: 'Network error' }); });
    }

    function bumpPinnedBadge(delta) {
      var badge = document.getElementById('pinned-count');
      if (!badge) return;
      var n = (parseInt(badge.textContent, 10) || 0) + delta;
      badge.textContent = n > 0 ? n : '';
    }

    function removeRow(row) {
      row.style.transition = 'opacity 0.15s ease';
      row.style.opacity = '0';
      setTimeout(function () { row.remove(); }, 150);
    }

    document.querySelectorAll('.mail-row').forEach(function (row) {
      var id = row.dataset.msg;
      var pinBtn = row.querySelector('.row-action--pin');
      var archBtn = row.querySelector('.row-action[title="Archive"]');
      var trashBtn = row.querySelector('.row-action[title="Delete"]');

      if (pinBtn) pinBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var nowPinned = !pinBtn.classList.contains('is-pinned');
        pinBtn.classList.toggle('is-pinned', nowPinned); // optimistic
        msgAction(id, 'pin', { pinned: nowPinned ? '1' : '0' }, function (res) {
          if (!res.ok) { pinBtn.classList.toggle('is-pinned', !nowPinned); return; }
          bumpPinnedBadge(nowPinned ? 1 : -1);
          if (!nowPinned && currentView === 'pinned') removeRow(row);
        });
      });

      if (archBtn) archBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        msgAction(id, 'archive', {}, function (res) {
          if (res.ok) removeRow(row);
        });
      });

      if (trashBtn) trashBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        msgAction(id, 'trash', {}, function (res) {
          if (res.ok) removeRow(row);
        });
      });
    });

    // Reader toolbar: archive the currently open message.
    var readerArchive = document.querySelector('.reader__toolbar .pill:not(#reader-reply):not(#reader-forward)');
    if (readerArchive) readerArchive.addEventListener('click', function () {
      if (!currentMsgId) return;
      msgAction(currentMsgId, 'archive', {}, function (res) {
        if (!res.ok) return;
        var row = document.querySelector('.mail-row[data-msg="' + currentMsgId + '"]');
        if (row) removeRow(row);
        document.body.setAttribute('data-mobile-view', 'list');
      });
    });

    document.querySelectorAll('[data-go]').forEach(function (el) {
      el.addEventListener('click', function () {
        document.body.setAttribute('data-mobile-view', el.dataset.go);
      });
    });
    document.querySelectorAll('.sidebar > div.sidebar__item:not(#open-settings)').forEach(function (el) {
      el.addEventListener('click', function () {
        document.body.setAttribute('data-mobile-view', 'list');
      });
    });

    // Reply / Forward → open the compose panel prefilled.
    function quote(msg) {
      return '\n\n\n----- Original message -----\n' +
        'From: ' + msg.sender + ' <' + msg.email + '>\n' +
        'Subject: ' + (msg.subject || '') + '\n\n' +
        msg.body.split('\n').map(function (l) { return '> ' + l; }).join('\n');
    }
    var replyBtn = document.getElementById('reader-reply');
    if (replyBtn) replyBtn.addEventListener('click', function () {
      var msg = messages[currentMsgId];
      if (!msg || !window.baruaCompose) return;
      var subj = /^re:/i.test(msg.subject || '') ? msg.subject : 'Re: ' + (msg.subject || '');
      window.baruaCompose({
        title: 'Reply', fromAccount: msg.accountId, to: msg.email,
        subject: subj, body: quote(msg), inReplyTo: msg.messageId, references: msg.messageId
      });
    });
    var fwdBtn = document.getElementById('reader-forward');
    if (fwdBtn) fwdBtn.addEventListener('click', function () {
      var msg = messages[currentMsgId];
      if (!msg || !window.baruaCompose) return;
      var subj = /^fwd?:/i.test(msg.subject || '') ? msg.subject : 'Fwd: ' + (msg.subject || '');
      window.baruaCompose({
        title: 'Forward', fromAccount: msg.accountId, to: '',
        subject: subj, body: quote(msg)
      });
    });
  </script>

  <?php require __DIR__ . '/settings_modal.php'; ?>
  <?php require __DIR__ . '/compose.php'; ?>
</body>
</html>
