    var messages = window.Barua.messages;
    var currentMsgId = window.Barua.selectedId;
    var readTimer = null; // Outlook-style: mark read only after dwelling on a mail

    // HTML mail rendering: follows the current theme (dark mail on dark themes),
    // remote images blocked by default, toggleable via the floating icon row.
    var themeMode = (localStorage.getItem('barua_theme') || 'dark-neutral').split('-')[0];
    var readerImages = false;
    // Per-mail light/dark is a PERSISTENT reading preference: once set it applies to every
    // mail (great for reading bright newsletters dark at night), defaulting to the app theme
    // until the user overrides it.
    function readerDarkPref() {
      try {
        var v = localStorage.getItem('barua_reader_dark');
        if (v === '1') return true;
        if (v === '0') return false;
      } catch (e) {}
      return themeMode === 'dark';
    }
    var readerDark = readerDarkPref();
    var currentHasHtml = window.Barua.selectedHasHtml;

    var SUN_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
    var MOON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

    function readerFrameSrc() {
      var p = [];
      if (readerImages) p.push('images=1');
      if (readerDark) p.push('dark=1');
      return '/messages/' + currentMsgId + '/html' + (p.length ? '?' + p.join('&') : '');
    }
    function loadReaderFrame() {
      var frame = document.getElementById('reader-frame');
      if (frame && currentMsgId) frame.src = readerFrameSrc();
    }

    // Size the frame to its content instead of a fixed height: a short mail no longer
    // leaves a large void before the attachments, and a long one scrolls with the reader
    // as a whole rather than inside its own nested scrollbar.
    function sizeReaderFrame() {
      var frame = document.getElementById('reader-frame');
      if (!frame) return;
      var doc;
      try { doc = frame.contentDocument; } catch (e) { return; }
      if (!doc || !doc.documentElement) return;
      // Collapse first, else a previously taller frame keeps its own height as the floor.
      frame.style.height = '0px';
      var h = Math.max(
        doc.documentElement.scrollHeight,
        doc.body ? doc.body.scrollHeight : 0
      );
      frame.style.height = Math.max(h, 120) + 'px';
    }
    function updateThemeToggleIcon() {
      var btn = document.getElementById('reader-theme');
      if (btn) btn.innerHTML = readerDark ? SUN_SVG : MOON_SVG; // shows what you'll switch TO
    }

    // Apply the current light/dark choice to whichever body is showing: HTML mail is
    // re-rendered in the iframe, plain text swaps its own surface colours. Keeps the
    // toggle meaningful — and therefore always visible — for every mail.
    function applyReaderMode() {
      updateThemeToggleIcon();
      var plain = document.getElementById('reader-body');
      if (plain) {
        plain.classList.toggle('is-mail-dark', readerDark);
        plain.classList.toggle('is-mail-light', !readerDark);
      }
      // The HTML mail frame needs its own dark backdrop in dark mode — the mail renders
      // transparent+inverted over it, so this is what makes it dark in a light theme too.
      var frame = document.getElementById('reader-frame');
      if (frame) frame.classList.toggle('is-mail-dark', readerDark);
      if (currentHasHtml) loadReaderFrame();
    }

    // Reflect a message's pinned state on the reader's pin button.
    function setReaderPin(pinned) {
      var btn = document.getElementById('reader-pin');
      if (btn) btn.classList.toggle('is-pinned', !!pinned);
    }

    function showReaderBody(msg) {
      setReaderPin(msg.pinned);
      var plain = document.getElementById('reader-body');
      var wrap = document.getElementById('reader-html');
      var imgbar = document.getElementById('reader-imgbar');
      if (!wrap) return;
      currentHasHtml = !!msg.hasHtml;
      readerImages = false;                 // reset per message
      readerDark = readerDarkPref();        // persistent choice, not reset to the theme
      // Only the remote-image notice is HTML-specific; the rest of the bar is universal.
      if (imgbar) imgbar.style.display = currentHasHtml ? '' : 'none';
      plain.style.display = currentHasHtml ? 'none' : '';
      wrap.style.display = currentHasHtml ? '' : 'none';
      applyReaderMode();
    }

    var loadImagesBtn = document.getElementById('load-images');
    if (loadImagesBtn) loadImagesBtn.addEventListener('click', function () {
      readerImages = true;
      loadReaderFrame();
      document.getElementById('reader-imgbar').style.display = 'none';
    });

    var themeToggleBtn = document.getElementById('reader-theme');
    if (themeToggleBtn) themeToggleBtn.addEventListener('click', function () {
      readerDark = !readerDark;
      try { localStorage.setItem('barua_reader_dark', readerDark ? '1' : '0'); } catch (e) {}
      applyReaderMode();
    });

    var readerFrameEl = document.getElementById('reader-frame');
    if (readerFrameEl) {
      readerFrameEl.addEventListener('load', function () {
        sizeReaderFrame();
        // Images that finish decoding after load can still grow the document.
        setTimeout(sizeReaderFrame, 300);
      });
    }
    var frameResizeTimer = null;
    window.addEventListener('resize', function () {
      clearTimeout(frameResizeTimer);
      frameResizeTimer = setTimeout(sizeReaderFrame, 150); // content reflows at new width
    });

    var printBtn = document.getElementById('reader-print');
    if (printBtn) printBtn.addEventListener('click', function () {
      // Always print from a throwaway LIGHT frame, never the on-screen one — a mail shown
      // dark would otherwise print white-on-white. The /html endpoint (no dark param)
      // renders both HTML and plain-text bodies on white; carry the images choice through.
      if (!currentMsgId) return;
      var tmp = document.createElement('iframe');
      tmp.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
      tmp.src = '/messages/' + currentMsgId + '/html' + (readerImages ? '?images=1' : '');
      tmp.onload = function () {
        try { tmp.contentWindow.focus(); tmp.contentWindow.print(); } catch (e) {}
        setTimeout(function () { tmp.remove(); }, 2000);
      };
      document.body.appendChild(tmp);
    });

    // Initialise the pre-selected message's rendering (respecting the app theme).
    applyReaderMode();
    if (currentMsgId) fetchThread(currentMsgId);

    // Row actions: pin toggle (IMAP \Flagged), archive, trash — server write + cache + UI.
    var mainCsrf = window.Barua.csrf;
    var currentView = window.Barua.view;              // folder axis
    var currentType = window.Barua.type;              // type axis
    var filterPinnedOn = window.Barua.filterPinned;
    var filterAttachOn = window.Barua.filterAttach;
    var currentAccount = window.Barua.account;
    var isDraftView = window.Barua.isDraftView;

    function msgAction(id, action, data, cb) {
      var body = new URLSearchParams();
      body.set('csrf_token', mainCsrf);
      Object.keys(data || {}).forEach(function (k) { body.set(k, data[k]); });
      fetch('/messages/' + id + '/' + action, { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(cb)
        .catch(function () { cb({ ok: false, error: 'Network error' }); });
    }

    function bumpPinnedBadge(delta) {
      var badge = document.getElementById('pinned-count');
      if (!badge) return;
      var n = (parseInt(badge.textContent, 10) || 0) + delta;
      badge.textContent = n > 0 ? n : '';
    }

    function removeRow(row) {
      row.style.transition = 'opacity 0.15s ease';
      row.style.opacity = '0';
      setTimeout(function () { row.remove(); }, 150);
    }

    // Draft rows: click reopens the composer with the draft; trash deletes it.
    var drafts = window.Barua.drafts;
    document.querySelectorAll('.mail-row[data-draft]').forEach(function (row) {
      var did = parseInt(row.dataset.draft, 10);
      row.addEventListener('click', function () {
        var d = drafts[did];
        if (!d || !window.baruaCompose) return;
        window.baruaCompose({
          title: 'Draft', fromAccount: d.accountId, to: d.to, cc: d.cc, bcc: d.bcc,
          subject: d.subject, body: d.body, draftId: did, attachments: d.attachments || []
        });
      });
      var delBtn = row.querySelector('.row-action[title="Delete draft"]');
      if (delBtn) delBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var body = new URLSearchParams();
        body.set('csrf_token', mainCsrf);
        fetch('/drafts/' + did + '/delete', { method: 'POST', body: body })
          .then(function (r) { return r.json(); })
          .then(function (res) { if (res.ok) removeRow(row); });
      });
    });

    // Human file size, mirrors PHP's formatBytes() in helpers.php.
    function formatSize(bytes) {
      if (bytes < 1024) return bytes + ' B';
      if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
      return (Math.round(bytes / 1024 / 1024 * 10) / 10) + ' MB';
    }
    function escapeHtml(s) {
      return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    var ATTACHMENT_SVG = '<svg class="sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05 12.25 20.24a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>';
    var MORE_DOTS_SVG = '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>';
    function renderReaderAttachments(list) {
      var wrap = document.getElementById('reader-attachments');
      if (!wrap) return;
      if (!list || !list.length) {
        wrap.style.display = 'none';
        wrap.innerHTML = '';
        return;
      }
      wrap.innerHTML = list.map(function (att) {
        var preview = att.previewable
          ? '<a class="attachment-chip__menu-item" href="/attachments/' + att.id + '?preview=1" target="_blank" rel="noopener">Preview</a>'
          : '';
        return '<div class="attachment-chip" data-att-id="' + att.id + '">' + ATTACHMENT_SVG
          + '<div class="attachment-chip__info">'
          + '<span class="attachment-chip__name">' + escapeHtml(att.filename) + '</span>'
          + '<span class="attachment-chip__size">' + escapeHtml(formatSize(att.size)) + '</span>'
          + '</div>'
          + '<button type="button" class="attachment-chip__more" title="More">' + MORE_DOTS_SVG + '</button>'
          + '<div class="attachment-chip__menu">'
          + '<a class="attachment-chip__menu-item" href="/attachments/' + att.id + '">Download</a>'
          + preview
          + '</div></div>';
      }).join('');
      wrap.style.display = '';
    }

    // ---- Conversation thread stack ----
    // Fetched newest-first; cached so the order toggle re-renders without a refetch.
    var THREAD_ORDER_KEY = 'barua_thread_order';
    var threadCache = [];
    function threadOrderAsc() {
      try { return localStorage.getItem(THREAD_ORDER_KEY) === 'asc'; } catch (e) { return false; }
    }
    function renderThread() {
      var wrap = document.getElementById('reader-thread');
      var list = document.getElementById('thread-list');
      if (!wrap || !list) return;
      if (!threadCache.length) { wrap.style.display = 'none'; list.innerHTML = ''; return; }
      var asc = threadOrderAsc();
      var items = asc ? threadCache.slice().reverse() : threadCache;
      document.getElementById('thread-count').textContent = threadCache.length;
      var orderBtn = document.getElementById('thread-order');
      if (orderBtn) orderBtn.textContent = asc ? 'Oldest first' : 'Newest first';
      list.innerHTML = items.map(function (m) {
        var badge = m.folder === 'sent' ? '<span class="thread-msg__badge">Sent</span>'
          : m.folder === 'archive' ? '<span class="thread-msg__badge">Archive</span>' : '';
        return '<div class="thread-msg is-collapsed" tabindex="0" role="button">'
          + '<div class="thread-msg__row">'
          + '<span class="thread-msg__sender">' + escapeHtml(m.sender) + '</span>'
          + badge
          + '<span class="thread-msg__time">' + escapeHtml(m.time) + '</span>'
          + '</div>'
          + '<div class="thread-msg__preview">' + escapeHtml(m.snippet) + '</div>'
          + '<div class="thread-msg__body">' + escapeHtml(m.body).replace(/\n/g, '<br>') + '</div>'
          + '</div>';
      }).join('');
      wrap.style.display = '';
    }
    function fetchThread(msgId) {
      threadCache = [];
      renderThread(); // collapse the old thread immediately while the new one loads
      if (!msgId) return;
      fetch('/messages/' + msgId + '/thread')
        .then(function (r) { return r.json(); })
        .then(function (res) { threadCache = (res && res.ok && res.messages) || []; renderThread(); })
        .catch(function () {});
    }
    var threadListEl = document.getElementById('thread-list');
    if (threadListEl) {
      threadListEl.addEventListener('click', function (e) {
        var strip = e.target.closest('.thread-msg');
        if (strip) strip.classList.toggle('is-collapsed');
      });
    }
    var threadOrderBtn = document.getElementById('thread-order');
    if (threadOrderBtn) {
      threadOrderBtn.addEventListener('click', function () {
        try { localStorage.setItem(THREAD_ORDER_KEY, threadOrderAsc() ? 'desc' : 'asc'); } catch (e) {}
        renderThread();
      });
    }

    // Wire one message row: reader-open on click + pin/archive/trash actions.
    // Used for the initial rows AND for rows slid in later by the live stream.
    function wireMessageRow(row) {
      var id = row.dataset.msg;

      row.addEventListener('click', function () {
        document.querySelectorAll('.mail-row').forEach(function (r) { r.classList.remove('is-selected'); });
        row.classList.add('is-selected');
        var wasUnread = row.classList.contains('is-unread');

        var msg = messages[id];
        if (!msg) return;
        currentMsgId = parseInt(id, 10);

        // Mark read only after dwelling ~3s (like Outlook): a quick glance that
        // moves on before the timer fires leaves the mail unread and skips the
        // IMAP \Seen write entirely. Switching mails cancels a pending timer.
        if (readTimer) { clearTimeout(readTimer); readTimer = null; }
        if (wasUnread) {
          var targetId = currentMsgId;
          readTimer = setTimeout(function () {
            readTimer = null;
            if (currentMsgId !== targetId) return; // navigated away
            row.classList.remove('is-unread');
            var rb = new URLSearchParams();
            rb.set('csrf_token', mainCsrf);
            fetch('/messages/' + targetId + '/read', { method: 'POST', body: rb });
            setInboxBadge((parseInt(document.getElementById('inbox-count').textContent, 10) || 1) - 1);
          }, 3000);
        }

        document.getElementById('reader-subject').textContent = msg.subject || '(no subject)';
        document.getElementById('reader-body').textContent = msg.body;
        showReaderBody(msg);
        var meta = document.getElementById('reader-meta');
        var avatar = meta.querySelector('.reader__avatar');
        avatar.style.background = msg.accountColour;
        avatar.textContent = msg.initial;
        avatar.setAttribute('data-account', msg.accountId);
        meta.querySelector('.reader__from-name').textContent = msg.sender;
        meta.querySelector('.reader__from-email').textContent = msg.email + ' · ' + msg.accountLabel;
        meta.querySelector('.reader__time').textContent = msg.fullTime;
        renderReaderAttachments(msg.attachments);
        fetchThread(currentMsgId);

        document.body.setAttribute('data-mobile-view', 'reader');
      });

      var pinBtn = row.querySelector('.row-action--pin');
      var archBtn = row.querySelector('.row-action[title="Archive"]');
      var trashBtn = row.querySelector('.row-action[title="Delete"]');

      if (pinBtn) pinBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var nowPinned = !pinBtn.classList.contains('is-pinned');
        pinBtn.classList.toggle('is-pinned', nowPinned); // optimistic
        msgAction(id, 'pin', { pinned: nowPinned ? '1' : '0' }, function (res) {
          if (!res.ok) { pinBtn.classList.toggle('is-pinned', !nowPinned); return; }
          bumpPinnedBadge(nowPinned ? 1 : -1);
          if (messages[id]) messages[id].pinned = nowPinned;
          // Keep the reader's pin in step when it's the same message.
          if (parseInt(id, 10) === currentMsgId) setReaderPin(nowPinned);
          if (!nowPinned && filterPinnedOn) removeRow(row); // no longer matches the active filter
        });
      });

      if (archBtn) archBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        msgAction(id, 'archive', {}, function (res) { if (res.ok) removeRow(row); });
      });

      if (trashBtn) trashBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        msgAction(id, 'trash', {}, function (res) { if (res.ok) removeRow(row); });
      });
    }

    document.querySelectorAll('.mail-row[data-msg]').forEach(wireMessageRow);

    function setInboxBadge(n) {
      var badge = document.getElementById('inbox-count');
      if (badge) badge.textContent = n > 0 ? n : '';
      updateTabIndicator(n);
    }

    // ---- Tab indicator: put the unread count in the title, e.g. "(3) Barua Mail". This is
    // also what makes Firefox show its own blue "attention" dot on a backgrounded pinned tab
    // — that dot is drawn by the BROWSER; the page only has to change the title to trigger it. ----
    var TAB_BASE_TITLE = 'Barua Mail';
    var lastTabUnread = -1;
    function updateTabIndicator(n) {
      n = n > 0 ? n : 0;
      if (n === lastTabUnread) return;
      lastTabUnread = n;
      document.title = n > 0 ? '(' + n + ') ' + TAB_BASE_TITLE : TAB_BASE_TITLE;
    }
    updateTabIndicator(window.Barua.totalUnread);

    // Reader toolbar: archive the currently open message.
    var readerArchive = document.getElementById('reader-archive');
    if (readerArchive) readerArchive.addEventListener('click', function () {
      if (!currentMsgId) return;
      msgAction(currentMsgId, 'archive', {}, function (res) {
        if (!res.ok) return;
        var row = document.querySelector('.mail-row[data-msg="' + currentMsgId + '"]');
        if (row) removeRow(row);
        document.body.setAttribute('data-mobile-view', 'list');
      });
    });

    // Attachment chips: ⋮ opens Download/Preview, chip body is a click-to-download
    // shortcut. Delegated on the wrapper since chips are replaced via innerHTML on every
    // message switch — per-chip listeners would be lost, delegation survives it.
    var attWrap = document.getElementById('reader-attachments');
    if (attWrap) {
      function closeAttachmentMenus() {
        attWrap.querySelectorAll('.attachment-chip.is-menu-open').forEach(function (c) {
          c.classList.remove('is-menu-open', 'is-menu-up');
        });
      }
      // Flip the menu above its chip when it wouldn't fit below. The reader scrolls with
      // overflow-y, which clips absolutely positioned children at its edge, so the last
      // chip row's menu would otherwise disappear under the Reply/Forward/Archive bar.
      function placeAttachmentMenu(chip) {
        var menu = chip.querySelector('.attachment-chip__menu');
        var scroller = document.querySelector('.reader__content');
        if (!menu || !scroller) return;
        var room = scroller.getBoundingClientRect().bottom - chip.getBoundingClientRect().bottom;
        chip.classList.toggle('is-menu-up', room < menu.offsetHeight + 12);
      }
      attWrap.addEventListener('click', function (e) {
        var chip = e.target.closest('.attachment-chip');
        if (!chip) return;
        var moreBtn = e.target.closest('.attachment-chip__more');
        if (moreBtn) {
          e.stopPropagation();
          var wasOpen = chip.classList.contains('is-menu-open');
          closeAttachmentMenus();
          if (!wasOpen) {
            chip.classList.add('is-menu-open'); // must be visible before it can be measured
            placeAttachmentMenu(chip);
          }
          return;
        }
        if (e.target.closest('.attachment-chip__menu')) {
          return; // Download/Preview link — let it navigate normally
        }
        window.location.href = '/attachments/' + chip.dataset.attId;
      });
      document.addEventListener('click', closeAttachmentMenus);
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAttachmentMenus();
      });
    }

    // Reader pin: same action as the row icon, and both stay in step.
    var readerPinBtn = document.getElementById('reader-pin');
    if (readerPinBtn) readerPinBtn.addEventListener('click', function () {
      if (!currentMsgId) return;
      var msgId = currentMsgId;
      var nowPinned = !readerPinBtn.classList.contains('is-pinned');
      setReaderPin(nowPinned); // optimistic
      msgAction(msgId, 'pin', { pinned: nowPinned ? '1' : '0' }, function (res) {
        if (!res.ok) { setReaderPin(!nowPinned); return; }
        if (messages[msgId]) messages[msgId].pinned = nowPinned;
        bumpPinnedBadge(nowPinned ? 1 : -1);
        var rowPin = document.querySelector('.mail-row[data-msg="' + msgId + '"] .row-action--pin');
        if (rowPin) rowPin.classList.toggle('is-pinned', nowPinned);
        var row = document.querySelector('.mail-row[data-msg="' + msgId + '"]');
        if (!nowPinned && filterPinnedOn && row) removeRow(row);
      });
    });

    // Reader ⋮ menu: Delete / Archive / Mark as spam for the open message.
    var moreBtn = document.getElementById('reader-more');
    var moreMenu = document.getElementById('reader-menu');
    if (moreBtn && moreMenu) {
      moreBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        moreMenu.classList.toggle('is-open');
      });
      moreMenu.addEventListener('click', function (e) { e.stopPropagation(); });
      document.addEventListener('click', function () { moreMenu.classList.remove('is-open'); });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') moreMenu.classList.remove('is-open');
      });
      // Which view each group is shown in, so we know whether the mail still belongs
      // in the list we're looking at after being refiled.

      moreMenu.querySelectorAll('.reader__menu-item').forEach(function (item) {
        item.addEventListener('click', function () {
          moreMenu.classList.remove('is-open');
          if (!currentMsgId) return;
          var msgId = currentMsgId;

          // Reply / Forward — same handlers the toolbar pills use.
          if (item.dataset.compose) {
            if (item.dataset.compose === 'reply') { openReply(); } else { openForward(); }
            return;
          }

          // "Move to group" — local only, no IMAP round-trip.
          if (item.dataset.group) {
            msgAction(msgId, 'group', { group: item.dataset.group }, function (res) {
              if (!res.ok) return;
              // Drop the row only when a type is active and the mail no longer matches
              // it. Clean Inbox counts too: moving a mail to Newsletters/Notifications
              // takes it out of that list. With no type active it simply stays put.
              var stillFits = item.dataset.group === currentType
                || (currentType === 'clean' && item.dataset.group === 'people');
              if (currentType !== '' && !stillFits) {
                var gRow = document.querySelector('.mail-row[data-msg="' + msgId + '"]');
                if (gRow) removeRow(gRow);
                document.body.setAttribute('data-mobile-view', 'list');
              }
            });
            return;
          }

          msgAction(msgId, item.dataset.action, {}, function (res) {
            if (!res.ok) return;
            var row = document.querySelector('.mail-row[data-msg="' + msgId + '"]');
            if (row) removeRow(row);
            document.body.setAttribute('data-mobile-view', 'list');
          });
        });
      });
    }

    // Mobile filter button: pops the filter pills out above it, so a view change is
    // two thumb taps. The pills are the same elements the desktop row uses.
    var filterFab = document.getElementById('filter-fab');
    var filterPills = document.getElementById('filter-pills');
    if (filterFab && filterPills) {
      var FILTER_SVG = filterFab.innerHTML;
      var CLOSE_SVG = '<svg class="sidebar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
      var setFilterOpen = function (open) {
        filterPills.classList.toggle('is-open', open);
        filterFab.innerHTML = open ? CLOSE_SVG : FILTER_SVG;
      };
      filterFab.addEventListener('click', function (e) {
        e.stopPropagation();
        setFilterOpen(!filterPills.classList.contains('is-open'));
      });
      // Both kinds of pill are links, so a tap reloads the page — "staying open" means
      // surviving that reload. A FILTER is a switch you often flip twice in a row, so it
      // reopens the pop-out; a TYPE is a destination, so it closes (no flag written).
      var FILTERS_OPEN_KEY = 'barua_filters_open';
      filterPills.addEventListener('click', function (e) {
        e.stopPropagation();
        if (e.target.closest('.filter-pill--toggle')) {
          try { sessionStorage.setItem(FILTERS_OPEN_KEY, '1'); } catch (err) {}
        }
      });
      try {
        if (sessionStorage.getItem(FILTERS_OPEN_KEY) === '1') {
          sessionStorage.removeItem(FILTERS_OPEN_KEY); // one reload only
          setFilterOpen(true);
        }
      } catch (err) {}

      // When the pop-out is open, the first outside tap should ONLY dismiss it — not also
      // open whatever it landed on (a mail row). Capture phase runs before the row's own
      // click handler, so swallowing the event here stops the mail from opening; the next
      // tap works normally. Taps on the FAB / inside the pop-out have their own handlers.
      document.addEventListener('click', function (e) {
        if (!filterPills.classList.contains('is-open')) return;
        if (e.target.closest('#filter-fab') || e.target.closest('#filter-pills')) return;
        setFilterOpen(false);
        e.stopPropagation();
        e.preventDefault();
      }, true);
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setFilterOpen(false);
      });
    }

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

    // Reply / Forward → open the compose panel prefilled.
    function quote(msg) {
      return '\n\n\n----- Original message -----\n' +
        'From: ' + msg.sender + ' <' + msg.email + '>\n' +
        'Subject: ' + (msg.subject || '') + '\n\n' +
        msg.body.split('\n').map(function (l) { return '> ' + l; }).join('\n');
    }
    // Named so the toolbar pills and the ⋮ menu can share them.
    function openReply() {
      var msg = messages[currentMsgId];
      if (!msg || !window.baruaCompose) return;
      var subj = /^re:/i.test(msg.subject || '') ? msg.subject : 'Re: ' + (msg.subject || '');
      window.baruaCompose({
        title: 'Reply', fromAccount: msg.accountId, to: msg.email,
        subject: subj, body: quote(msg), inReplyTo: msg.messageId, references: msg.messageId
      });
    }
    function openForward() {
      var msg = messages[currentMsgId];
      if (!msg || !window.baruaCompose) return;
      var subj = /^fwd?:/i.test(msg.subject || '') ? msg.subject : 'Fwd: ' + (msg.subject || '');
      window.baruaCompose({
        title: 'Forward', fromAccount: msg.accountId, to: '',
        subject: subj, body: quote(msg)
      });
    }
    var replyBtn = document.getElementById('reader-reply');
    if (replyBtn) replyBtn.addEventListener('click', openReply);
    var fwdBtn = document.getElementById('reader-forward');
    if (fwdBtn) fwdBtn.addEventListener('click', openForward);

    // ---- Live stream: poll for new mail, slide it into the list ----
    var notifyEl = document.getElementById('list-notify');
    var notifyTimer = null;
    var notifyCount = 0;
    function hideNotify() {
      if (notifyEl) notifyEl.classList.remove('is-visible');
      notifyCount = 0;
    }
    // Announce N new messages; accumulate if already showing. Stays until the user
    // "sees" it (scrolls the list to top or clicks it), with a generous fallback.
    function baruaNotify(n) {
      if (!notifyEl) return;
      if (!notifyEl.classList.contains('is-visible')) notifyCount = 0;
      notifyCount += n;
      notifyEl.textContent = notifyCount === 1 ? '1 new message ↑' : notifyCount + ' new messages ↑';
      notifyEl.classList.add('is-visible');
      if (notifyTimer) clearTimeout(notifyTimer);
      notifyTimer = setTimeout(hideNotify, 12000);
    }
    if (notifyEl) notifyEl.addEventListener('click', function () {
      var sc = document.getElementById('mail-list-scroll');
      if (sc) sc.scrollTo({ top: 0, behavior: 'smooth' });
      hideNotify();
    });
    var listScrollEl = document.getElementById('mail-list-scroll');
    if (listScrollEl) listScrollEl.addEventListener('scroll', function () {
      if (listScrollEl.scrollTop < 8) hideNotify();
    });

    function streamCursor() {
      var max = 0;
      document.querySelectorAll('.mail-row[data-msg]').forEach(function (r) {
        var id = parseInt(r.dataset.msg, 10);
        if (id > max) max = id;
      });
      return max;
    }

    function ensureTodayGroup(scroll) {
      var first = scroll.querySelector('.mail-list__date-group');
      if (first && first.dataset.group === 'Today') return first;
      var hdr = document.createElement('div');
      hdr.className = 'mail-list__date-group';
      hdr.dataset.group = 'Today';
      hdr.textContent = 'Today';
      scroll.insertBefore(hdr, scroll.firstChild);
      return hdr;
    }

    function pollStream() {
      if (isDraftView || document.hidden) return;
      // Carry every axis, or streamed-in rows wouldn't match the narrowing on screen.
      var url = '/api/stream?view=' + encodeURIComponent(currentView) +
                '&type=' + encodeURIComponent(currentType) +
                (filterPinnedOn ? '&pinned=1' : '') +
                (filterAttachOn ? '&attachments=1' : '') +
                '&after=' + streamCursor() +
                (currentAccount !== null ? '&account=' + currentAccount : '');
      fetch(url).then(function (r) { return r.json(); }).then(function (res) {
        if (!res || !res.ok) return;
        if (typeof res.unread === 'number') setInboxBadge(res.unread);
        if (!res.rows || !res.rows.length) return;

        var scroll = document.getElementById('mail-list-scroll');
        if (!scroll) return;
        var todayHeader = ensureTodayGroup(scroll);
        var atTop = scroll.scrollTop < 8;
        var addedHeight = 0;

        // API returns newest-first; insert so newest ends up on top, just under "Today".
        res.rows.slice().reverse().forEach(function (item) {
          if (document.querySelector('.mail-row[data-msg="' + item.id + '"]')) return;
          messages[item.id] = item.data;
          var tmp = document.createElement('div');
          tmp.innerHTML = item.html;
          var row = tmp.firstElementChild;
          row.classList.add('just-arrived');
          todayHeader.after(row);
          wireMessageRow(row);
          addedHeight += row.offsetHeight;
        });

        // Don't yank the list while the user is reading further down.
        if (!atTop) scroll.scrollTop += addedHeight;

        baruaNotify(res.rows.length);
      }).catch(function () {});
    }

    // Manual refresh (⟳): run a real IMAP sync, then reflect new mail live — no page reload.
    var syncBtn = document.getElementById('sync-now');
    if (syncBtn) syncBtn.addEventListener('click', function () {
      if (syncBtn.classList.contains('spinning')) return;
      syncBtn.classList.add('spinning');
      var body = new URLSearchParams();
      body.set('csrf_token', mainCsrf);
      body.set('ajax', '1');
      fetch('/sync', { method: 'POST', body: body })
        .then(function (r) { return r.json().catch(function () { return { ok: true }; }); })
        .then(function () { pollStream(); })
        .catch(function () {})
        .finally(function () { syncBtn.classList.remove('spinning'); });
    });

    // Gentle background heartbeat (cron only produces mail every ~3 min) + instant catch-up
    // whenever the tab regains focus. The ⟳ button is the immediate manual path.
    setInterval(pollStream, 150000);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) pollStream();
    });
