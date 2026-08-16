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

      function renderIntelCard(title, val, icon, accentColor) {
        if (!val) return '';
        const isMultiLine = String(val).includes('\n');
        const content = isMultiLine 
          ? '<div style="white-space:pre-line;line-height:1.65;color:var(--text);font-size:13px;">' + SLC.escape(String(val)) + '</div>'
          : '<div style="line-height:1.65;color:var(--text);font-size:13px;">' + SLC.escape(String(val)) + '</div>';
        
        return '<div style="background:var(--panel2);border:1px solid var(--border);border-radius:8px;padding:12px 14px;margin-bottom:10px;">' +
          '<div style="display:flex;align-items:center;gap:6px;font-weight:700;font-size:12.5px;color:' + (accentColor || 'var(--accent2)') + ';margin-bottom:6px;">' +
            '<span>' + (icon || '📌') + '</span> ' + SLC.escape(title) +
          '</div>' +
          content +
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
        (r.recommended_service ? '<div style="background:rgba(124,92,255,0.08);border:1px solid rgba(124,92,255,0.25);border-radius:8px;padding:10px 14px;margin-bottom:12px;"><div style="font-size:11px;font-weight:700;color:var(--accent);text-transform:uppercase;">Recommended Service</div><div style="font-weight:700;font-size:13.5px;color:var(--accent2);margin-top:2px;">' + SLC.escape(r.recommended_service) + '</div></div>' : '') +
        (r.pitch_strategy ? renderIntelCard('Pitch Strategy', r.pitch_strategy, '🎯', 'var(--accent)') : '') +
        '<div style="font-weight:800;font-size:13.5px;color:var(--text);margin-top:16px;margin-bottom:10px;border-bottom:1px solid var(--border);padding-bottom:6px;">🏭 Packaging Intelligence</div>' +
        renderIntelCard('Company Overview', r.overview, '📋', '#818cf8') +
        renderIntelCard('Packaged Products', r.products, '📦', '#fbbf24') +
        renderIntelCard('Likely Label Requirements', r.label_requirements, '🏷️', '#34d399') +
        ((r.suggested_department || r.decision_maker) ? 
          '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">' +
            (r.suggested_department ? '<div style="background:var(--panel2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;"><div style="font-size:11px;font-weight:700;color:#60a5fa;margin-bottom:3px;">🏢 Target Dept.</div><div style="font-size:12.5px;font-weight:600;">' + SLC.escape(r.suggested_department) + '</div></div>' : '') +
            (r.decision_maker ? '<div style="background:var(--panel2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;"><div style="font-size:11px;font-weight:700;color:#f472b6;margin-bottom:3px;">👤 Decision Maker</div><div style="font-size:12.5px;font-weight:600;">' + SLC.escape(r.decision_maker) + '</div></div>' : '') +
          '</div>' : '') +
        renderIntelCard('Why Shree Label Creation Fits', r.why_relevant, '✨', '#c084fc') +
        '<div class="detail-row" style="margin-top:14px;"><span class="k">Confidence</span><span class="v">' + SLC.ui.scoreBar(r.confidence_score) + '</span></div>' +
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
