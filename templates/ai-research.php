<?php declare(strict_types=1); /** @var array $slcJs */ ?>
<div class="page" data-page="ai-research">
  <div class="card" style="margin-bottom:18px">
    <div class="card-h">
      <div>
        <h3 style="display:flex;align-items:center;gap:8px;">🔬 AI Company Research & Lead Closer</h3>
        <span class="sub">Deep packaging analysis, lead qualification (Cold/Warm/Hot), and multi-channel outreach kit</span>
      </div>
      <span class="badge badge-purple" style="font-weight:600;">✨ SKILL Lead-Filter Powered</span>
    </div>
    <div class="form-grid">
      <div class="field full"><label class="fld">Select a target company</label><select class="fld" id="resCompany"><option value="">Select a company to analyze…</option></select></div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;margin-top:6px;">
      <button class="btn-primary" id="resRun" <?php if (!$slcJs['ai']['configured']) echo 'disabled'; ?>>✨ Run Deep Sales Intelligence</button>
      <?php if (!$slcJs['ai']['configured']): ?><span class="badge badge-gray">AI Not Configured</span><?php endif; ?>
    </div>
  </div>
  <div id="resOutput"></div>
</div>
