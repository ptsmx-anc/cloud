<?php
/**
 * Emerald Central Hub V11 — login view.
 * Rendered by index.php when no session exists.
 * Talks to index.php?action=login|get_sec_q|reset_pass (V10-compatible).
 */
$__u = auth_user();
$__csrf = sec_csrf_token();
?><!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?> — Sign in</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-page">
  <div class="login-wrap">
    <div class="login-card">
      <div class="login-brand">
        <div class="logo-badge">E</div>
        <h1><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="muted"><?php echo htmlspecialchars(APP_TAGLINE, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>

      <div class="vf" id="view-login">
        <div class="field">
          <label>Identity</label>
          <input id="login_user" type="text" placeholder="your.username" autocomplete="username" autofocus>
        </div>
        <div class="field">
          <label>Password</label>
          <input id="login_pass" type="password" placeholder="••••••••••" autocomplete="current-password">
        </div>
        <button class="btn-primary btn-block" id="loginBtn">Sign in</button>
        <button type="button" class="linkbtn btn-block" id="toResetBtn">Forgot password?</button>
      </div>

      <div class="vf hide" id="view-reset">
        <div class="field" id="resetStep1">
          <label>Identity</label>
          <input id="reset_user" type="text" placeholder="your.username" autocomplete="username">
          <button class="btn-primary btn-block" id="resetAskBtn" style="margin-top:14px">Get security question</button>
        </div>
        <div class="field hide" id="resetStep2">
          <p class="qtext" id="sec_q_display"></p>
          <label>Answer</label>
          <input id="reset_answer" type="text" placeholder="your answer" autocomplete="off">
          <label>New password</label>
          <input id="reset_new_pass" type="password" placeholder="••••••••••" autocomplete="new-password">
          <button class="btn-primary btn-block" id="resetBtn" style="margin-top:14px">Reset password</button>
        </div>
        <button type="button" class="linkbtn btn-block" id="backToLoginBtn">← Back to sign in</button>
      </div>

      <div class="login-foot">
        <button type="button" class="theme-toggle" id="themeToggle" title="Toggle theme">◐ Theme</button>
        <span class="muted" id="versionTag">v<?php echo htmlspecialchars(APP_VERSION, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
    </div>
  </div>
<script>
window.EM = window.EM || {};
window.EM.csrf = <?php echo json_encode($__csrf); ?>;
</script>
<script src="assets/js/app.js"></script>
<script>
(function () {
  'use strict';
  var EM = window.EM;

  function show(v) {
    document.getElementById('view-login').classList.toggle('hide', v === 'reset');
    document.getElementById('view-reset').classList.toggle('hide', v !== 'reset');
  }

  document.getElementById('loginBtn').addEventListener('click', function () {
    var btn = this, user = document.getElementById('login_user').value.trim(), pass = document.getElementById('login_pass').value;
    if (!user || !pass) { EM.toast('Identity and password are required', 'err'); return; }
    btn.disabled = true;
    EM.post('index.php?action=login', { username: user, password: pass }).then(function (res) {
      btn.disabled = false;
      if (res.status === 'ok') { window.location.href = 'index.php'; return; }
      EM.toast(res.message || 'Invalid credentials', 'err');
    }).catch(function () { btn.disabled = false; EM.toast('Network error', 'err'); });
  });

  document.getElementById('resetAskBtn').addEventListener('click', function () {
    var btn = this, user = document.getElementById('reset_user').value.trim();
    if (!user) { EM.toast('Identity required', 'err'); return; }
    btn.disabled = true;
    EM.post('index.php?action=get_sec_q', { username: user }).then(function (res) {
      btn.disabled = false;
      if (res.status !== 'success') { EM.toast(res.message || 'Identity not found', 'err'); return; }
      document.getElementById('reset_user').value = res.actual_user;
      document.getElementById('sec_q_display').textContent = res.question;
      document.getElementById('resetStep1').classList.add('hide');
      document.getElementById('resetStep2').classList.remove('hide');
    }).catch(function () { btn.disabled = false; EM.toast('Network error', 'err'); });
  });

  document.getElementById('resetBtn').addEventListener('click', function () {
    var btn = this, user = document.getElementById('reset_user').value.trim(),
        answer = document.getElementById('reset_answer').value.trim(),
        pass = document.getElementById('reset_new_pass').value;
    if (!user || !answer || !pass) { EM.toast('Fill in all fields', 'err'); return; }
    btn.disabled = true;
    EM.post('index.php?action=reset_pass', { username: user, answer: answer, new_pass: pass }).then(function (res) {
      btn.disabled = false;
      if (res.status !== 'ok') { EM.toast(res.message || 'Reset failed', 'err'); return; }
      EM.toast('Password updated — sign in now', 'ok');
      document.getElementById('reset_answer').value = '';
      document.getElementById('reset_new_pass').value = '';
      show('login');
      document.getElementById('login_user').value = user;
      document.getElementById('login_pass').focus();
    }).catch(function () { btn.disabled = false; EM.toast('Network error', 'err'); });
  });

  document.getElementById('toResetBtn').addEventListener('click', function () { show('reset'); document.getElementById('reset_user').focus(); });
  document.getElementById('backToLoginBtn').addEventListener('click', function () { show('login'); document.getElementById('login_user').focus(); });

  ['login_user','login_pass'].forEach(function (id) {
    document.getElementById(id).addEventListener('keydown', function (e) { if (e.key === 'Enter') { document.getElementById('loginBtn').click(); } });
  });
  ['reset_answer','reset_new_pass'].forEach(function (id) {
    document.getElementById(id).addEventListener('keydown', function (e) { if (e.key === 'Enter') { document.getElementById('resetBtn').click(); } });
  });
})();
</script>
</body>
</html>
