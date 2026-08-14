/**
 * User & Role / Permission Management Client
 */
(function () {
  'use strict';
  const SLC = window.SLC || {};

  let allUsers = [];
  let availablePermissions = {};
  let roleDefaults = {};
  let debounce = null;
  let bulk = null;

  const tbody = document.getElementById('usersTbody');
  const userSearch = document.getElementById('userSearch');
  const userRoleFilter = document.getElementById('userRoleFilter');
  const btnNewUser = document.getElementById('btnNewUser');

  if (!tbody) return;

  async function loadUsers() {
    try {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">Loading users...</td></tr>';
      const res = await SLC.api.get('/users');
      allUsers = res.users || (res.data && res.data.users) || [];
      availablePermissions = res.available_permissions || (res.data && res.data.available_permissions) || {};
      roleDefaults = res.role_defaults || (res.data && res.data.role_defaults) || {};
      renderTable();
    } catch (err) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--bad);">Error: ' + SLC.escape(err.message) + '</td></tr>';
    }
  }

  function renderTable() {
    const q = (userSearch ? userSearch.value : '').toLowerCase().trim();
    const roleFilter = userRoleFilter ? userRoleFilter.value : '';

    const filtered = allUsers.filter(u => {
      const matchQ = !q || (u.name && u.name.toLowerCase().includes(q)) || (u.email && u.email.toLowerCase().includes(q));
      const matchRole = !roleFilter || u.role === roleFilter;
      return matchQ && matchRole;
    });

    if (filtered.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:var(--muted);">No users found.</td></tr>';
      bulk && bulk.update();
      return;
    }

    const currentUserId = (SLC.user && SLC.user.id) || (SLC.config && SLC.config.user && SLC.config.user.id);

    tbody.innerHTML = filtered.map(u => {
      const isMe = currentUserId && currentUserId === u.id;
      const roleBadge = u.role === 'admin' 
        ? '<span class="badge" style="background:rgba(99,102,241,0.15); color:var(--primary); font-weight:600;">Admin</span>'
        : '<span class="badge" style="background:rgba(100,116,139,0.15); color:var(--muted); font-weight:600;">User</span>';

      const statusBadge = u.is_active == 1
        ? '<span class="badge" style="background:rgba(34,197,94,0.15); color:var(--good);">Active</span>'
        : '<span class="badge" style="background:rgba(239,68,68,0.15); color:var(--bad);">Inactive</span>';

      let permSummary = '';
      if (u.role === 'admin') {
        permSummary = '<span style="color:var(--good); font-size:12px; font-weight:600;">FULL ACCESS (All Modules)</span>';
      } else {
        const p = u.computed_permissions || {};
        const aiLead = p['ai_lead_finder.view'] ? '<span class="badge" style="color:var(--good); font-size:11px;">AI Lead: ON</span>' : '<span class="badge" style="color:var(--bad); font-size:11px;">AI Lead: OFF</span>';
        const configSec = p['configuration.view'] ? '<span class="badge" style="color:var(--good); font-size:11px;">Config: ON</span>' : '<span class="badge" style="color:var(--bad); font-size:11px;">Config: OFF</span>';
        permSummary = '<div style="display:flex; gap:6px; flex-wrap:wrap;">' + aiLead + ' ' + configSec + '</div>';
      }

      return (
        '<tr>' +
          '<td class="td-cb" onclick="event.stopPropagation()">' +
            (!isMe ? '<input type="checkbox" class="cb-custom user-cb" data-id="' + u.id + '">' : '') +
          '</td>' +
          '<td>' +
            '<div style="font-weight:600; color:var(--text);">' + SLC.escape(u.name) + (isMe ? ' <span style="font-size:11px; color:var(--primary); font-weight:normal;">(You)</span>' : '') + '</div>' +
            '<div style="font-size:12px; color:var(--muted);">' + SLC.escape(u.email) + '</div>' +
          '</td>' +
          '<td>' + roleBadge + '</td>' +
          '<td>' + permSummary + '</td>' +
          '<td>' + statusBadge + '</td>' +
          '<td style="font-size:12px; color:var(--muted);">' + SLC.escape(u.last_login_at || 'Never') + '</td>' +
          '<td style="text-align:right;">' +
            '<button class="btn-secondary btn-edit-user" data-id="' + u.id + '" style="padding:4px 10px; font-size:12px; margin-right:4px;">Edit</button>' +
            (!isMe ? '<button class="btn-secondary btn-delete-user" data-id="' + u.id + '" style="padding:4px 10px; font-size:12px; color:var(--bad);">Delete</button>' : '') +
          '</td>' +
        '</tr>'
      );
    }).join('');

    tbody.querySelectorAll('.btn-edit-user').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.getAttribute('data-id'), 10);
        const u = allUsers.find(x => x.id === id);
        if (u) openUserModal(u);
      });
    });

    tbody.querySelectorAll('.btn-delete-user').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = parseInt(btn.getAttribute('data-id'), 10);
        deleteUser(id);
      });
    });

    bulk && bulk.update();
  }

  function renderPermGridHtml(role, overrides) {
    overrides = overrides || {};
    const defaults = roleDefaults[role] || {};
    const keys = Object.keys(availablePermissions);

    return (
      '<div id="permGridContainer" style="display:grid; grid-template-columns:1fr 1fr; gap:10px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); padding:12px;">' +
      keys.map(key => {
        const label = availablePermissions[key] || key;
        const isChecked = role === 'admin'
          ? true
          : (key in overrides ? !!overrides[key] : !!defaults[key]);
        const isDisabled = role === 'admin';

        return (
          '<label style="display:flex; align-items:flex-start; gap:8px; font-size:12px; cursor:' + (isDisabled ? 'default' : 'pointer') + '; opacity:' + (isDisabled ? '0.75' : '1') + ';">' +
            '<input type="checkbox" class="perm-chk" data-perm="' + SLC.escape(key) + '" ' + (isChecked ? 'checked' : '') + ' ' + (isDisabled ? 'disabled' : '') + ' style="margin-top:2px; accent-color:var(--primary);">' +
            '<span>' +
              '<strong style="display:block; color:var(--text);">' + SLC.escape(label) + '</strong>' +
              '<code style="font-size:10.5px; color:var(--muted);">' + SLC.escape(key) + '</code>' +
            '</span>' +
          '</label>'
        );
      }).join('') +
      '</div>'
    );
  }

  function openUserModal(user) {
    const isEdit = !!user;
    const initialRole = user ? user.role : 'user';
    const initialOverrides = user ? (user.permissions_raw || {}) : {};

    const bodyHtml = (
      '<div style="display:flex; flex-direction:column; gap:14px;">' +
        '<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">' +
          '<div class="field">' +
            '<label class="fld" style="display:block; margin-bottom:4px; font-size:12px; font-weight:600;">Full Name *</label>' +
            '<input type="text" id="modalUserName" class="fld" value="' + SLC.escape(user ? user.name : '') + '" placeholder="e.g. Rahul Sharma" style="width:100%;">' +
          '</div>' +
          '<div class="field">' +
            '<label class="fld" style="display:block; margin-bottom:4px; font-size:12px; font-weight:600;">Email Address *</label>' +
            '<input type="email" id="modalUserEmail" class="fld" value="' + SLC.escape(user ? user.email : '') + '" placeholder="rahul@shreelabel.com" style="width:100%;">' +
          '</div>' +
        '</div>' +
        '<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">' +
          '<div class="field">' +
            '<label class="fld" style="display:block; margin-bottom:4px; font-size:12px; font-weight:600;">Password ' + (isEdit ? '<span style="font-weight:400; color:var(--muted);">(leave blank to keep)</span>' : '<span style="color:var(--bad);">* (min 6 chars)</span>') + '</label>' +
            '<input type="password" id="modalUserPassword" class="fld" placeholder="••••••••" style="width:100%;">' +
          '</div>' +
          '<div class="field">' +
            '<label class="fld" style="display:block; margin-bottom:4px; font-size:12px; font-weight:600;">Role *</label>' +
            '<select id="modalUserRole" class="fld" style="width:100%;">' +
              '<option value="admin" ' + (initialRole === 'admin' ? 'selected' : '') + '>Admin (Full Access)</option>' +
              '<option value="user" ' + (initialRole === 'user' ? 'selected' : '') + '>User (Standard Sales)</option>' +
            '</select>' +
          '</div>' +
        '</div>' +
        '<div class="field">' +
          '<label class="fld" style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px;">' +
            '<input type="checkbox" id="modalUserActive" ' + (!user || user.is_active == 1 ? 'checked' : '') + ' style="accent-color:var(--primary);">' +
            '<span>Active Account</span>' +
          '</label>' +
        '</div>' +
        '<div style="border-top:1px solid var(--border); padding-top:12px;">' +
          '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">' +
            '<div>' +
              '<h4 style="margin:0; font-size:13px; font-weight:700;">Granular Permissions</h4>' +
              '<p style="margin:2px 0 0; color:var(--muted); font-size:11.5px;">Override default role permissions for this user.</p>' +
            '</div>' +
            '<span id="modalRoleBadgeHelp" class="badge" style="font-size:11px;">' + (initialRole === 'admin' ? 'Admin Full Access' : 'User Permissions') + '</span>' +
          '</div>' +
          '<div id="modalPermWrap">' + renderPermGridHtml(initialRole, initialOverrides) + '</div>' +
        '</div>' +
      '</div>'
    );

    const m = SLC.modal.open({
      title: isEdit ? 'Edit User: ' + user.name : 'Add New User',
      body: bodyHtml,
      size: 'lg',
      footer: '<button class="btn-ghost" data-close>Cancel</button><button class="btn-primary" id="modalSaveUserBtn">Save User</button>',
    });

    const roleSelect = m.el.querySelector('#modalUserRole');
    const permWrap = m.el.querySelector('#modalPermWrap');
    const badgeHelp = m.el.querySelector('#modalRoleBadgeHelp');

    roleSelect.addEventListener('change', () => {
      const selected = roleSelect.value;
      badgeHelp.textContent = selected === 'admin' ? 'Admin Full Access' : 'User Permissions';
      permWrap.innerHTML = renderPermGridHtml(selected, {});
    });

    m.el.querySelector('#modalSaveUserBtn').addEventListener('click', async () => {
      const saveBtn = m.el.querySelector('#modalSaveUserBtn');
      const name = m.el.querySelector('#modalUserName').value.trim();
      const email = m.el.querySelector('#modalUserEmail').value.trim();
      const password = m.el.querySelector('#modalUserPassword').value;
      const role = roleSelect.value;
      const isActive = m.el.querySelector('#modalUserActive').checked ? 1 : 0;

      if (!name) {
        SLC.toast('Name is required', 'error');
        return;
      }
      if (!email) {
        SLC.toast('Email is required', 'error');
        return;
      }
      if (!isEdit && (!password || password.length < 6)) {
        SLC.toast('Password must be at least 6 characters', 'error');
        return;
      }

      const payload = {
        name: name,
        email: email,
        role: role,
        is_active: isActive,
      };

      if (password) {
        payload.password = password;
      }

      if (role === 'user') {
        const perms = {};
        m.el.querySelectorAll('.perm-chk').forEach(chk => {
          const k = chk.getAttribute('data-perm');
          perms[k] = chk.checked;
        });
        payload.permissions = perms;
      } else {
        payload.permissions = null;
      }

      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';

      try {
        if (isEdit) {
          await SLC.api.put('/users/' + user.id, payload);
        } else {
          await SLC.api.post('/users', payload);
        }
        SLC.toast(isEdit ? 'User updated successfully' : 'User created successfully', 'success');
        m.close();
        loadUsers();
      } catch (err) {
        SLC.toast(err.message || 'Failed to save user', 'error');
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save User';
      }
    });
  }

  async function deleteUser(userId) {
    const u = allUsers.find(x => x.id === userId);
    if (!u) return;
    if (!confirm('Are you sure you want to delete user "' + u.name + '" (' + u.email + ')?')) {
      return;
    }

    try {
      await SLC.api.del('/users/' + userId);
      SLC.toast('User deleted successfully', 'success');
      loadUsers();
    } catch (err) {
      SLC.toast(err.message || 'Failed to delete user', 'error');
    }
  }

  if (btnNewUser) {
    btnNewUser.addEventListener('click', () => openUserModal(null));
  }

  if (userSearch) {
    userSearch.addEventListener('input', () => {
      clearTimeout(debounce);
      debounce = setTimeout(renderTable, 200);
    });
  }

  if (userRoleFilter) {
    userRoleFilter.addEventListener('change', renderTable);
  }

  bulk = SLC.ui.bindBulkActions({
    selectAllId: 'selectAllUsers',
    bulkBarId: 'userBulkBar',
    countId: 'userSelectedCount',
    deleteBtnId: 'userBulkDeleteBtn',
    clearBtnId: 'userBulkClearBtn',
    rowSelector: '.user-cb',
    customDelete: async (ids) => {
      return await SLC.api.post('/users/bulk-delete', { ids: ids });
    },
    entityName: 'users',
    onDeleted: () => {
      loadUsers();
    }
  });

  loadUsers();
})();
