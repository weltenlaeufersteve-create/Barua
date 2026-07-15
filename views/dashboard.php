<?php
use Barua\Mail\MessageRepository;

$activeAccountId = $activeAccountId ?? null;
$accounts = MessageRepository::accountsWithUnread();
$totalUnread = MessageRepository::totalUnread();
$rows = MessageRepository::unifiedInbox(100, $activeAccountId);

// Header title: account label when filtered, else "Inbox".
$activeAccount = null;
if ($activeAccountId !== null) {
    foreach ($accounts as $a) {
        if ((int) $a['id'] === $activeAccountId) { $activeAccount = $a; break; }
    }
}
$listTitle = $activeAccount ? $activeAccount['label'] : 'Inbox';
$listSubtitle = $activeAccount ? $activeAccount['email'] : 'Unified';

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

      <a href="/" class="sidebar__item<?= $activeAccount === null ? ' is-active' : '' ?>">Inbox <span class="sidebar__count"><?= $totalUnread ?: '' ?></span></a>

      <div class="sidebar__divider"></div>

      <?php foreach ($accounts as $acc): ?>
        <a href="/?account=<?= (int) $acc['id'] ?>" class="sidebar__item<?= $activeAccount && (int) $activeAccount['id'] === (int) $acc['id'] ? ' is-active' : '' ?>">
          <span class="account-avatar" style="background: <?= htmlspecialchars($acc['colour']) ?>"><?= htmlspecialchars(mb_strtoupper(mb_substr($acc['label'], 0, 1))) ?></span>
          <?= htmlspecialchars($acc['label']) ?>
          <?php if ((int) $acc['unread'] > 0): ?>
            <span class="sidebar__count"><?= (int) $acc['unread'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <div class="sidebar__divider"></div>

      <div class="sidebar__item">Pinned</div>
      <div class="sidebar__item">Drafts</div>
      <div class="sidebar__item">Sent</div>
      <div class="sidebar__item">Archive</div>
      <div class="sidebar__item">Spam</div>
      <div class="sidebar__item">Trash</div>

      <div class="sidebar__section-header">Groups</div>
      <div class="sidebar__item">People</div>
      <div class="sidebar__item">Newsletters</div>
      <div class="sidebar__item">Notifications</div>
      <div class="sidebar__item">Starred</div>

      <div class="sidebar__spacer"></div>
      <div class="sidebar__item" id="open-settings">⚙ Settings</div>
      <a href="/logout" class="sidebar__item">Sign out</a>
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
          No messages yet. Click ⟳ to sync your accounts.
        </div>
      <?php endif; ?>

      <?php foreach ($groups as $groupName => $groupRows): ?>
        <div class="mail-list__date-group"><?= htmlspecialchars($groupName) ?></div>
        <?php foreach ($groupRows as $row):
          $isUnread = (int) $row['is_read'] === 0;
          $isSelected = $selected && $row['id'] === $selected['id'];
        ?>
          <div class="mail-row<?= $isUnread ? ' is-unread' : '' ?><?= $isSelected ? ' is-selected' : '' ?>" data-msg="<?= (int) $row['id'] ?>" data-account="<?= (int) $row['account_id'] ?>">
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
        <button class="pill">↩ Reply</button>
        <button class="pill">↪ Forward</button>
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

    document.querySelectorAll('.mail-row').forEach(function (row) {
      row.addEventListener('click', function () {
        document.querySelectorAll('.mail-row').forEach(function (r) { r.classList.remove('is-selected'); });
        row.classList.add('is-selected');
        row.classList.remove('is-unread');

        var msg = messages[row.dataset.msg];
        if (!msg) return;
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
  </script>

  <?php require __DIR__ . '/settings_modal.php'; ?>
</body>
</html>
