<?php declare(strict_types=1); ?>
<div class="page" data-page="email-composer">
  <div class="toolbar">
    <span class="badge badge-purple">Draft-only · No email is ever sent</span>
    <button class="btn-primary" id="composeBtn" style="margin-left:auto">✍️ Compose Draft</button>
    <button class="btn-secondary" id="aiEmailBtn">✨ Generate with AI</button>
  </div>
  <div class="grid" style="grid-template-columns:1fr 1fr;gap:18px">
    <div class="card">
      <div class="card-h"><h3>Saved Drafts</h3></div>
      <div id="draftList"></div>
    </div>
    <div class="card">
      <div class="card-h"><h3>Email Templates</h3><button class="btn-ghost btn-sm" id="addTpl">+ Template</button></div>
      <div id="tplList"></div>
    </div>
  </div>
</div>
