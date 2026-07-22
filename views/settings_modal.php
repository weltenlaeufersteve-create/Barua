
<div class="settings-overlay" id="settings-overlay">
  <div class="settings-modal">
    <span class="settings-close" id="settings-close">✕</span>
    <div class="settings-tabs">
      <div class="settings-tabs__header"><strong>Settings</strong></div>
      <div class="settings-tab is-active" data-tab="accounts">Accounts &amp; Colours</div>
      <div class="settings-tab" data-tab="appearance">Appearance</div>
      <div class="settings-tab" data-tab="signatures">Signatures</div>
      <div class="settings-tab" data-tab="general">General</div>
      <div class="settings-tab" data-tab="security">Security</div>
      <div class="settings-tab" data-tab="about">About</div>
    </div>

    <div class="settings-panel is-active" data-panel="accounts">
      <?php
        $paletteColours = \Barua\Accounts\ColorPalette::all();
        $settingsAccounts = \Barua\Accounts\AccountRepository::all();
        $settingsSignatures = \Barua\Mail\SignatureRepository::all();
      ?>
      <div class="set-panel-head">
        <h3 class="set-panel-title">Accounts &amp; Colours</h3>
        <p class="set-panel-desc">Manage your mail accounts, pick an accent colour and signature for each, and drag to reorder how they appear in the sidebar.</p>
      </div>
      <?php if (empty($settingsAccounts)): ?>
        <p style="color: var(--text-tertiary);">No accounts yet.</p>
      <?php endif; ?>
      <div id="accounts-sortable">
      <?php foreach ($settingsAccounts as $sa): ?>
        <div class="set-account-block" data-account-id="<?= (int) $sa['id'] ?>">
        <div class="set-account" data-account="<?= (int) $sa['id'] ?>">
          <span class="set-drag-handle" title="Drag to reorder"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><circle cx="9" cy="5" r="1.6"/><circle cx="15" cy="5" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="19" r="1.6"/><circle cx="15" cy="19" r="1.6"/></svg></span>
          <div class="set-account__avatar" data-avatar style="background: <?= htmlspecialchars($sa['colour']) ?>">
            <?= htmlspecialchars(mb_strtoupper(mb_substr($sa['label'], 0, 1))) ?>
          </div>
          <div class="set-account__info">
            <strong><?= htmlspecialchars($sa['label']) ?></strong>
            <span><?= htmlspecialchars($sa['email']) ?></span>
          </div>
          <div class="set-colour-picker">
            <span class="set-colour-ball" data-colour-toggle style="background: <?= htmlspecialchars($sa['colour']) ?>"></span>
            <div class="set-colour-dropdown">
              <?php foreach ($paletteColours as $c): ?>
                <span class="set-swatch<?= strcasecmp($c, $sa['colour']) === 0 ? ' is-active' : '' ?>"
                      style="background: <?= htmlspecialchars($c) ?>; color: <?= htmlspecialchars($c) ?>;"
                      data-colour="<?= htmlspecialchars($c) ?>"></span>
              <?php endforeach; ?>
            </div>
          </div>
          <button type="button" class="set-account__edit" data-edit-toggle title="Edit"><?= sidebarIcon('edit') ?></button>
          <form method="post" action="/accounts/<?= (int) $sa['id'] ?>/delete" onsubmit="return confirm('Remove this account?');" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit" class="set-account__remove" title="Remove"><?= sidebarIcon('trash') ?></button>
          </form>
        </div>
        <form class="set-account__editform" method="post" action="/accounts/<?= (int) $sa['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <div class="set-grid">
            <label>Label<input type="text" name="label" value="<?= htmlspecialchars($sa['label']) ?>" required></label>
            <label>Email<input type="email" name="email" value="<?= htmlspecialchars($sa['email']) ?>" required></label>
          </div>
          <div class="set-subhead">IMAP (incoming)</div>
          <div class="set-grid">
            <label>Host<input type="text" name="imap_host" value="<?= htmlspecialchars($sa['imap_host']) ?>" required></label>
            <label>Port<input type="number" name="imap_port" value="<?= (int) $sa['imap_port'] ?>" required></label>
            <label>Encryption
              <select name="imap_encryption">
                <?php foreach (['ssl','tls','none'] as $enc): ?>
                  <option value="<?= $enc ?>"<?= $sa['imap_encryption'] === $enc ? ' selected' : '' ?>><?= strtoupper($enc) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Username<input type="text" name="imap_username" value="<?= htmlspecialchars($sa['imap_username']) ?>" required></label>
            <label>Password<input type="password" name="imap_password" placeholder="leave blank to keep"></label>
          </div>
          <div class="set-subhead">SMTP (outgoing)</div>
          <div class="set-grid">
            <label>Host<input type="text" name="smtp_host" value="<?= htmlspecialchars($sa['smtp_host']) ?>" required></label>
            <label>Port<input type="number" name="smtp_port" value="<?= (int) $sa['smtp_port'] ?>" required></label>
            <label>Encryption
              <select name="smtp_encryption">
                <?php foreach (['tls','ssl','none'] as $enc): ?>
                  <option value="<?= $enc ?>"<?= $sa['smtp_encryption'] === $enc ? ' selected' : '' ?>><?= strtoupper($enc) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Username<input type="text" name="smtp_username" value="<?= htmlspecialchars($sa['smtp_username']) ?>" required></label>
            <label>Password<input type="password" name="smtp_password" placeholder="leave blank to keep"></label>
          </div>
          <label>Signature
            <select name="signature_id">
              <option value="">— None —</option>
              <?php foreach ($settingsSignatures as $sig): ?>
                <option value="<?= (int) $sig['id'] ?>"<?= (int) ($sa['signature_id'] ?? 0) === (int) $sig['id'] ? ' selected' : '' ?>><?= htmlspecialchars($sig['name']) ?> (<?= strtoupper($sig['format']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="set-editactions">
            <button type="submit" class="set-save" style="border-color: <?= htmlspecialchars($sa['colour']) ?>">Save changes</button>
            <button type="button" class="set-cancel" data-edit-toggle style="border-color: <?= htmlspecialchars($sa['colour']) ?>">Cancel</button>
          </div>
        </form>
        </div>
      <?php endforeach; ?>
      </div>
      <button type="button" class="set-add-link" id="set-add-toggle">+ Add account</button>
      <form class="set-account__editform set-addform" id="set-addform" method="post" action="/accounts">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <div class="set-grid">
          <label>Label<input type="text" name="label" required></label>
          <label>Email<input type="email" name="email" required></label>
        </div>
        <!-- Quick path: email + password + detect fills the IMAP/SMTP fields below (verified
             by a real login). The detailed fields stay editable as a fallback/override. -->
        <div class="set-detect">
          <label>Password<input type="password" id="detect-password" autocomplete="new-password"></label>
          <button type="button" class="set-save" id="detect-btn">Detect settings</button>
          <span class="set-detect__status" id="detect-status"></span>
        </div>
        <div class="set-subhead">IMAP (incoming)</div>
        <div class="set-grid">
          <label>Host<input type="text" name="imap_host" required></label>
          <label>Port<input type="number" name="imap_port" value="993" required></label>
          <label>Encryption
            <select name="imap_encryption">
              <?php foreach (['ssl','tls','none'] as $enc): ?>
                <option value="<?= $enc ?>"><?= strtoupper($enc) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Username<input type="text" name="imap_username" required></label>
          <label>Password<input type="password" name="imap_password" required></label>
        </div>
        <div class="set-subhead">SMTP (outgoing)</div>
        <div class="set-grid">
          <label>Host<input type="text" name="smtp_host" required></label>
          <label>Port<input type="number" name="smtp_port" value="587" required></label>
          <label>Encryption
            <select name="smtp_encryption">
              <?php foreach (['tls','ssl','none'] as $enc): ?>
                <option value="<?= $enc ?>"><?= strtoupper($enc) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Username<input type="text" name="smtp_username" required></label>
          <label>Password<input type="password" name="smtp_password" required></label>
        </div>
        <label>Signature
          <select name="signature_id">
            <option value="">— None —</option>
            <?php foreach ($settingsSignatures as $sig): ?>
              <option value="<?= (int) $sig['id'] ?>"><?= htmlspecialchars($sig['name']) ?> (<?= strtoupper($sig['format']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="set-editactions">
          <button type="submit" class="set-save">Add account</button>
          <button type="button" class="set-cancel" id="set-add-cancel">Cancel</button>
        </div>
      </form>
    </div>

    <div class="settings-panel" data-panel="appearance">
      <div class="set-panel-head">
        <h3 class="set-panel-title">Appearance</h3>
        <p class="set-panel-desc">Choose a light or dark mode and an accent colour. Your choice is saved in this browser and applied instantly.</p>
      </div>
      <div class="set-subhead">Mode</div>
      <div class="appearance-row" id="appearance-modes">
        <button type="button" class="appearance-opt" data-mode="light">☀&nbsp; Light</button>
        <button type="button" class="appearance-opt" data-mode="dark">🌙&nbsp; Dark</button>
      </div>
      <div class="set-subhead" style="margin-top:22px;">Colour</div>
      <div class="appearance-row" id="appearance-tints">
        <button type="button" class="appearance-opt" data-tint="neutral"><span class="tint-dot" style="background:#8A8F98"></span> Neutral</button>
        <button type="button" class="appearance-opt" data-tint="sand"><span class="tint-dot" style="background:#C9A96A"></span> Sand</button>
        <button type="button" class="appearance-opt" data-tint="aubergine"><span class="tint-dot" style="background:#8E5A86"></span> Aubergine</button>
        <button type="button" class="appearance-opt" data-tint="steel"><span class="tint-dot" style="background:#5E7C99"></span> Steel Blue</button>
      </div>
    </div>

    <div class="settings-panel" data-panel="signatures">
      <div class="set-panel-head">
        <h3 class="set-panel-title">Signatures</h3>
        <p class="set-panel-desc">Create reusable plain-text or HTML signatures, then assign one to each account under Accounts &amp; Colours. It's added automatically when you compose a new mail.</p>
      </div>
      <?php if (empty($settingsSignatures)): ?>
        <p style="color: var(--text-tertiary);">No signatures yet — create one below and assign it to an account under Accounts &amp; Colours.</p>
      <?php endif; ?>
      <?php foreach ($settingsSignatures as $sig): ?>
        <div class="set-sig-block">
          <div class="set-sig">
            <div class="set-sig__info">
              <strong><?= htmlspecialchars($sig['name']) ?></strong>
              <span class="set-sig__badge"><?= strtoupper($sig['format']) ?></span>
            </div>
            <button type="button" class="set-account__edit" data-edit-toggle title="Edit"><?= sidebarIcon('edit') ?></button>
            <form method="post" action="/signatures/<?= (int) $sig['id'] ?>/delete" onsubmit="return confirm('Delete this signature?');" style="margin:0;">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
              <button type="submit" class="set-account__remove" title="Delete"><?= sidebarIcon('trash') ?></button>
            </form>
          </div>
          <form class="set-account__editform" method="post" action="/signatures/<?= (int) $sig['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <label>Name<input type="text" name="name" value="<?= htmlspecialchars($sig['name']) ?>" required></label>
            <div class="set-sig-format">
              <label class="set-sig-radio"><input type="radio" name="format" value="plain"<?= $sig['format'] !== 'html' ? ' checked' : '' ?>> Plain text</label>
              <label class="set-sig-radio"><input type="radio" name="format" value="html"<?= $sig['format'] === 'html' ? ' checked' : '' ?>> HTML</label>
            </div>
            <label>Body<textarea name="body" rows="6" spellcheck="false"><?= htmlspecialchars($sig['body']) ?></textarea></label>
            <div class="set-editactions">
              <button type="submit" class="set-save">Save changes</button>
              <button type="button" class="set-cancel" data-edit-toggle>Cancel</button>
            </div>
          </form>
        </div>
      <?php endforeach; ?>

      <button type="button" class="set-add-link" id="set-add-sig-toggle">+ New signature</button>
      <form class="set-account__editform set-addform" id="set-add-sig-form" method="post" action="/signatures">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <label>Name<input type="text" name="name" required></label>
        <div class="set-sig-format">
          <label class="set-sig-radio"><input type="radio" name="format" value="plain" checked> Plain text</label>
          <label class="set-sig-radio"><input type="radio" name="format" value="html"> HTML</label>
        </div>
        <label>Body<textarea name="body" rows="6" spellcheck="false"></textarea></label>
        <div class="set-editactions">
          <button type="submit" class="set-save">Create signature</button>
          <button type="button" class="set-cancel" id="set-add-sig-cancel">Cancel</button>
        </div>
      </form>
    </div>
    <div class="settings-panel" data-panel="general">
      <div class="set-panel-head">
        <h3 class="set-panel-title">General</h3>
        <p class="set-panel-desc">App-wide preferences and account security. More options are on the way.</p>
      </div>
      <p style="color: var(--text-tertiary);">Coming soon.</p>
    </div>

    <div class="settings-panel" data-panel="security">
      <div class="set-panel-head">
        <h3 class="set-panel-title">Security</h3>
        <p class="set-panel-desc">Every sign-in attempt to Barua — successful, failed, or blocked by rate-limiting — with when, where and what.</p>
      </div>
      <?php $loginLog = \Barua\Auth\Auth::recentLoginAttempts(100); ?>
      <?php if (empty($loginLog)): ?>
        <p style="color: var(--text-tertiary);">No sign-in attempts recorded yet.</p>
      <?php else: ?>
        <div class="set-log">
          <?php foreach ($loginLog as $a): ?>
            <div class="set-log__row">
              <span class="set-log__badge set-log__badge--<?= htmlspecialchars($a['outcome']) ?>"><?= htmlspecialchars(ucfirst($a['outcome'])) ?></span>
              <div class="set-log__main">
                <div class="set-log__top"><span class="set-log__time"><?= htmlspecialchars($a['time']) ?></span><span class="set-log__ip"><?= htmlspecialchars($a['xff'] !== '' ? $a['xff'] : $a['ip']) ?></span></div>
                <div class="set-log__meta">user <strong><?= htmlspecialchars($a['user']) ?></strong> · <?= htmlspecialchars($a['ua'] !== '' ? $a['ua'] : 'unknown device') ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="settings-panel" data-panel="about">
      <div class="set-panel-head">
        <h3 class="set-panel-title">About</h3>
        <p class="set-panel-desc"><strong>Barua Mail</strong> — a fast, self-hosted client that merges your IMAP accounts into one calm, colour-coded inbox.</p>
      </div>
      <div class="set-about">
        <div class="set-about__block">
          <div class="set-about__label">Highlights</div>
          <ul class="set-about__features">
            <li>Unified inbox across all your accounts, colour-coded per sender</li>
            <li>Two-axis filtering — type (Clean Inbox, Correspondents, Newsletters, Notifications) × Pinned / Attachments</li>
            <li>Conversation threading with your own replies inline</li>
            <li>Per-account HTML &amp; plain-text signatures</li>
            <li>Attachments with preview, and an attachments filter</li>
            <li>Auto-detect server settings when adding an account</li>
            <li>Eight themes, light &amp; dark, with per-mail reading mode</li>
          </ul>
        </div>
        <div class="set-about__block">
          <div class="set-about__label">Support</div>
          <p class="set-about__line">Weltenläufer Media</p>
          <p class="set-about__line"><a href="mailto:support@weltenlaeufer.de">support@weltenlaeufer.de</a></p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var overlay = document.getElementById('settings-overlay');
    var openBtn = document.getElementById('open-settings');
    var closeBtn = document.getElementById('settings-close');

    if (openBtn) openBtn.addEventListener('click', function () { overlay.classList.add('is-open'); });
    closeBtn.addEventListener('click', function () { overlay.classList.remove('is-open'); });

    // Reopen the modal on a given tab after a settings round-trip (?settings=<tab>).
    var wantTab = new URLSearchParams(location.search).get('settings');
    if (wantTab) {
      overlay.classList.add('is-open');
      var tabEl = document.querySelector('.settings-tab[data-tab="' + wantTab + '"]');
      var panelEl = document.querySelector('.settings-panel[data-panel="' + wantTab + '"]');
      if (tabEl && panelEl) {
        document.querySelectorAll('.settings-tab').forEach(function (t) { t.classList.remove('is-active'); });
        document.querySelectorAll('.settings-panel').forEach(function (p) { p.classList.remove('is-active'); });
        tabEl.classList.add('is-active');
        panelEl.classList.add('is-active');
      }
      history.replaceState(null, '', location.pathname);
    }
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) overlay.classList.remove('is-open');
    });

    document.querySelectorAll('.settings-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        document.querySelectorAll('.settings-tab').forEach(function (t) { t.classList.remove('is-active'); });
        document.querySelectorAll('.settings-panel').forEach(function (p) { p.classList.remove('is-active'); });
        tab.classList.add('is-active');
        document.querySelector('.settings-panel[data-panel="' + tab.dataset.tab + '"]').classList.add('is-active');
      });
    });

    // ---- Account colour: ball opens dropdown, swatch changes colour live ----
    var csrf = <?= json_encode($csrfToken) ?>;

    document.querySelectorAll('.set-colour-ball').forEach(function (ball) {
      ball.addEventListener('click', function (e) {
        e.stopPropagation();
        var dd = ball.nextElementSibling;
        var wasOpen = dd.classList.contains('is-open');
        document.querySelectorAll('.set-colour-dropdown').forEach(function (d) { d.classList.remove('is-open'); });
        if (!wasOpen) dd.classList.add('is-open');
      });
    });
    document.addEventListener('click', function () {
      document.querySelectorAll('.set-colour-dropdown').forEach(function (d) { d.classList.remove('is-open'); });
    });

    document.querySelectorAll('.set-colour-dropdown').forEach(function (dd) {
      var row = dd.closest('.set-account');
      var accountId = row.dataset.account;
      var ball = row.querySelector('.set-colour-ball');
      dd.addEventListener('click', function (e) { e.stopPropagation(); });
      dd.querySelectorAll('.set-swatch').forEach(function (swatch) {
        swatch.addEventListener('click', function () {
          var colour = swatch.dataset.colour;
          var body = new URLSearchParams();
          body.set('csrf_token', csrf);
          body.set('colour', colour);
          fetch('/accounts/' + accountId + '/colour', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
              if (!res.ok) return;
              dd.querySelectorAll('.set-swatch').forEach(function (s) { s.classList.remove('is-active'); });
              swatch.classList.add('is-active');
              ball.style.background = colour;
              row.querySelector('[data-avatar]').style.background = colour;
              dd.classList.remove('is-open');
              recolourAccount(accountId, colour);
            });
        });
      });
    });

    // ---- Edit form toggles ----
    document.querySelectorAll('[data-edit-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var form = btn.closest('.set-account')
          ? btn.closest('.set-account').nextElementSibling
          : btn.closest('.set-sig')
          ? btn.closest('.set-sig').nextElementSibling
          : btn.closest('.set-account__editform');
        if (form) form.classList.toggle('is-open');
      });
    });

    // ---- Add-account form toggle (in-modal, consistent with edit forms) ----
    var addToggle = document.getElementById('set-add-toggle');
    var addForm = document.getElementById('set-addform');
    if (addToggle && addForm) {
      addToggle.addEventListener('click', function () { addForm.classList.toggle('is-open'); });
      var addCancel = document.getElementById('set-add-cancel');
      if (addCancel) addCancel.addEventListener('click', function () { addForm.classList.remove('is-open'); });
    }

    // ---- New-signature form toggle ----
    var addSigToggle = document.getElementById('set-add-sig-toggle');
    var addSigForm = document.getElementById('set-add-sig-form');
    if (addSigToggle && addSigForm) {
      addSigToggle.addEventListener('click', function () { addSigForm.classList.toggle('is-open'); });
      var addSigCancel = document.getElementById('set-add-sig-cancel');
      if (addSigCancel) addSigCancel.addEventListener('click', function () { addSigForm.classList.remove('is-open'); });
    }

    // ---- Auto-detect IMAP/SMTP from email + password (fills the fields below) ----
    var detectBtn = document.getElementById('detect-btn');
    if (detectBtn) {
      var addF = document.getElementById('set-addform');
      var setField = function (name, val) {
        var el = addF.querySelector('[name="' + name + '"]');
        if (el && val != null) el.value = val;
      };
      detectBtn.addEventListener('click', function () {
        var email = (addF.querySelector('[name="email"]').value || '').trim();
        var pass = document.getElementById('detect-password').value;
        var status = document.getElementById('detect-status');
        status.className = 'set-detect__status';
        if (!email || !pass) { status.textContent = 'Enter email and password first.'; status.classList.add('is-error'); return; }
        detectBtn.disabled = true;
        status.textContent = 'Detecting…';
        var body = new URLSearchParams();
        body.set('csrf_token', csrf);
        body.set('email', email);
        body.set('password', pass);
        fetch('/accounts/detect', { method: 'POST', body: body })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            detectBtn.disabled = false;
            if (!res.ok) { status.textContent = res.error || 'Detection failed.'; status.classList.add('is-error'); return; }
            if (res.imap) {
              setField('imap_host', res.imap.host); setField('imap_port', res.imap.port);
              setField('imap_encryption', res.imap.encryption); setField('imap_username', res.imap.username);
              setField('imap_password', pass);
            }
            if (res.smtp) {
              setField('smtp_host', res.smtp.host); setField('smtp_port', res.smtp.port);
              setField('smtp_encryption', res.smtp.encryption); setField('smtp_username', res.smtp.username);
              setField('smtp_password', pass);
            }
            var bits = [];
            bits.push(res.imap ? 'IMAP ✓' : 'IMAP not found');
            bits.push(res.smtp ? 'SMTP ✓' : 'SMTP not found');
            status.textContent = bits.join(' · ') + (res.imap && res.smtp ? ' — review and create.' : ' — fill the rest manually.');
            status.classList.add(res.imap && res.smtp ? 'is-ok' : 'is-error');
          })
          .catch(function () { detectBtn.disabled = false; status.textContent = 'Network error.'; status.classList.add('is-error'); });
      });
    }

    function recolourAccount(accountId, colour) {
      var side = document.querySelector('.sidebar__item[data-account="' + accountId + '"] .account-avatar');
      if (side) side.style.background = colour;
      document.querySelectorAll('.mail-row[data-account="' + accountId + '"] .mail-row__stripe')
        .forEach(function (s) { s.style.background = colour; });
      var ra = document.querySelector('.reader__avatar[data-account="' + accountId + '"]');
      if (ra) ra.style.background = colour;
      // account-context button borders in this settings row + its edit form
      var settingsRow = document.querySelector('.set-account[data-account="' + accountId + '"]');
      if (settingsRow) {
        settingsRow.querySelectorAll('.set-account__edit, .set-account__remove')
          .forEach(function (b) { b.style.borderColor = colour; });
        var form = settingsRow.nextElementSibling;
        if (form) form.querySelectorAll('.set-save, .set-cancel')
          .forEach(function (b) { b.style.borderColor = colour; });
      }
    }

    // ---- Drag & drop account ordering: persist + mirror into the sidebar ----
    var sortable = document.getElementById('accounts-sortable');
    if (sortable) {
      var dragBlock = null;

      sortable.querySelectorAll('.set-account-block').forEach(function (block) {
        var handle = block.querySelector('.set-drag-handle');
        handle.addEventListener('mousedown', function () { block.setAttribute('draggable', 'true'); });
        block.addEventListener('dragstart', function (e) {
          dragBlock = block;
          block.classList.add('is-dragging');
          e.dataTransfer.effectAllowed = 'move';
          try { e.dataTransfer.setData('text/plain', ''); } catch (err) {}
        });
        block.addEventListener('dragend', function () {
          block.removeAttribute('draggable');
          block.classList.remove('is-dragging');
          dragBlock = null;
          persistOrder();
        });
      });

      sortable.addEventListener('dragover', function (e) {
        if (!dragBlock) return;
        e.preventDefault();
        var blocks = Array.prototype.slice.call(sortable.querySelectorAll('.set-account-block:not(.is-dragging)'));
        var next = null;
        for (var i = 0; i < blocks.length; i++) {
          var r = blocks[i].getBoundingClientRect();
          if (e.clientY < r.top + r.height / 2) { next = blocks[i]; break; }
        }
        if (next) { sortable.insertBefore(dragBlock, next); } else { sortable.appendChild(dragBlock); }
      });

      function persistOrder() {
        var ids = Array.prototype.map.call(
          sortable.querySelectorAll('.set-account-block'),
          function (b) { return b.dataset.accountId; }
        );
        var body = new URLSearchParams();
        body.set('csrf_token', csrf);
        body.set('order', ids.join(','));
        fetch('/accounts/reorder', { method: 'POST', body: body })
          .then(function (r) { return r.json(); })
          .then(function (res) { if (res.ok) reorderSidebar(ids); });
      }

      function reorderSidebar(ids) {
        var anchors = {};
        document.querySelectorAll('.sidebar__item[data-account]').forEach(function (a) {
          anchors[a.dataset.account] = a;
        });
        var first = document.querySelector('.sidebar__item[data-account]');
        if (!first) return;
        var parent = first.parentNode;
        var marker = document.createComment('acc-order');
        parent.insertBefore(marker, first);
        ids.forEach(function (id) {
          if (anchors[id]) parent.insertBefore(anchors[id], marker);
        });
        parent.removeChild(marker);
      }
    }

    // ---- Appearance: mode (light/dark) × tint (neutral/sand/aubergine/steel) ----
    var MODES = ['light', 'dark'];
    var TINTS = ['neutral', 'sand', 'aubergine', 'steel'];

    function parseTheme() {
      var parts = (localStorage.getItem('barua_theme') || 'dark-neutral').split('-');
      var mode = MODES.indexOf(parts[0]) >= 0 ? parts[0] : 'dark';
      var tint = TINTS.indexOf(parts[1]) >= 0 ? parts[1] : 'neutral';
      return { mode: mode, tint: tint };
    }

    function applyTheme(mode, tint) {
      var key = mode + '-' + tint;
      document.documentElement.setAttribute('data-theme', key);
      localStorage.setItem('barua_theme', key);
      highlight();
    }

    function highlight() {
      var t = parseTheme();
      document.querySelectorAll('#appearance-modes .appearance-opt').forEach(function (b) {
        b.classList.toggle('is-active', b.dataset.mode === t.mode);
      });
      document.querySelectorAll('#appearance-tints .appearance-opt').forEach(function (b) {
        b.classList.toggle('is-active', b.dataset.tint === t.tint);
      });
    }

    document.querySelectorAll('#appearance-modes .appearance-opt').forEach(function (b) {
      b.addEventListener('click', function () { applyTheme(b.dataset.mode, parseTheme().tint); });
    });
    document.querySelectorAll('#appearance-tints .appearance-opt').forEach(function (b) {
      b.addEventListener('click', function () { applyTheme(parseTheme().mode, b.dataset.tint); });
    });

    highlight();
  })();
</script>
