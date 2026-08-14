<?php declare(strict_types=1); ?>
<div class="page" data-page="leads">
  <div class="toolbar">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input id="leadSearch" placeholder="Search leads, industry, location…">
    </div>
    <select class="filter" id="leadStatus">
      <option value="">All statuses</option>
      <option>New</option><option>Contacted</option><option>Interested</option><option>Requirement</option><option>Quotation</option><option>Negotiation</option><option>Won</option><option>Lost</option>
    </select>
    <select class="filter" id="leadPriority"><option value="">All priority</option><option>High</option><option>Medium</option><option>Low</option></select>
    <select class="filter" id="leadSource">
      <option value="">All sources</option>
      <option value="Apollo CSV">Apollo CSV</option>
      <option value="AI Lead Discovery">AI Discovery</option>
      <option value="Manual">Manual</option>
    </select>
    <select class="filter" id="leadAssignedUser">
      <option value="">All Assignees</option>
    </select>
    <button class="btn-primary" id="addLeadBtn" style="margin-left:auto">+ Add Lead</button>
  </div>
  <div class="bulk-bar" id="leadBulkBar">
    <div class="bulk-count"><span class="pill" id="leadSelectedCount">0</span> selected</div>
    <div class="bulk-actions" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <select class="filter btn-sm" id="leadBulkAssignSelect" style="padding:5px 8px;font-size:12px;background:var(--panel2);border:1px solid var(--border);color:var(--text);border-radius:8px;">
        <option value="">Assign to...</option>
      </select>
      <button class="btn-secondary btn-sm" id="leadBulkAssignBtn">👤 Assign</button>
      <button class="btn-danger btn-sm" id="leadBulkDeleteBtn">🗑️ Delete Selected</button>
      <button class="btn-ghost btn-sm" id="leadBulkClearBtn">Cancel</button>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th class="th-cb"><input type="checkbox" class="cb-custom" id="selectAllLeads" title="Select all"></th>
        <th>Company</th><th>Industry</th><th>Location</th><th>Status</th><th>Priority</th><th>AI Score</th><th>Value</th><th>Assigned To</th><th>Next Follow-up</th><th></th>
      </tr></thead>
      <tbody id="leadRows"></tbody>
    </table>
    <div class="pager" id="leadPager"></div>
  </div>
</div>
