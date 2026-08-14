<?php declare(strict_types=1);
use SLC\Core\Auth;

/** @var string $pageSlug @var array $slcJs */
$nav = [
    'dashboard'       => ['grid', 'Dashboard', false, null, 'dashboard.view'],
    'ai-lead-finder'  => ['sparkles', 'AI Lead Finder', true, 'imports', 'ai_lead_finder.view'],
    'ai-research'     => ['search', 'AI Research', true, null, 'research.view'],
    'companies'       => ['building', 'Companies', false, 'companies', 'companies.view'],
    'contacts'        => ['users', 'Contacts', false, 'contacts', 'contacts.view'],
    'leads'           => ['flag', 'Leads', false, 'leads', 'leads.view'],
    'campaigns'       => ['send', 'Campaigns', false, 'campaigns', 'campaigns.view'],
    'followups'       => ['calendar', 'Follow-ups', false, 'followups', 'followups.view'],
    'opportunities'   => ['trending', 'Opportunities', false, 'opportunities', 'opportunities.view'],
    'email-composer'  => ['mail', 'Email Drafts', false, 'email-composer', 'email_composer.view'],
    'research-reports'=> ['file-text', 'Research Reports', false, 'research-reports', 'research.view'],
];

$configNav = [
    'ai-settings'  => ['cpu', 'AI Settings', 'ai_settings.view'],
    'integrations' => ['plug', 'Integrations', 'integrations.view'],
    'users'        => ['users', 'Users & Roles', 'users.manage'],
    'profile'      => ['user', 'My Profile', 'profile.view'],
];

$svg = [
    'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
    'building' => '<path d="M3 21V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v16"/><path d="M14 9h5a2 2 0 0 1 2 2v10"/><path d="M7 7h0M7 11h0M7 15h0M10 7h0M10 11h0M10 15h0"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'flag' => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
    'send' => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
    'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'trending' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
    'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
    'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/>',
    'sparkles' => '<path d="M12 3l1.9 5.8L20 11l-6.1 2.2L12 19l-1.9-5.8L4 11l6.1-2.2z"/><path d="M19 3v4M21 5h-4"/>',
    'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    'cpu' => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="2" x2="9" y2="4"/><line x1="15" y1="2" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="22"/><line x1="15" y1="20" x2="15" y2="22"/><line x1="20" y1="9" x2="22" y2="9"/><line x1="20" y1="15" x2="22" y2="15"/><line x1="2" y1="9" x2="4" y2="9"/><line x1="2" y1="15" x2="4" y2="15"/>',
    'plug' => '<path d="M12 22v-5"/><path d="M9 8V2"/><path d="M15 8V2"/><path d="M18 8v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8Z"/>',
    'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
];
$icon = function (string $name) use ($svg): string {
    $p = $svg[$name] ?? '';
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
};
$base = $slcJs['base'];
$hasConfigSectionPerm = Auth::can('configuration.view');
?>
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="brand-logo" style="font-weight:800;background:linear-gradient(135deg,var(--accent),var(--accent2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;">SLC</div>
    <div class="brand-text">
      <div class="brand-title" style="font-size:14px;font-weight:700;color:var(--text);">Shree Label</div>
      <div class="brand-sub" style="font-size:11px;color:var(--accent2);font-weight:600;">Digital Sales Person</div>
    </div>
  </div>

  <nav class="nav">
    <?php foreach ($nav as $slug => $item):
      $ic = $item[0]; $label = $item[1]; $aiTag = !empty($item[2]); $countKey = $item[3] ?? null; $perm = $item[4] ?? null;
      if ($perm && !Auth::can($perm)) {
          continue;
      }
      $cVal = ($countKey && isset($sidebarCounts[$countKey])) ? (int)$sidebarCounts[$countKey] : null;
      $cText = $cVal !== null ? (string)$cVal : '0';
    ?>
      <a href="<?= e($base) ?>/<?= e($slug) ?>" class="nav-item <?= $slug === $pageSlug ? 'active' : '' ?>">
        <?= $icon($ic) ?><span><?= e($label) ?></span>
        <?php if ($aiTag): ?><span class="ai-pill">AI</span><?php endif; ?>
        <?php if ($countKey): ?><span class="nav-counter" data-count-key="<?= e($countKey) ?>" title="<?= e($label) ?>: <?= $cText ?>"><?= $cText ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php
    $visibleConfig = [];
    foreach ($configNav as $slug => $item) {
        $perm = $item[2] ?? null;
        if ($slug === 'profile') {
            if (Auth::can('profile.view')) {
                $visibleConfig[$slug] = $item;
            }
            continue;
        }
        if ($hasConfigSectionPerm && (!$perm || Auth::can($perm))) {
            $visibleConfig[$slug] = $item;
        }
    }
  ?>

  <?php if (!empty($visibleConfig)): ?>
    <?php if ($hasConfigSectionPerm): ?>
      <div class="nav-section">Configuration</div>
    <?php endif; ?>
    <nav class="nav">
      <?php foreach ($visibleConfig as $slug => [$ic, $label]): ?>
        <a href="<?= e($base) ?>/<?= e($slug) ?>" class="nav-item <?= $slug === $pageSlug ? 'active' : '' ?>">
          <?= $icon($ic) ?><span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if ($hasConfigSectionPerm || Auth::can('ai_settings.view')): ?>
    <div class="ai-status-card">
      <div class="ai-status-dot <?= $slcJs['ai']['configured'] ? 'on' : 'off' ?>"></div>
      <div>
        <div class="ai-status-label"><?= $slcJs['ai']['configured'] ? 'AI Configured' : 'AI Not Configured' ?></div>
        <div class="ai-status-model"><?= e($slcJs['ai']['model']) ?></div>
      </div>
    </div>
  <?php endif; ?>
</aside>
