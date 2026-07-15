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

  .settings-tabs {
    width: 200px;
    flex-shrink: 0;
    border-right: 1px solid var(--border);
    padding: 16px 10px;
  }

  .settings-tabs__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 8px 14px;
  }
  .settings-tabs__header strong { font-size: 15px; }
  .settings-tabs__close {
    cursor: pointer;
    color: var(--text-secondary);
    font-size: 16px;
    line-height: 1;
  }
  .settings-tabs__close:hover { color: var(--text-primary); }

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
    padding: 24px 28px;
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
  .set-account:last-child { border-bottom: none; }
  .set-account__avatar {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 700; font-size: 14px; flex-shrink: 0;
  }
  .set-account__info { flex: 1; min-width: 0; }
  .set-account__info strong { display: block; font-size: 13.5px; color: var(--text-primary); }
  .set-account__info span { font-size: 12px; color: var(--text-tertiary); }
  .set-account__swatches { display: flex; flex-wrap: wrap; gap: 6px; max-width: 200px; }
  .set-swatch {
    width: 20px; height: 20px; border-radius: 50%; cursor: pointer;
    box-shadow: 0 0 0 2px transparent; transition: box-shadow 0.1s;
  }
  .set-swatch.is-active { box-shadow: 0 0 0 2px var(--sidebar-bg), 0 0 0 4px currentColor; }
  .set-account__remove {
    background: transparent; border: 1px solid var(--border); color: var(--text-tertiary);
    border-radius: var(--radius-sm); padding: 5px 10px; font-size: 12px; cursor: pointer;
    flex-shrink: 0;
  }
  .set-account__remove:hover { background: var(--hover-bg); color: var(--text-secondary); }
  .set-add-link {
    display: inline-block; margin-top: 18px; font-size: 13px; color: var(--acc-blue);
    text-decoration: none;
  }
  .set-account__edit {
    background: transparent; border: 1px solid var(--border); color: var(--text-tertiary);
    border-radius: var(--radius-sm); padding: 5px 10px; font-size: 12px; cursor: pointer;
    flex-shrink: 0;
  }
  .set-account__edit:hover { background: var(--hover-bg); color: var(--text-secondary); }

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
  .set-save {
    background: var(--acc-blue); border: none; color: #fff; font-weight: 600;
    border-radius: var(--radius-sm); padding: 8px 16px; font-size: 13px; cursor: pointer;
  }
  .set-cancel {
    background: transparent; border: 1px solid var(--border); color: var(--text-secondary);
    border-radius: var(--radius-sm); padding: 8px 16px; font-size: 13px; cursor: pointer;
  }
</style>

<div class="settings-overlay" id="settings-overlay">
  <div class="settings-modal">
    <div class="settings-tabs">
      <div class="settings-tabs__header">
        <strong>Settings</strong>
        <span class="settings-tabs__close" id="settings-close">✕</span>
      </div>
      <div class="settings-tab is-active" data-tab="accounts">Accounts &amp; Colours</div>
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
          <div class="set-account__swatches">
            <?php foreach ($paletteColours as $c): ?>
              <span class="set-swatch<?= strcasecmp($c, $sa['colour']) === 0 ? ' is-active' : '' ?>"
                    style="background: <?= htmlspecialchars($c) ?>; color: <?= htmlspecialchars($c) ?>;"
                    data-colour="<?= htmlspecialchars($c) ?>"></span>
            <?php endforeach; ?>
          </div>
          <button type="button" class="set-account__edit" data-edit-toggle>Edit</button>
          <form method="post" action="/accounts/<?= (int) $sa['id'] ?>/delete" onsubmit="return confirm('Remove this account?');" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit" class="set-account__remove">Remove</button>
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
            <button type="submit" class="set-save">Save changes</button>
            <button type="button" class="set-cancel" data-edit-toggle>Cancel</button>
          </div>
        </form>
      <?php endforeach; ?>
      <a href="/accounts" class="set-add-link">+ Add account</a>
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

    if (openBtn) {
      openBtn.addEventListener('click', function () {
        overlay.classList.add('is-open');
      });
    }
    closeBtn.addEventListener('click', function () {
      overlay.classList.remove('is-open');
    });
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

    // Live colour change: persist via AJAX, then recolour everywhere in the DOM.
    var csrf = <?= json_encode($csrfToken) ?>;
    document.querySelectorAll('.set-account__swatches').forEach(function (group) {
      var row = group.closest('.set-account');
      var accountId = row.dataset.account;
      group.querySelectorAll('.set-swatch').forEach(function (swatch) {
        swatch.addEventListener('click', function () {
          var colour = swatch.dataset.colour;
          var body = new URLSearchParams();
          body.set('csrf_token', csrf);
          body.set('colour', colour);
          fetch('/accounts/' + accountId + '/colour', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
              if (!res.ok) return;
              // swatch ring
              group.querySelectorAll('.set-swatch').forEach(function (s) { s.classList.remove('is-active'); });
              swatch.classList.add('is-active');
              // settings avatar
              row.querySelector('[data-avatar]').style.background = colour;
              // recolour everywhere this account appears
              recolourAccount(accountId, colour);
            });
        });
      });
    });

    // Edit form open/close toggles.
    document.querySelectorAll('[data-edit-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var form = btn.closest('.set-account')
          ? btn.closest('.set-account').nextElementSibling
          : btn.closest('.set-account__editform');
        if (form) form.classList.toggle('is-open');
      });
    });

    function recolourAccount(accountId, colour) {
      // sidebar avatar
      var side = document.querySelector('.sidebar__item[href="/?account=' + accountId + '"] .account-avatar');
      if (side) side.style.background = colour;
      // mail-list stripes
      document.querySelectorAll('.mail-row[data-account="' + accountId + '"] .mail-row__stripe')
        .forEach(function (s) { s.style.background = colour; });
      // reader avatar (if showing a message from this account)
      var ra = document.querySelector('.reader__avatar[data-account="' + accountId + '"]');
      if (ra) ra.style.background = colour;
    }
  })();
</script>
