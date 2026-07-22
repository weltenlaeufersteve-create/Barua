<?php
$composeAccounts = \Barua\Accounts\AccountRepository::all();
// Map each account to its assigned signature. The compose editor is plain text, so we
// always carry a `plain` form for the textarea; `html` (non-empty only for HTML sigs)
// is spliced back in on send so the recipient gets the real HTML signature.
$composeSignatures = [];
foreach ($composeAccounts as $ca) {
    $sig = null;
    if (!empty($ca['signature_id'])) {
        $sig = \Barua\Mail\SignatureRepository::find((int) $ca['signature_id']);
    }
    if ($sig === null) {
        $composeSignatures[(int) $ca['id']] = ['format' => 'plain', 'plain' => '', 'html' => ''];
        continue;
    }
    if ($sig['format'] === 'html') {
        // Readable plain rendering for the textarea: drop tags, collapse <br> to newlines.
        $plain = trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $sig['body'])), ENT_QUOTES, 'UTF-8'));
        $composeSignatures[(int) $ca['id']] = ['format' => 'html', 'plain' => $plain, 'html' => $sig['body']];
    } else {
        $composeSignatures[(int) $ca['id']] = ['format' => 'plain', 'plain' => $sig['body'], 'html' => ''];
    }
}
?>

<div class="compose-overlay" id="compose-panel">
  <div class="compose-accounts">
    <div class="compose-accounts__title">From</div>
    <?php foreach ($composeAccounts as $ca): ?>
      <div class="compose-account" data-account-id="<?= (int) $ca['id'] ?>" data-colour="<?= htmlspecialchars($ca['colour']) ?>">
        <span class="compose-account__dot" style="background: <?= htmlspecialchars($ca['colour']) ?>"></span>
        <div class="compose-account__meta">
          <div class="compose-account__name"><?= htmlspecialchars($ca['label']) ?></div>
          <div class="compose-account__email"><?= htmlspecialchars($ca['email']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="compose-main">
    <div class="compose-main__header">
      <h2 id="compose-title">New email</h2>
      <div class="compose-main__spacer"></div>
      <span class="compose__status" id="compose-status"></span>
      <button class="compose__send" id="compose-send"><svg class="compose__send-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Send</button>
      <span class="compose__close" id="compose-min" title="Minimize / expand">⤡</span>
      <span class="compose__close" id="compose-close" title="Close">✕</span>
    </div>

    <div class="compose-fields">
      <div class="compose__from-mini-wrap">
        <div class="compose__from-mini" id="compose-from-mini" title="Change sender">
          <span class="compose-account__dot" id="mini-dot"></span>
          <span id="mini-name"></span>
          <span class="compose__from-caret">▾</span>
        </div>
        <div class="compose__from-dropdown" id="compose-from-dropdown">
          <?php foreach ($composeAccounts as $ca): ?>
            <div class="compose__from-option" data-account-id="<?= (int) $ca['id'] ?>" data-colour="<?= htmlspecialchars($ca['colour']) ?>">
              <span class="compose-account__dot" style="background: <?= htmlspecialchars($ca['colour']) ?>"></span>
              <div class="compose-account__meta">
                <div class="compose-account__name"><?= htmlspecialchars($ca['label']) ?></div>
                <div class="compose-account__email"><?= htmlspecialchars($ca['email']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="compose__row">
        <label>To</label>
        <input type="text" id="compose-to" placeholder="recipient@example.com">
        <span class="compose__cc-toggle" id="compose-cc-toggle">Cc/Bcc</span>
      </div>
      <div class="compose__row" id="compose-cc-row" style="display:none;"><label>Cc</label><input type="text" id="compose-cc"></div>
      <div class="compose__row" id="compose-bcc-row" style="display:none;"><label>Bcc</label><input type="text" id="compose-bcc"></div>
      <div class="compose__row"><label>Subject</label><input type="text" id="compose-subject"></div>
    </div>

    <textarea class="compose__textarea" id="compose-textarea" placeholder="Write your message…"></textarea>

    <div class="compose__attachments" id="compose-attachments"></div>

    <div class="compose-main__toolbar">
      <label class="icon-btn" id="compose-attach-btn" title="Attach file"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg><input type="file" id="compose-file-input" multiple hidden></label>
      <div class="icon-btn" title="Insert image (coming soon)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>
      <span class="compose__draft-status" id="compose-draft-status"></span>
    </div>
  </div>
</div>

