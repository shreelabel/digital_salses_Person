<?php declare(strict_types=1); ?>
<div class="page" data-page="opportunities">
  <div class="toolbar">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input id="oppSearch" placeholder="Search opportunities…">
    </div>
    <select class="filter" id="oppStage"><option value="">All stages</option><option>Prospecting</option><option>Qualification</option><option>Proposal</option><option>Negotiation</option><option>Closing</option><option>Won</option><option>Lost</option></select>
    <button class="btn-primary" id="addOppBtn" style="margin-left:auto">+ Add Opportunity</button>
  </div>
  <div class="bulk-bar" id="oppBulkBar">
    <div class="bulk-count"><span class="pill" id="oppSelectedCount">0</span> selected</div>
    <div class="bulk-actions">
      <button class="btn-danger btn-sm" id="oppBulkDeleteBtn">🗑️ Delete Selected</button>
      <button class="btn-ghost btn-sm" id="oppBulkClearBtn">Cancel</button>
    </div>
  </div>

  <div class="grid kpi-grid" id="oppSummary" style="margin-bottom:16px"></div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th class="th-cb"><input type="checkbox" class="cb-custom" id="selectAllOpps" title="Select all"></th>
        <th>Title</th><th>Company</th><th>Stage</th><th>Amount</th><th>Probability</th><th>Close Date</th><th></th>
      </tr></thead>
      <tbody id="oppRows"></tbody>
    </table>
  </div>
</div>
