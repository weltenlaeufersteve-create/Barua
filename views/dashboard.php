<?php
// Dummy data for the static layout preview — real accounts/messages come with the next milestones.
$accounts = [
    ['id' => 1, 'name' => 'Steve — Weltenläufer', 'colour' => '#5B8DEF', 'unread' => 4],
    ['id' => 2, 'name' => 'Pepea NGO', 'colour' => '#4FAE7E', 'unread' => 1],
    ['id' => 3, 'name' => 'Moyo Reisen', 'colour' => '#F2994A', 'unread' => 0],
];

$messages = [
    [
        'group' => 'Today', 'account' => 1, 'sender' => 'Anna Berger', 'subject' => 'Re: Invoice for July',
        'preview' => 'Thanks, the invoice looks good — I will forward it to accounting today.',
        'time' => '10:42', 'unread' => true,
        'body' => "Hi Steve,\n\nThanks, the invoice looks good — I will forward it to accounting today.\n\nBest,\nAnna",
    ],
    [
        'group' => 'Today', 'account' => 2, 'sender' => 'Pepea Program Team', 'subject' => 'Activity report — June',
        'preview' => 'Attached is the June activity report for the Karatu project.',
        'time' => '09:15', 'unread' => true,
        'body' => "Hello,\n\nAttached is the June activity report for the Karatu project.\n\nRegards,\nPepea Team",
    ],
    [
        'group' => 'Yesterday', 'account' => 3, 'sender' => 'Moyo Reisen Booking', 'subject' => 'Your booking confirmation',
        'preview' => 'Your trip to Arusha has been confirmed for August.',
        'time' => '18:03', 'unread' => false,
        'body' => "Dear customer,\n\nYour trip to Arusha has been confirmed for August.\n\nSafe travels,\nMoyo Reisen",
    ],
    [
        'group' => 'Last week', 'account' => 1, 'sender' => 'Design Handoff Tool', 'subject' => 'New export ready',
        'preview' => 'A new design export "Unified Inbox v2" is ready for review.',
        'time' => 'Jul 9', 'unread' => false,
        'body' => "Hi,\n\nA new design export \"Unified Inbox v2\" is ready for review.\n\n— Handoff Bot",
    ],
];

$accountsById = array_column($accounts, null, 'id');
$selected = $messages[0];

