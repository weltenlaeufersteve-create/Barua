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
    var csrf = window.Barua.csrf;

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
              var avatarEl = row.querySelector('[data-avatar]');
              avatarEl.style.background = colour;
              avatarEl.style.borderColor = colour;
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
      if (side) { side.style.background = colour; side.style.borderColor = colour; }
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
