/* Emerald Central Hub V11 — Dashboard module logic */
(function () {
  'use strict';
  var EM = window.EM;

  function rows(tbodyId, arr, builder) {
    var tb = document.getElementById(tbodyId);
    if (!tb) { return; }
    if (!arr || !arr.length) { tb.innerHTML = '<tr><td colspan="9" class="empty">No entries yet.</td></tr>'; return; }
    tb.innerHTML = '';
    arr.slice(-8).reverse().forEach(function (item, i) {
      var tr = document.createElement('tr');
      tr.innerHTML = builder(item, i);
      tb.appendChild(tr);
    });
  }

  EM.get('index.php?api=dashboard&action=sys_info').then(function (res) {
    if (!res || res.status !== 'success') { return; }
    var e = res.extended || {};
    var st = document.getElementById('stDisk');
    if (st) { st.textContent = (e.disk_free || '—') + ' free / ' + (e.disk_total || '—'); }
    var s = res.stats || {};
    if (s.domain && document.getElementById('stDomain')) { document.getElementById('stDomain').textContent = s.domain; }
    if (s.server_ip && document.getElementById('stIp')) { document.getElementById('stIp').textContent = s.server_ip; }
    if (s.php_version && document.getElementById('stPhp')) { document.getElementById('stPhp').textContent = s.php_version; }
    function setCount(id, v) { var el = document.getElementById(id); if (el) { el.textContent = v; } }
    setCount('stUsers', res.users_count);
    setCount('stCloaks', res.cloaks_count);
    setCount('stFw', res.firewall_count);
    setCount('stNotes', res.notes_count);

    rows('actTable', res.activity, function (a) {
      return '<td class="mono">' + EM.escapeHtml(EM.fmtTime(a.time)) + '</td>'
           + '<td>' + EM.escapeHtml(a.user) + '</td>'
           + '<td>' + EM.escapeHtml(a.detail) + '</td>';
    });
    rows('logTable', res.logs, function (l) {
      var cls = l.status === 'Success' ? 'badge ok' : (l.status === 'Failed' ? 'badge err' : 'badge warn');
      return '<td class="mono">' + EM.escapeHtml(EM.fmtTime(l.time)) + '</td>'
           + '<td>' + EM.escapeHtml(l.user) + '</td>'
           + '<td class="mono">' + EM.escapeHtml(l.ip) + '</td>'
           + '<td><span class="' + cls + '">' + EM.escapeHtml(l.status) + '</span></td>';
    });
  }).catch(function () {
    var tb = document.getElementById('actTable');
    if (tb) { tb.innerHTML = '<tbody><tr><td colspan="9" class="empty">Could not load dashboard data.</td></tr></tbody>'; }
  });
})();
