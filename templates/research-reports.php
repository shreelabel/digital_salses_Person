<?php declare(strict_types=1); ?>
<div class="page" data-page="research-reports">
  <div class="toolbar">
    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input id="reportSearch" placeholder="Search reports, company, industry…">
    </div>
    <span class="badge badge-purple" style="font-weight:600;">AI-generated, Google-Search-grounded reports</span>
    <a class="btn-secondary" href="<?= e($slcJs['base']) ?>/ai-research" style="margin-left:auto">+ ✨ New Research</a>
  </div>

  <div class="bulk-bar" id="reportBulkBar">
    <div class="bulk-count"><span class="pill" id="reportSelectedCount">0</span> selected</div>
    <div class="bulk-actions">
      <button class="btn-danger btn-sm" id="reportBulkDeleteBtn">🗑️ Delete Selected</button>
      <button class="btn-ghost btn-sm" id="reportBulkClearBtn">Cancel</button>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th class="th-cb"><input type="checkbox" class="cb-custom" id="selectAllReports" title="Select all"></th>
        <th>Company</th><th>Industry</th><th>Confidence</th><th>Sources</th><th>Generated</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody id="reportRows"></tbody>
    </table>
  </div>
</div>
