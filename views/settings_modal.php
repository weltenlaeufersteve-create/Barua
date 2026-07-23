
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
          <div class="set-account__avatar" data-avatar style="background: <?= htmlspecialchars($sa['colour']) ?>; border-color: <?= htmlspecialchars($sa['colour']) ?>">
            <?php if (($sa['avatar_state'] ?? 'unknown') === 'has'): ?>
              <img src="/avatars/<?= (int) $sa['id'] ?>" alt="">
            <?php else: ?>
              <?= htmlspecialchars(mb_strtoupper(mb_substr($sa['label'], 0, 1))) ?>
            <?php endif; ?>
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
        <p class="set-panel-desc">One timeline of sign-ins and sensitive actions — each classified, newest first, with when, where and what.</p>
      </div>
      <?php
        // Merge the two sources (sign-in attempts + activity) into one classified,
        // time-sorted timeline. Each row is normalised to: badge label, colour modifier,
        // a human sentence, plus time/ip for the top line and a sortable timestamp.
        $entries = [];

        foreach (\Barua\Auth\Auth::recentLoginAttempts(200) as $a) {
            $verb = [
                'success' => 'Signed in',
                'fail'    => 'Failed sign-in',
                'blocked' => 'Blocked sign-in (rate-limited)',
            ][$a['outcome']] ?? 'Sign-in';
            $entries[] = [
                'sort'     => strtotime($a['time']) ?: 0,
                'time'     => $a['time'],
                'ip'       => $a['xff'] !== '' ? $a['xff'] : $a['ip'],
                'badge'    => 'Login',
                'mod'      => $a['outcome'] === 'success' ? 'success' : 'danger',
                'sentence' => $verb . ' — user ' . $a['user'] . ' · ' . ($a['ua'] !== '' ? $a['ua'] : 'unknown device'),
            ];
        }

        // activity action → [badge label, colour modifier, human sentence prefix]
        $actMap = [
            'empty'          => ['Emptied',  'danger',  'Emptied '],
            'account_add'    => ['Account',  'success', 'Added account '],
            'account_edit'   => ['Account',  'neutral', 'Edited account '],
            'account_remove' => ['Account',  'danger',  'Removed account '],
            'logout'         => ['Logout',   'neutral', 'Signed out'],
        ];
        foreach (\Barua\Security\ActivityLog::recent(200) as $a) {
            [$badge, $mod, $prefix] = $actMap[$a['action']] ?? [ucfirst($a['action']), 'neutral', $a['action'] . ' '];
            $entries[] = [
                'sort'     => strtotime($a['time']) ?: 0,
                'time'     => $a['time'],
                'ip'       => $a['xff'] !== '' ? $a['xff'] : $a['ip'],
                'badge'    => $badge,
                'mod'      => $mod,
                'sentence' => $prefix . $a['detail'],
            ];
        }

        usort($entries, fn($x, $y) => $y['sort'] <=> $x['sort']); // newest first (stable on PHP 8)
        $entries = array_slice($entries, 0, 150);
      ?>
      <?php if (empty($entries)): ?>
        <p style="color: var(--text-tertiary);">Nothing logged yet.</p>
      <?php else: ?>
        <div class="set-log">
          <?php foreach ($entries as $ev): ?>
            <div class="set-log__row">
              <span class="set-log__badge set-log__badge--<?= htmlspecialchars($ev['mod']) ?>"><?= htmlspecialchars($ev['badge']) ?></span>
              <div class="set-log__main">
                <div class="set-log__top"><span class="set-log__time"><?= htmlspecialchars($ev['time']) ?></span><span class="set-log__ip"><?= htmlspecialchars($ev['ip']) ?></span></div>
                <div class="set-log__meta"><?= htmlspecialchars($ev['sentence']) ?></div>
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

