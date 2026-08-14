/* companies.js — full CRUD + view slide-over */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const R = api.resource('companies');
  let page = 1, q = '', debounce;

  let assignableUsers = [];
  const INDUSTRIES = ['Pharmaceutical','FMCG','Food & Beverage','Cosmetics','Tea','Agro','Chemicals','Packaging','Personal Care','Healthcare','Manufacturing','Beverages','Other'];

  async function loadUsers() {
    try {
      const res = await api.get('users/assignable');
      assignableUsers = res.users || [];
      const filterSel = document.getElementById('companyAssignedUser');
      const bulkSel = document.getElementById('companyBulkAssignSelect');
      if (filterSel) {
        filterSel.innerHTML = '<option value="">All Assignees</option>' + assignableUsers.map(u => `<option value="${u.id}">${SLC.escape(u.name)} (${u.role})</option>`).join('');
      }
      if (bulkSel) {
        bulkSel.innerHTML = '<option value="">Assign to...</option>' + assignableUsers.map(u => `<option value="${u.id}">${SLC.escape(u.name)}</option>`).join('');
      }
    } catch (e) {}
  }

  function fields(company) {
    const userOpts = assignableUsers.map(u => `<option value="${u.id}" ${company && parseInt(company.assigned_to, 10) === u.id ? 'selected' : ''}>${SLC.escape(u.name)} (${u.role})</option>`).join('');

    return '<div class="form-grid">' +
      f('name', 'Company name *', 'text', '', true) +
      sel('industry', 'Industry', INDUSTRIES) +
      f('sub_industry', 'Sub-industry', 'text') +
      f('city', 'City', 'text') +
      f('state', 'State', 'text') +
      f('country', 'Country', 'text', 'India') +
      f('website', 'Website', 'url') +
      f('phone', 'Phone', 'text') +
      f('email', 'Email', 'email') +
      f('employee_count', 'Employees', 'text') +
      sel('ai_priority', 'Priority', ['High','Medium','Low']) +
      num('ai_score', 'AI Score (0-100)', 'number', '') +
      '<div class="field"><label class="fld">Assigned To (Sales Person)</label><select class="fld" name="assigned_to"><option value="">Unassigned / Admin</option>' + userOpts + '</select></div>' +
      '<div class="field full"><label class="fld">Description</label><textarea class="fld" name="description"></textarea></div>' +
      '<div class="field full"><label class="fld">Source</label><input class="fld" name="source" placeholder="Manual"></div>' +
      '</div>';
  }
  function f(n, l, t, v, req) { return '<div class="field"><label class="fld">' + l + '</label><input class="fld" name="' + n + '" type="' + (t || 'text') + '" value="' + SLC.escape(v || '') + '" ' + (req ? 'required' : '') + '></div>'; }
  function num(n, l, t, v) { return '<div class="field"><label class="fld">' + l + '</label><input class="fld" name="' + n + '" type="' + t + '" value="' + SLC.escape(v || '') + '"></div>'; }
  function sel(n, l, opts) {
    return '<div class="field"><label class="fld">' + l + '</label><select class="fld" name="' + n + '"><option value="">—</option>' + opts.map(o => '<option>' + SLC.escape(o) + '</option>').join('') + '</select></div>';
  }
  function readForm(modal) {
    const out = {};
    modal.el.querySelectorAll('[name]').forEach(el => { out[el.name] = el.value; });
    return out;
  }

  let bulk;

  async function load() {
    const tbody = document.getElementById('companyRows');
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px">' + SLC.ui.spinner() + '</td></tr>';
    try {
      const params = { page: page, per_page: 20 };
      if (q) params.q = q;
      const ind = document.getElementById('companyIndustry')?.value;
      const pr = document.getElementById('companyPriority')?.value;
      const assigned = document.getElementById('companyAssignedUser')?.value;
      if (ind) params.industry = ind;
      if (pr) params.ai_priority = pr;
      if (assigned) params.assigned_to = assigned;
      const res = await R.list(params);
      const rows = (res.data || []);
      tbody.innerHTML = rows.length ? rows.map(c =>
        '<tr class="row-link" data-view="' + c.id + '">' +
        '<td class="td-cb" onclick="event.stopPropagation()"><input type="checkbox" class="cb-custom company-cb" data-id="' + c.id + '"></td>' +
        '<td><div class="strong">' + SLC.escape(c.name) + '</div><div class="muted" style="font-size:11px">' + SLC.escape(c.website || '') + '</div></td>' +
        '<td>' + SLC.escape(c.industry || '—') + '</td>' +
        '<td>' + SLC.escape([c.city, c.state].filter(Boolean).join(', ') || '—') + '</td>' +
        '<td>' + SLC.ui.scoreBar(c.ai_score) + '</td>' +
        '<td>' + SLC.ui.priorityBadge(c.ai_priority) + '</td>' +
        '<td><span class="muted">' + SLC.escape(c.source || 'Manual') + '</span></td>' +
        '<td><span class="badge" style="background:var(--panel2);border:1px solid var(--border);color:var(--text);font-size:11px;">👤 ' + SLC.escape(c.assigned_user_name || 'Admin') + '</span></td>' +
        '<td class="muted">' + SLC.rel(c.created_at) + '</td>' +
        '<td style="text-align:right"><button class="btn-icon btn-sm" data-edit="' + c.id + '" title="Edit">✏️</button> <button class="btn-icon btn-sm" data-del="' + c.id + '" title="Delete">🗑️</button></td>' +
        '</tr>'
      ).join('') : '<tr><td colspan="10">' + SLC.ui.empty('No companies yet', 'Add one or run AI Lead Finder.') + '</td></tr>';
      SLC.pagerRender && SLC.pagerRender('companyPager', res, page, load, p => { page = p; load(); });
      bulk && bulk.update();
    } catch (e) { SLC.toast(e.message, 'error'); }
  }

  async function view(id) {
    const m = SLC.slideover.open({ title: 'Company details', body: SLC.ui.spinner() });
    try {
      const res = await R.get(id);
      const c = res.company;
      const contacts = c.contacts || [], leads = c.leads || [], acts = c.activities || [], reports = c.research_reports || [];
      m.el.querySelector('.slideover-body').innerHTML =
        '<div class="detail-row"><span class="k">Name</span><span class="v">' + SLC.escape(c.name) + '</span></div>' +
        SLC.ui.field('Industry', c.industry) + SLC.ui.field('Sub-industry', c.sub_industry) +
        SLC.ui.field('Location', [c.city, c.state, c.country].filter(Boolean).join(', ')) +
        SLC.ui.field('Website', c.website) + SLC.ui.field('Phone', c.phone) + SLC.ui.field('Email', c.email) +
        SLC.ui.field('Employees', c.employee_count) + SLC.ui.field('Source', c.source) +
        '<div class="detail-row"><span class="k">AI Score</span><span class="v">' + SLC.ui.scoreBar(c.ai_score) + '</span></div>' +
        '<div class="detail-row"><span class="k">Priority</span><span class="v">' + SLC.ui.priorityBadge(c.ai_priority) + '</span></div>' +
        (c.description ? '<div class="detail-row"><span class="k">Description</span><span class="v" style="max-width:100%">' + SLC.escape(c.description) + '</span></div>' : '') +
        '<div class="section-title" style="margin-top:18px">Contacts (' + contacts.length + ')</div>' +
        (contacts.length ? contacts.map(x => '<div class="detail-row"><span class="k">' + SLC.escape(x.name) + (x.is_primary ? ' ★' : '') + '</span><span class="v">' + SLC.escape(x.designation || x.email || '—') + '</span></div>').join('') : '<div class="muted">None</div>') +
        '<div class="section-title" style="margin-top:18px">Leads (' + leads.length + ')</div>' +
        (leads.length ? leads.map(x => '<div class="detail-row"><span class="k">' + SLC.ui.statusBadge(x.status) + '</span><span class="v">' + SLC.escape(x.title || x.industry || 'Lead') + '</span></div>').join('') : '<div class="muted">None</div>') +
        '<div class="section-title" style="margin-top:18px">Research Reports (' + reports.length + ')</div>' +
        (reports.length ? reports.map(x => '<div class="detail-row"><span class="k">' + SLC.rel(x.created_at) + '</span><span class="v">Confidence ' + (x.confidence_score ?? '—') + '</span></div>').join('') : '<div class="muted">None</div>') +
        '<div class="section-title" style="margin-top:18px">Activity</div>' +
        (acts.length ? acts.map(x => '<div class="timeline-item"><div class="tl-dot"></div><div style="font-size:12px">' + SLC.escape(x.description) + '<div class="muted">' + SLC.rel(x.created_at) + '</div></div></div>').join('') : '<div class="muted">None</div>');
    } catch (e) { m.el.querySelector('.slideover-body').innerHTML = SLC.ui.empty('Failed to load', e.message); }
  }

  function openModal(company) {
    const m = SLC.modal.open({
      title: company ? 'Edit Company' : 'Add Company',
      body: fields(company),
      footer: '<button class="btn-ghost" data-close>Cancel</button><button class="btn-primary" data-save>Save</button>',
    });
    if (company) {
      Object.keys(company).forEach(k => { const el = m.el.querySelector('[name="' + k + '"]'); if (el) el.value = company[k] ?? ''; });
    }
    m.el.querySelector('[data-save]').addEventListener('click', async () => {
      const data = readForm(m);
      if (!data.name) { SLC.toast('Name is required', 'error'); return; }
      try {
        if (company) await R.update(company.id, data); else await R.create(data);
        SLC.toast('Saved', 'success'); m.close(); load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      } catch (e) { SLC.toast(e.message, 'error'); }
    });
  }

  document.addEventListener('DOMContentLoaded', async function () {
    bulk = SLC.ui.bindBulkActions({
      selectAllId: 'selectAllCompanies',
      bulkBarId: 'companyBulkBar',
      countId: 'companySelectedCount',
      deleteBtnId: 'companyBulkDeleteBtn',
      clearBtnId: 'companyBulkClearBtn',
      rowSelector: '.company-cb',
      resource: R,
      entityName: 'companies',
      onDeleted: () => {
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      }
    });

    // Bulk Assign handler
    document.getElementById('companyBulkAssignBtn')?.addEventListener('click', async () => {
      const targetUserId = parseInt(document.getElementById('companyBulkAssignSelect')?.value, 10);
      if (!targetUserId) {
        SLC.toast('Please select a user to assign companies to.', 'error');
        return;
      }
      const selectedIds = bulk ? bulk.getSelected().map(Number) : [];
      if (!selectedIds.length) {
        SLC.toast('No companies selected.', 'error');
        return;
      }
      const targetUserText = document.getElementById('companyBulkAssignSelect')?.options[document.getElementById('companyBulkAssignSelect')?.selectedIndex]?.text || 'User';
      try {
        const res = await api.post('companies/bulk-assign', {
          ids: selectedIds,
          assigned_to: targetUserId,
        });
        SLC.toast(`Assigned ${res.count || selectedIds.length} company(ies) to ${targetUserText}!`, 'success');
        bulk.clear();
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      } catch (e) {
        SLC.toast(e.message, 'error');
      }
    });

    await loadUsers();
    load();
    const ind = document.getElementById('companyIndustry');
    if (ind) INDUSTRIES.forEach(i => ind.appendChild(new Option(i, i)));
    document.getElementById('companySearch')?.addEventListener('input', e => { clearTimeout(debounce); debounce = setTimeout(() => { q = e.target.value.trim(); page = 1; load(); }, 300); });
    ['companyIndustry', 'companyPriority', 'companyAssignedUser'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => { page = 1; load(); });
    });
    document.getElementById('addCompanyBtn')?.addEventListener('click', () => openModal(null));

    document.getElementById('companyRows')?.addEventListener('click', async (e) => {
      const edit = e.target.closest('[data-edit]'), del = e.target.closest('[data-del]'), row = e.target.closest('[data-view]');
      if (del) {
        e.stopPropagation();
        const id = del.getAttribute('data-del');
        if (confirm('Delete this company?')) {
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
      if (edit) { e.stopPropagation(); const id = edit.getAttribute('data-edit'); try { const res = await R.get(id); openModal(res.company); } catch (er) { SLC.toast(er.message, 'error'); } return; }
      if (row) view(row.getAttribute('data-view'));
    });
  });

  // expose a shared pager renderer for reuse
  if (!SLC.pagerRender) {
    SLC.pagerRender = function (id, res, curPage, reload, go) {
      const el = document.getElementById(id); if (!el) return;
      const total = res.total || 0, per = res.per_page || 20, pages = Math.max(1, Math.ceil(total / per));
      el.innerHTML = '<span>Showing ' + ((res.data || []).length) + ' of ' + total + '</span>' +
        '<span><button class="btn-icon btn-sm" ' + (curPage <= 1 ? 'disabled' : '') + ' data-prev>‹</button> ' + curPage + '/' + pages + ' <button class="btn-icon btn-sm" ' + (curPage >= pages ? 'disabled' : '') + ' data-next>›</button></span>';
      el.querySelector('[data-prev]')?.addEventListener('click', () => { if (curPage > 1) go(curPage - 1); });
      el.querySelector('[data-next]')?.addEventListener('click', () => { if (curPage < pages) go(curPage + 1); });
    };
  }
})();
