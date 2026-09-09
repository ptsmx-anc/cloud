/* Emerald Central Hub V11 — Notepad logic (SSO-aware, V10-compatible API) */
(function () {
  'use strict';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };
  var API = 'notepad/';                 // relative path — works for both in-shell page and standalone
  var cur = { user: null, file: null };
  var SSO = EM.user;                     // logged-in shell identity (null on standalone page)

  function userCard(u) {
    var d = document.createElement('div');
    d.className = 'np-user';
    d.innerHTML =
      '<img class="np-avatar" src="' + EM.escapeHtml(u.avatar) + '" alt="' + EM.escapeHtml(u.username) + '">' +
      '<div class="np-name">' + EM.escapeHtml(u.username) + '</div>' +
      '<div class="muted small">' + (u.last_active ? new Date(u.last_active * 1000).toLocaleString() : 'never') + '</div>';
    d.addEventListener('click', function () { openUser(u.username); });
    return d;
  }

  function openUser(username) {
    if (SSO && SSO === username) {
      enterNotes(username, null);
      return;
    }
    var pass = prompt('Password for ' + username + ' (SSO only bypasses this for the signed-in identity):');
    if (pass === null) { return; }
    fetch(API + '?api=verify_access', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'user=' + encodeURIComponent(username) + '&password=' + encodeURIComponent(pass)
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (res.status === 'success') { enterNotes(username, null); }
      else { EM.toast(res.message || 'Access denied', 'err'); }
    }).catch(function () { EM.toast('Access check failed', 'err'); });
  }

  function enterNotes(username, file) {
    cur.user = username;
    cur.file = file;
    $('npUserGrid').parentElement.classList.add('hide');
    $('npNotesCard').classList.remove('hide');
    $('npEditorCard').classList.add('hide');
    $('npOwnerTitle').textContent = 'Notes — ' + username;
    loadNotes();
  }

  function api(action, data) {
    var body = new URLSearchParams();
    body.set('api', action);
    if (cur.user) { body.set('user', cur.user); }
    if (cur.file) { body.set('file', cur.file); }
    if (data) {
      Object.keys(data).forEach(function (k) { body.set(k, data[k]); });
    }
    return fetch(API + '?api=' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    }).then(function (r) { return r.json(); });
  }

  function loadNotes() {
    var tb = $('npNoteTable').querySelector('tbody');
    tb.innerHTML = '<tr><td colspan="2" class="empty">Loading…</td></tr>';
    api('list_notes').then(function (res) {
      tb.innerHTML = '';
      var notes = (res.status === 'success' && res.notes) ? res.notes : [];
      if (!notes.length) { tb.innerHTML = '<tr><td colspan="2" class="empty">No notes yet.</td></tr>'; return; }
      notes.forEach(function (f) {
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="mono"><a href="#" data-open="' + EM.escapeHtml(f) + '">' + EM.escapeHtml(f) + '</a></td>' +
          '<td><div class="actions"><button class="btn ghost sm" data-open="' + EM.escapeHtml(f) + '">Open</button><button class="btn danger sm" data-del="' + EM.escapeHtml(f) + '">Delete</button></div></td>';
        var open = tr.querySelector('[data-open="' + CSS.escape(f) + '"]');
        if (open) { open.addEventListener('click', function (e) { e.preventDefault(); openNote(f); }); }
        var del = tr.querySelector('[data-del="' + CSS.escape(f) + '"]');
        if (del) { del.addEventListener('click', function () { deleteNote(f); }); }
        tb.appendChild(tr);
      });
    }).catch(function () { tb.innerHTML = '<tr><td colspan="2" class="empty">Could not load notes.</td></tr>'; });
  }

  function openNote(file) {
    cur.file = file;
    $('npNotesCard').classList.add('hide');
    $('npEditorCard').classList.remove('hide');
    $('npEditTitle').textContent = 'Editor — ' + cur.user + '/' + file;
    $('npContent').value = 'Loading…';
    api('load_note', {}).then(function (res) {
      $('npContent').value = res.status === 'success' ? res.content : '';
    }).catch(function () { $('npContent').value = ''; });
  }

  function deleteNote(file) {
    if (!confirm('Delete ' + file + '?')) { return; }
    cur.file = file;
    api('delete_note').then(function (res) {
      EM.toast(res.status === 'success' ? 'Deleted' : (res.message || 'Failed'), res.status === 'success' ? 'ok' : 'err');
      cur.file = null;
      loadNotes();
    });
  }

  $('npBack').addEventListener('click', function () {
    $('npNotesCard').classList.add('hide');
    $('npUserGrid').parentElement.classList.remove('hide');
  });
  $('npBackNotes').addEventListener('click', function () {
    $('npEditorCard').classList.add('hide');
    $('npNotesCard').classList.remove('hide');
    cur.file = null;
    loadNotes();
  });
  $('npSaveBtn').addEventListener('click', function () {
    api('save_note', { content: $('npContent').value }).then(function (res) {
      EM.toast(res.status === 'success' ? 'Saved' : (res.message || 'Failed'), res.status === 'success' ? 'ok' : 'err');
    });
  });
  $('npDelBtn').addEventListener('click', function () { deleteNote(cur.file); });
  $('npNewNote').addEventListener('click', function () {
    $('npNewName').value = '';
    $('npNewName').focus();
  });
  $('npCreateBtn').addEventListener('click', function () {
    var name = $('npNewName').value.trim();
    if (!name) { return; }
    cur.file = name;
    api('create_note').then(function (res) {
      EM.toast(res.status === 'success' ? 'Created' : (res.message || 'Failed'), res.status === 'success' ? 'ok' : 'err');
      cur.file = null;
      loadNotes();
    });
  });

  api('list_users').then(function (res) {
    var grid = $('npUserGrid');
    grid.innerHTML = '';
    var list = (res && res.data && res.data.length) ? res.data : ((res && Array.isArray(res)) ? res : []);
    list.forEach(function (u) { grid.appendChild(userCard(u)); });
    if (!list.length) { grid.innerHTML = '<div class="empty">No identities.</div>'; }
  }).catch(function () { $('npUserGrid').innerHTML = '<div class="empty">Could not load identities.</div>'; });
})();
