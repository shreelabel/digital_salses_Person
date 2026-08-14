<?php declare(strict_types=1); /** @var array $activeUser @var string $base */ ?>
<div class="page" data-page="403">
  <div class="card" style="max-width: 540px; margin: 60px auto; text-align: center; padding: 44px 32px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--panel);">
    <div style="width: 58px; height: 58px; border-radius: 16px; background: rgba(239, 68, 68, 0.12); color: var(--bad); display: grid; place-items: center; margin: 0 auto 20px; font-size: 26px;">
      🔒
    </div>
    <h2 style="font-size: 20px; font-weight: 700; color: var(--text); margin-bottom: 10px;">Access Restricted</h2>
    <p style="color: var(--muted); font-size: 13.5px; line-height: 1.6; margin-bottom: 24px;">
      You do not have permission to access this section.<br>
      Please contact an administrator if you need access.
    </p>
    <a href="<?= e($base) ?>/dashboard" class="btn-primary" style="display: inline-block; padding: 10px 22px; text-decoration: none;">
      Return to Dashboard
    </a>
  </div>
</div>
