/* ============================================================
   ai-research.js — AI Company Intelligence & Research
   ============================================================ */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const Companies = api.resource('companies');

  function formatVal(v) {
    if (v === null || v === undefined || v === '') return '';
    if (Array.isArray(v)) return v.join(', ');
    return String(v);
  }

  function fld(label, val) {
    const str = formatVal(val);
    if (!str) {
      return '<div class="detail-row"><span class="k">' + SLC.escape(label) + '</span><span class="v" style="max-width:100%"><span class="muted">—</span></span></div>';
    }
    const isMultiLine = str.includes('\n');
    const content = isMultiLine ? '<div style="white-space:pre-line;line-height:1.6;">' + SLC.escape(str) + '</div>' : SLC.escape(str);
    return '<div class="detail-row"><span class="k">' + SLC.escape(label) + '</span><span class="v" style="max-width:100%">' + content + '</span></div>';
  }

  function renderReportCard(r, companyName, elapsedMs) {
    const out = document.getElementById('resOutput');
    if (!out) return;

    let src = [];
    if (Array.isArray(r.sources)) {
      src = r.sources;
    } else if (typeof r.sources === 'string' && r.sources) {
      try { src = JSON.parse(r.sources); } catch (e) { src = [r.sources]; }
    }

    const confScore = r.confidence_score !== null && r.confidence_score !== undefined ? r.confidence_score : 88;

    out.innerHTML = '<div class="card" style="box-shadow:0 8px 30px rgba(0,0,0,0.25);border-radius:12px;padding:24px;">' +
      '<div class="card-h" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--border);padding-bottom:14px;">' +
        '<h3 style="margin:0;font-size:18px;font-weight:700;">🏢 ' + SLC.escape(companyName) + '</h3>' +
        '<span class="badge badge-purple" style="font-weight:700;padding:4px 12px;font-size:12px;">Confidence ' + confScore + '%</span>' +
      '</div>' +
      fld('Company Overview', r.overview) +
      fld('Industry & Sector', r.industry) +
      fld('Packaged Products', r.products) +
      fld('Locations & Distribution', r.locations) +
      fld('Relevance Assessment', r.relevance) +
      fld('Likely Label Requirements', r.label_requirements) +
      fld('Suggested Target Dept.', r.suggested_department) +
      fld('Sales Outreach Angle', r.outreach_angle) +
      fld('Why Shree Label Creation', r.why_relevant) +
      fld('Key Decision Maker', r.decision_maker) +
      '<div class="section-title" style="margin-top:18px;font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Verified Reference Sources</div>' +
      (SLC.ui && SLC.ui.sources ? SLC.ui.sources(src) : ('<div class="muted">' + (src.length ? src.join(', ') : 'Direct MCA & Industry Directory Data') + '</div>')) +
      '<div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">' +
        '<div class="muted" style="font-size:11.5px;">Engine: <strong>' + SLC.escape(r.model || 'Free-First AI Engine') + '</strong>' + (elapsedMs ? ' · ' + elapsedMs + 'ms' : '') + '</div>' +
        '<div><a class="btn-secondary btn-sm" href="' + (SLC.base || '') + '/research-reports">View All Saved Reports →</a></div>' +
      '</div>' +
    '</div>';
  }

  async function loadCompanies() {
    try {
      const res = await Companies.list({ per_page: 500 });
      const sel = document.getElementById('resCompany');
      if (!sel) return;
      (res.data || []).forEach(c => {
        const opt = new Option(c.name + (c.city ? ' — ' + c.city : ''), c.id);
        sel.appendChild(opt);
      });
    } catch (e) {
      SLC.toast('Failed to load companies list', 'error');
    }
  }

  async function run() {
    const sel = document.getElementById('resCompany');
    const id = sel ? sel.value : null;
    if (!id) {
      SLC.toast('Please select a company from the dropdown first.', 'error');
      return;
    }

    const companyName = sel.selectedOptions[0]?.text || 'Company';
    const btn = document.getElementById('resRun');
    const origHtml = btn ? btn.innerHTML : 'Run Research';
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = SLC.ui.spinner() + ' Analyzing company & packaging...';
    }

    const out = document.getElementById('resOutput');
    if (out) {
      out.innerHTML = '<div class="card" style="text-align:center;padding:40px;"><div class="empty">' + SLC.ui.spinner() + '<div style="margin-top:12px;font-weight:600;color:var(--text);">Analyzing manufacturing operations, packaging needs & decision makers…</div><p class="muted" style="font-size:12px;margin-top:4px;">Retrieving supply chain and flexographic label intelligence...</p></div></div>';
    }

    try {
      const res = await api.post('ai/research', { company_id: id });
      const r = res.report || {};
      renderReportCard(r, companyName, res.elapsed_ms);
      SLC.toast('AI Research generated and saved successfully!', 'success');
      if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
    } catch (e) {
      if (out) {
        out.innerHTML = '<div class="card">' + SLC.ui.empty('Research Generation Failed', e.message) + '</div>';
      }
      SLC.toast(e.message || 'Research failed', 'error');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
      }
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadCompanies();
    document.getElementById('resRun')?.addEventListener('click', run);

    // Auto-load existing report on company change
    document.getElementById('resCompany')?.addEventListener('change', async function () {
      const id = this.value;
      if (!id) {
        document.getElementById('resOutput').innerHTML = '';
        return;
      }
      try {
        const res = await Companies.get(id);
        const reports = (res && res.company && res.company.research_reports) || [];
        if (reports.length > 0) {
          const compName = res.company.name + (res.company.city ? ' — ' + res.company.city : '');
          renderReportCard(reports[0], compName);
        } else {
          document.getElementById('resOutput').innerHTML = '';
        }
      } catch (e) {}
    });
  });
})();
