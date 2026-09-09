/* Emerald Central Hub V11 — Storage module logic */
(function () {
  'use strict';
  var EM = window.EM;
  var state = { path: '', selected: [], clipboard: null };

  var $ = function (id) { return document.getElementById(id); };

  function refresh() {
    $('fileTable').querySelector('tbody').innerHTML = '<tr><td colspan="7" class="empty">Loading…</td></tr>';
    state.selected = [];
    renderClipboard();
    EM.get('index.php?api=storage&action=list_files&path=' + encodeURIComponent(state.path)).then(function (res) {
      if (!res || !Array.isArray(res.files)) {
        $('fileTable').querySelector('tbody').innerHTML = '<tr><td colspan="7" class="empty">Could not load vault.</td></tr>';
        return;
      }
      state.path = res.path || '';
      $('crumbPath').textContent = '/' + state.path;
      var tb = $('fileTable').querySelector('tbody');
      tb.innerHTML = '';
      if (!res.files.length) {
        tb.innerHTML = '<tr><td colspan="7" class="empty">Empty directory.</td></tr>';
        return;
      }
      res.files.forEach(function (f) {
        var tr = document.createElement('tr');
        var ext = f.is_dir ? 'DIR' : f.ext.toUpperCase();
        var ico = f.is_dir ? '📁' : (f.ext === 'zip' ? '🗜' : '📄');
        var canMod = (f.owner === EM.user || f.owner === 'System');
        tr.innerHTML =
          '<td><input type="checkbox" class="selbox" data-name="' + EM.escapeHtml(f.name) + '"></td>' +
          '<td><div class="file-row"><span class="ico">' + ico + '</span><span class="nm">' + EM.escapeHtml(f.name) + '</span></div></td>' +
          '<td class="mono">' + EM.escapeHtml(ext) + '</td>' +
          '<td class="mono">' + EM.escapeHtml(f.size) + '</td>' +
          '<td class="mono small">' + EM.escapeHtml(f.modified) + '</td>' +
          '<td>' + EM.escapeHtml(f.owner) + '</td>' +
          '<td><div class="actions">' +
            (f.is_dir
              ? '<button class="btn ghost sm" data-act="open">Open</button>' +
                (canMod ? '<button class="btn ghost sm" data-act="zip">Zip</button>' : '') +
                (canMod ? '<button class="btn danger sm" data-act="del">Del</button>' : '')
              : '<button class="btn ghost sm" data-act="read">Edit</button>' +
                (f.ext === 'zip' ? '<button class="btn ghost sm" data-act="unzip">Unzip</button>' : '') +
                (canMod ? '<button class="btn danger sm" data-act="del">Del</button>' : '')) +
          '</div></td>';
        tr.querySelector('.selbox').addEventListener('change', function () {
          var i = state.selected.indexOf(f.name);
          if (this.checked) { if (i < 0) { state.selected.push(f.name); } }
          else { if (i >= 0) { state.selected.splice(i, 1); } }
          renderClipboard();
        });
        tr.querySelector('.nm').addEventListener('click', function () { f.is_dir ? openDir(f.name) : openFile(f.name); });
        tr.querySelectorAll('[data-act]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var act = btn.getAttribute('data-act');
            if (act === 'open') { openDir(f.name); }
            else if (act === 'read') { openFile(f.name); }
            else if (act === 'del') { delOne(f.name); }
            else if (act === 'zip') { doZip(f.name); }
            else if (act === 'unzip') { doUnzip(f.name); }
          });
        });
        tb.appendChild(tr);
      });
    }).catch(function () {
      $('fileTable').querySelector('tbody').innerHTML = '<tr><td colspan="7" class="empty">Could not load vault.</td></tr>';
    });
  }

  function openDir(name) {
    state.path = state.path ? state.path + '/' + name : name;
    refresh();
  }
  function openFile(name) {
    EM.post('index.php?api=storage&action=read_file', { path: state.path, file: name }).then(function (res) {
      if (!res || res.status !== 'success') { EM.toast('Could not read file', 'err'); return; }
      $('editorTitle').textContent = name + ' (' + res.size + ')';
      $('editorArea').value = res.content;
      $('editorArea').dataset.file = name;
      $('editorCard').classList.remove('hide');
    });
  }
  function delOne(name) {
    if (!confirm('Delete "' + name + '" permanently?')) { return; }
    EM.post('index.php?api=storage&action=delete_file', { path: state.path, file: name }).then(function (res) {
      EM.toast(res.status === 'success' ? 'Deleted' : (res.message || 'Delete failed'), res.status === 'success' ? 'ok' : 'err');
      refresh();
    });
  }
  function doZip(name) {
    EM.post('index.php?api=storage&action=zip_file', { path: state.path, file: name }).then(function (res) {
      EM.toast(res.status === 'success' ? 'Archived' : (res.message || 'Zip failed'), res.status === 'success' ? 'ok' : 'err');
      refresh();
    });
  }
  function doUnzip(name) {
    EM.post('index.php?api=storage&action=unzip_file', { path: state.path, file: name }).then(function (res) {
      EM.toast(res.status === 'success' ? 'Extracted' : (res.message || 'Unzip failed'), res.status === 'success' ? 'ok' : 'err');
      refresh();
    });
  }
  function renderClipboard() {
    var has = !!state.clipboard;
    $('btnCutSel').classList.toggle('hide', !state.selected.length);
    $('btnCopySel').classList.toggle('hide', !state.selected.length);
    $('btnDeleteSel').classList.toggle('hide', !state.selected.length);
    $('btnPaste').classList.toggle('hide', !has);
  }

  $('btnUpDir').addEventListener('click', function () {
    var parts = state.path.split('/').filter(Boolean);
    parts.pop();
    state.path = parts.join('/');
    refresh();
  });
  $('btnNewFolder').addEventListener('click', function () {
    var name = prompt('Folder name:');
    if (!name) { return; }
    EM.post('index.php?api=storage&action=create_folder', { path: state.path, name: name }).then(function (res) {
      EM.toast(res.status === 'success' ? 'Created' : (res.message || 'Failed'), res.status === 'success' ? 'ok' : 'err');
      refresh();
    });
  });
  $('btnNewFile').addEventListener('click', function () {
    var name = prompt('File name:');
    if (!name) { return; }
    EM.post('index.php?api=storage&action=create_file', { path: state.path, name: name }).then(function (res) {
      EM.toast(res.status === 'success' ? 'Created' : (res.message || 'Failed'), res.status === 'success' ? 'ok' : 'err');
      refresh();
    });
  });
  $('btnUpload').addEventListener('click', function () { $('fileInput').click(); });
  $('fileInput').addEventListener('change', function () {
    var files = Array.prototype.slice.call(this.files);
    if (!files.length) { return; }
    var p = Promise.resolve();
    files.forEach(function (f) {
      p = p.then(function () {
        var fd = new FormData();
        fd.append('csrf', EM.csrf);
        fd.append('path', state.path);
        fd.append('file', f);
        return fetch('index.php?api=storage&action=upload', { method: 'POST', body: fd })
          .then(function (r) { return r.json(); });
      });
    });
    p.then(function () {
      EM.toast('Upload finished', 'ok');
      $('fileInput').value = '';
      refresh();
    });
  });
  $('selAll').addEventListener('change', function () {
    var boxes = document.querySelectorAll('#fileTable .selbox');
    state.selected = [];
    for (var i = 0; i < boxes.length; i++) {
      boxes[i].checked = this.checked;
      if (this.checked) { state.selected.push(boxes[i].getAttribute('data-name')); }
    }
    renderClipboard();
  });
  $('btnDeleteSel').addEventListener('click', function () {
    if (!state.selected.length || !confirm('Delete ' + state.selected.length + ' item(s)?')) { return; }
    EM.post('index.php?api=storage&action=multi_delete', { path: state.path, files: JSON.stringify(state.selected) }).then(function (res) {
      EM.toast(res.status === 'success' ? 'Deleted' : (res.message || 'Some items could not be deleted'), res.status === 'success' ? 'ok' : 'err');
      state.selected = [];
      refresh();
    });
  });
  $('btnCutSel').addEventListener('click', function () { state.clipboard = { mode: 'cut', source: state.path, files: state.selected.slice() }; renderClipboard(); EM.toast('Cut ' + state.clipboard.files.length + ' item(s) — choose destination and paste'); });
  $('btnCopySel').addEventListener('click', function () { state.clipboard = { mode: 'copy', source: state.path, files: state.selected.slice() }; renderClipboard(); EM.toast('Copied ' + state.clipboard.files.length + ' item(s) — choose destination and paste'); });
  $('btnPaste').addEventListener('click', function () {
    if (!state.clipboard) { return; }
    var source = state.path;
    EM.post('index.php?api=storage&action=paste_files', {
      files: JSON.stringify(state.clipboard.files),
      source_path: state.clipboard.source || '',
      target_path: state.path,
      mode: state.clipboard.mode
    }).then(function (res) {
      EM.toast(res.status === 'success' ? 'Pasted' : (res.message || 'Paste failed'), res.status === 'success' ? 'ok' : 'err');
      state.clipboard = null;
      state.selected = [];
      refresh();
    });
  });
  $('btnSaveFile').addEventListener('click', function () {
    var name = $('editorArea').dataset.file;
    if (!name) { return; }
    EM.post('index.php?api=storage&action=save_file', { path: state.path, file: name, content: $('editorArea').value }).then(function (res) {
      EM.toast(res.status === 'success' ? 'Saved' : (res.message || 'Save failed'), res.status === 'success' ? 'ok' : 'err');
    });
  });
  $('btnCloseEditor').addEventListener('click', function () {
    $('editorCard').classList.add('hide');
    $('editorArea').value = '';
  });

  refresh();
})();
