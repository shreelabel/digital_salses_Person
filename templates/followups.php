<?php declare(strict_types=1); ?>
<div class="page" data-page="followups">
  <div class="toolbar">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input id="fuSearch" placeholder="Search follow-ups, companies, notes…">
    </div>
    <select class="filter" id="fuStatus">
      <option value="">All statuses</option>
      <option>Pending</option>
      <option>Completed</option>
    </select>
    <select class="filter" id="fuType">
      <option value="">All types</option>
      <option>Call</option>
      <option>Email</option>
      <option>Meeting</option>
      <option>Visit</option>
      <option>WhatsApp</option>
    </select>
    <select class="filter" id="fuAssignedUser">
      <option value="">All Assignees</option>
    </select>
    <button class="btn-primary" id="addFuBtn" style="margin-left:auto">+ Schedule Follow-up</button>
  </div>

  <div class="bulk-bar" id="fuBulkBar">
    <div class="bulk-count"><span class="pill" id="fuSelectedCount">0</span> selected</div>
    <div class="bulk-actions">
      <button class="btn-danger btn-sm" id="fuBulkDeleteBtn">🗑️ Delete Selected</button>
      <button class="btn-ghost btn-sm" id="fuBulkClearBtn">Cancel</button>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th class="th-cb"><input type="checkbox" class="cb-custom" id="selectAllFollowups" title="Select all"></th>
        <th>When</th><th>Company</th><th>Type</th><th>Status</th><th>Assigned To</th><th>Notes</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody id="fuRows"></tbody>
    </table>
    <div class="pager" id="fuPager"></div>
  </div>
</div>
