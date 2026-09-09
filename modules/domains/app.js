/* Emerald Central Hub V11 — Domains vault logic */
(function () {
  'use strict';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };
  var editId = null;

  function refresh() {
    var tb = $('domainTable').querySelector('tbody');
    tb.innerHTML = '<tr><td colspan="6" class="empty">Loading…</td></tr>';
    EM.get('index.php?api=domains&action=list_domains').then(function (res) {
      var list = (res && res.status === 'success' && res.domains) ? res.domains : [];
      tb.innerHTML = '';
      if (!list.length) { tb.innerHTML = '<tr><td colspan="6" class="empty">No domains yet — add your first one.</td></tr>'; return; }
      list.forEach(function (d) {
        var tr = document.createElement('tr');
        var cls = d.status === 'active' ? 'ok' : (d.status === 'expired' || d.status === 'suspended' ? 'err' : 'warn');
        tr.innerHTML =
          '<td class="mono"><b>' + EM.escapeHtml(d.domain) + '</b></td>' +
          '<td><span class="badge ' + cls + '">' + EM.escapeHtml(d.status || 'active') + '</span></td>' +
          '<td>' + EM.escapeHtml(d.username || '—') + '</td>' +
          '<td class="mono small">' + EM.escapeHtml(d.expiry || '—') + '</td>' +
          '<td>' + EM.escapeHtml(d.owner || 'System') + '</td>' +
          '<td><div class="actions">' +
            '<button class="btn ghost sm" data-act="edit">Edit</button>' +
            '<button class="btn danger sm" data-act="del">Del</button>' +
          '</div></td>';
        tr.querySelector('[data-act="edit"]').addEventListener('click', function () { openForm(d); });
        tr.querySelector('[data-act="del"]').addEventListener('click', function () {
          if (!confirm('Delete domain "' + d.domain + '" from the vault?')) { return; }
          EM.post('index.php?api=domains&action=delete_domain', { id: d.id }).then(function (r) {
            EM.toast(r.status === 'success' ? 'Deleted' : (r.message || 'Delete failed'), r.status === 'success' ? 'ok' : 'err');
            refresh();
          });
        });
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = '<tr><td colspan="6" class="empty">Could not load vault.</td></tr>';
    });
  }

  function openForm(d) {
    editId = d ? d.id : null;
    $('domainFormTitle').textContent = d ? 'Edit domain' : 'Add domain';
    $('f_domain').value = d ? (d.domain || '') : '';
    $('f_status').value = d ? (d.status || 'active') : 'active';
    $('f_username').value = d ? (d.username || '') : '';
    $('f_expiry').value = d ? (d.expiry || '') : '';
    $('f_uapi').value = d ? (d.uapi_token || '') : '';
    $('f_cpanel').value = d ? (d.cpanel_token || '') : '';
    $('f_notes').value = d ? (d.notes || '') : '';
    $('domainFormCard').classList.remove('hide');
  }
  function closeForm() {
    $('domainFormCard').classList.add('hide');
    editId = null;
  }

  $('btnAddDomain').addEventListener('click', function () { openForm(null); });
  $('btnCancelDomain').addEventListener('click', closeForm);
  $('btnSaveDomain').addEventListener('click', function () {
    var domain = $('f_domain').value.trim();
    if (!domain) { EM.toast('Domain is required', 'err'); return; }
    EM.post('index.php?api=domains&action=save_domain', {
      id: editId || '',
      domain: domain,
      status: $('f_status').value,
      username: $('f_username').value.trim(),
      expiry: $('f_expiry').value.trim(),
      uapi_token: $('f_uapi').value,
      cpanel_token: $('f_cpanel').value,
      notes: $('f_notes').value
    }).then(function (r) {
      if (r.status !== 'success') { EM.toast(r.message || 'Save failed', 'err'); return; }
      EM.toast('Domain saved', 'ok');
      closeForm();
      refresh();
    });
  });

  refresh();
})();
