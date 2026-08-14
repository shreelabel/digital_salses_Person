/* ai-research.js */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const Companies = api.resource('companies');

  async function loadCompanies() {
    try {
      const res = await Companies.list({ per_page: 500 });
      const sel = document.getElementById('resCompany');
      (res.data || []).forEach(c => sel.appendChild(new Option(c.name + (c.city ? ' — ' + c.city : ''), c.id)));
    } catch (e) { SLC.toast('Failed to load companies', 'error'); }
  }

  async function run() {
    const id = document.getElementById('resCompany').value;
    if (!id) { SLC.toast('Select a company', 'error'); return; }
    if (!SLC.aiConfigured()) { SLC.toast('Gemini not configured', 'error'); return; }
    const btn = document.getElementById('resRun'); const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = SLC.ui.spinner() + ' Researching…';
    const out = document.getElementById('resOutput');
    out.innerHTML = '<div class="card"><div class="empty">' + SLC.ui.spinner() + '<div style="margin-top:8px">Searching the web and analysing…</div></div></div>';
    try {
      const res = await api.post('ai/research', { company_id: id });
      const r = res.report || {};
      const src = r.sources || [];
      out.innerHTML = '<div class="card">' +
        '<div class="card-h"><h3>' + SLC.escape(document.getElementById('resCompany').selectedOptions[0].text) + '</h3>' +
        '<span class="badge badge-purple">Confidence ' + (r.confidence_score ?? '—') + '</span></div>' +
        fld('Overview', r.overview) + fld('Industry', r.industry) + fld('Products', r.products) +
        fld('Locations', r.locations) + fld('Relevance', r.relevance) + fld('Likely Label Requirements', r.label_requirements) +
        fld('Suggested Department', r.suggested_department) + fld('Outreach Angle', r.outreach_angle) +
        fld('Why Shree Label Creation', r.why_relevant) + fld('Decision Maker (if verified)', r.decision_maker) +
        '<div class="section-title" style="margin-top:14px">Sources</div>' + SLC.ui.sources(src) +
        '<div class="muted" style="margin-top:12px;font-size:11px">Model: ' + SLC.escape(r.model || '—') + ' · ' + (res.elapsed_ms || 0) + 'ms</div>' +
        '<div style="margin-top:14px"><a class="btn-secondary btn-sm" href="' + (SLC.base || '') + '/research-reports">View all reports →</a></div>' +
        '</div>';
      SLC.toast('Research saved', 'success');
    } catch (e) { out.innerHTML = '<div class="card">' + SLC.ui.empty('Research failed', e.message) + '</div>'; SLC.toast(e.message, 'error'); }
    finally { btn.disabled = false; btn.innerHTML = orig; }
  }
  function fld(l, v) { return '<div class="detail-row"><span class="k">' + SLC.escape(l) + '</span><span class="v" style="max-width:100%">' + (v ? SLC.escape(v) : '<span class="muted">—</span>') + '</span></div>'; }

  document.addEventListener('DOMContentLoaded', function () {
    loadCompanies();
    document.getElementById('resRun').addEventListener('click', run);
  });
})();
