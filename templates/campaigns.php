<?php declare(strict_types=1); ?>
<div class="page" data-page="campaigns">
  <div class="toolbar">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input id="campSearch" placeholder="Search campaigns…">
    </div>
    <select class="filter" id="campStatusFilter">
      <option value="">All Statuses</option>
      <option>Draft</option><option>Active</option><option>Paused</option><option>Completed</option>
    </select>
    <span class="badge badge-purple">Drafts only — no email is ever sent</span>
    <button class="btn-primary" id="addCampBtn" style="margin-left:auto">+ New Campaign</button>
  </div>

  <div class="bulk-bar" id="campBulkBar">
    <div class="bulk-count"><span class="pill" id="campSelectedCount">0</span> selected</div>
    <div class="bulk-actions">
      <button class="btn-danger btn-sm" id="campBulkDeleteBtn">🗑️ Delete Selected</button>
      <button class="btn-ghost btn-sm" id="campBulkClearBtn">Cancel</button>
    </div>
  </div>

  <div class="table-scroll-hint"><span>⇄ Swipe / Scroll horizontally to view all campaign details</span></div>
  <div class="table-wrap">
    <table class="data" id="campaignsTable">
      <thead><tr>
        <th class="th-cb"><input type="checkbox" class="cb-custom" id="selectAllCampaigns" title="Select all"></th>
        <th style="min-width:200px;">Campaign</th>
        <th style="min-width:180px;">Objective</th>
        <th style="min-width:160px;">Audience</th>
        <th style="min-width:90px;">Leads</th>
        <th style="min-width:110px;">Status</th>
        <th style="min-width:160px;">Period</th>
        <th style="min-width:90px;text-align:right;">Actions</th>
      </tr></thead>
      <tbody id="campRows"></tbody>
    </table>
  </div>
</div>
