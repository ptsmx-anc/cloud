<?php
/**
 * Emerald Central Hub V11 — Dashboard fragment (rendered inside the shell).
 * Data is loaded by modules/dashboard/app.js via ?api=dashboard&action=sys_info.
 */
if (!defined('EMERALD_SHELL')) {
    header('Location: index.php?p=dashboard');
    exit;
}
$__sys = getSystemStats();
?>
<div class="grid grid-4" id="statGrid">
  <div class="stat"><div class="k">Domain</div><div class="v small" id="stDomain"><?php echo sec_html(isset($__sys['domain']) ? $__sys['domain'] : '—'); ?></div></div>
  <div class="stat"><div class="k">Server IP</div><div class="v small mono" id="stIp"><?php echo sec_html(isset($__sys['server_ip']) ? $__sys['server_ip'] : '—'); ?></div></div>
  <div class="stat"><div class="k">PHP</div><div class="v small mono" id="stPhp"><?php echo sec_html(isset($__sys['php_version']) ? $__sys['php_version'] : '—'); ?></div></div>
  <div class="stat"><div class="k">Disk</div><div class="v small" id="stDisk">…</div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2>Recent activity</h2>
    <div class="table-wrap"><table class="tbl" id="actTable">
      <thead><tr><th>Time</th><th>User</th><th>Detail</th></tr></thead>
      <tbody><tr><td colspan="3" class="empty">Loading…</td></tr></tbody>
    </table></div>
  </div>
  <div class="card">
    <h2>Login logs</h2>
    <div class="table-wrap"><table class="tbl" id="logTable">
      <thead><tr><th>Time</th><th>User</th><th>IP</th><th>Status</th></tr></thead>
      <tbody><tr><td colspan="4" class="empty">Loading…</td></tr></tbody>
    </table></div>
  </div>
</div>

<div class="card">
  <h2>Vault overview</h2>
  <div class="grid grid-4">
    <div class="stat"><div class="k">Identities</div><div class="v" id="stUsers">…</div></div>
    <div class="stat"><div class="k">Cloaks / Domains</div><div class="v" id="stCloaks">…</div></div>
    <div class="stat"><div class="k">Firewall IPs</div><div class="v" id="stFw">…</div></div>
    <div class="stat"><div class="k">Notes</div><div class="v" id="stNotes">…</div></div>
  </div>
</div>
<script src="modules/dashboard/app.js"></script>
