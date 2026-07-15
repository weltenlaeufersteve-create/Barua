<style>
  .settings-overlay {
    position: fixed; inset: 0;
    background: var(--overlay);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 100;
  }
  .settings-overlay.is-open { display: flex; }

  .settings-modal {
    position: relative;
    width: 760px;
    max-width: calc(100vw - 32px);
    height: 540px;
    max-height: calc(100vh - 32px);
    background: var(--sidebar-bg);
    border-radius: var(--radius-lg);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    display: flex;
    overflow: hidden;
  }

  /* Close ✕ — top-right of the modal, with a small gap to the content. */
  .settings-close {
    position: absolute;
    top: 12px; right: 12px;
    width: 30px; height: 30px;
    display: flex; align-items: center; justify-content: center;
    border-radius: var(--radius-sm);
    cursor: pointer; color: var(--text-secondary); font-size: 16px; line-height: 1;
    z-index: 5;
  }
  .settings-close:hover { background: var(--hover-bg); color: var(--text-primary); }

  .settings-tabs {
    width: 200px;
    flex-shrink: 0;
    border-right: 1px solid var(--border);
    padding: 16px 10px;
  }
  .settings-tabs__header { padding: 0 8px 14px; }
  .settings-tabs__header strong { font-size: 15px; }

  .settings-tab {
    padding: 9px 12px;
    border-radius: var(--radius-sm);
    font-size: 13.5px;
    color: var(--text-secondary);
    cursor: pointer;
  }
  .settings-tab:hover { background: var(--hover-bg); }
  .settings-tab.is-active {
    background: var(--selected-bg);
    color: var(--text-primary);
    font-weight: 600;
  }

  .settings-panel {
    flex: 1;
    padding: 24px 44px 24px 28px; /* extra right padding clears the close ✕ */
    overflow-y: auto;
    display: none;
    color: var(--text-tertiary);
    font-size: 13.5px;
  }
  .settings-panel.is-active { display: block; }

  .set-account {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
  }
  .set-account:last-of-type { border-bottom: none; }
  .set-account__avatar {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 14px; flex-shrink: 0;
  }
  .set-account__info { flex: 1; min-width: 0; }
  .set-account__info strong { display: block; font-size: 13.5px; color: var(--text-primary); }
  .set-account__info span { font-size: 12px; color: var(--text-tertiary); }

  /* Single colour ball + dropdown of the 20 palette colours. */
  .set-colour-picker { position: relative; flex-shrink: 0; }
  .set-colour-ball {
    width: 22px; height: 22px; border-radius: 50%; cursor: pointer;
    box-shadow: 0 0 0 2px var(--border); display: block;
  }
  .set-colour-dropdown {
    display: none; position: absolute; top: 30px; right: 0; z-index: 10;
    background: var(--sidebar-bg); border: 1px solid var(--border);
    border-radius: var(--radius-md); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    padding: 10px; grid-template-columns: repeat(5, 20px); gap: 8px; width: max-content;
  }
  .set-colour-dropdown.is-open { display: grid; }
  .set-swatch {
    width: 20px; height: 20px; border-radius: 50%; cursor: pointer;
    box-shadow: 0 0 0 2px transparent;
  }
  .set-swatch.is-active { box-shadow: 0 0 0 2px var(--sidebar-bg), 0 0 0 4px currentColor; }

  .set-account__edit,
  .set-account__remove {
    background: var(--hover-bg); border: 1.5px solid var(--border); color: var(--text-primary);
    border-radius: 999px; padding: 5px 13px; font-size: 12px; cursor: pointer; flex-shrink: 0;
  }
  .set-account__edit:hover,
  .set-account__remove:hover { background: var(--selected-bg); }
  .set-add-link {
    display: inline-block; margin-top: 18px; font-size: 13px; color: var(--accent);
    text-decoration: none;
  }

  .set-account__editform { display: none; padding: 4px 0 16px; border-bottom: 1px solid var(--border); }
  .set-account__editform.is-open { display: block; }
  .set-subhead {
    font-size: 11px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;
    color: var(--text-tertiary); margin: 14px 0 8px;
  }
  .set-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .set-account__editform label {
    display: flex; flex-direction: column; gap: 4px;
    font-size: 11.5px; color: var(--text-secondary); margin-bottom: 6px;
  }
  .set-account__editform input,
  .set-account__editform select {
    padding: 7px 9px; border-radius: var(--radius-sm); border: 1px solid var(--border);
    background: var(--input-bg); color: var(--text-primary); font-size: 13px;
  }
  .set-editactions { display: flex; gap: 8px; margin-top: 14px; }
  .set-save, .set-cancel {
    background: var(--hover-bg); border: 1.5px solid var(--border); color: var(--text-primary);
    font-weight: 600; border-radius: 999px; padding: 8px 18px; font-size: 13px; cursor: pointer;
  }
  .set-cancel { font-weight: 400; }
  .set-save:hover, .set-cancel:hover { background: var(--selected-bg); }

  /* Appearance tab */
  .appearance-row { display: flex; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
  .appearance-opt {
    display: flex; align-items: center; gap: 8px;
    background: transparent; border: 1px solid var(--border); color: var(--text-secondary);
    border-radius: 999px; padding: 9px 16px; font-size: 13px; cursor: pointer;
  }
  .appearance-opt:hover { background: var(--hover-bg); }
  .appearance-opt.is-active {
    border-color: var(--accent); color: var(--text-primary);
    box-shadow: 0 0 0 1px var(--accent);
  }
  .tint-dot { width: 16px; height: 16px; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 0 1px var(--border); }
</style>

<div class="settings-overlay" id="settings-overlay">
  <div class="settings-modal">
    <span class="settings-close" id="settings-close">✕</span>
    <div class="settings-tabs">
      <div class="settings-tabs__header"><strong>Settings</strong></div>
      <div class="settings-tab is-active" data-tab="accounts">Accounts &amp; Colours</div>
      <div class="settings-tab" data-tab="appearance">Appearance</div>
      <div class="settings-tab" data-tab="signatures">Signatures</div>
      <div class="settings-tab" data-tab="general">General</div>
    </div>

    <div class="settings-panel is-active" data-panel="accounts">
      <?php
        $paletteColours = \Barua\Accounts\ColorPalette::all();
        $settingsAccounts = \Barua\Accounts\AccountRepository::all();
      ?>
      <?php if (empty($settingsAccounts)): ?>
        <p style="color: var(--text-tertiary);">No accounts yet.</p>
      <?php endif; ?>
      <?php foreach ($settingsAccounts as $sa): ?>
        <div class="set-account" data-account="<?= (int) $sa['id'] ?>">
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
          <button type="button" class="set-account__edit" data-edit-toggle style="border-color: <?= htmlspecialchars($sa['colour']) ?>">Edit</button>
          <form method="post" action="/accounts/<?= (int) $sa['id'] ?>/delete" onsubmit="return confirm('Remove this account?');" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit" class="set-account__remove" style="border-color: <?= htmlspecialchars($sa['colour']) ?>">Remove</button>
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
          <label>Signature<input type="text" name="signature" value="<?= htmlspecialchars($sa['signature'] ?? '') ?>"></label>
          <div class="set-editactions">
            <button type="submit" class="set-save" style="border-color: <?= htmlspecialchars($sa['colour']) ?>">Save changes</button>
            <button type="button" class="set-cancel" data-edit-toggle style="border-color: <?= htmlspecialchars($sa['colour']) ?>">Cancel</button>
          </div>
        </form>
      <?php endforeach; ?>
      <a href="/accounts" class="set-add-link">+ Add account</a>
    </div>

    <div class="settings-panel" data-panel="appearance">
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

    <div class="settings-panel" data-panel="signatures">Signatures — coming soon.</div>
    <div class="settings-panel" data-panel="general">General — coming soon.</div>
  </div>
</div>

<script>
  (function () {
    var overlay = document.getElementById('settings-overlay');
    var openBtn = document.getElementById('open-settings');
    var closeBtn = document.getElementById('settings-close');

    if (openBtn) openBtn.addEventListener('click', function () { overlay.classList.add('is-open'); });
    closeBtn.addEventListener('click', function () { overlay.classList.remove('is-open'); });
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
          : btn.closest('.set-account__editform');
        if (form) form.classList.toggle('is-open');
      });
    });

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
