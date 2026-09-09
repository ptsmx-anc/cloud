/* Emerald Central Hub V11 — Users module logic */
(function () {
  'use strict';
  var EM = window.EM;
  var $ = function (id) { return document.getElementById(id); };

  function refresh() {
    var tb = $('userTable').querySelector('tbody');
    tb.innerHTML = '<tr><td colspan="4" class="empty">Loading…</td></tr>';
    EM.get('index.php?api=users&action=list_users').then(function (list) {
      if (!Array.isArray(list)) { tb.innerHTML = '<tr><td colspan="4" class="empty">Could not load users.</td></tr>'; return; }
      tb.innerHTML = '';
      if (!list.length) { tb.innerHTML = '<tr><td colspan="4" class="empty">No users.</td></tr>'; return; }
      list.forEach(function (u) {
        var tr = document.createElement('tr');
        var isSelf = u.username === EM.user;
        tr.innerHTML =
          '<td><span class="flex"><span class="avatar-mini">' + EM.escapeHtml(u.username.charAt(0).toUpperCase()) + '</span><b>' + EM.escapeHtml(u.username) + '</b>' + (isSelf ? ' <span class="badge info">you</span>' : '') + '</span></td>' +
          '<td><span class="badge role-' + EM.escapeHtml(u.role) + '">' + EM.escapeHtml(u.role) + '</span></td>' +
          '<td class="mono small">' + EM.escapeHtml(EM.fmtTime(u.last_active)) + '</td>' +
          '<td><div class="actions">' +
            (isSelf ? '<button class="btn ghost sm" data-act="profile">Profile</button>' : '') +
            (isSelf ? '<button class="btn danger sm" data-act="del">Delete me</button>' : '') +
          '</div></td>';
        var delBtn = tr.querySelector('[data-act="del"]');
        if (delBtn) {
          delBtn.addEventListener('click', function () {
            if (!confirm('Delete your identity "' + u.username + '"? Data migration or purge follows.')) { return; }
            var migrate = prompt('Migrate data to another user? Leave empty to PURGE all your data.\nType target username or press Cancel/OK with empty value for purge.');
            if (migrate === null) { return; }
            EM.post('index.php?api=users&action=delete_user', { target_user: u.username, migrate_to: migrate.trim() }).then(function (r) {
              if (r.status !== 'success') { EM.toast(r.message || 'Delete failed', 'err'); return; }
              EM.toast('Identity deleted', 'ok');
              setTimeout(function () { window.location.href = 'index.php'; }, 900);
            });
          });
        }
        var profBtn = tr.querySelector('[data-act="profile"]');
        if (profBtn) { profBtn.addEventListener('click', function () { openProfile(u); }); }
        tb.appendChild(tr);
      });
    }).catch(function () {
      tb.innerHTML = '<tr><td colspan="4" class="empty">Could not load users.</td></tr>';
    });
  }

  function openProfile(u) {
    $('p_username').value = u.username;
    $('p_avatar').value = u.avatar || '';
    $('p_password').value = '';
    $('profileCard').classList.remove('hide');
    $('profileCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  $('btnAddUser').addEventListener('click', function () { $('userFormCard').classList.remove('hide'); });
  $('btnCancelUser').addEventListener('click', function () { $('userFormCard').classList.add('hide'); });
  $('btnSaveUser').addEventListener('click', function () {
    var username = $('u_username').value.trim();
    var password = $('u_password').value;
    if (!username || !password) { EM.toast('Username and password required', 'err'); return; }
    EM.post('index.php?api=users&action=add_user', { username: username, role: $('u_role').value, password: password }).then(function (r) {
      if (r.status !== 'success') { EM.toast(r.message || 'Create failed', 'err'); return; }
      EM.toast('User created', 'ok');
      $('userFormCard').classList.add('hide');
      $('u_username').value = ''; $('u_password').value = '';
      refresh();
    });
  });
  $('btnSaveProfile').addEventListener('click', function () {
    var username = $('p_username').value.trim();
    if (!username) { EM.toast('Username cannot be empty', 'err'); return; }
    EM.post('index.php?api=users&action=update_profile', {
      username: username,
      avatar: $('p_avatar').value.trim(),
      password: $('p_password').value
    }).then(function (r) {
      if (r.status !== 'success') { EM.toast(r.message || 'Save failed', 'err'); return; }
      EM.toast('Profile updated', 'ok');
      $('profileCard').classList.add('hide');
      if (r.new_user && r.new_user !== EM.user) { window.location.href = 'index.php'; } else { refresh(); }
    });
  });

  refresh();
})();
