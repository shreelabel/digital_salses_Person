/* research.js — list saved research reports */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const R = api.resource('research-reports');

  let bulk, q = '', debounce = null;

  async function load() {
    const tbody = document.getElementById('reportRows');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px">' + SLC.ui.spinner() + '</td></tr>';
    try {
      const params = { per_page: 50 };
      if (q) params.q = q;
      const res = await R.list(params);
      const rows = res.data || [];
      tbody.innerHTML = rows.length ? rows.map(r => {
        let src = [];
        try { src = JSON.parse(r.sources || '[]'); } catch (e) {}
        return (
          '<tr>' +
            '<td class="td-cb" onclick="event.stopPropagation()">' +
              '<input type="checkbox" class="cb-custom report-cb" data-id="' + r.id + '">' +
            '</td>' +
            '<td>' +
              '<div class="strong" style="color:var(--text);font-size:13.5px;">' + SLC.escape(r.company_name || '—') + '</div>' +
            '</td>' +
            '<td><span class="badge badge-gray">' + SLC.escape(r.industry || '—') + '</span></td>' +
            '<td>' + SLC.ui.scoreBar(r.confidence_score) + '</td>' +
            '<td>' +
              '<span class="pill" style="background:var(--panel2);border:1px solid var(--border);padding:2px 8px;border-radius:12px;font-size:11.5px;">🔗 ' + src.length + ' source' + (src.length === 1 ? '' : 's') + '</span>' +
            '</td>' +
            '<td class="muted" style="font-size:12px;">' + SLC.rel(r.created_at) + '</td>' +
            '<td style="text-align:right;white-space:nowrap;">' +
              '<button class="btn-icon btn-sm" data-view="' + r.id + '" title="View Report">👁️</button> ' +
              '<button class="btn-icon btn-sm" data-del="' + r.id + '" title="Delete">🗑️</button>' +
            '</td>' +
          '</tr>'
        );
      }).join('') : '<tr><td colspan="7">' + SLC.ui.empty('No reports found', 'Run AI Research on any company to generate structured intelligence.') + '</td></tr>';

      bulk && bulk.update();
    } catch (e) { SLC.toast(e.message, 'error'); }
  }

  async function view(id) {
    const so = SLC.slideover.open({ title: 'Research Report', body: SLC.ui.spinner() });
    try {
      const res = await R.get(id);
      const r = res.report;
      let src = []; try { src = JSON.parse(r.sources || '[]'); } catch (e) {}
      so.el.querySelector('.slideover-body').innerHTML =
        '<div class="section-title">' + SLC.escape(r.industry || '') + '</div>' +
        SLC.ui.field('Overview', r.overview) +
        SLC.ui.field('Products', r.products) +
        SLC.ui.field('Locations', r.locations) +
        SLC.ui.field('Relevance', r.relevance) +
        SLC.ui.field('Label Requirements', r.label_requirements) +
        SLC.ui.field('Suggested Department', r.suggested_department) +
        SLC.ui.field('Outreach Angle', r.outreach_angle) +
        SLC.ui.field('Why Shree Label Creation', r.why_relevant) +
        SLC.ui.field('Decision Maker', r.decision_maker) +
        '<div class="detail-row"><span class="k">Confidence</span><span class="v">' + SLC.ui.scoreBar(r.confidence_score) + '</span></div>' +
        '<div class="section-title" style="margin-top:14px">Sources (' + src.length + ')</div>' + SLC.ui.sources(src) +
        '<div class="muted" style="margin-top:12px;font-size:11px">Model: ' + SLC.escape(r.model || '—') + '</div>';
    } catch (e) { so.el.querySelector('.slideover-body').innerHTML = SLC.ui.empty('Failed to load', ''); }
  }

  document.addEventListener('DOMContentLoaded', function () {
    bulk = SLC.ui.bindBulkActions({
      selectAllId: 'selectAllReports',
      bulkBarId: 'reportBulkBar',
      countId: 'reportSelectedCount',
      deleteBtnId: 'reportBulkDeleteBtn',
      clearBtnId: 'reportBulkClearBtn',
      rowSelector: '.report-cb',
      resource: R,
      entityName: 'reports',
      onDeleted: () => {
        load();
        if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
      }
    });

    load();

    document.getElementById('reportSearch')?.addEventListener('input', e => {
      clearTimeout(debounce);
      debounce = setTimeout(() => { q = e.target.value.trim(); load(); }, 300);
    });

    document.getElementById('reportRows')?.addEventListener('click', async (e) => {
      const del = e.target.closest('[data-del]');
      if (del) {
        if (confirm('Delete this report?')) {
          try {
            await R.remove(del.getAttribute('data-del'));
            SLC.toast('Deleted', 'success');
            load();
            if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
          } catch (er) { SLC.toast(er.message, 'error'); }
        }
        return;
      }
      const v = e.target.closest('[data-view]'); if (v) view(v.getAttribute('data-view'));
    });
  });
})();
