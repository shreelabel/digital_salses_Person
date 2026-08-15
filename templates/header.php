<?php declare(strict_types=1);
/** @var string $pageTitle @var string $pageIcon @var array $activeUser @var array $slcJs */
$base = $slcJs['base'];
?>
<header class="topbar">
  <div class="topbar-left" style="display:flex;align-items:center;gap:12px;">
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Sidebar (Collapse / Expand)" aria-label="Toggle sidebar">
      <svg class="sidebar-toggle-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
        <line x1="9" y1="3" x2="9" y2="21"/>
        <polyline points="14 9 11 12 14 15" class="toggle-chevron"/>
      </svg>
    </button>
    <button class="hamburger" id="menuToggle" aria-label="Toggle menu" style="display:none;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="topbar-title">
      <h1><?= e($pageTitle) ?></h1>
    </div>
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
