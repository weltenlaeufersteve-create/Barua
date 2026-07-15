<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barua</title>
<script>
  (function () {
    var theme = localStorage.getItem('barua_theme');
    if (theme) document.documentElement.setAttribute('data-theme', theme);
  })();
</script>
<link rel="stylesheet" href="/css/theme.css">
<link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="topbar">
    <strong>Barua</strong>
    <a href="/logout">Sign out</a>
  </div>
  <div class="center-screen" style="min-height: calc(100vh - 60px);">
    <div class="card" style="text-align:center;">
      <h1>Signed in as <?= htmlspecialchars($username) ?></h1>
      <p style="color: var(--text-secondary); font-size: 13px;">Unified inbox scaffold — accounts, sync and message list are next.</p>
    </div>
  </div>
</body>
</html>
