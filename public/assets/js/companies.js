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
  let currentCompaniesList = [];

  async function loadFilterOptions() {
    try {
      const res = await api.get('companies/filter-options');
      const indSel = document.getElementById('companyIndustry');
      const locSel = document.getElementById('companyLocation');
      if (indSel && res.industries) {
        const curVal = indSel.value;
        indSel.innerHTML = '<option value="">All Industries</option>' + res.industries.map(i => `<option value="${SLC.escape(i)}">${SLC.escape(i)}</option>`).join('');
        if (curVal) indSel.value = curVal;
      }
      if (locSel && res.locations) {
        const curVal = locSel.value;
        locSel.innerHTML = '<option value="">All Locations</option>' + res.locations.map(l => `<option value="${SLC.escape(l)}">${SLC.escape(l)}</option>`).join('');
        if (curVal) locSel.value = curVal;
      }
    } catch (e) {}
  }

  async function load() {
    const tbody = document.getElementById('companyRows');
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px">' + SLC.ui.spinner() + '</td></tr>';
    try {
      const params = { page: page, per_page: 20 };
      if (q) params.q = q;
      const ind = document.getElementById('companyIndustry')?.value;
      const loc = document.getElementById('companyLocation')?.value;
      const pr = document.getElementById('companyPriority')?.value;
      const assigned = document.getElementById('companyAssignedUser')?.value;
      if (ind) params.industry = ind;
      if (loc) params.location = loc;
      if (pr) params.ai_priority = pr;
      if (assigned) params.assigned_to = assigned;
      const res = await R.list(params);
      currentCompaniesList = (res.data || []);
      const rows = currentCompaniesList;
      tbody.innerHTML = rows.length ? rows.map(c =>
        '<tr class="row-link" data-view="' + c.id + '" style="cursor:pointer;">' +
        '<td class="td-cb" onclick="event.stopPropagation()"><input type="checkbox" class="cb-custom company-cb" data-id="' + c.id + '"></td>' +
        '<td class="company-cell">' +
          '<div style="display:flex;align-items:center;gap:10px;">' +
            '<div style="width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,rgba(124,92,255,0.22),rgba(91,140,255,0.16));border:1px solid rgba(124,92,255,0.35);color:#c4b5fd;display:grid;place-items:center;font-size:12px;font-weight:700;flex:0 0 30px;">' +
              SLC.escape((c.name || 'C').charAt(0).toUpperCase()) +
            '</div>' +
            '<div style="min-width:0;">' +
              '<div class="strong" style="color:var(--text);font-size:13px;white-space:nowrap;">' + SLC.escape(c.name) + '</div>' +
              (c.website ? '<a href="' + (c.website.startsWith('http') ? SLC.escape(c.website) : 'https://' + SLC.escape(c.website)) + '" target="_blank" rel="noopener noreferrer" class="muted website-link" onclick="event.stopPropagation()" style="font-size:11px;color:#93c5fd;display:inline-flex;align-items:center;gap:3px;margin-top:2px;text-decoration:none;white-space:nowrap;">🌐 ' + SLC.escape(c.website.replace(/^https?:\/\//,'').replace(/^www\./,'')) + ' ↗</a>' : '') +
            '</div>' +
          '</div>' +
        '</td>' +
        '<td class="industry-cell" style="color:var(--muted);">' + SLC.escape(c.industry || '—') + '</td>' +
        '<td class="location-cell">' + SLC.escape([c.city, c.state].filter(Boolean).join(', ') || '—') + '</td>' +
        '<td class="score-cell">' + SLC.ui.scoreBar(c.ai_score) + '</td>' +
        '<td class="priority-cell">' + SLC.ui.priorityBadge(c.ai_priority) + '</td>' +
        '<td class="source-cell"><span class="muted">' + SLC.escape(c.source || 'Manual') + '</span></td>' +
        '<td>' + SLC.assigneeBadge(c.assigned_user_name, c.assigned_at) + '</td>' +
        '<td>' + SLC.dateBadge(c.created_at) + '</td>' +
        '<td style="text-align:right;white-space:nowrap;" onclick="event.stopPropagation()"><button class="btn-icon btn-sm" data-edit="' + c.id + '" title="Edit">✏️</button> <button class="btn-icon btn-sm" data-del="' + c.id + '" title="Delete">🗑️</button></td>' +
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
        '<div class="section-title">Company Info</div>' +
        '<div class="detail-row"><span class="k">Name</span><span class="v">' + SLC.escape(c.name) + '</span></div>' +
        '<div class="detail-row"><span class="k">Industry</span><span class="v">' + SLC.escape(c.industry || '—') + '</span></div>' +
        '<div class="detail-row"><span class="k">Location</span><span class="v">' + SLC.escape([c.city, c.state, c.country].filter(Boolean).join(', ') || '—') + '</span></div>' +
        '<div class="detail-row"><span class="k">Website</span><span class="v">' + (c.website ? '<a href="' + SLC.escape(c.website) + '" target="_blank">' + SLC.escape(c.website) + '</a>' : '—') + '</span></div>' +
        '<div class="detail-row"><span class="k">Phone</span><span class="v">' + SLC.escape(c.phone || '—') + '</span></div>' +
        '<div class="detail-row"><span class="k">Email</span><span class="v">' + SLC.escape(c.email || '—') + '</span></div>' +
        '<div class="detail-row"><span class="k">AI Priority</span><span class="v">' + SLC.ui.priorityBadge(c.ai_priority) + '</span></div>' +
        '<div class="detail-row"><span class="k">AI Score</span><span class="v">' + (c.ai_score ?? '—') + '/100</span></div>' +
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
      const cleanTargetName = targetUserText.replace(/\s*\([^)]*\)/g, '').trim();
      try {
        const res = await api.post('companies/bulk-assign', {
          ids: selectedIds,
          assigned_to: targetUserId,
        });
        SLC.toast(`Assigned ${res.count || selectedIds.length} company(ies) to ${cleanTargetName}!`, 'success');
        
        const assignedItems = (currentCompaniesList || []).filter(c => selectedIds.includes(parseInt(c.id, 10))).map(c => ({
          name: c.name,
          company_name: c.name,
          phone: c.phone,
          email: c.email,
          location: [c.city, c.state].filter(Boolean).join(', '),
          industry: c.industry,
        }));

        if (assignedItems.length && typeof SLC.openWhatsAppShareModal === 'function') {
          SLC.openWhatsAppShareModal({
            assignedToName: cleanTargetName,
            items: assignedItems,
            typeLabel: 'Companies',
          });
        }

        bulk.clear();
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      } catch (e) {
        SLC.toast(e.message, 'error');
      }
    });

    // Dedicated WhatsApp Share button for selected companies
    document.getElementById('companyBulkWaBtn')?.addEventListener('click', () => {
      const selectedIds = bulk ? bulk.getSelected().map(Number) : [];
      if (!selectedIds.length) {
        SLC.toast('Select companies from table first to copy WhatsApp message.', 'warn');
        return;
      }
      const targetUserText = document.getElementById('companyBulkAssignSelect')?.options[document.getElementById('companyBulkAssignSelect')?.selectedIndex]?.text || 'Sales Team';
      const cleanTargetName = targetUserText === 'Assign to...' ? 'Sales Team' : targetUserText.replace(/\s*\([^)]*\)/g, '').trim();
      const selectedItems = (currentCompaniesList || []).filter(c => selectedIds.includes(parseInt(c.id, 10))).map(c => ({
        name: c.name,
        company_name: c.name,
        phone: c.phone,
        email: c.email,
        location: [c.city, c.state].filter(Boolean).join(', '),
        industry: c.industry,
      }));
      if (selectedItems.length && typeof SLC.openWhatsAppShareModal === 'function') {
        SLC.openWhatsAppShareModal({
          assignedToName: cleanTargetName,
          items: selectedItems,
          typeLabel: 'Companies',
        });
      }
    });

    await loadUsers();
    await loadFilterOptions();
    load();
    document.getElementById('companySearch')?.addEventListener('input', e => { clearTimeout(debounce); debounce = setTimeout(() => { q = e.target.value.trim(); page = 1; load(); }, 300); });
    ['companyIndustry', 'companyLocation', 'companyPriority', 'companyAssignedUser'].forEach(id => {
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
