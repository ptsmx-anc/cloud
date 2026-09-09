<?php
/**
 * Emerald Central Hub V11 — Firewall fragment.
 * Rendered inside the dashboard shell; logic in modules/firewall/app.js.
 */
if (!defined('EMERALD_SHELL')) {
    header('Location: index.php?p=firewall');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>Firewall whitelist</h2>
    <button class="btn btn-primary sm" id="btnAddFw">+ Whitelist IP</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="fwTable">
      <thead><tr>
        <th>IP</th><th>Note</th><th>Added</th><th>Owner</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="5" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="fwFormCard">
  <h2>Whitelist IP</h2>
  <div class="grid grid-2">
    <div class="field"><label>IP address</label><input type="text" id="fw_ip" placeholder="1.2.3.4"></div>
    <div class="field"><label>Note (optional)</label><input type="text" id="fw_note" placeholder="Home network"></div>
  </div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveFw">Add to whitelist</button>
    <button class="btn ghost" id="btnCancelFw">Cancel</button>
  </div>
</div>
<script src="modules/firewall/app.js"></script>
