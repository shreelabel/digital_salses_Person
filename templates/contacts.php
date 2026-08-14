<?php declare(strict_types=1); ?>
<div class="page" data-page="contacts">
  <div class="toolbar">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input id="contactSearch" placeholder="Search contacts, designation, email…">
    </div>
    <select class="filter" id="contactDm"><option value="">All contacts</option><option value="1">Decision makers</option></select>
    <select class="filter" id="contactAssignedUser"><option value="">All Assignees</option></select>
    <button class="btn-primary" id="addContactBtn" style="margin-left:auto">+ Add Contact</button>
  </div>
  <div class="bulk-bar" id="contactBulkBar">
    <div class="bulk-count"><span class="pill" id="contactSelectedCount">0</span> selected</div>
    <div class="bulk-actions" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <select class="filter btn-sm" id="contactBulkAssignSelect" style="padding:5px 8px;font-size:12px;background:var(--panel2);border:1px solid var(--border);color:var(--text);border-radius:8px;">
        <option value="">Assign to...</option>
      </select>
      <button class="btn-secondary btn-sm" id="contactBulkAssignBtn">👤 Assign</button>
      <button class="btn-danger btn-sm" id="contactBulkDeleteBtn">🗑️ Delete Selected</button>
      <button class="btn-ghost btn-sm" id="contactBulkClearBtn">Cancel</button>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th class="th-cb"><input type="checkbox" class="cb-custom" id="selectAllContacts" title="Select all"></th>
        <th>Name</th><th>Company</th><th>Designation</th><th>Department</th><th>Email</th><th>Phone</th><th>Primary</th><th>Assigned To</th><th></th>
      </tr></thead>
      <tbody id="contactRows"></tbody>
    </table>
    <div class="pager" id="contactPager"></div>
  </div>
</div>
