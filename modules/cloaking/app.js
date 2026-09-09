/* Emerald Central Hub V11 — Cloaking logic */
(function () {
  'use strict';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };
  var editId = null;

  function refresh() {
    var tb = $('cloakTable').querySelector('tbody');
    tb.innerHTML = '<tr><td colspan="5" class="empty">Loading…</td></tr>';
    EM.get('index.php?api=cloaking&action=list_cloaking').then(function (res) {
      var list = (res && res.status === 'success' && res.cloaks) ? res.cloaks : [];
      tb.innerHTML = '';
      if (!list.length) { tb.innerHTML = '<tr><td colspan="5" class="empty">No cloaks yet.</td></tr>'; return; }
      list.forEach(function (c) {
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="mono"><b>' + EM.escapeHtml(c.domain) + '</b></td>' +
          '<td class="mono small">' + EM.escapeHtml(c.path || '/') + '</td>' +
          '<td><span class="badge ' + (c.type === 'global' ? 'info' : 'warn') + '">' + EM.escapeHtml(c.type || 'personal') + '</span></td>' +
          '<td><span class="flex"><span class="avatar-mini" style="width:22px;height:22px;font-size:10px;flex-basis:22px">' +
            (c.avatar && c.avatar.indexOf('http') === 0 ? '' : EM.escapeHtml((c.owner || 'S').charAt(0).toUpperCase())) +
          '</span>' + EM.escapeHtml(c.owner || 'System') + '</span></td>' +
          '<td><div class="actions">' +
            '<button class="btn ghost sm" data-act="edit">Edit</button>' +
            '<button class="btn danger sm" data-act="del">Del</button>' +
          '</div></td>';
        tr.querySelector('[data-act="edit"]').addEventListener('click', function () { openForm(c); });
        tr.querySelector('[data-act="del"]').addEventListener('click', function () {
          if (!confirm('Delete cloak for "' + c.domain + '"?')) { return; }
          EM.post('index.php?api=cloaking&action=delete_cloaking', { id: c.id }).then(function (r) {
            EM.toast(r.status === 'success' ? 'Deleted' : (r.message || 'Delete failed'), r.status === 'success' ? 'ok' : 'err');
            refresh();
          });
        });
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = '<tr><td colspan="5" class="empty">Could not load cloaks.</td></tr>';
    });
  }

  function openForm(c) {
    editId = c ? c.id : null;
    $('cloakFormTitle').textContent = c ? 'Edit cloak' : 'New cloak';
    $('c_domain').value = c ? (c.domain || '') : '';
    $('c_path').value = c ? (c.path || '') : '';
    $('c_type').value = c ? (c.type || 'personal') : 'personal';
    $('c_content').value = c ? (c.content || '') : '';
    $('cloakFormCard').classList.remove('hide');
  }

  $('btnAddCloak').addEventListener('click', function () { openForm(null); });
  $('btnCancelCloak').addEventListener('click', function () { $('cloakFormCard').classList.add('hide'); editId = null; });
  $('btnSaveCloak').addEventListener('click', function () {
    var domain = $('c_domain').value.trim();
    if (!domain) { EM.toast('Domain is required', 'err'); return; }
    EM.post('index.php?api=cloaking&action=save_cloaking', {
      id: editId || '',
      domain: domain,
      path: $('c_path').value.trim(),
      type: $('c_type').value,
      content: $('c_content').value
    }).then(function (r) {
      if (r.status !== 'success') { EM.toast(r.message || 'Save failed', 'err'); return; }
      EM.toast('Cloak saved', 'ok');
      $('cloakFormCard').classList.add('hide');
      editId = null;
      refresh();
    });
  });

  refresh();
})();
