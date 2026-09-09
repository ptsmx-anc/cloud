<?php
/**
 * Emerald Central Hub V11 — Monitor fragment.
 * Rendered inside the dashboard shell; logic in modules/monitor/app.js.
 */
if (!defined('EMERALD_SHELL')) {
    header('Location: index.php?p=monitor');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>System processes</h2>
    <button class="btn ghost sm" id="btnRefreshProc">⟳ Refresh</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="procTable">
      <thead><tr>
        <th>User</th><th>PID</th><th>CPU%</th><th>MEM%</th><th>Command</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="6" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>System snapshot</h2>
    <button class="btn btn-primary sm" id="btnSnapshot">Create snapshot (.emerald_data → zip)</button>
  </div>
  <p class="muted small">Builds a ZIP of the encrypted vault (.emerald_data) and stores it in the file vault as <code>System_Snapshot_YYYY-MM-DD_HH-MM-SS.zip</code>.</p>
</div>
<script src="modules/monitor/app.js"></script>
