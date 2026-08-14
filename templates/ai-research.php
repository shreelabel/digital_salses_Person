<?php declare(strict_types=1); /** @var array $slcJs */ ?>
<div class="page" data-page="ai-research">
  <div class="card" style="margin-bottom:18px">
    <div class="card-h"><h3>🔬 AI Company Research</h3><span class="sub">Fresh Google-Search-grounded analysis</span></div>
    <div class="form-grid">
      <div class="field full"><label class="fld">Select a company</label><select class="fld" id="resCompany"><option value="">Select…</option></select></div>
    </div>
    <button class="btn-primary" id="resRun" <?php if (!$slcJs['ai']['configured']) echo 'disabled'; ?>>✨ Run Research</button>
    <?php if (!$slcJs['ai']['configured']): ?><span class="badge badge-gray" style="margin-left:10px">AI Not Configured</span><?php endif; ?>
  </div>
  <div id="resOutput"></div>
</div>
