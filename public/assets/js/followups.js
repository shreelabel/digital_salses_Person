/* followups.js */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const R = api.resource('followups');
  const Companies = api.resource('companies');
  const Users = api.resource('users');
  let page = 1, companies = [], users = [], q = '', debounce = null;

  async function loadCompanies() { try { const r = await Companies.list({ per_page: 300 }); companies = r.data || []; } catch (e) {} }

  async function loadUsers() {
    try {
      const res = await Users.list({ per_page: 100 });
      users = res.users || (res.data && res.data.users) || [];
      const sel = document.getElementById('fuAssignedUser');
      if (sel) {
        sel.innerHTML = '<option value="">All Assignees</option>' + users.map(u => '<option value="' + u.id + '">' + SLC.escape(u.name) + '</option>').join('');
      }
    } catch (e) {}
  }

  function fields(fu) {
    fu = fu || {};
    return '<div class="form-grid">' +
      '<div class="field full"><label class="fld">Company</label><select class="fld" name="company_id"><option value="">Select company…</option>' + companies.map(c => '<option value="' + c.id + '" ' + (fu.company_id == c.id ? 'selected' : '') + '>' + SLC.escape(c.name) + '</option>').join('') + '</select></div>' +
      f('type', 'Type', fu.type) + f('scheduled_at', 'Scheduled at *', (fu.scheduled_at || '').replace(' ', 'T').slice(0, 16), 'datetime-local') +
      '<div class="field"><label class="fld">Status</label><select class="fld" name="status"><option>Pending</option><option>Completed</option></select></div>' +
      '<div class="field full"><label class="fld">Notes</label><textarea class="fld" name="notes">' + SLC.escape(fu.notes || '') + '</textarea></div>' +
      '</div>';
  }
  function f(n, l, v, t) { return '<div class="field"><label class="fld">' + l + '</label><input class="fld" name="' + n + '" type="' + (t || 'text') + '" value="' + SLC.escape(v || '') + '"></div>'; }

  let bulk;

  async function load() {
    const tbody = document.getElementById('fuRows');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px">' + SLC.ui.spinner() + '</td></tr>';
    try {
      const params = { page, per_page: 25, order_by: 'scheduled_at', dir: 'ASC' };
      const st = document.getElementById('fuStatus')?.value;
      const ty = document.getElementById('fuType')?.value;
      const au = document.getElementById('fuAssignedUser')?.value;
      if (q) params.q = q;
      if (st) params.status = st;
      if (ty) params.type = ty;
      if (au) params.assigned_to = au;

      const res = await R.list(params);
      const now = Date.now();
      tbody.innerHTML = (res.data || []).length ? (res.data || []).map(x => {
        const overdue = x.status === 'Pending' && new Date(x.scheduled_at).getTime() < now;
        const cls = overdue ? 'Overdue' : x.status;
        const assigneeBadge = x.assigned_user_name
          ? '<span class="badge badge-purple" style="font-size:11px;">👤 ' + SLC.escape(x.assigned_user_name) + '</span>'
          : '<span class="muted" style="font-size:12px;">Unassigned</span>';

        return '<tr>' +
          '<td class="td-cb" onclick="event.stopPropagation()"><input type="checkbox" class="cb-custom fu-cb" data-id="' + x.id + '"></td>' +
          '<td style="font-weight:600;font-size:13px;">' + (x.scheduled_at ? new Date(x.scheduled_at).toLocaleString([], {dateStyle:'medium', timeStyle:'short'}) : '—') + '</td>' +
          '<td class="strong" style="color:var(--text);">' + SLC.escape(x.company_name || '—') + '</td>' +
          '<td><span class="badge badge-gray">' + SLC.escape(x.type || 'Follow-up') + '</span></td>' +
          '<td>' + SLC.ui.statusPill(cls) + '</td>' +
          '<td>' + assigneeBadge + '</td>' +
          '<td class="muted" style="font-size:12.5px;">' + SLC.escape(x.notes || '—') + '</td>' +
          '<td style="text-align:right;white-space:nowrap">' +
            (x.status === 'Pending' ? '<button class="btn-secondary btn-sm" data-done="' + x.id + '" style="font-size:11.5px;padding:3px 8px;">✓ Done</button> ' : '') +
            '<button class="btn-icon btn-sm" data-del="' + x.id + '" title="Delete">🗑️</button>' +
          '</td>' +
          '</tr>';
      }).join('') : '<tr><td colspan="8">' + SLC.ui.empty('No follow-ups found', 'Schedule a follow-up task to keep track of sales activities.') + '</td></tr>';

      SLC.pagerRender('fuPager', res, page, load, p => { page = p; load(); });
      bulk && bulk.update();
    } catch (e) { SLC.toast(e.message, 'error'); }
  }

  function openModal(fu) {
    const m = SLC.modal.open({ title: fu ? 'Edit Follow-up' : 'Schedule Follow-up', body: fields(fu), footer: '<button class="btn-ghost" data-close>Cancel</button><button class="btn-primary" data-save>Save</button>' });
    if (fu && fu.status) m.el.querySelector('[name=status]').value = fu.status;
    m.el.querySelector('[data-save]').addEventListener('click', async () => {
      const data = {}; m.el.querySelectorAll('[name]').forEach(el => data[el.name] = el.value);
      if (!data.scheduled_at) { SLC.toast('Scheduled time required', 'error'); return; }
      try { if (fu) await R.update(fu.id, data); else await R.create(data); SLC.toast('Saved', 'success'); m.close(); load(); } catch (e) { SLC.toast(e.message, 'error'); }
    });
  }

  document.addEventListener('DOMContentLoaded', async function () {
    bulk = SLC.ui.bindBulkActions({
      selectAllId: 'selectAllFollowups',
      bulkBarId: 'fuBulkBar',
      countId: 'fuSelectedCount',
      deleteBtnId: 'fuBulkDeleteBtn',
      clearBtnId: 'fuBulkClearBtn',
      rowSelector: '.fu-cb',
      resource: R,
      entityName: 'follow-ups',
      onDeleted: () => {
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      }
    });

    await loadCompanies();
    await loadUsers();
    load();

    document.getElementById('fuSearch')?.addEventListener('input', e => {
      clearTimeout(debounce);
      debounce = setTimeout(() => { q = e.target.value.trim(); page = 1; load(); }, 300);
    });

    ['fuStatus', 'fuType', 'fuAssignedUser'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => { page = 1; load(); });
    });

    document.getElementById('addFuBtn')?.addEventListener('click', () => openModal(null));

    document.getElementById('fuRows')?.addEventListener('click', async (e) => {
      const del = e.target.closest('[data-del]'), done = e.target.closest('[data-done]');
      if (del) {
        if (confirm('Delete this follow-up?')) {
          try {
            await R.remove(del.getAttribute('data-del'));
            SLC.toast('Deleted', 'success');
            load();
            if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
          } catch (er) { SLC.toast(er.message, 'error'); }
        }
        return;
      }
      if (done) {
        try {
          await R.update(done.getAttribute('data-done'), { status: 'Completed' });
          SLC.toast('Marked complete', 'success');
          load();
          if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
        } catch (er) { SLC.toast(er.message, 'error'); }
      }
    });
  });
})();
