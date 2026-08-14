/* dashboard.js — KPIs, pipeline, follow-ups, top companies, activity */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;

  async function loadStats() {
    try {
      const res = await api.get('dashboard/stats');
      const s = (res && res.stats) || {};
      const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
      set('kpi-total_prospects', s.total_prospects ?? 0);
      set('kpi-new_leads', s.new_leads ?? 0);
      set('kpi-high_potential', s.high_potential ?? 0);
      set('kpi-followups_due', s.followups_due ?? 0);
      set('kpi-interested', s.interested ?? 0);
      set('kpi-open_opportunities', s.open_opportunities ?? 0);
      set('kpi-email_drafts', s.email_drafts ?? 0);
      const el = document.getElementById('kpi-open_pipeline_value');
      if (el) el.textContent = SLC.money(s.open_pipeline_value);
    } catch (e) { SLC.toast('Failed to load stats', 'error'); }
  }

  async function loadPipeline() {
    const wrap = document.getElementById('pipelineBars');
    const fu = document.getElementById('upcomingFollowups');
    const tc = document.getElementById('topCompanies');
    try {
      const res = await api.get('dashboard/pipeline');
      const p = (res && res.pipeline) || {};
      const stages = ['New','Contacted','Interested','Requirement','Quotation','Negotiation','Won','Lost'];
      const max = Math.max(1, ...stages.map(s => p[s] || 0));
      if (wrap) {
        wrap.innerHTML = stages.map(s => {
          const v = p[s] || 0;
          const w = Math.round((v / max) * 100);
          const col = s === 'Won' ? '#22c55e' : s === 'Lost' ? '#ef4444' : '#7c5cff';
          return '<div style="margin-bottom:12px"><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>' + s + '</span><span class="strong">' + v + '</span></div>' +
            '<div style="height:8px;background:#2a3050;border-radius:4px;overflow:hidden"><i style="display:block;height:100%;width:' + w + '%;background:' + col + '"></i></div></div>';
        }).join('');
      }
      const ups = res.upcoming_followups || [];
      if (fu) fu.innerHTML = ups.length ? ups.map(f =>
        '<div class="timeline-item"><div class="tl-dot"></div><div><div class="strong">' + SLC.escape(f.company_name || '—') + '</div>' +
        '<div class="muted" style="font-size:12px">' + SLC.escape(f.type) + ' · ' + new Date(f.scheduled_at).toLocaleString([], {dateStyle:'medium', timeStyle:'short'}) + '</div></div></div>'
      ).join('') : SLC.ui.empty('No follow-ups', '');

      const tops = res.top_companies || [];
      if (tc) tc.innerHTML = tops.length ? tops.map(c =>
        '<div class="detail-row"><span class="k">' + SLC.escape(c.name) + '</span><span class="v">' + SLC.ui.scoreBar(c.ai_score) + '</span></div>'
      ).join('') : SLC.ui.empty('No scored companies yet', '');
    } catch (e) { /* ignore */ }
  }

  async function loadActivity() {
    const wrap = document.getElementById('recentActivity');
    if (!wrap) return;
    try {
      const res = await api.get('dashboard/recent-activity?limit=8');
      const acts = (res && res.activities) || [];
      wrap.innerHTML = acts.length ? acts.map(a =>
        '<div class="timeline-item"><div class="tl-dot"></div><div style="flex:1"><div style="font-size:13px">' + SLC.escape(a.description) + '</div>' +
        '<div class="muted" style="font-size:11px">' + SLC.escape(a.user_name || 'System') + ' · ' + SLC.rel(a.created_at) + '</div></div></div>'
      ).join('') : SLC.ui.empty('No activity yet', '');
    } catch (e) { /* ignore */ }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadStats();
    loadPipeline();
    loadActivity();
  });
})();
