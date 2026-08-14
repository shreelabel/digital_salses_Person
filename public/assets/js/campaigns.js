/* campaigns.js — CRUD + activate/pause + lead sequence builder. Drafts only. */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const R = api.resource('campaigns');
  const Leads = api.resource('leads');
  let q = '';

  function fields(c) {
    c = c || {};
    return '<div class="form-grid">' +
      '<div class="field full"><label class="fld">Name *</label><input class="fld" name="name" value="' + SLC.escape(c.name || '') + '"></div>' +
      '<div class="field full"><label class="fld">Objective</label><input class="fld" name="objective" value="' + SLC.escape(c.objective || '') + '"></div>' +
      f('audience_industry', 'Audience industry', c.audience_industry) +
      f('audience_location', 'Audience location', c.audience_location) +
      f('start_date', 'Start date', (c.start_date || ''), 'date') +
      f('end_date', 'End date', (c.end_date || ''), 'date') +
      '<div class="field full"><label class="fld">Description</label><textarea class="fld" name="description">' + SLC.escape(c.description || '') + '</textarea></div>' +
      '</div>';
  }
  function f(n, l, v, t) { return '<div class="field"><label class="fld">' + l + '</label><input class="fld" name="' + n + '" type="' + (t || 'text') + '" value="' + SLC.escape(v || '') + '"></div>'; }

  let bulk;

  let status = '';

  async function load() {
    const tbody = document.getElementById('campRows');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px">' + SLC.ui.spinner() + '</td></tr>';
    try {
      const params = { per_page: 50 };
      if (q) params.q = q;
      if (status) params.status = status;
      const res = await R.list(params);
      const items = res.data || [];
      tbody.innerHTML = items.length ? items.map(c => {
        const periodStr = (c.start_date || c.end_date)
          ? (c.start_date || 'N/A') + ' → ' + (c.end_date || 'N/A')
          : '<span class="muted">—</span>';

        const audParts = [c.audience_industry, c.audience_location].filter(Boolean);
        const audienceHtml = audParts.length
          ? audParts.map(x => '<span class="badge badge-gray" style="font-size:11px;margin-right:4px;">' + SLC.escape(x) + '</span>').join('')
          : '<span class="muted">—</span>';

        return (
          '<tr>' +
            '<td class="td-cb" onclick="event.stopPropagation()">' +
              '<input type="checkbox" class="cb-custom camp-cb" data-id="' + c.id + '">' +
            '</td>' +
            '<td>' +
              '<div class="strong" style="font-size:13.5px;color:var(--text);">' + SLC.escape(c.name) + '</div>' +
              (c.description ? '<div style="font-size:11.5px;color:var(--muted);margin-top:2px;">' + SLC.escape(c.description) + '</div>' : '') +
            '</td>' +
            '<td><span style="font-size:12.5px;color:var(--text);">' + SLC.escape(c.objective || '—') + '</span></td>' +
            '<td>' + audienceHtml + '</td>' +
            '<td>' +
              '<span class="pill" style="background:var(--panel2);border:1px solid var(--border);padding:2px 8px;border-radius:12px;font-size:12px;font-weight:600;">👥 ' + (c.lead_count ?? 0) + '</span>' +
            '</td>' +
            '<td>' + SLC.ui.statusPill(c.status) + '</td>' +
            '<td style="font-size:12px;">' + periodStr + '</td>' +
            '<td style="text-align:right;white-space:nowrap">' +
              (c.status === 'Active'
                ? '<button class="btn-ghost btn-sm" data-pause="' + c.id + '" style="font-size:11.5px;padding:3px 8px;">Pause</button>'
                : '<button class="btn-secondary btn-sm" data-activate="' + c.id + '" style="font-size:11.5px;padding:3px 8px;">Activate</button>') + ' ' +
              '<button class="btn-icon btn-sm" data-leads="' + c.id + '" title="Manage leads">👥</button> ' +
              '<button class="btn-icon btn-sm" data-edit="' + c.id + '" title="Edit">✏️</button> ' +
              '<button class="btn-icon btn-sm" data-del="' + c.id + '" title="Delete">🗑️</button>' +
            '</td>' +
          '</tr>'
        );
      }).join('') : '<tr><td colspan="8">' + SLC.ui.empty('No campaigns yet', 'Click "+ New Campaign" to create your first sequence draft.') + '</td></tr>';

      bulk && bulk.update();
    } catch (e) { SLC.toast(e.message, 'error'); }
  }

  function openModal(camp) {
    const m = SLC.modal.open({ title: camp ? 'Edit Campaign' : 'New Campaign', body: fields(camp), footer: '<button class="btn-ghost" data-close>Cancel</button><button class="btn-primary" data-save>Save</button>' });
    m.el.querySelector('[data-save]').addEventListener('click', async () => {
      const data = {}; m.el.querySelectorAll('[name]').forEach(el => data[el.name] = el.value);
      if (!data.name) { SLC.toast('Name required', 'error'); return; }
      if (!camp) data.status = 'Draft';
      try { if (camp) await R.update(camp.id, data); else await R.create(data); SLC.toast('Saved', 'success'); m.close(); load(); } catch (e) { SLC.toast(e.message, 'error'); }
    });
  }

  async function leadBuilder(id) {
    const m = SLC.modal.open({ title: 'Campaign Sequence Builder', body: '<p class="muted">Select leads to add to this campaign. This builds a draft sequence — no email is sent.</p><div id="leadPick" style="margin-top:12px">' + SLC.ui.spinner() + '</div>', footer: '<button class="btn-ghost" data-close>Cancel</button><button class="btn-primary" data-add>Add Selected</button>', size: 'lg' });
    try {
      const res = await Leads.list({ per_page: 200 });
      const detail = await R.get(id);
      const existing = new Set((detail.campaign && detail.campaign.leads || []).map(x => x.lead_id));
      m.el.querySelector('#leadPick').innerHTML = '<div style="max-height:340px;overflow:auto;border:1px solid var(--border);border-radius:10px">' +
        (res.data || []).map(l => '<label class="checkbox-row" style="padding:9px 12px;border-bottom:1px solid var(--border);display:flex"><input type="checkbox" value="' + l.id + '" ' + (existing.has(l.id) ? 'checked disabled' : '') + ' style="margin-right:8px"><span>' + SLC.escape(l.company_name || 'Lead') + ' · ' + SLC.ui.statusBadge(l.status) + '</span></label>').join('') + '</div>';
    } catch (e) { m.el.querySelector('#leadPick').innerHTML = SLC.ui.empty('Failed to load leads', ''); }
    m.el.querySelector('[data-add]').addEventListener('click', async () => {
      const ids = Array.from(m.el.querySelectorAll('#leadPick input:checked:not(:disabled)')).map(c => parseInt(c.value, 10));
      if (!ids.length) { SLC.toast('No new leads selected', 'error'); return; }
      try { const r = await api.post('campaigns/' + id + '/leads', { lead_ids: ids }); SLC.toast(r.added + ' lead(s) added', 'success'); m.close(); load(); } catch (e) { SLC.toast(e.message, 'error'); }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bulk = SLC.ui.bindBulkActions({
      selectAllId: 'selectAllCampaigns',
      bulkBarId: 'campBulkBar',
      countId: 'campSelectedCount',
      deleteBtnId: 'campBulkDeleteBtn',
      clearBtnId: 'campBulkClearBtn',
      rowSelector: '.camp-cb',
      resource: R,
      entityName: 'campaigns',
      onDeleted: () => {
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      }
    });

    load();
    let d;
    document.getElementById('campSearch')?.addEventListener('input', e => {
      clearTimeout(d);
      d = setTimeout(() => { q = e.target.value.trim(); load(); }, 300);
    });

    document.getElementById('campStatusFilter')?.addEventListener('change', e => {
      status = e.target.value;
      load();
    });

    document.getElementById('addCampBtn')?.addEventListener('click', () => openModal(null));

    document.getElementById('campRows')?.addEventListener('click', async (e) => {
      if (e.target.closest('[data-del]')) {
        if (confirm('Delete campaign?')) {
          try {
            await R.remove(e.target.closest('[data-del]').getAttribute('data-del'));
            SLC.toast('Deleted', 'success');
            load();
            if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
          } catch (er) { SLC.toast(er.message, 'error'); }
        }
        return;
      }
      if (e.target.closest('[data-edit]')) { try { const res = await R.get(e.target.closest('[data-edit]').getAttribute('data-edit')); openModal(res.campaign); } catch (er) { SLC.toast(er.message, 'error'); } return; }
      if (e.target.closest('[data-activate]')) { try { await api.post('campaigns/' + e.target.closest('[data-activate]').getAttribute('data-activate') + '/activate'); SLC.toast('Activated', 'success'); load(); } catch (er) { SLC.toast(er.message, 'error'); } return; }
      if (e.target.closest('[data-pause]')) { try { await api.post('campaigns/' + e.target.closest('[data-pause]').getAttribute('data-pause') + '/pause'); SLC.toast('Paused', 'success'); load(); } catch (er) { SLC.toast(er.message, 'error'); } return; }
      if (e.target.closest('[data-leads]')) { leadBuilder(e.target.closest('[data-leads]').getAttribute('data-leads')); }
    });
  });
})();
