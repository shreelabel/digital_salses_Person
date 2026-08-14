/* opportunities.js */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const R = api.resource('opportunities');
  const Companies = api.resource('companies');
  let q = '', companies = [];
  const STAGES = ['Prospecting','Qualification','Proposal','Negotiation','Closing','Won','Lost'];

  async function loadCompanies() { try { const r = await Companies.list({ per_page: 300 }); companies = r.data || []; } catch (e) {} }

  function fields(o) {
    o = o || {};
    return '<div class="form-grid">' +
      '<div class="field full"><label class="fld">Company *</label><select class="fld" name="company_id" required><option value="">Select company…</option>' + companies.map(c => '<option value="' + c.id + '" ' + (o.company_id == c.id ? 'selected' : '') + '>' + SLC.escape(c.name) + '</option>').join('') + '</select></div>' +
      '<div class="field full"><label class="fld">Title *</label><input class="fld" name="title" value="' + SLC.escape(o.title || '') + '"></div>' +
      '<div class="field"><label class="fld">Amount (₹)</label><input class="fld" name="amount" type="number" value="' + SLC.escape(o.amount ?? '') + '"></div>' +
      '<div class="field"><label class="fld">Probability (%)</label><input class="fld" name="probability" type="number" min="0" max="100" value="' + SLC.escape(o.probability ?? '10') + '"></div>' +
      '<div class="field"><label class="fld">Stage</label><select class="fld" name="stage">' + STAGES.map(s => '<option ' + (s === o.stage ? 'selected' : '') + '>' + s + '</option>').join('') + '</select></div>' +
      '<div class="field"><label class="fld">Expected close</label><input class="fld" name="expected_close_date" type="date" value="' + SLC.escape(o.expected_close_date || '') + '"></div>' +
      '<div class="field full"><label class="fld">Notes</label><textarea class="fld" name="notes">' + SLC.escape(o.notes || '') + '</textarea></div>' +
      '</div>';
  }

  let bulk;

  async function load() {
    const tbody = document.getElementById('oppRows');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px">' + SLC.ui.spinner() + '</td></tr>';
    try {
      const params = { per_page: 50 };
      if (q) params.q = q;
      const st = document.getElementById('oppStage').value; if (st) params.stage = st;
      const res = await R.list(params);
      const rows = res.data || [];
      const openV = rows.filter(o => !['Won','Lost'].includes(o.stage)).reduce((s,o) => s + parseFloat(o.amount || 0), 0);
      const wonV = rows.filter(o => o.stage === 'Won').reduce((s,o) => s + parseFloat(o.amount || 0), 0);
      document.getElementById('oppSummary').innerHTML =
        kpiCard('Open Value', SLC.money(openV), 'accent2') + kpiCard('Won Value', SLC.money(wonV), 'good') + kpiCard('Open Deals', rows.filter(o => !['Won','Lost'].includes(o.stage)).length, 'high') + kpiCard('Total', rows.length, 'warn');
      tbody.innerHTML = rows.length ? rows.map(o =>
        '<tr>' +
        '<td class="td-cb" onclick="event.stopPropagation()"><input type="checkbox" class="cb-custom opp-cb" data-id="' + o.id + '"></td>' +
        '<td class="strong">' + SLC.escape(o.title) + '</td>' +
        '<td>' + SLC.escape(o.company_name || '—') + '</td>' +
        '<td>' + SLC.ui.statusBadge(o.stage === 'Prospecting' ? 'New' : o.stage === 'Won' ? 'Won' : o.stage === 'Lost' ? 'Lost' : 'Contacted') + '</td>' +
        '<td>' + SLC.money(o.amount) + '</td>' +
        '<td>' + (o.probability ?? 0) + '%</td>' +
        '<td class="muted">' + SLC.escape(o.expected_close_date || '—') + '</td>' +
        '<td style="text-align:right"><button class="btn-icon btn-sm" data-edit="' + o.id + '">✏️</button> <button class="btn-icon btn-sm" data-del="' + o.id + '">🗑️</button></td>' +
        '</tr>'
      ).join('') : '<tr><td colspan="8">' + SLC.ui.empty('No opportunities yet', '') + '</td></tr>';
      bulk && bulk.update();
    } catch (e) { SLC.toast(e.message, 'error'); }
  }
  function kpiCard(label, val, cls) { return '<div class="kpi ' + cls + '"><div class="kpi-label">' + label + '</div><div class="kpi-val" style="font-size:22px;margin-top:6px">' + val + '</div></div>'; }

  function openModal(o) {
    const m = SLC.modal.open({ title: o ? 'Edit Opportunity' : 'Add Opportunity', body: fields(o), footer: '<button class="btn-ghost" data-close>Cancel</button><button class="btn-primary" data-save>Save</button>' });
    m.el.querySelector('[data-save]').addEventListener('click', async () => {
      const data = {}; m.el.querySelectorAll('[name]').forEach(el => data[el.name] = el.value);
      if (!data.company_id || !data.title) { SLC.toast('Company and title required', 'error'); return; }
      try { if (o) await R.update(o.id, data); else await R.create(data); SLC.toast('Saved', 'success'); m.close(); load(); } catch (e) { SLC.toast(e.message, 'error'); }
    });
  }

  document.addEventListener('DOMContentLoaded', async function () {
    bulk = SLC.ui.bindBulkActions({
      selectAllId: 'selectAllOpps',
      bulkBarId: 'oppBulkBar',
      countId: 'oppSelectedCount',
      deleteBtnId: 'oppBulkDeleteBtn',
      clearBtnId: 'oppBulkClearBtn',
      rowSelector: '.opp-cb',
      resource: R,
      entityName: 'opportunities',
      onDeleted: () => {
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      }
    });

    await loadCompanies(); load();
    let d; document.getElementById('oppSearch').addEventListener('input', e => { clearTimeout(d); d = setTimeout(() => { q = e.target.value.trim(); load(); }, 300); });
    document.getElementById('oppStage').addEventListener('change', load);
    document.getElementById('addOppBtn').addEventListener('click', () => openModal(null));
    document.getElementById('oppRows').addEventListener('click', async (e) => {
      const del = e.target.closest('[data-del]'), edit = e.target.closest('[data-edit]');
      if (del) { if (confirm('Delete this opportunity?')) { try { await R.remove(del.getAttribute('data-del')); SLC.toast('Deleted', 'success'); load(); } catch (er) { SLC.toast(er.message, 'error'); } } return; }
      if (edit) { try { const res = await R.get(edit.getAttribute('data-edit')); openModal(res.opportunity); } catch (er) { SLC.toast(er.message, 'error'); } }
    });
  });
})();
