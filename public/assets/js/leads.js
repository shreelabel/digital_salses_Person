/* leads.js — pipeline CRUD + Apollo CSV source support */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const R = api.resource('leads');
  const Companies = api.resource('companies');
  let page = 1, q = '', debounce;
  let companies = [];
  let assignableUsers = [];
  const STATUSES = ['New','Contacted','Interested','Requirement','Quotation','Negotiation','Won','Lost'];

  async function loadCompanies() { try { const r = await Companies.list({ per_page: 300 }); companies = r.data || []; } catch (e) {} }

  async function loadUsers() {
    try {
      const res = await api.get('users/assignable');
      assignableUsers = res.users || [];
      const filterSel = document.getElementById('leadAssignedUser');
      const bulkSel = document.getElementById('leadBulkAssignSelect');
      if (filterSel) {
        filterSel.innerHTML = '<option value="">All Assignees</option>' + assignableUsers.map(u => `<option value="${u.id}">${SLC.escape(u.name)} (${u.role})</option>`).join('');
      }
      if (bulkSel) {
        bulkSel.innerHTML = '<option value="">Assign to...</option>' + assignableUsers.map(u => `<option value="${u.id}">${SLC.escape(u.name)}</option>`).join('');
      }
    } catch (e) {}
  }

  function fields(lead) {
    const isApollo = lead && lead.source === 'Apollo CSV';
    const apolloBtn = isApollo ? `
      <div class="field full" style="margin-top:6px;">
        <button type="button" class="btn-secondary btn-sm" id="modalViewApolloBtn" style="display:flex;align-items:center;gap:6px;">
          🔍 View Preserved Apollo Dataset (70+ Fields)
        </button>
      </div>
    ` : '';

    const userOpts = assignableUsers.map(u => `<option value="${u.id}" ${lead && parseInt(lead.assigned_to, 10) === u.id ? 'selected' : ''}>${SLC.escape(u.name)} (${u.role})</option>`).join('');

    return '<div class="form-grid">' +
      '<div class="field full"><label class="fld">Company *</label><select class="fld" name="company_id" required><option value="">Select company…</option>' + companies.map(c => '<option value="' + c.id + '" ' + (lead && lead.company_id == c.id ? 'selected' : '') + '>' + SLC.escape(c.name) + '</option>').join('') + '</select></div>' +
      f('title', 'Title') + f('industry', 'Industry') +
      f('location', 'Location') + sel('status', 'Status', STATUSES) +
      sel('priority', 'Priority', ['High','Medium','Low']) + f('estimated_value', 'Estimated value (₹)', 'number') +
      num('ai_score', 'AI Score (0-100)') + f('next_followup_at', 'Next follow-up', 'datetime-local') +
      '<div class="field"><label class="fld">Assigned To (Sales Person)</label><select class="fld" name="assigned_to"><option value="">Unassigned / Admin</option>' + userOpts + '</select></div>' +
      '<div class="field"><label class="fld">Source</label><input class="fld" name="source"></div>' +
      '<div class="field full"><label class="fld">Notes</label><textarea class="fld" name="notes"></textarea></div>' +
      apolloBtn +
      '</div>';
  }
  function f(n, l, t) { return '<div class="field"><label class="fld">' + l + '</label><input class="fld" name="' + n + '" type="' + (t || 'text') + '"></div>'; }
  function num(n, l) { return '<div class="field"><label class="fld">' + l + '</label><input class="fld" name="' + n + '" type="number" min="0" max="100"></div>'; }
  function sel(n, l, opts) { return '<div class="field"><label class="fld">' + l + '</label><select class="fld" name="' + n + '">' + opts.map(o => '<option>' + SLC.escape(o) + '</option>').join('') + '</select></div>'; }

  function sourceBadge(src) {
    if (!src) return '';
    if (src === 'Apollo CSV') {
      return '<span class="badge" style="background:var(--accent-soft);color:var(--accent);border:1px solid rgba(124,92,255,0.3);font-size:10.5px;padding:2px 7px;">Apollo CSV</span>';
    }
    if (src === 'AI Lead Discovery') {
      return '<span class="badge" style="background:rgba(34,197,94,0.12);color:var(--good);border:1px solid rgba(34,197,94,0.25);font-size:10.5px;padding:2px 7px;">AI Discovery</span>';
    }
    return '<span class="badge badge-gray" style="font-size:10.5px;">' + SLC.escape(src) + '</span>';
  }

  let bulk;
  let currentLeadsList = [];

  async function loadFilterOptions() {
    try {
      const res = await api.get('leads/filter-options');
      const indSel = document.getElementById('leadIndustry');
      const locSel = document.getElementById('leadLocation');
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
    const tbody = document.getElementById('leadRows');
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px">' + SLC.ui.spinner() + '</td></tr>';
    try {
      const params = { page, per_page: 25 };
      if (q) params.q = q;
      const ind = document.getElementById('leadIndustry')?.value;
      const loc = document.getElementById('leadLocation')?.value;
      const st = document.getElementById('leadStatus')?.value;
      const pr = document.getElementById('leadPriority')?.value;
      const src = document.getElementById('leadSource')?.value;
      const assigned = document.getElementById('leadAssignedUser')?.value;
      if (ind) params.industry = ind;
      if (loc) params.location = loc;
      if (st) params.status = st;
      if (pr) params.priority = pr;
      if (src) params.source = src;
      if (assigned) params.assigned_to = assigned;
      const res = await R.list(params);
      currentLeadsList = res.data || [];
      tbody.innerHTML = currentLeadsList.length ? currentLeadsList.map(l => {
        const contactInfo = l.contact_name ? `<div style="font-size:11.5px;color:#10b981;font-weight:600;margin-top:3px;display:inline-flex;align-items:center;gap:5px;flex-wrap:wrap;"><span style="color:#10b981;">👤 ${SLC.escape(l.contact_name)}</span>${l.contact_designation ? '<span style="color:#34d399;font-size:10.5px;background:rgba(16,185,129,0.14);border:1px solid rgba(16,185,129,0.25);padding:1px 6px;border-radius:4px;font-weight:500;">' + SLC.escape(l.contact_designation) + '</span>' : ''}</div>` : '';
        const isApollo = l.source === 'Apollo CSV';
        const apolloAction = isApollo ? `<button class="btn-icon btn-sm" data-apollo-view="${l.id}" title="Inspect 70+ Apollo Fields" style="color:var(--accent);">🔍</button> ` : '';

        return '<tr class="row-link" data-edit="' + l.id + '" style="cursor:pointer;">' +
          '<td class="td-cb" onclick="event.stopPropagation()"><input type="checkbox" class="cb-custom lead-cb" data-id="' + l.id + '"></td>' +
          '<td>' +
            '<div class="strong" style="color:var(--text);">' + SLC.escape(l.company_name || '—') + '</div>' +
            contactInfo +
          '</td>' +
          '<td>' +
            '<div>' + SLC.escape(l.industry || '—') + '</div>' +
            '<div style="margin-top:3px;">' + sourceBadge(l.source) + '</div>' +
          '</td>' +
          '<td>' + SLC.escape(l.location || '—') + '</td>' +
          '<td onclick="event.stopPropagation()"><select class="filter btn-sm" data-status="' + l.id + '">' + STATUSES.map(s => '<option ' + (s === l.status ? 'selected' : '') + '>' + s + '</option>').join('') + '</select></td>' +
          '<td>' + SLC.ui.priorityBadge(l.priority) + '</td>' +
          '<td>' + SLC.ui.scoreBar(l.ai_score) + '</td>' +
          '<td>' + SLC.money(l.estimated_value) + '</td>' +
          '<td>' + SLC.assigneeBadge(l.assigned_user_name, l.assigned_at) + '</td>' +
          '<td>' + (l.next_followup_at ? SLC.dateBadge(l.next_followup_at) : ('<div class="muted" style="font-size:11px;" title="Created at: ' + SLC.escape(SLC.formatDate(l.created_at)) + '">📅 ' + SLC.escape(SLC.formatDate(l.created_at, false)) + ' <span style="font-size:10px;">(' + SLC.escape(SLC.rel(l.created_at)) + ')</span></div>')) + '</td>' +
          '<td style="text-align:right">' + apolloAction + '<button class="btn-icon btn-sm" data-edit="' + l.id + '" title="Edit">✏️</button> <button class="btn-icon btn-sm" data-del="' + l.id + '" title="Delete">🗑️</button></td>' +
          '</tr>';
      }).join('') : '<tr><td colspan="10">' + SLC.ui.empty('No leads yet', '') + '</td></tr>';
      SLC.pagerRender('leadPager', res, page, load, p => { page = p; load(); });
      bulk && bulk.update();
    } catch (e) { SLC.toast(e.message, 'error'); }
  }

  async function openApolloModal(leadId) {
    try {
      const res = await api.get('leads/' + leadId + '/apollo-details');
      const lead = res.lead;
      const data = res.original_apollo_data || {};
      const keys = Object.keys(data);

      const rows = keys.map(k => `
        <div style="display:grid;grid-template-columns:220px 1fr;gap:12px;padding:8px 0;border-bottom:1px solid var(--border);font-size:12.5px;">
          <div style="font-weight:600;color:var(--muted);">${SLC.escape(k)}</div>
          <div style="color:var(--text);word-break:break-all;">${SLC.escape(data[k] !== null && data[k] !== '' ? String(data[k]) : '—')}</div>
        </div>
      `).join('');

      SLC.modal({
        title: `Apollo Lead Intelligence: ${lead.company_name || 'Lead Details'}`,
        body: `
          <div style="margin-bottom:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-weight:700;">${SLC.escape(lead.source || 'Apollo CSV')}</span>
            <span style="font-size:12px;color:var(--muted);">Batch: <code>${SLC.escape(lead.import_batch_id || 'manual')}</code></span>
          </div>
          <div style="max-height:55vh;overflow-y:auto;padding-right:8px;">
            ${rows || '<div class="muted">No raw Apollo data attached.</div>'}
          </div>
        `,
        actions: [{ label: 'Close', primary: true, close: true }]
      });
    } catch (e) {
      SLC.toast('Failed to load Apollo details: ' + e.message, 'error');
    }
  }

  function openModal(lead) {
    SLC.modal({
      title: lead ? 'Edit Lead' : 'New Lead',
      body: fields(lead),
      onOpen: (modalEl) => {
        const apolloBtn = modalEl.querySelector('#modalViewApolloBtn');
        if (apolloBtn && lead && lead.id) {
          apolloBtn.addEventListener('click', () => {
            openApolloModal(lead.id);
          });
        }
      },
      actions: [
        { label: 'Cancel', close: true },
        { label: 'Save', primary: true, onClick: async (m) => {
          const body = SLC.formCollect(m);
          try {
            if (lead) await R.update(lead.id, body);
            else await R.create(body);
            SLC.toast(lead ? 'Lead updated' : 'Lead created', 'success');
            m.close();
            load();
            if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
          } catch (e) {
            SLC.toast(e.message, 'error');
          }
        }}
      ]
    });
    if (lead) SLC.formPopulate(document.querySelector('.modal'), lead);
  }

  document.addEventListener('DOMContentLoaded', async () => {
    bulk = SLC.ui.bindBulkActions({
      selectAllId: 'selectAllLeads',
      bulkBarId: 'leadBulkBar',
      countId: 'leadSelectedCount',
      deleteBtnId: 'leadBulkDeleteBtn',
      clearBtnId: 'leadBulkClearBtn',
      rowSelector: '.lead-cb',
      resource: R,
      entityName: 'leads',
      onDeleted: () => {
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      }
    });

    // Bulk Assign handler
    document.getElementById('leadBulkAssignBtn')?.addEventListener('click', async () => {
      const targetUserId = parseInt(document.getElementById('leadBulkAssignSelect')?.value, 10);
      if (!targetUserId) {
        SLC.toast('Please select a user to assign leads to.', 'error');
        return;
      }
      const selectedIds = bulk ? bulk.getSelected().map(Number) : [];
      if (!selectedIds.length) {
        SLC.toast('No leads selected.', 'error');
        return;
      }
      const targetUserText = document.getElementById('leadBulkAssignSelect')?.options[document.getElementById('leadBulkAssignSelect')?.selectedIndex]?.text || 'User';
      const cleanTargetName = targetUserText.replace(/\s*\([^)]*\)/g, '').trim();
      try {
        const res = await api.post('leads/bulk-assign', {
          ids: selectedIds,
          assigned_to: targetUserId,
          cascade_company_contact: 1,
        });
        SLC.toast(`Assigned ${res.count || selectedIds.length} lead(s) to ${cleanTargetName}!`, 'success');
        
        const assignedItems = (currentLeadsList || []).filter(l => selectedIds.includes(parseInt(l.id, 10))).map(l => ({
          name: l.title || l.company_name,
          company_name: l.company_name,
          contact_name: l.contact_name,
          designation: l.contact_designation,
          phone: l.contact_phone || l.company_phone,
          email: l.contact_email || l.company_email,
          location: l.location,
          industry: l.industry,
          requirement: l.notes || '',
        }));

        if (assignedItems.length && typeof SLC.openWhatsAppShareModal === 'function') {
          SLC.openWhatsAppShareModal({
            assignedToName: cleanTargetName,
            items: assignedItems,
            typeLabel: 'Leads',
          });
        }

        bulk.clear();
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      } catch (e) {
        SLC.toast(e.message, 'error');
      }
    });

    // Dedicated WhatsApp Share button for selected leads
    document.getElementById('leadBulkWaBtn')?.addEventListener('click', () => {
      const selectedIds = bulk ? bulk.getSelected().map(Number) : [];
      if (!selectedIds.length) {
        SLC.toast('Select leads from table first to copy WhatsApp message.', 'warn');
        return;
      }
      const targetUserText = document.getElementById('leadBulkAssignSelect')?.options[document.getElementById('leadBulkAssignSelect')?.selectedIndex]?.text || 'Sales Team';
      const cleanTargetName = targetUserText === 'Assign to...' ? 'Sales Team' : targetUserText.replace(/\s*\([^)]*\)/g, '').trim();
      const selectedItems = (currentLeadsList || []).filter(l => selectedIds.includes(parseInt(l.id, 10))).map(l => ({
        name: l.title || l.company_name,
        company_name: l.company_name,
        contact_name: l.contact_name,
        designation: l.contact_designation,
        phone: l.contact_phone || l.company_phone,
        email: l.contact_email || l.company_email,
        location: l.location,
        industry: l.industry,
        requirement: l.notes || '',
      }));
      if (selectedItems.length && typeof SLC.openWhatsAppShareModal === 'function') {
        SLC.openWhatsAppShareModal({
          assignedToName: cleanTargetName,
          items: selectedItems,
          typeLabel: 'Leads',
        });
      }
    });

    await loadCompanies();
    await loadUsers();
    await loadFilterOptions();
    load();
    document.getElementById('leadSearch')?.addEventListener('input', e => { clearTimeout(debounce); debounce = setTimeout(() => { q = e.target.value.trim(); page = 1; load(); }, 300); });
    ['leadIndustry', 'leadLocation', 'leadStatus', 'leadPriority', 'leadSource', 'leadAssignedUser'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => { page = 1; load(); });
    });
    document.getElementById('addLeadBtn').addEventListener('click', () => openModal(null));
    document.getElementById('leadRows').addEventListener('click', async (e) => {
      const apollo = e.target.closest('[data-apollo-view]');
      if (apollo) {
        e.stopPropagation();
        const id = apollo.getAttribute('data-apollo-view');
        openApolloModal(id);
        return;
      }
      const del = e.target.closest('[data-del]');
      if (del) {
        e.stopPropagation();
        const id = del.getAttribute('data-del');
        if (confirm('Delete this lead?')) {
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
      const edit = e.target.closest('[data-edit]') || e.target.closest('.row-link');
      if (edit) {
        const id = edit.getAttribute('data-edit') || edit.getAttribute('data-id');
        if (id) {
          try {
            const res = await R.get(id);
            if (res && res.lead) {
              openModal(res.lead);
            }
          } catch (er) {
            SLC.toast(er.message, 'error');
          }
        }
      }
    });
    document.getElementById('leadRows').addEventListener('change', async (e) => {
      const sel = e.target.closest('[data-status]');
      if (sel) {
        try {
          await R.update(sel.getAttribute('data-status'), { status: sel.value });
          SLC.toast('Status updated', 'success');
        } catch (er) {
          SLC.toast(er.message, 'error');
          load();
        }
      }
    });
  });
})();
