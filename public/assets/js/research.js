/* research.js — list saved research reports */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const R = api.resource('research-reports');

  let bulk, q = '', debounce = null;

  function copyText(text, btn) {
    if (!navigator.clipboard) {
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    } else {
      navigator.clipboard.writeText(text);
    }
    if (btn) {
      const orig = btn.innerHTML;
      btn.innerHTML = '✓ Copied!';
      setTimeout(() => { btn.innerHTML = orig; }, 2000);
    }
    SLC.toast('Copied to clipboard!', 'success');
  }

  function getLeadBadge(category) {
    const cat = String(category || '').toLowerCase();
    if (cat.includes('hot')) {
      return '<span class="badge" style="background:rgba(239,68,68,0.18);color:#f87171;border:1px solid rgba(239,68,68,0.4);font-weight:800;padding:4px 10px;font-size:11.5px;border-radius:20px;">🔥 HOT LEAD</span>';
    }
    if (cat.includes('warm')) {
      return '<span class="badge" style="background:rgba(245,158,11,0.18);color:#fbbf24;border:1px solid rgba(245,158,11,0.4);font-weight:800;padding:4px 10px;font-size:11.5px;border-radius:20px;">🌤 WARM LEAD</span>';
    }
    if (cat.includes('cold')) {
      return '<span class="badge" style="background:rgba(59,130,246,0.18);color:#60a5fa;border:1px solid rgba(59,130,246,0.4);font-weight:800;padding:4px 10px;font-size:11.5px;border-radius:20px;">❄️ COLD LEAD</span>';
    }
    return '';
  }

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
        const leadCatBadge = getLeadBadge(r.lead_category);
        return (
          '<tr>' +
            '<td class="td-cb" onclick="event.stopPropagation()">' +
              '<input type="checkbox" class="cb-custom report-cb" data-id="' + r.id + '">' +
            '</td>' +
            '<td>' +
              '<div class="strong" style="color:var(--text);font-size:13.5px;display:flex;align-items:center;gap:6px;">' +
                SLC.escape(r.company_name || '—') +
              '</div>' +
              (leadCatBadge ? '<div style="margin-top:3px;">' + leadCatBadge + '</div>' : '') +
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
    const so = SLC.slideover.open({ title: 'AI Sales Intelligence Report', body: SLC.ui.spinner() });
    try {
      const res = await R.get(id);
      const r = res.report;
      let src = []; try { src = JSON.parse(r.sources || '[]'); } catch (e) {}

      // Parse Insights & Outreach
      let insights = [];
      if (Array.isArray(r.key_insights)) insights = r.key_insights;
      else if (typeof r.key_insights === 'string' && r.key_insights) {
        try { insights = JSON.parse(r.key_insights); } catch (e) { insights = [r.key_insights]; }
      }

      let emailData = r.email_outreach;
      if (typeof emailData === 'string' && emailData) {
        try { emailData = JSON.parse(emailData); } catch (e) {}
      }

      let callData = r.cold_call_script;
      if (typeof callData === 'string' && callData) {
        try { callData = JSON.parse(callData); } catch (e) {}
      }

      let insightsHtml = '';
      if (insights.length) {
        insightsHtml = '<div style="background:rgba(124,92,255,0.08);border:1px solid rgba(124,92,255,0.25);border-radius:8px;padding:12px;margin:12px 0;">' +
          '<div style="font-weight:700;font-size:12.5px;color:var(--accent2);margin-bottom:6px;">⚡ Key Strategic Insights</div>' +
          '<ul style="margin:0;padding-left:16px;font-size:12.5px;line-height:1.5;">' +
            insights.map(i => '<li>' + SLC.escape(String(i)) + '</li>').join('') +
          '</ul>' +
        '</div>';
      }

      let outreachHtml = '';
      if (emailData && (emailData.body || emailData.subject_lines)) {
        const subjs = Array.isArray(emailData.subject_lines) ? emailData.subject_lines : (emailData.subject ? [emailData.subject] : []);
        const copyTxt = (subjs.length ? 'Subject: ' + subjs[0] + '\n\n' : '') + (emailData.body || '');
        outreachHtml += '<div style="margin-top:14px;background:var(--panel2);border:1px solid var(--border);border-radius:8px;padding:12px;">' +
          '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">' +
            '<strong style="font-size:13px;">📧 Email Outreach Pitch</strong>' +
            '<button class="btn-secondary btn-sm copy-btn" data-copy="' + SLC.escape(copyTxt) + '">📋 Copy</button>' +
          '</div>' +
          (subjs.length ? '<div class="muted" style="font-size:11.5px;margin-bottom:6px;"><strong>Subject:</strong> ' + SLC.escape(subjs[0]) + '</div>' : '') +
          '<div style="white-space:pre-line;font-size:12.5px;line-height:1.5;background:var(--panel);padding:10px;border-radius:6px;">' + SLC.escape(emailData.body || '') + '</div>' +
        '</div>';
      }

      if (r.whatsapp_message) {
        outreachHtml += '<div style="margin-top:12px;background:var(--panel2);border:1px solid var(--border);border-radius:8px;padding:12px;">' +
          '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">' +
            '<strong style="font-size:13px;">💬 WhatsApp Pitch</strong>' +
            '<button class="btn-secondary btn-sm copy-btn" data-copy="' + SLC.escape(r.whatsapp_message) + '">📋 Copy</button>' +
          '</div>' +
          '<div style="white-space:pre-line;font-size:12.5px;line-height:1.5;background:#0b201a;color:#86efac;padding:10px;border-radius:6px;">' + SLC.escape(r.whatsapp_message) + '</div>' +
        '</div>';
      }

      const bodyEl = so.el.querySelector('.slideover-body');
      bodyEl.innerHTML =
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">' +
          '<div class="section-title" style="margin:0;">' + SLC.escape(r.industry || 'Manufacturing') + '</div>' +
          (r.lead_category ? getLeadBadge(r.lead_category) : '') +
        '</div>' +
        (r.lead_category_reasoning ? '<div style="background:var(--panel2);border-left:3px solid var(--accent);padding:8px 12px;font-size:12px;border-radius:4px;margin-bottom:12px;"><strong>Qualification:</strong> ' + SLC.escape(r.lead_category_reasoning) + '</div>' : '') +
        insightsHtml +
        (r.recommended_service ? '<div class="detail-row"><span class="k">Recommended Pitch</span><span class="v" style="font-weight:700;color:var(--accent2);">' + SLC.escape(r.recommended_service) + '</span></div>' : '') +
        (r.pitch_strategy ? SLC.ui.field('Pitch Strategy', r.pitch_strategy) : '') +
        SLC.ui.field('Company Overview', r.overview) +
        SLC.ui.field('Packaged Products', r.products) +
        SLC.ui.field('Locations', r.locations) +
        SLC.ui.field('Label Requirements', r.label_requirements) +
        SLC.ui.field('Suggested Department', r.suggested_department) +
        SLC.ui.field('Decision Maker', r.decision_maker) +
        SLC.ui.field('Why Shree Label Creation', r.why_relevant) +
        '<div class="detail-row"><span class="k">Confidence</span><span class="v">' + SLC.ui.scoreBar(r.confidence_score) + '</span></div>' +
        (outreachHtml ? '<div class="section-title" style="margin-top:16px;">🚀 Sales Outreach Kit</div>' + outreachHtml : '') +
        '<div class="section-title" style="margin-top:16px">Sources (' + src.length + ')</div>' + SLC.ui.sources(src) +
        '<div class="muted" style="margin-top:12px;font-size:11px">Model: ' + SLC.escape(r.model || '—') + '</div>';

      bodyEl.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', () => copyText(btn.getAttribute('data-copy'), btn));
      });
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
