<?php declare(strict_types=1); /** @var array $slcJs */ ?>
<div class="page" data-page="companies">
  <div class="toolbar">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input id="companySearch" placeholder="Search companies, industry, city…">
    </div>
    <select class="filter" id="companyIndustry"><option value="">All industries</option></select>
    <select class="filter" id="companyPriority"><option value="">All priority</option><option>High</option><option>Medium</option><option>Low</option></select>
    <select class="filter" id="companyAssignedUser"><option value="">All Assignees</option></select>
    <button class="btn-primary" id="addCompanyBtn" style="margin-left:auto">+ Add Company</button>
  </div>

  <div class="bulk-bar" id="companyBulkBar">
    <div class="bulk-count"><span class="pill" id="companySelectedCount">0</span> selected</div>
    <div class="bulk-actions" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <select class="filter btn-sm" id="companyBulkAssignSelect" style="padding:5px 8px;font-size:12px;background:var(--panel2);border:1px solid var(--border);color:var(--text);border-radius:8px;">
        <option value="">Assign to...</option>
      </select>
      <button class="btn-secondary btn-sm" id="companyBulkAssignBtn">👤 Assign</button>
      <button class="btn-danger btn-sm" id="companyBulkDeleteBtn">🗑️ Delete Selected</button>
      <button class="btn-ghost btn-sm" id="companyBulkClearBtn">Cancel</button>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th class="th-cb"><input type="checkbox" class="cb-custom" id="selectAllCompanies" title="Select all"></th>
        <th>Company</th><th>Industry</th><th>Location</th><th>AI Score</th><th>Priority</th><th>Source</th><th>Assigned To</th><th>Added Date</th><th></th>
      </tr></thead>
      <tbody id="companyRows"></tbody>
    </table>
    <div class="pager" id="companyPager"></div>
  </div>
</div>
