/* Emerald Central Hub V11 — Monitor logic */
(function () {
  'use strict';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };
  var isPriv = (EM.role === 'owner' || EM.role === 'admin');

  function refresh() {
    var tb = $('procTable').querySelector('tbody');
    tb.innerHTML = '<tr><td colspan="6" class="empty">Loading…</td></tr>';
    EM.get('index.php?api=monitor&action=list_processes').then(function (res) {
      var list = (res && res.status === 'success' && res.data) ? res.data : [];
      tb.innerHTML = '';
      if (!list.length) { tb.innerHTML = '<tr><td colspan="6" class="empty">Process listing unavailable (shell_exec disabled) or no processes found.</td></tr>'; return; }
      list.forEach(function (p) {
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td>' + EM.escapeHtml(p.user) + '</td>' +
          '<td class="mono">' + EM.escapeHtml(p.pid) + '</td>' +
          '<td class="mono">' + EM.escapeHtml(p.cpu) + '</td>' +
          '<td class="mono">' + EM.escapeHtml(p.mem) + '</td>' +
          '<td class="mono small" style="max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + EM.escapeHtml(p.cmd) + '</td>' +
          '<td><div class="actions">' + (isPriv ? '<button class="btn danger sm" data-act="kill">Kill</button>' : '<span class="muted small">read-only</span>') + '</div></td>';
        var kill = tr.querySelector('[data-act="kill"]');
        if (kill) {
          kill.addEventListener('click', function () {
            if (!confirm('SIGKILL PID ' + p.pid + '?')) { return; }
            EM.post('index.php?api=monitor&action=kill_process', { pid: p.pid }).then(function (r) {
              EM.toast(r.status === 'success' ? 'Signal sent' : (r.message || 'Failed'), r.status === 'success' ? 'ok' : 'err');
              refresh();
            });
          });
        }
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = '<tr><td colspan="6" class="empty">Could not load processes.</td></tr>';
    });
  }

  $('btnRefreshProc').addEventListener('click', refresh);
  $('btnSnapshot').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    EM.post('index.php?api=monitor&action=create_snapshot', {}).then(function (r) {
      btn.disabled = false;
      EM.toast(r.status === 'success' ? 'Snapshot created: ' + (r.file || '') : (r.message || 'Failed'), r.status === 'success' ? 'ok' : 'err');
    }).catch(function () { btn.disabled = false; EM.toast('Snapshot failed', 'err'); });
  });

  refresh();
})();
