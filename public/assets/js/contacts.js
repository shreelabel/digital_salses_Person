/* contacts.js — CRUD. Company must be selected from existing companies. */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const R = api.resource('contacts');
  const Companies = api.resource('companies');
  let page = 1, q = '', debounce;
  let companyCache = [];
  let assignableUsers = [];

  async function loadCompanies() {
    try { const res = await Companies.list({ per_page: 200 }); companyCache = res.data || []; } catch (e) {}
  }

  async function loadUsers() {
    try {
      const res = await api.get('users/assignable');
      assignableUsers = res.users || [];
      const filterSel = document.getElementById('contactAssignedUser');
      const bulkSel = document.getElementById('contactBulkAssignSelect');
      if (filterSel) {
        filterSel.innerHTML = '<option value="">All Assignees</option>' + assignableUsers.map(u => `<option value="${u.id}">${SLC.escape(u.name)} (${u.role})</option>`).join('');
      }
      if (bulkSel) {
        bulkSel.innerHTML = '<option value="">Assign to...</option>' + assignableUsers.map(u => `<option value="${u.id}">${SLC.escape(u.name)}</option>`).join('');
      }
    } catch (e) {}
  }

  function companyOptions(selected) {
    return companyCache.map(c => '<option value="' + c.id + '" ' + (selected == c.id ? 'selected' : '') + '>' + SLC.escape(c.name) + '</option>').join('');
  }

  function fields(contact) {
    const userOpts = assignableUsers.map(u => `<option value="${u.id}" ${contact && parseInt(contact.assigned_to, 10) === u.id ? 'selected' : ''}>${SLC.escape(u.name)} (${u.role})</option>`).join('');

    return '<div class="form-grid">' +
      '<div class="field full"><label class="fld">Company *</label><select class="fld" name="company_id" required><option value="">Select company…</option>' + companyOptions(contact?.company_id) + '</select></div>' +
      f('name', 'Name *') + f('designation', 'Designation') +
      f('department', 'Department') + f('email', 'Email', 'email') +
      f('phone', 'Phone') + f('mobile', 'Mobile') +
      f('linkedin_url', 'LinkedIn URL', 'url') + f('importance', 'Importance') +
      '<div class="field"><label class="fld">Assigned To (Sales Person)</label><select class="fld" name="assigned_to"><option value="">Unassigned / Admin</option>' + userOpts + '</select></div>' +
      '<div class="field full"><label class="fld">Notes</label><textarea class="fld" name="notes"></textarea></div>' +
      '<div class="field"><div class="checkbox-row"><input type="checkbox" name="is_decision_maker" value="1"><label>Decision maker</label></div></div>' +
      '<div class="field"><div class="checkbox-row"><input type="checkbox" name="is_primary" value="1"><label>Primary contact</label></div></div>' +
      '</div>';
  }
  function f(n, l, t) { return '<div class="field"><label class="fld">' + l + '</label><input class="fld" name="' + n + '" type="' + (t || 'text') + '"></div>'; }

  let bulk;

  async function load() {
    const tbody = document.getElementById('contactRows');
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px">' + SLC.ui.spinner() + '</td></tr>';
    try {
      const params = { page, per_page: 25 };
      if (q) params.q = q;
      const dm = document.getElementById('contactDm')?.value;
      const assigned = document.getElementById('contactAssignedUser')?.value;
      if (dm) params.is_decision_maker = dm;
      if (assigned) params.assigned_to = assigned;
      const res = await R.list(params);
      tbody.innerHTML = (res.data || []).length ? (res.data || []).map(c =>
        '<tr class="row-link" data-edit="' + c.id + '" style="cursor:pointer;">' +
        '<td class="td-cb" onclick="event.stopPropagation()"><input type="checkbox" class="cb-custom contact-cb" data-id="' + c.id + '"></td>' +
        '<td><div class="strong">' + SLC.escape(c.name) + '</div>' + (c.is_decision_maker ? '<span class="badge badge-purple" style="margin-top:3px">Decision maker</span>' : '') + '</td>' +
        '<td>' + SLC.escape(c.company_name || '—') + '</td>' +
        '<td>' + SLC.escape(c.designation || '—') + '</td>' +
        '<td>' + SLC.escape(c.department || '—') + '</td>' +
        '<td>' + SLC.escape(c.email || '—') + '</td>' +
        '<td>' + SLC.escape(c.phone || c.mobile || '—') + '</td>' +
        '<td>' + (c.is_primary == 1 ? '★' : '—') + '</td>' +
        '<td><span class="badge" style="background:var(--panel2);border:1px solid var(--border);color:var(--text);font-size:11px;">👤 ' + SLC.escape(c.assigned_user_name || 'Admin') + '</span></td>' +
        '<td style="text-align:right"><button class="btn-icon btn-sm" data-edit="' + c.id + '" title="Edit">✏️</button> <button class="btn-icon btn-sm" data-del="' + c.id + '" title="Delete">🗑️</button></td>' +
        '</tr>'
      ).join('') : '<tr><td colspan="10">' + SLC.ui.empty('No contacts yet', '') + '</td></tr>';
      SLC.pagerRender('contactPager', res, page, load, p => { page = p; load(); });
      bulk && bulk.update();
    } catch (e) { SLC.toast(e.message, 'error'); }
  }

  function openModal(contact) {
    const m = SLC.modal.open({
      title: contact ? 'Edit Contact' : 'Add Contact',
      body: fields(contact),
      footer: '<button class="btn-ghost" data-close>Cancel</button><button class="btn-primary" data-save>Save</button>',
    });
    if (contact) {
      Object.keys(contact).forEach(k => { const el = m.el.querySelector('[name="' + k + '"]'); if (el) { if (el.type === 'checkbox') el.checked = contact[k] == 1; else el.value = contact[k] ?? ''; } });
    }
    m.el.querySelector('[data-save]').addEventListener('click', async () => {
      const data = {};
      m.el.querySelectorAll('[name]').forEach(el => { if (el.type === 'checkbox') data[el.name] = el.checked ? 1 : 0; else data[el.name] = el.value; });
      if (!data.company_id) { SLC.toast('Select a company', 'error'); return; }
      if (!data.name) { SLC.toast('Name is required', 'error'); return; }
      try {
        if (contact) await R.update(contact.id, data);
        else await R.create(data);
        SLC.toast('Saved', 'success');
        m.close();
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      } catch (e) { SLC.toast(e.message, 'error'); }
    });
  }

  document.addEventListener('DOMContentLoaded', async function () {
    bulk = SLC.ui.bindBulkActions({
      selectAllId: 'selectAllContacts',
      bulkBarId: 'contactBulkBar',
      countId: 'contactSelectedCount',
      deleteBtnId: 'contactBulkDeleteBtn',
      clearBtnId: 'contactBulkClearBtn',
      rowSelector: '.contact-cb',
      resource: R,
      entityName: 'contacts',
      onDeleted: () => {
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      }
    });

    // Bulk Assign handler
    document.getElementById('contactBulkAssignBtn')?.addEventListener('click', async () => {
      const targetUserId = parseInt(document.getElementById('contactBulkAssignSelect')?.value, 10);
      if (!targetUserId) {
        SLC.toast('Please select a user to assign contacts to.', 'error');
        return;
      }
      const selectedIds = bulk ? bulk.getSelected().map(Number) : [];
      if (!selectedIds.length) {
        SLC.toast('No contacts selected.', 'error');
        return;
      }
      const targetUserText = document.getElementById('contactBulkAssignSelect')?.options[document.getElementById('contactBulkAssignSelect')?.selectedIndex]?.text || 'User';
      try {
        const res = await api.post('contacts/bulk-assign', {
          ids: selectedIds,
          assigned_to: targetUserId,
        });
        SLC.toast(`Assigned ${res.count || selectedIds.length} contact(s) to ${targetUserText}!`, 'success');
        bulk.clear();
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      } catch (e) {
        SLC.toast(e.message, 'error');
      }
    });

    await loadCompanies();
    await loadUsers();
    load();
    document.getElementById('contactSearch')?.addEventListener('input', e => { clearTimeout(debounce); debounce = setTimeout(() => { q = e.target.value.trim(); page = 1; load(); }, 300); });
    ['contactDm', 'contactAssignedUser'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => { page = 1; load(); });
    });
    document.getElementById('addContactBtn')?.addEventListener('click', () => openModal(null));
    document.getElementById('contactRows')?.addEventListener('click', async (e) => {
      const del = e.target.closest('[data-del]');
      if (del) {
        e.stopPropagation();
        const id = del.getAttribute('data-del');
        if (confirm('Delete this contact?')) {
          try {
            await R.remove(id);
            SLC.toast('Deleted', 'success');
            load();
            if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
          } catch (er) {
            SLC.toast(er.message, 'error');
          }
        }
        return;
      }
      const targetRow = e.target.closest('[data-edit]') || e.target.closest('.row-link');
      if (targetRow) {
        const id = targetRow.getAttribute('data-edit') || targetRow.getAttribute('data-id');
        if (id) {
          try {
            const res = await R.get(id);
            if (res && res.contact) {
              openModal(res.contact);
            }
          } catch (er) {
            SLC.toast(er.message, 'error');
          }
        }
      }
    });
  });
})();
