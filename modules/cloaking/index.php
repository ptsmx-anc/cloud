<?php
/**
 * Emerald Central Hub V11 — Cloaking fragment.
 * Rendered inside the dashboard shell; logic in modules/cloaking/app.js.
 */
if (!defined('EMERALD_SHELL')) {
    header('Location: index.php?p=cloaking');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>Cloaking</h2>
    <button class="btn btn-primary sm" id="btnAddCloak">+ New cloak</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="cloakTable">
      <thead><tr>
        <th>Domain</th><th>Path</th><th>Type</th><th>Owner</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="5" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="cloakFormCard">
  <h2 id="cloakFormTitle">New cloak</h2>
  <div class="grid grid-2">
    <div class="field"><label>Domain</label><input type="text" id="c_domain" placeholder="example.com"></div>
    <div class="field"><label>Path</label><input type="text" id="c_path" placeholder="/landing"></div>
    <div class="field"><label>Type</label><select id="c_type">
      <option value="personal">personal</option><option value="global">global</option>
    </select></div>
  </div>
  <div class="field"><label>Content</label><textarea id="c_content" placeholder="HTML content served at the cloaked path…"></textarea></div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveCloak">Save cloak</button>
    <button class="btn ghost" id="btnCancelCloak">Cancel</button>
  </div>
</div>
<script src="modules/cloaking/app.js"></script>
