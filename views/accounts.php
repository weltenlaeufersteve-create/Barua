<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barua — Accounts</title>
<script>
  (function () {
    var theme = localStorage.getItem('barua_theme');
    if (theme) document.documentElement.setAttribute('data-theme', theme);
  })();
</script>
<link rel="stylesheet" href="/css/theme.css">
<link rel="stylesheet" href="/css/app.css">
<style>
  .wrap { max-width: 760px; margin: 0 auto; padding: 32px 24px; }
  .account-row {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; border: 1px solid var(--border); border-radius: var(--radius-md);
    margin-bottom: 10px;
  }
  .account-row .dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
  .account-row .info { flex: 1; }
  .account-row .info strong { display: block; font-size: 14px; }
  .account-row .info span { font-size: 12.5px; color: var(--text-secondary); }
  .account-row form { margin: 0; }
  .account-row button {
    background: transparent; border: 1px solid var(--border); color: var(--text-secondary);
    border-radius: var(--radius-sm); padding: 6px 12px; font-size: 12.5px; cursor: pointer;
  }
  .account-row button:hover { background: var(--hover-bg); }
  fieldset { border: 1px solid var(--border); border-radius: var(--radius-md); margin: 0 0 16px; padding: 14px 16px; }
  legend { padding: 0 6px; font-size: 12.5px; color: var(--text-secondary); }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
</style>
</head>
<body>
  <div class="topbar">
    <strong>Barua — Accounts</strong>
    <a href="/">Back to inbox</a>
  </div>
  <div class="wrap">
    <?php if (!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($accounts)): ?>
      <p style="color: var(--text-tertiary);">No accounts yet — add your first one below.</p>
    <?php endif; ?>

    <?php foreach ($accounts as $acc): ?>
      <div class="account-row">
        <span class="dot" style="background: <?= htmlspecialchars($acc['colour']) ?>"></span>
        <div class="info">
          <strong><?= htmlspecialchars($acc['label']) ?></strong>
          <span><?= htmlspecialchars($acc['email']) ?> — <?= htmlspecialchars($acc['imap_host']) ?></span>
        </div>
        <form method="post" action="/accounts/<?= $acc['id'] ?>/delete" onsubmit="return confirm('Remove this account?');">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <button type="submit">Remove</button>
        </form>
      </div>
    <?php endforeach; ?>

    <h2 style="font-size: 16px; margin: 28px 0 14px;">Add account</h2>
    <form method="post" action="/accounts">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

      <fieldset>
        <legend>General</legend>
        <div class="field"><label>Label</label><input type="text" name="label" required></div>
        <div class="field"><label>Email address</label><input type="email" name="email" required></div>
        <div class="field"><label>Signature (optional)</label><input type="text" name="signature"></div>
      </fieldset>

      <fieldset>
        <legend>IMAP (incoming)</legend>
        <div class="grid-2">
          <div class="field"><label>Host</label><input type="text" name="imap_host" required></div>
          <div class="field"><label>Port</label><input type="number" name="imap_port" value="993" required></div>
        </div>
        <div class="field">
          <label>Encryption</label>
          <select name="imap_encryption" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--border); background:var(--input-bg); color:var(--text-primary);">
            <option value="ssl">SSL</option>
            <option value="tls">TLS</option>
            <option value="none">None</option>
          </select>
        </div>
        <div class="field"><label>Username</label><input type="text" name="imap_username" required></div>
        <div class="field"><label>Password</label><input type="password" name="imap_password" required></div>
      </fieldset>

      <fieldset>
        <legend>SMTP (outgoing)</legend>
        <div class="grid-2">
          <div class="field"><label>Host</label><input type="text" name="smtp_host" required></div>
          <div class="field"><label>Port</label><input type="number" name="smtp_port" value="587" required></div>
        </div>
        <div class="field">
          <label>Encryption</label>
          <select name="smtp_encryption" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--border); background:var(--input-bg); color:var(--text-primary);">
            <option value="tls">TLS</option>
            <option value="ssl">SSL</option>
            <option value="none">None</option>
          </select>
        </div>
        <div class="field"><label>Username</label><input type="text" name="smtp_username" required></div>
        <div class="field"><label>Password</label><input type="password" name="smtp_password" required></div>
      </fieldset>

      <button class="btn" type="submit" style="width: auto; padding: 10px 24px;">Add account</button>
    </form>
  </div>
</body>
</html>
