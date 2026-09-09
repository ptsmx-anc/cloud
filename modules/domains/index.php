<?php
/**
 * Emerald Central Hub V11 — Domains vault fragment.
 * Rendered inside the dashboard shell; logic in modules/domains/app.js.
 */
if (!defined('EMERALD_SHELL')) {
    header('Location: index.php?p=domains');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>Domains vault</h2>
    <button class="btn btn-primary sm" id="btnAddDomain">+ Add domain</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="domainTable">
      <thead><tr>
        <th>Domain</th><th>Status</th><th>Username</th><th>Expiry</th><th>Owner</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="6" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="domainFormCard">
  <h2 id="domainFormTitle">Add domain</h2>
  <div class="grid grid-2">
    <div class="field"><label>Domain</label><input type="text" id="f_domain" placeholder="example.com"></div>
    <div class="field"><label>Status</label><select id="f_status">
      <option value="active">active</option><option value="pending">pending</option><option value="expired">expired</option><option value="suspended">suspended</option>
    </select></div>
    <div class="field"><label>cPanel username</label><input type="text" id="f_username" autocomplete="off"></div>
    <div class="field"><label>Expiry date</label><input type="text" id="f_expiry" placeholder="2027-01-31"></div>
    <div class="field"><label>UAPI token</label><input type="password" id="f_uapi" autocomplete="off"></div>
    <div class="field"><label>cPanel token</label><input type="password" id="f_cpanel" autocomplete="off"></div>
  </div>
  <div class="field"><label>Notes</label><textarea id="f_notes" style="min-height:80px"></textarea></div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveDomain">Save domain</button>
    <button class="btn ghost" id="btnCancelDomain">Cancel</button>
  </div>
</div>
<script src="modules/domains/app.js"></script>
