<?php
/**
 * Emerald Central Hub V11 — Notes fragment (system containers).
 * Rendered inside the dashboard shell; logic in modules/notes/app.js.
 */
if (!defined('EMERALD_SHELL')) {
    header('Location: index.php?p=notes');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>System containers</h2>
    <button class="btn btn-primary sm" id="btnAddNote">+ New container</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="noteTable">
      <thead><tr>
        <th>Title</th><th>Host</th><th>User</th><th>Status</th><th>Owner</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="6" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="noteFormCard">
  <h2 id="noteFormTitle">New container</h2>
  <div class="grid grid-2">
    <div class="field"><label>Title</label><input type="text" id="n_title" placeholder="Production server"></div>
    <div class="field"><label>Status</label><select id="n_status"><option value="active">active</option><option value="inactive">inactive</option></select></div>
    <div class="field"><label>Host</label><input type="text" id="n_host" placeholder="host:port"></div>
    <div class="field"><label>Directory</label><input type="text" id="n_dir" placeholder="/home/user"></div>
    <div class="field"><label>Username</label><input type="text" id="n_user" autocomplete="off"></div>
    <div class="field"><label>Password</label><input type="password" id="n_pass" autocomplete="off"></div>
  </div>
  <div class="field"><label>Command list (one per line, "--> " prefix optional)</label><textarea id="n_list" placeholder="--> cd /app&#10;--> npm run build"></textarea></div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveNote">Save container</button>
    <button class="btn ghost" id="btnCancelNote">Cancel</button>
  </div>
</div>
<script src="modules/notes/app.js"></script>
