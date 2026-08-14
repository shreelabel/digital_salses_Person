<?php declare(strict_types=1); /** @var array $slcJs */ ?>
<div class="page" data-page="dashboard">
  <div class="kpi-grid grid" id="kpiGrid">
    <?php foreach ([
      ['total_prospects','building','Total Prospects','accent2','Companies in CRM'],
      ['new_leads','flag','New Leads','accent2','Status: New'],
      ['high_potential','trending','High Potential','high','AI score ≥ 70 or High priority'],
      ['followups_due','calendar','Follow-ups Due','warn','Pending & past due'],
      ['interested','sparkles','Interested','good','Leads showing interest'],
      ['open_opportunities','grid','Open Opportunities','accent2','Active deals'],
      ['email_drafts','mail','Email Drafts','accent2','Draft-only · never sent'],
      ['open_pipeline_value','trending','Open Pipeline','good','Sum of open deal value'],
    ] as $kpi): ?>
      <div class="kpi <?= e($kpi[3]) ?>">
        <div class="kpi-top">
          <div class="kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><use href="#i-<?= e($kpi[1]) ?>"/></svg></div>
          <div class="kpi-label"><?= e($kpi[2]) ?></div>
        </div>
        <div class="kpi-val" id="kpi-<?= e($kpi[0]) ?>">–</div>
        <div class="kpi-foot"><?= e($kpi[4]) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- inline SVG icon defs -->
  <svg width="0" height="0" style="position:absolute">
    <symbol id="i-building" viewBox="0 0 24 24"><path d="M3 21V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v16M14 9h5a2 2 0 0 1 2 2v10"/></symbol>
    <symbol id="i-flag" viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/></symbol>
    <symbol id="i-trending" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></symbol>
    <symbol id="i-sparkles" viewBox="0 0 24 24"><path d="M12 3l1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2z"/></symbol>
    <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></symbol>
    <symbol id="i-mail" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></symbol>
  </svg>

  <div class="grid" style="grid-template-columns:2fr 1fr;margin-top:18px">
    <div class="card">
      <div class="card-h"><h3>Sales Pipeline</h3><span class="sub">Leads by stage</span></div>
      <div id="pipelineBars"></div>
    </div>
    <div class="card">
      <div class="card-h"><h3>Upcoming Follow-ups</h3></div>
      <div id="upcomingFollowups"></div>
    </div>
  </div>

  <div class="grid" style="grid-template-columns:1fr 1fr;margin-top:18px">
    <div class="card">
      <div class="card-h"><h3>Top AI-Scored Companies</h3><span class="sub">Highest relevance</span></div>
      <div id="topCompanies"></div>
    </div>
    <div class="card">
      <div class="card-h"><h3>Recent Activity</h3></div>
      <div id="recentActivity"></div>
    </div>
  </div>
</div>
