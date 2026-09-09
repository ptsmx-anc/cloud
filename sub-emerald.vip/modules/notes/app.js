/* Emerald Central Hub V11 — Notes (system containers) logic */
(function () {
  'use strict';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };
  var editId = null;

  function parseData(note) {
    try { return JSON.parse(note.data || '{}'); } catch (e) { return {}; }
  }

  function refresh() {
    var tb = $('noteTable').querySelector('tbody');
    tb.innerHTML = '<tr><td colspan="6" class="empty">Loading…</td></tr>';
    EM.get('index.php?api=notes&action=list_notes').then(function (res) {
      var list = (res && res.status === 'success' && res.notes) ? res.notes : [];
      tb.innerHTML = '';
      if (!list.length) { tb.innerHTML = '<tr><td colspan="6" class="empty">No containers yet.</td></tr>'; return; }
      list.forEach(function (n) {
        var d = parseData(n);
        var auth = d.auth || {};
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td><b>' + EM.escapeHtml(n.title || 'Untitled') + '</b></td>' +
          '<td class="mono small">' + EM.escapeHtml(auth.host || '—') + '</td>' +
          '<td>' + EM.escapeHtml(auth.user || '—') + '</td>' +
          '<td><span class="badge ' + ((d.status || 'active') === 'active' ? 'ok' : 'warn') + '">' + EM.escapeHtml(d.status || 'active') + '</span></td>' +
          '<td>' + EM.escapeHtml(n.owner || 'System') + '</td>' +
          '<td><div class="actions">' +
            '<button class="btn ghost sm" data-act="edit">Edit</button>' +
            '<button class="btn danger sm" data-act="del">Del</button>' +
          '</div></td>';
        tr.querySelector('[data-act="edit"]').addEventListener('click', function () { openForm(n, d); });
        tr.querySelector('[data-act="del"]').addEventListener('click', function () {
          if (!confirm('Delete container "' + (n.title || n.id) + '"?')) { return; }
          EM.post('index.php?api=notes&action=delete_note', { id: n.id }).then(function (r) {
            EM.toast(r.status === 'success' ? 'Deleted' : (r.message || 'Delete failed'), r.status === 'success' ? 'ok' : 'err');
            refresh();
          });
        });
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = '<tr><td colspan="6" class="empty">Could not load containers.</td></tr>';
    });
  }

  function openForm(n, d) {
    editId = n ? n.id : null;
    var auth = (d && d.auth) || {};
    $('noteFormTitle').textContent = n ? 'Edit container' : 'New container';
    $('n_title').value = n ? (n.title || '') : '';
    $('n_status').value = (d && d.status) || 'active';
    $('n_host').value = auth.host || '';
    $('n_dir').value = auth.dir || '';
    $('n_user').value = auth.user || '';
    $('n_pass').value = auth.pass || '';
    $('n_list').value = (d && d.list) || '';
    $('noteFormCard').classList.remove('hide');
  }

  $('btnAddNote').addEventListener('click', function () { openForm(null, {}); });
  $('btnCancelNote').addEventListener('click', function () { $('noteFormCard').classList.add('hide'); editId = null; });
  $('btnSaveNote').addEventListener('click', function () {
    var title = $('n_title').value.trim();
    if (!title) { EM.toast('Title is required', 'err'); return; }
    EM.post('index.php?api=notes&action=save_note', {
      id: editId || '',
      title: title,
      status: $('n_status').value,
      host: $('n_host').value.trim(),
      dir: $('n_dir').value.trim(),
      user: $('n_user').value.trim(),
      pass: $('n_pass').value,
      text_list: $('n_list').value
    }).then(function (r) {
      if (r.status !== 'success') { EM.toast(r.message || 'Save failed', 'err'); return; }
      EM.toast('Container saved', 'ok');
      $('noteFormCard').classList.add('hide');
      editId = null;
      refresh();
    });
  });

  refresh();
})();
