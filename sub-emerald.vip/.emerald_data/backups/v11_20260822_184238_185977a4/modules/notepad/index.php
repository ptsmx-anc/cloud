<?php
/**
 * Emerald Central Hub V11 — Notepad fragment (in-shell).
 * SSO: when the selected identity matches the logged-in session user,
 * notes unlock without a password. Otherwise the identity password is
 * required (V10 public behaviour).
 * Rendered inside the dashboard shell; logic in modules/notepad/app.js.
 */
if (!defined('EMERALD_SHELL')) {
    header('Location: index.php?p=notepad');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>Notepad <span class="badge info">SSO: <?php echo sec_html($__user); ?></span></h2>
    <a class="btn ghost sm" href="notepad/" target="_blank" rel="noopener">Open public page ↗</a>
  </div>
  <div class="grid" id="npUserGrid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
    <div class="empty">Loading identities…</div>
  </div>
</div>

<div class="card hide" id="npNotesCard">
  <div class="card-header">
    <h2 id="npOwnerTitle">Notes</h2>
    <div class="row">
      <button class="btn ghost sm" id="npBack">← identities</button>
      <button class="btn ghost sm" id="npNewNote">+ Note</button>
    </div>
  </div>
  <div class="row mb">
    <input type="text" id="npNewName" placeholder="new-note.txt" style="max-width:260px">
    <button class="btn ok sm" id="npCreateBtn">Create</button>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="npNoteTable">
      <thead><tr><th>File</th><th class="right">Actions</th></tr></thead>
      <tbody><tr><td colspan="2" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="npEditorCard">
  <div class="card-header">
    <h2 id="npEditTitle">Editor</h2>
    <div class="row">
      <button class="btn ok sm" id="npSaveBtn">Save</button>
      <button class="btn danger sm" id="npDelBtn">Delete</button>
      <button class="btn ghost sm" id="npBackNotes">← back</button>
    </div>
  </div>
  <textarea id="npContent" spellcheck="false"></textarea>
</div>
<script src="modules/notepad/app.js"></script>
