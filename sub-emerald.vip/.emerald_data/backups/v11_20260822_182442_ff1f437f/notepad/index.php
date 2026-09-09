<?php
/**
 * Emerald Central Hub V11 — public Notepad (V10-compatible entry point).
 *
 * This file keeps the old /notepad/ URL working after the upgrade.
 * It renders a standalone page that talks to modules/notepad/api.php in
 * "public mode" (per-user password auth via verifyUserPassword — V10 flow).
 *
 * In V10 the notepad lived at public_notepad/ on disk; in V11 the same
 * files live under modules/notepad/data/ and are fully preserved.
 */
session_start();
require_once dirname(__DIR__) . '/core/config.php';

/* V10-compatible API routing:  notepad/?api=<action>  →  module api (public mode) */
if (isset($_GET['api']) || isset($_POST['api'])) {
    require_once dirname(__DIR__) . '/modules/notepad/api.php';
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Emerald Notepad</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:#0b1019;color:#dbe4f3;min-height:100vh}
  .wrap{max-width:980px;margin:0 auto;padding:28px 20px 60px}
  .top{display:flex;align-items:center;justify-content:space-between;margin-bottom:26px;flex-wrap:wrap;gap:10px}
  .brand{display:flex;align-items:center;gap:12px}
  .logo{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#0ea5e9,#6366f1);display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:18px}
  .brand h1{font-size:18px;font-weight:700}
  .brand small{display:block;color:#7c8aa5;font-size:12px;font-weight:500}
  .card{background:#111a2a;border:1px solid #22304a;border-radius:16px;padding:24px;margin-bottom:18px}
  h2{font-size:15px;color:#8ab4ff;margin-bottom:16px}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
  .user-card{background:#16213a;border:1px solid #24324f;border-radius:12px;padding:14px;text-align:center;cursor:pointer;transition:.15s}
  .user-card:hover{border-color:#0ea5e9;transform:translateY(-1px)}
  .avatar{width:44px;height:44px;border-radius:50%;background:#0ea5e9;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;margin:0 auto 8px;font-size:16px;overflow:hidden}
  .avatar img{width:100%;height:100%;object-fit:cover}
  .user-card .nm{font-size:13px;font-weight:600;word-break:break-all}
  .row{display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap}
  input[type=text],input[type=password],textarea{width:100%;background:#0b111d;border:1px solid #26324a;border-radius:10px;padding:10px 12px;color:#e6edf7;font-size:14px;outline:none}
  input:focus,textarea:focus{border-color:#0ea5e9}
  textarea{min-height:280px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;line-height:1.6;resize:vertical}
  .btn{background:#0ea5e9;border:0;border-radius:10px;color:#fff;padding:10px 18px;font-size:14px;font-weight:600;cursor:pointer}
  .btn:hover{background:#0284c7}
  .btn.ghost{background:#1c2740;color:#c3cfe4}
  .btn.danger{background:#7f1d1d;color:#fecaca}
  .btn.sm{padding:6px 12px;font-size:12px}
  .btn:disabled{opacity:.5;cursor:not-allowed}
  .note-list{display:flex;flex-direction:column;gap:8px}
  .note-item{display:flex;align-items:center;justify-content:space-between;background:#16213a;border:1px solid #24324f;border-radius:10px;padding:10px 14px;font-size:13px;gap:10px}
  .note-item .nm{word-break:break-all;cursor:pointer;color:#dbe4f3;flex:1}
  .note-item .nm:hover{color:#8ab4ff}
  .muted{color:#7c8aa5;font-size:12px}
  .back{color:#8ab4ff;cursor:pointer;font-size:13px;margin-bottom:14px;display:inline-block}
  .toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1e293b;border:1px solid #334155;color:#e2e8f0;padding:12px 20px;border-radius:12px;font-size:13px;display:none;z-index:99;box-shadow:0 8px 30px rgba(0,0,0,.4)}
  .toast.show{display:block}
  .toast.ok{border-color:#14532d;color:#86efac}
  .toast.err{border-color:#7f1d1d;color:#fca5a5}
  .hide{display:none!important}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div class="brand"><div class="logo">N</div><div><h1>Emerald Notepad</h1><small>Physical .txt notes · V11</small></div></div>
    <a class="btn ghost sm" href="../index.php">← Back to Hub</a>
  </div>

  <div id="view-users" class="card">
    <h2>Choose an identity</h2>
    <div class="grid" id="userGrid"><div class="muted">Loading identities…</div></div>
  </div>

  <div id="view-auth" class="card hide">
    <span class="back" id="backToUsers">← choose another identity</span>
    <h2 id="authTitle">Authenticate</h2>
    <input type="password" id="authPass" placeholder="Passphrase for this identity" autocomplete="current-password">
    <div class="row" style="margin-top:12px"><button class="btn" id="authBtn">Unlock notes</button></div>
  </div>

  <div id="view-notes" class="card hide">
    <span class="back" id="backToAuth">← switch identity</span>
    <h2>Notes — <span id="notesOwner" style="color:#dbe4f3"></span></h2>
    <div class="row">
      <input type="text" id="newNoteName" placeholder="new-note.txt" style="flex:1">
      <button class="btn" id="createNoteBtn">Create</button>
    </div>
    <div class="note-list" id="noteList"><div class="muted">Loading notes…</div></div>
  </div>

  <div id="view-editor" class="card hide">
    <span class="back" id="backToNotes">← back to notes</span>
    <div class="row">
      <input type="text" id="editFileName" style="flex:1">
      <button class="btn" id="saveNoteBtn">Save</button>
      <button class="btn danger" id="deleteNoteBtn">Delete</button>
    </div>
    <textarea id="editContent" placeholder="Write something…"></textarea>
  </div>
</div>
<div class="toast" id="toast"></div>
<script>
(function () {
  'use strict';
  var API = '../modules/notepad/api.php';
  var state = { user: null, pass: null, files: [] };

  function $(id) { return document.getElementById(id); }
  function show(id) { ['view-users','view-auth','view-notes','view-editor'].forEach(function (v) { $(v).classList.toggle('hide', v !== id); }); }
  function toast(msg, type) { var t = $('toast'); t.textContent = msg; t.className = 'toast show ' + (type || ''); clearTimeout(t._h); t._h = setTimeout(function () { t.className = 'toast'; }, 2600); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
  function initials(name) { return String(name).split(/[\s._-]+/).filter(Boolean).map(function (p) { return p[0].toUpperCase(); }).join('').slice(0,2) || '?'; }
  function post(data, extra) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(API + (extra || ''), { method: 'POST', body: fd }).then(function (r) { return r.json(); });
  }

  function loadUsers() {
    fetch(API + '?api=list_users').then(function (r) { return r.json(); }).then(function (list) {
      var grid = $('userGrid');
      if (!Array.isArray(list) || !list.length) { grid.innerHTML = '<div class="muted">No identities found.</div>'; return; }
      grid.innerHTML = '';
      list.forEach(function (u) {
        var d = document.createElement('div'); d.className = 'user-card';
        d.innerHTML = '<div class="avatar">' + (u.avatar ? '<img src="' + esc(u.avatar) + '" alt="">' : esc(initials(u.username))) + '</div><div class="nm">' + esc(u.username) + '</div>';
        d.addEventListener('click', function () { state.user = u.username; state.pass = ''; $('authTitle').textContent = 'Unlock ' + u.username; $('authPass').value = ''; show('view-auth'); $('authPass').focus(); });
        grid.appendChild(d);
      });
    }).catch(function () { $('userGrid').innerHTML = '<div class="muted">Could not load identities.</div>'; });
  }

  function unlock() {
    var pass = $('authPass').value;
    if (!pass) { toast('Passphrase required', 'err'); return; }
    post({ user: state.user, password: pass }, '?api=verify_access').then(function (res) {
      if (res.status !== 'success') { toast('Incorrect passphrase', 'err'); return; }
      state.pass = pass;
      loadNotes();
    });
  }

  function loadNotes() {
    post({ user: state.user }, '?api=list_notes').then(function (res) {
      if (res.status !== 'success') { toast(res.message || 'Failed to load notes', 'err'); return; }
      state.files = res.notes || [];
      $('notesOwner').textContent = state.user;
      var list = $('noteList');
      if (!state.files.length) { list.innerHTML = '<div class="muted">No notes yet. Create one above.</div>'; }
      else {
        list.innerHTML = '';
        state.files.forEach(function (f) {
          var item = document.createElement('div'); item.className = 'note-item';
          item.innerHTML = '<span class="nm"></span><button class="btn ghost sm">open</button>';
          item.querySelector('.nm').textContent = f;
          item.querySelector('.nm').addEventListener('click', function () { openNote(f); });
          item.querySelector('button').addEventListener('click', function () { openNote(f); });
          list.appendChild(item);
        });
      }
      show('view-notes');
    });
  }

  function openNote(file) {
    post({ user: state.user, password: state.pass, file: file }, '?api=load_note').then(function (res) {
      if (res.status !== 'success') { toast(res.message || 'Could not load note', 'err'); return; }
      $('editFileName').value = file;
      $('editContent').value = res.content || '';
      show('view-editor');
    });
  }

  function saveNote() {
    var file = $('editFileName').value.trim();
    if (!file) { toast('Filename required', 'err'); return; }
    if (!/\.txt$/i.test(file)) { file += '.txt'; $('editFileName').value = file; }
    post({ user: state.user, password: state.pass, file: file, content: $('editContent').value }, '?api=save_note').then(function (res) {
      if (res.status !== 'success') { toast(res.message || 'Save failed', 'err'); return; }
      toast('Saved', 'ok');
      loadNotes();
    });
  }

  function deleteNote() {
    var file = $('editFileName').value.trim();
    if (!file || !confirm('Delete "' + file + '" permanently?')) { return; }
    post({ user: state.user, password: state.pass, file: file }, '?api=delete_note').then(function (res) {
      if (res.status !== 'success') { toast(res.message || 'Delete failed', 'err'); return; }
      toast('Deleted', 'ok');
      loadNotes();
    });
  }

  $('authBtn').addEventListener('click', unlock);
  $('authPass').addEventListener('keydown', function (e) { if (e.key === 'Enter') { unlock(); } });
  $('createNoteBtn').addEventListener('click', function () {
    var name = $('newNoteName').value.trim();
    if (!name) { toast('Note name required', 'err'); return; }
    if (!/\.txt$/i.test(name)) { name += '.txt'; }
    post({ user: state.user, password: state.pass, file: name }, '?api=create_note').then(function (res) {
      if (res.status !== 'success') { toast(res.message || 'Create failed', 'err'); return; }
      toast('Created', 'ok');
      $('newNoteName').value = '';
      loadNotes();
    });
  });
  $('saveNoteBtn').addEventListener('click', saveNote);
  $('deleteNoteBtn').addEventListener('click', deleteNote);
  $('backToUsers').addEventListener('click', function () { show('view-users'); });
  $('backToAuth').addEventListener('click', function () { show('view-auth'); });
  $('backToNotes').addEventListener('click', function () { show('view-notes'); });

  loadUsers();
})();
</script>
</body>
</html>