$groups = [];
foreach ($messages as $i => $m) {
    $groups[$m['group']][] = $i + 1;
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
<body>
  <div class="app">
    <!-- Sidebar -->
    <div class="sidebar">
      <div class="sidebar__title">Barua</div>

      <a href="/" class="sidebar__item is-active">Inbox <span class="sidebar__count">5</span></a>

      <div class="sidebar__divider"></div>

      <?php foreach ($accounts as $acc): ?>
        <div class="sidebar__item">
          <span class="sidebar__dot" style="background: <?= htmlspecialchars($acc['colour']) ?>"></span>
          <?= htmlspecialchars($acc['name']) ?>
          <?php if ($acc['unread'] > 0): ?>
            <span class="sidebar__count"><?= $acc['unread'] ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="sidebar__divider"></div>

      <div class="sidebar__item">Pinned <span class="sidebar__count">2</span></div>
      <div class="sidebar__item">Drafts <span class="sidebar__count">0</span></div>
      <div class="sidebar__item">Sent</div>
      <div class="sidebar__item">Archive</div>
      <div class="sidebar__item">Spam</div>
      <div class="sidebar__item">Trash</div>

      <div class="sidebar__section-header">Groups</div>
      <div class="sidebar__item">People <span class="sidebar__count">3</span></div>
      <div class="sidebar__item">Newsletters <span class="sidebar__count">0</span></div>
      <div class="sidebar__item">Notifications <span class="sidebar__count">1</span></div>
      <div class="sidebar__item">Starred <span class="sidebar__count">0</span></div>

      <div class="sidebar__spacer"></div>
      <a href="#" class="sidebar__item">⚙ Settings</a>
      <a href="/logout" class="sidebar__item">Sign out</a>
    </div>

    <!-- Mail list -->
    <div class="mail-list-col">
      <div class="mail-list__header">
        <div>
          <span class="mail-list__title">Inbox</span>
          <span class="mail-list__subtitle">Focused list</span>
        </div>
        <div class="mail-list__icons">
          <div class="icon-btn" title="Search">🔍</div>
          <div class="icon-btn" title="Compose">✎</div>
        </div>
      </div>

      <?php foreach ($groups as $groupName => $indices): ?>
        <div class="mail-list__date-group"><?= htmlspecialchars($groupName) ?></div>
        <?php foreach ($indices as $i):
          $m = $messages[$i - 1];
          $acc = $accountsById[$m['account']];
          $isSelected = $i === 1;
        ?>
          <div class="mail-row<?= $m['unread'] ? ' is-unread' : '' ?><?= $isSelected ? ' is-selected' : '' ?>" data-msg="<?= $i ?>">
            <span class="mail-row__stripe" style="background: <?= htmlspecialchars($acc['colour']) ?>"></span>
            <div class="mail-row__body">
              <div class="mail-row__top">
                <span class="mail-row__sender"><?= htmlspecialchars($m['sender']) ?></span>
                <span class="mail-row__time"><?= htmlspecialchars($m['time']) ?></span>
              </div>
              <div class="mail-row__subject"><?= htmlspecialchars($m['subject']) ?></div>
              <div class="mail-row__preview"><?= htmlspecialchars($m['preview']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>

    <!-- Reader -->
    <div class="reader-col">
      <div class="reader__content">
        <h1 class="reader__subject" id="reader-subject"><?= htmlspecialchars($selected['subject']) ?></h1>
        <div class="reader__meta" id="reader-meta">
          <div class="reader__avatar" style="background: <?= htmlspecialchars($accountsById[$selected['account']]['colour']) ?>">
            <?= htmlspecialchars(mb_substr($selected['sender'], 0, 1)) ?>
          </div>
          <div>
            <div class="reader__from-name"><?= htmlspecialchars($selected['sender']) ?></div>
            <div class="reader__from-email"><?= htmlspecialchars($accountsById[$selected['account']]['name']) ?></div>
          </div>
          <div class="reader__time"><?= htmlspecialchars($selected['time']) ?></div>
        </div>
        <div class="reader__body" id="reader-body"><?= htmlspecialchars($selected['body']) ?></div>
      </div>
      <div class="reader__toolbar">
        <button class="pill">↩ Reply</button>
        <button class="pill">↪ Forward</button>
        <button class="pill">🗄 Archive</button>
      </div>
    </div>
  </div>

  <script>
    var messages = <?= json_encode(array_map(function ($m, $i) use ($accountsById) {
        return [
            'id' => $i + 1,
            'subject' => $m['subject'],
            'sender' => $m['sender'],
            'accountName' => $accountsById[$m['account']]['name'],
            'accountColour' => $accountsById[$m['account']]['colour'],
            'time' => $m['time'],
            'body' => $m['body'],
        ];
    }, $messages, array_keys($messages))) ?>;

    document.querySelectorAll('.mail-row').forEach(function (row) {
      row.addEventListener('click', function () {
        document.querySelectorAll('.mail-row').forEach(function (r) { r.classList.remove('is-selected'); });
        row.classList.add('is-selected');

        var msg = messages[parseInt(row.dataset.msg, 10) - 1];
        document.getElementById('reader-subject').textContent = msg.subject;
        document.getElementById('reader-body').textContent = msg.body;
        var meta = document.getElementById('reader-meta');
        meta.querySelector('.reader__avatar').style.background = msg.accountColour;
        meta.querySelector('.reader__avatar').textContent = msg.sender.charAt(0);
        meta.querySelector('.reader__from-name').textContent = msg.sender;
        meta.querySelector('.reader__from-email').textContent = msg.accountName;
        meta.querySelector('.reader__time').textContent = msg.time;
      });
    });
  </script>
</body>
</html>
