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

<script>
  (function () {
    var panel = document.getElementById('compose-panel');
    var accountRows = panel.querySelectorAll('.compose-account');
    var toI = document.getElementById('compose-to');
    var ccI = document.getElementById('compose-cc');
    var bccI = document.getElementById('compose-bcc');

    // ---- Recipient autocomplete from correspondents (name/email suggestions) ----
    function acEsc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    // The address currently being typed = text after the last comma/semicolon.
    function acLastToken(v) {
      var i = Math.max(v.lastIndexOf(','), v.lastIndexOf(';'));
      return { start: i + 1, text: v.slice(i + 1).trim() };
    }
    function attachAutocomplete(input) {
      var menu = document.createElement('div');
      menu.className = 'compose-ac';
      input.parentNode.appendChild(menu);
      var items = [], active = -1, timer = null;

      function close() { menu.classList.remove('is-open'); menu.innerHTML = ''; items = []; active = -1; }
      function render(list) {
        items = list;
        if (!list.length) { close(); return; }
        active = 0;
        menu.innerHTML = list.map(function (r, i) {
          return '<div class="compose-ac__item' + (i === 0 ? ' is-active' : '') + '" data-i="' + i + '">'
            + (r.name ? '<span class="compose-ac__name">' + acEsc(r.name) + '</span>' : '')
            + '<span class="compose-ac__email">' + acEsc(r.email) + '</span></div>';
        }).join('');
        menu.classList.add('is-open');
      }
      function highlight(i) {
        var els = menu.querySelectorAll('.compose-ac__item');
        if (!els.length) return;
        active = (i + els.length) % els.length;
        els.forEach(function (e, k) { e.classList.toggle('is-active', k === active); });
        els[active].scrollIntoView({ block: 'nearest' });
      }
      function pick(i) {
        var r = items[i];
        if (!r) return;
        var t = acLastToken(input.value);
        input.value = input.value.slice(0, t.start) + (t.start > 0 ? ' ' : '') + r.email + ', ';
        close();
        input.focus();
        scheduleAutosave();
      }
      input.addEventListener('input', function () {
        var q = acLastToken(input.value).text;
        clearTimeout(timer);
        if (q.length < 1) { close(); return; }
        timer = setTimeout(function () {
          fetch('/api/correspondents?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (res) { render((res && res.results) || []); })
            .catch(function () { close(); });
        }, 130);
      });
      input.addEventListener('keydown', function (e) {
        if (!menu.classList.contains('is-open')) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
        else if (e.key === 'Enter' || e.key === 'Tab') { if (active >= 0) { e.preventDefault(); pick(active); } }
        else if (e.key === 'Escape') { close(); }
      });
      menu.addEventListener('mousedown', function (e) {
        var it = e.target.closest('.compose-ac__item');
        if (it) { e.preventDefault(); pick(parseInt(it.dataset.i, 10)); }
      });
      input.addEventListener('blur', function () { setTimeout(close, 150); });
    }
    [toI, ccI, bccI].forEach(function (el) { if (el) attachAutocomplete(el); });
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
    var currentDraftId = null;
    var draftTimer = null;

    function sigInfo(accountId) {
      return signatures[accountId] || { format: 'plain', plain: '', html: '' };
    }

    // The plain text appended to the (plain-text) textarea for a given sender.
    function sigFor(accountId) {
      var s = sigInfo(accountId);
      return s.plain ? ('\n\n-- \n' + s.plain) : '';
    }

    function htmlEscape(t) {
      return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // For HTML-signature accounts, build the HTML body: the typed text (escaped, newlines
    // → <br>) followed by the real HTML signature. Returns '' for plain accounts, so the
    // send stays plain-text exactly as before.
    function buildHtmlBody() {
      var s = sigInfo(currentFromId);
      if (s.format !== 'html' || !s.html) return '';
      var full = bodyI.value;
      var sig = bodyI.dataset.sig || '';
      var base = (sig && full.endsWith(sig)) ? full.slice(0, -sig.length) : full;
      return htmlEscape(base).replace(/\n/g, '<br>') + '<br><br>-- <br>' + s.html;
    }

    function selectAccount(accountId, keepBody) {
      var row = panel.querySelector('.compose-account[data-account-id="' + accountId + '"]');
      if (!row) row = accountRows[0];
      if (!row) return;
      accountRows.forEach(function (r) { r.classList.remove('is-active'); });
      row.classList.add('is-active');
      currentFromId = row.dataset.accountId;
      // Send button border follows the account colour (outline, light theme fill).
      // Button stays neutral; the paper-plane icon carries the account colour.
      var sendIcon = sendBtn.querySelector('.compose__send-icon');
      if (sendIcon) sendIcon.style.color = row.dataset.colour;
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

    // ---- File attachments (uploaded on select, stored against the draft) ----
    var composeAttachments = []; // {id, filename, size}
    var attWrapEl = document.getElementById('compose-attachments');
    function fmtSize(b) {
      if (b < 1024) return b + ' B';
      if (b < 1024 * 1024) return Math.round(b / 1024) + ' KB';
      return (Math.round(b / 1024 / 1024 * 10) / 10) + ' MB';
    }
    function escAtt(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    // Same paperclip + chip markup as the received-mail attachments, for visual consistency.
    var ATT_CLIP_SVG = '<svg class="sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>';
    function renderAttachments() {
      if (!attWrapEl) return;
      attWrapEl.innerHTML = composeAttachments.map(function (a) {
        var right = a.pending
          ? '<span class="attachment-chip__size">uploading…</span>'
          : '<span class="attachment-chip__size">' + fmtSize(a.size) + '</span>';
        var remove = a.pending ? ''
          : '<button type="button" class="attachment-chip__remove" title="Remove" data-remove="' + a.id + '">&times;</button>';
        return '<div class="attachment-chip' + (a.pending ? ' is-pending' : '') + '"' + (a.pending ? '' : ' data-att-id="' + a.id + '"') + '>'
          + ATT_CLIP_SVG
          + '<div class="attachment-chip__info"><span class="attachment-chip__name">' + escAtt(a.filename) + '</span>' + right + '</div>'
          + remove
          + '</div>';
      }).join('');
      attWrapEl.style.display = composeAttachments.length ? 'flex' : 'none';
    }
    function uploadOne(file) {
      var pending = { pending: true, filename: file.name, tmp: Math.random() };
      composeAttachments.push(pending);
      renderAttachments();
      var fd = new FormData();
      fd.append('csrf_token', csrf);
      fd.append('account_id', currentFromId);
      fd.append('draft_id', currentDraftId || '');
      fd.append('file', file);
      fetch('/compose/attach', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          composeAttachments = composeAttachments.filter(function (a) { return a.tmp !== pending.tmp; });
          if (res.ok) {
            currentDraftId = res.draftId;               // attach created/linked a draft
            composeAttachments.push(res.attachment);
            draftStatusEl.innerHTML = '<span class="ok">✓</span> Draft saved';
          } else {
            statusEl.textContent = (file.name + ': ' + (res.error || 'upload failed')).slice(0, 120);
            statusEl.classList.add('is-error');
          }
          renderAttachments();
        })
        .catch(function () {
          composeAttachments = composeAttachments.filter(function (a) { return a.tmp !== pending.tmp; });
          statusEl.textContent = 'Upload failed (network).';
          statusEl.classList.add('is-error');
          renderAttachments();
        });
    }
    var fileInput = document.getElementById('compose-file-input');
    if (fileInput) fileInput.addEventListener('change', function () {
      Array.prototype.forEach.call(this.files, uploadOne);
      this.value = ''; // allow re-picking the same file
    });
    if (attWrapEl) attWrapEl.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-remove]');
      if (!btn) return;
      var id = btn.dataset.remove;
      composeAttachments = composeAttachments.filter(function (a) { return String(a.id) !== String(id); });
      renderAttachments();
      var body = new URLSearchParams();
      body.set('csrf_token', csrf);
      fetch('/compose/attach/' + id + '/delete', { method: 'POST', body: body }).catch(function () {});
    });

    function openCompose(opts) {
      opts = opts || {};
      composeAttachments = (opts.attachments || []).slice();
      renderAttachments();
      titleEl.textContent = opts.title || 'New email';
      toI.value = opts.to || '';
      ccI.value = opts.cc || '';
      bccI.value = opts.bcc || '';
      var showCc = !!(opts.cc || opts.bcc);
      document.getElementById('compose-cc-row').style.display = showCc ? 'flex' : 'none';
      document.getElementById('compose-bcc-row').style.display = showCc ? 'flex' : 'none';
      subjI.value = opts.subject || '';
      currentInReplyTo = opts.inReplyTo || '';
      currentReferences = opts.references || '';
      currentDraftId = opts.draftId || null;
      if (draftTimer) { clearTimeout(draftTimer); draftTimer = null; }
      var dse = document.getElementById('compose-draft-status');
      dse.innerHTML = currentDraftId ? '<span class="ok">✓</span> Draft saved' : '';
      var initialAccount = opts.fromAccount || (accountRows[0] && accountRows[0].dataset.accountId);
      // Drafts already carry their signature — never append a second one.
      var sig = opts.draftId ? '' : sigFor(initialAccount);
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

    // ---- Draft autosave: debounced while typing, flushed on close ----
    function draftHasContent() {
      var sig = bodyI.dataset.sig || '';
      var core = bodyI.value;
      if (sig && core.endsWith(sig)) core = core.slice(0, -sig.length);
      return !!(toI.value.trim() || ccI.value.trim() || bccI.value.trim() || subjI.value.trim() || core.trim() || composeAttachments.length);
    }

    function saveDraft() {
      draftTimer = null;
      if (!panel.classList.contains('is-open') || !draftHasContent()) return;
      var body = new URLSearchParams();
      body.set('csrf_token', csrf);
      body.set('draft_id', currentDraftId || '');
      body.set('account_id', currentFromId);
      body.set('to', toI.value);
      body.set('cc', ccI.value);
      body.set('bcc', bccI.value);
      body.set('subject', subjI.value);
      body.set('body_plain', bodyI.value);
      fetch('/drafts/save', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok) {
            currentDraftId = res.id;
            draftStatusEl.innerHTML = '<span class="ok">✓</span> Draft saved';
          }
        })
        .catch(function () {});
    }

    var draftStatusEl = document.getElementById('compose-draft-status');
    function scheduleAutosave() {
      draftStatusEl.textContent = ''; // dirty again — the check returns after the next save
      if (draftTimer) clearTimeout(draftTimer);
      draftTimer = setTimeout(saveDraft, 2500);
    }

    [toI, ccI, bccI, subjI, bodyI].forEach(function (el) {
      el.addEventListener('input', scheduleAutosave);
    });

    document.getElementById('compose-close').addEventListener('click', function () {
      if (draftTimer) { clearTimeout(draftTimer); draftTimer = null; }
      saveDraft(); // flush so nothing is lost on close
      panel.classList.remove('is-open');
    });
    document.getElementById('compose-min').addEventListener('click', function () {
      panel.classList.toggle('is-min');
    });
    // Mini-mode sender switch: a dropdown, no need to expand to fullscreen.
    var fromDropdown = document.getElementById('compose-from-dropdown');
    document.getElementById('compose-from-mini').addEventListener('click', function (e) {
      e.stopPropagation();
      fromDropdown.classList.toggle('is-open');
      // mark the current sender in the dropdown
      fromDropdown.querySelectorAll('.compose__from-option').forEach(function (o) {
        o.classList.toggle('is-active', o.dataset.accountId === currentFromId);
      });
    });
    fromDropdown.querySelectorAll('.compose__from-option').forEach(function (opt) {
      opt.addEventListener('click', function (e) {
        e.stopPropagation();
        selectAccount(opt.dataset.accountId, false);
        fromDropdown.classList.remove('is-open');
      });
    });
    document.addEventListener('click', function () {
      fromDropdown.classList.remove('is-open');
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
      body.set('body_html', buildHtmlBody());
      body.set('in_reply_to', currentInReplyTo);
      body.set('references', currentReferences);
      body.set('draft_id', currentDraftId || '');
      fetch('/compose/send', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok) {
            if (draftTimer) { clearTimeout(draftTimer); draftTimer = null; }
            currentDraftId = null; // sent — the server deleted the draft (+ its attachments)
            composeAttachments = [];
            renderAttachments();
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
