<?php
$composeAccounts = \Barua\Accounts\AccountRepository::all();
$composeSignatures = [];
foreach ($composeAccounts as $ca) {
    $composeSignatures[(int) $ca['id']] = $ca['signature'] ?? '';
}
?>
<style>
  .compose-overlay {
    position: fixed;
    inset: 0;
    background: var(--bg);
    display: none;
    z-index: 200;
  }
  .compose-overlay.is-open { display: flex; }

  /* Left column: account (sender) selection, mirrors the main sidebar. */
  .compose-accounts {
    width: 260px;
    flex-shrink: 0;
    background: var(--sidebar-bg);
    border-right: 1px solid var(--border);
    padding: 18px 12px;
    overflow-y: auto;
  }
  .compose-accounts__title {
    font-size: 11px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;
    color: var(--text-tertiary); padding: 4px 8px 12px;
  }
  .compose-account {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 10px; border-radius: var(--radius-sm); cursor: pointer;
  }
  .compose-account:hover { background: var(--hover-bg); }
  .compose-account.is-active { background: var(--selected-bg); }
  .compose-account__dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
  .compose-account__meta { min-width: 0; }
  .compose-account__name { font-size: 13px; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .compose-account__email { font-size: 11.5px; color: var(--text-tertiary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

  /* Right column: the message. */
  .compose-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
  .compose-main__header {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 24px; border-bottom: 1px solid var(--border);
  }
  .compose-main__header h2 { font-size: 17px; font-weight: 700; margin: 0; }
  .compose-main__spacer { flex: 1; }
  .compose__send {
    background: var(--hover-bg); border: 1.5px solid var(--border); color: var(--text-primary);
    font-weight: 600; border-radius: 999px; padding: 9px 22px; font-size: 13.5px; cursor: pointer;
  }
  .compose__send:hover { background: var(--selected-bg); }
  .compose__send:disabled { opacity: 0.5; cursor: default; }
  .compose__status { font-size: 12.5px; color: var(--text-tertiary); }
  .compose__status.is-error { color: var(--acc-pink); }
  .compose__close {
    cursor: pointer; color: var(--text-secondary); font-size: 20px; line-height: 1;
    width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
    border-radius: var(--radius-sm);
  }
  .compose__close:hover { background: var(--hover-bg); color: var(--text-primary); }

  .compose-fields { padding: 0 24px; }
  .compose__row {
    display: flex; align-items: center; gap: 8px;
    border-bottom: 1px solid var(--border); padding: 12px 0;
  }
  .compose__row label { font-size: 12.5px; color: var(--text-tertiary); width: 60px; flex-shrink: 0; }
  .compose__row input {
    flex: 1; border: none; background: transparent; color: var(--text-primary);
    font-size: 14px; outline: none;
  }
  .compose__cc-toggle { font-size: 12px; color: var(--text-tertiary); cursor: pointer; }

  .compose__textarea {
    flex: 1; border: none; background: transparent; color: var(--text-primary);
    font-size: 14.5px; line-height: 1.65; resize: none; outline: none;
    padding: 20px 24px; font-family: var(--font-sans);
  }

  .compose-main__toolbar {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 24px; border-top: 1px solid var(--border);
  }
  .compose-main__toolbar .icon-btn {
    width: 34px; height: 34px; border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary); cursor: pointer;
  }
  .compose-main__toolbar .icon-btn:hover { background: var(--hover-bg); }

  /* Minimized: compact panel bottom-right, sender column hidden. */
  .compose-overlay.is-min {
    inset: auto;
    right: 20px; bottom: 0;
    width: 520px; max-width: calc(100vw - 40px);
    height: 540px; max-height: calc(100vh - 40px);
    border: 1px solid var(--border); border-bottom: none;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
  }
  .compose-overlay.is-min .compose-accounts { display: none; }
  .compose-overlay.is-min .compose-main__header { padding: 12px 16px; }
  .compose-overlay.is-min .compose-main__header h2 { font-size: 14px; }
  .compose-overlay.is-min .compose-fields { padding: 0 16px; }
  .compose-overlay.is-min .compose__textarea { padding: 14px 16px; }
  .compose-overlay.is-min .compose-main__toolbar { padding: 10px 16px; }

  .compose__from-mini {
    display: none; align-items: center; gap: 8px;
    padding: 10px 0; border-bottom: 1px solid var(--border);
    font-size: 13px; color: var(--text-secondary); cursor: pointer;
  }
  .compose-overlay.is-min .compose__from-mini { display: flex; }

  @media (max-width: 900px) {
    .compose-accounts { display: none; }
    .compose__from-mini { display: flex !important; }
  }
</style>

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
      <button class="compose__send" id="compose-send">Send</button>
      <span class="compose__close" id="compose-min" title="Minimize / expand">⤡</span>
      <span class="compose__close" id="compose-close" title="Close">✕</span>
    </div>

    <div class="compose-fields">
      <div class="compose__from-mini" id="compose-from-mini" title="Change sender (expand)">
        <span class="compose-account__dot" id="mini-dot"></span>
        <span id="mini-name"></span>
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

    <div class="compose-main__toolbar">
      <div class="icon-btn" title="Attach file (coming soon)">📎</div>
      <div class="icon-btn" title="Insert image (coming soon)">🖼</div>
    </div>
  </div>
</div>

<script>
  (function () {
    var panel = document.getElementById('compose-panel');
    var accountRows = panel.querySelectorAll('.compose-account');
    var toI = document.getElementById('compose-to');
    var ccI = document.getElementById('compose-cc');
    var bccI = document.getElementById('compose-bcc');
    var subjI = document.getElementById('compose-subject');
    var bodyI = document.getElementById('compose-textarea');
    var statusEl = document.getElementById('compose-status');
    var titleEl = document.getElementById('compose-title');
    var sendBtn = document.getElementById('compose-send');
    var signatures = <?= json_encode($composeSignatures, JSON_UNESCAPED_UNICODE) ?>;
    var csrf = <?= json_encode($csrfToken) ?>;
    var currentInReplyTo = '';
    var currentReferences = '';
    var currentFromId = null;

    function sigFor(accountId) {
      var s = signatures[accountId];
      return s ? ('\n\n-- \n' + s) : '';
    }

    function selectAccount(accountId, keepBody) {
      var row = panel.querySelector('.compose-account[data-account-id="' + accountId + '"]');
      if (!row) row = accountRows[0];
      if (!row) return;
      accountRows.forEach(function (r) { r.classList.remove('is-active'); });
      row.classList.add('is-active');
      currentFromId = row.dataset.accountId;
      // Send button border follows the account colour (outline, light theme fill).
      sendBtn.style.borderColor = row.dataset.colour;
      // Mini-mode sender indicator.
      document.getElementById('mini-dot').style.background = row.dataset.colour;
      document.getElementById('mini-name').textContent = row.querySelector('.compose-account__name').textContent;
      // Swap trailing signature when the sender changes.
      if (!keepBody) {
        var oldSig = bodyI.dataset.sig || '';
        var base = oldSig && bodyI.value.endsWith(oldSig) ? bodyI.value.slice(0, -oldSig.length) : bodyI.value;
        var newSig = sigFor(currentFromId);
        bodyI.value = base + newSig;
        bodyI.dataset.sig = newSig;
      }
    }

    accountRows.forEach(function (row) {
      row.addEventListener('click', function () { selectAccount(row.dataset.accountId, false); });
    });

    function openCompose(opts) {
      opts = opts || {};
      titleEl.textContent = opts.title || 'New email';
      toI.value = opts.to || '';
      ccI.value = '';
      bccI.value = '';
      subjI.value = opts.subject || '';
      currentInReplyTo = opts.inReplyTo || '';
      currentReferences = opts.references || '';
      var initialAccount = opts.fromAccount || (accountRows[0] && accountRows[0].dataset.accountId);
      var sig = sigFor(initialAccount);
      bodyI.value = (opts.body || '') + sig;
      bodyI.dataset.sig = sig;
      selectAccount(initialAccount, true);
      statusEl.textContent = '';
      statusEl.classList.remove('is-error');
      sendBtn.disabled = false;
      panel.classList.add('is-open');
      (opts.to ? bodyI : toI).focus();
    }
    window.baruaCompose = openCompose;

    document.getElementById('compose-close').addEventListener('click', function () {
      panel.classList.remove('is-open');
    });
    document.getElementById('compose-min').addEventListener('click', function () {
      panel.classList.toggle('is-min');
    });
    document.getElementById('compose-from-mini').addEventListener('click', function () {
      panel.classList.remove('is-min'); // expand to change sender
    });
    document.getElementById('compose-cc-toggle').addEventListener('click', function () {
      document.getElementById('compose-cc-row').style.display = 'flex';
      document.getElementById('compose-bcc-row').style.display = 'flex';
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && panel.classList.contains('is-open')) panel.classList.remove('is-open');
    });

    document.querySelectorAll('[title="Compose"]').forEach(function (btn) {
      btn.addEventListener('click', function () { openCompose({}); });
    });

    sendBtn.addEventListener('click', function () {
      sendBtn.disabled = true;
      statusEl.classList.remove('is-error');
      statusEl.textContent = 'Sending…';
      var body = new URLSearchParams();
      body.set('csrf_token', csrf);
      body.set('account_id', currentFromId);
      body.set('to', toI.value);
      body.set('cc', ccI.value);
      body.set('bcc', bccI.value);
      body.set('subject', subjI.value);
      body.set('body_plain', bodyI.value);
      body.set('in_reply_to', currentInReplyTo);
      body.set('references', currentReferences);
      fetch('/compose/send', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok) {
            statusEl.textContent = 'Sent ✓';
            setTimeout(function () { panel.classList.remove('is-open'); }, 900);
          } else {
            statusEl.classList.add('is-error');
            statusEl.textContent = res.error || 'Send failed.';
            sendBtn.disabled = false;
          }
        })
        .catch(function () {
          statusEl.classList.add('is-error');
          statusEl.textContent = 'Network error.';
          sendBtn.disabled = false;
        });
    });
  })();
</script>
