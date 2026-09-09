/* Emerald Central Hub V11 — Firewall logic */
(function () {
  'use strict';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };

  function refresh() {
    var tb = $('fwTable').querySelector('tbody');
    tb.innerHTML = '<tr><td colspan="5" class="empty">Loading…</td></tr>';
    EM.get('index.php?api=firewall&action=list_firewall').then(function (res) {
      var list = (res && res.status === 'success' && res.entries) ? res.entries : [];
      tb.innerHTML = '';
      if (!list.length) { tb.innerHTML = '<tr><td colspan="5" class="empty">Whitelist is empty — your own IP must be listed to access the app.</td></tr>'; return; }
      list.forEach(function (e) {
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="mono"><b>' + EM.escapeHtml(e.ip) + '</b></td>' +
          '<td>' + EM.escapeHtml(e.note || '—') + '</td>' +
          '<td class="mono small">' + EM.escapeHtml(EM.fmtTime(e.added)) + '</td>' +
          '<td>' + EM.escapeHtml(e.owner || 'System') + '</td>' +
          '<td><div class="actions"><button class="btn danger sm" data-act="del">Remove</button></div></td>';
        tr.querySelector('[data-act="del"]').addEventListener('click', function () {
          if (!confirm('Remove IP ' + e.ip + ' from the whitelist?')) { return; }
          EM.post('index.php?api=firewall&action=delete_firewall', { id: e.id }).then(function (r) {
            EM.toast(r.status === 'success' ? 'Removed' : (r.message || 'Remove failed'), r.status === 'success' ? 'ok' : 'err');
            refresh();
          });
        });
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = '<tr><td colspan="5" class="empty">Could not load whitelist.</td></tr>';
    });
  }

  $('btnAddFw').addEventListener('click', function () { $('fwFormCard').classList.remove('hide'); });
  $('btnCancelFw').addEventListener('click', function () { $('fwFormCard').classList.add('hide'); });
  $('btnSaveFw').addEventListener('click', function () {
    var ip = $('fw_ip').value.trim();
    if (!ip) { EM.toast('IP address required', 'err'); return; }
    EM.post('index.php?api=firewall&action=add_firewall', { ip: ip, note: $('fw_note').value.trim() }).then(function (r) {
      if (r.status !== 'success') { EM.toast(r.message || 'Add failed', 'err'); return; }
      EM.toast('IP whitelisted', 'ok');
      $('fwFormCard').classList.add('hide');
      $('fw_ip').value = ''; $('fw_note').value = '';
      refresh();
    });
  });

  refresh();
})();
