<?php
/**
 * Emerald Central Hub V11 — dashboard shell.
 * Rendered by index.php when an authenticated session exists.
 * Includes the active module fragment (modules/<p>/index.php).
 */
$__page = isset($GLOBALS['emerald_page']) ? $GLOBALS['emerald_page'] : 'dashboard';
$__user = auth_user();
$__users = db_load('users');
$__role = isset($__users[$__user]['role']) ? $__users[$__user]['role'] : 'user';
$__csrf = sec_csrf_token();

$__nav = array(
    'dashboard' => 'Dashboard',
    'storage'   => 'Storage',
    'domains'   => 'Domains',
    'cloaking'  => 'Cloaking',
    'notes'     => 'Notes',
    'users'     => 'Users',
    'firewall'  => 'Firewall',
    'monitor'   => 'Monitor',
    'notepad'   => 'Notepad',
);
?><!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($__nav[$__page], ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="assets/css/app.css">
<script>
window.EM = window.EM || {};
window.EM.csrf = <?php echo json_encode($__csrf); ?>;
window.EM.user = <?php echo json_encode($__user); ?>;
window.EM.role = <?php echo json_encode($__role); ?>;
window.EM.page = <?php echo json_encode($__page); ?>;
</script>
<script src="assets/js/app.js"></script>
</head>
<body class="app-body">
<div class="shell">
  <aside class="sidebar">
    <div class="side-brand">
      <div class="logo-badge">E</div>
      <div class="side-title"><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?><small>v<?php echo htmlspecialchars(APP_VERSION, ENT_QUOTES, 'UTF-8'); ?></small></div>
    </div>
    <nav class="side-nav">
      <?php foreach ($__nav as $__key => $__label): ?>
        <a href="index.php?p=<?php echo $__key; ?>" class="nav-item<?php echo $__key === $__page ? ' active' : ''; ?>" data-page="<?php echo $__key; ?>"><?php echo htmlspecialchars($__label, ENT_QUOTES, 'UTF-8'); ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="notepad/" class="nav-item" target="_blank" rel="noopener">Public Notepad ↗</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar-title"><h1><?php echo htmlspecialchars($__nav[$__page], ENT_QUOTES, 'UTF-8'); ?></h1></div>
      <div class="topbar-right">
        <span class="online-dot" id="hbDot" title="Connection status"></span>
        <button type="button" class="theme-toggle" id="themeToggle" title="Toggle theme">◐</button>
        <div class="user-chip">
          <span class="avatar-mini" id="topAvatar"><?php echo htmlspecialchars(strtoupper(substr($__user, 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="user-meta"><b><?php echo htmlspecialchars($__user, ENT_QUOTES, 'UTF-8'); ?></b><small><?php echo htmlspecialchars($__role, ENT_QUOTES, 'UTF-8'); ?></small></span>
        </div>
        <a class="btn ghost sm" href="index.php?action=logout">Sign out</a>
      </div>
    </header>

    <main class="content" id="content">
      <?php
        define('EMERALD_SHELL', true);
        $__frag = MODULES_DIR . '/' . $__page . '/index.php';
        if (is_file($__frag)) {
            require $__frag;
        } else {
            echo '<div class="card"><p class="muted">Module not found.</p></div>';
        }
      ?>
    </main>
  </div>
</div>
<div class="modal-mask hide" id="modalMask"><div class="modal" id="modalBox"></div></div>
<div class="toast" id="toast"></div>
</body>
</html>
