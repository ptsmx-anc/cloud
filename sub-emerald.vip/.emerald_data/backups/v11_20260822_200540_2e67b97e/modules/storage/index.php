<?php
/**
 * Emerald Central Hub V11 — Storage fragment (file vault).
 * Rendered inside the dashboard shell; logic in modules/storage/app.js.
 */
if (!defined('EMERALD_SHELL')) {
    header('Location: index.php?p=storage');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>File vault</h2>
    <div class="row">
      <button class="btn ghost sm" id="btnUpDir">↑ Up</button>
      <button class="btn sm" id="btnNewFolder">+ Folder</button>
      <button class="btn sm" id="btnNewFile">+ File</button>
      <button class="btn sm" id="btnUpload">Upload</button>
      <button class="btn danger sm hide" id="btnDeleteSel">Delete selected</button>
      <button class="btn ghost sm hide" id="btnCutSel">Cut</button>
      <button class="btn ghost sm hide" id="btnCopySel">Copy</button>
      <button class="btn ok sm hide" id="btnPaste">Paste here</button>
      <input type="file" id="fileInput" multiple class="hide">
    </div>
  </div>
  <p class="muted small mb" id="crumbPath">/</p>
  <div class="table-wrap">
    <table class="tbl" id="fileTable">
      <thead><tr>
        <th style="width:32px"><input type="checkbox" id="selAll"></th>
        <th>Name</th><th>Ext</th><th>Size</th><th>Modified</th><th>Owner</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="7" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="editorCard">
  <div class="card-header">
    <h2 id="editorTitle">Editor</h2>
    <div class="row">
      <button class="btn ok sm" id="btnSaveFile">Save</button>
      <button class="btn ghost sm" id="btnCloseEditor">Close</button>
    </div>
  </div>
  <textarea id="editorArea" spellcheck="false"></textarea>
</div>
<script src="modules/storage/app.js"></script>
