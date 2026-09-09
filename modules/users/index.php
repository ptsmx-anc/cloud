<?php
/**
 * Emerald Central Hub V11 — Users fragment.
 * Rendered inside the dashboard shell; logic in modules/users/app.js.
 */
if (!defined('EMERALD_SHELL')) {
    header('Location: index.php?p=users');
    exit;
}
?>
<div class="card">
  <div class="card-header">
    <h2>Identities</h2>
    <?php if ($__role === 'owner'): ?><button class="btn btn-primary sm" id="btnAddUser">+ Add user</button><?php endif; ?>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="userTable">
      <thead><tr>
        <th>User</th><th>Role</th><th>Last active</th><th class="right">Actions</th>
      </tr></thead>
      <tbody><tr><td colspan="4" class="empty">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="card hide" id="userFormCard">
  <h2>Add user</h2>
  <div class="grid grid-2">
    <div class="field"><label>Username</label><input type="text" id="u_username" autocomplete="off"></div>
    <div class="field"><label>Role</label><select id="u_role">
      <option value="user">user</option><option value="admin">admin</option><option value="guest">guest</option><option value="owner">owner</option>
    </select></div>
  </div>
  <div class="field"><label>Password</label><input type="password" id="u_password" autocomplete="new-password"></div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveUser">Create user</button>
    <button class="btn ghost" id="btnCancelUser">Cancel</button>
  </div>
</div>

<div class="card hide" id="profileCard">
  <h2>My profile</h2>
  <div class="grid grid-2">
    <div class="field"><label>Username</label><input type="text" id="p_username"></div>
    <div class="field"><label>Avatar URL (optional)</label><input type="text" id="p_avatar" placeholder="https://…/avatar.png"></div>
  </div>
  <div class="field"><label>New password (leave blank to keep)</label><input type="password" id="p_password" autocomplete="new-password"></div>
  <div class="row">
    <button class="btn btn-primary" id="btnSaveProfile">Save profile</button>
  </div>
</div>
<script src="modules/users/app.js"></script>
