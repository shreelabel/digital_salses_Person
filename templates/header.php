<?php declare(strict_types=1);
/** @var string $pageTitle @var string $pageIcon @var array $activeUser @var array $slcJs */
$base = $slcJs['base'];
?>
<header class="topbar">
  <button class="hamburger" id="menuToggle" aria-label="Toggle menu">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
  <div class="topbar-title">
    <h1><?= e($pageTitle) ?></h1>
  </div>
  <div class="topbar-actions">
    <div class="ai-badge <?= $slcJs['ai']['configured'] ? 'on' : 'off' ?>" title="<?= e($slcJs['ai']['api']) ?>">
      <span class="dot"></span><?= $slcJs['ai']['configured'] ? 'AI Online' : 'AI Offline' ?>
    </div>
    <div class="user-chip">
      <div class="avatar"><?= e(strtoupper(substr($activeUser['name'] ?? 'A', 0, 1))) ?></div>
      <div class="user-meta">
        <div class="user-name"><?= e($activeUser['name'] ?? 'User') ?></div>
        <div class="user-role"><?= e(ucfirst($activeUser['role'] ?? 'user')) ?></div>
      </div>
    </div>
    <a href="<?= e($base) ?>/logout" class="btn-ghost logout-link" id="logoutBtn" data-logout="1">Logout</a>
  </div>
</header>
