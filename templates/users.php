<?php declare(strict_types=1); /** @var array $activeUser @var string $base */ ?>
<div class="page" data-page="users">
  <div class="card">
    <div class="card-h" style="display:flex; justify-content:space-between; align-items:center;">
      <div>
        <h3 style="margin:0; font-size:16px; font-weight:700;">User & Role Management</h3>
        <p style="margin:4px 0 0; color:var(--muted); font-size:12.5px;">Manage system access, user roles, and granular module permissions.</p>
      </div>
      <button class="btn-primary" id="btnNewUser" style="display:inline-flex; align-items:center; gap:6px;">
        <svg style="width:15px; height:15px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span>Add User</span>
      </button>
    </div>

    <div class="toolbar" style="padding: 12px 18px; border-bottom: 1px solid var(--border); display:flex; gap:12px;">
      <input type="text" id="userSearch" class="fld" placeholder="Search users by name or email..." style="max-width:320px;">
      <select id="userRoleFilter" class="fld" style="max-width:160px;">
        <option value="">All Roles</option>
        <option value="admin">Admin</option>
        <option value="user">User</option>
      </select>
    </div>

    <div class="bulk-bar" id="userBulkBar" style="margin: 12px 18px 0;">
      <div class="bulk-count"><span class="pill" id="userSelectedCount">0</span> selected</div>
      <div class="bulk-actions">
        <button class="btn-danger btn-sm" id="userBulkDeleteBtn">🗑️ Delete Selected</button>
        <button class="btn-ghost btn-sm" id="userBulkClearBtn">Cancel</button>
      </div>
    </div>

    <div class="tbl-wrap" style="overflow-x:auto;">
      <table class="tbl" id="usersTable">
        <thead>
          <tr>
            <th class="th-cb"><input type="checkbox" class="cb-custom" id="selectAllUsers" title="Select all"></th>
            <th>User</th>
            <th>Role</th>
            <th>Permission Summary</th>
            <th>Status</th>
            <th>Last Login</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody id="usersTbody">
          <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">Loading users...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
