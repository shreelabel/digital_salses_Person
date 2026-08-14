<?php declare(strict_types=1);
/** @var array $slcJs */
/** @var array $providerStatus */

$providers = $providerStatus['providers'] ?? [];
$aiAvailable = !empty($providerStatus['ai_available']);
$discoveryAvailable = !empty($providerStatus['discovery_available']);
$primaryAi = $providerStatus['primary_ai'] ?? 'FreeLLMAPI';
$primaryDiscovery = $providerStatus['primary_discovery'] ?? 'Hunter';

$roleLabels = [
    'discovery'  => 'Discovery & Verification',
    'enrichment' => 'Decision Maker & People',
    'ai'         => 'AI Generation & Intelligence',
];

$roleBadges = [
    'discovery'  => 'badge-cyan',
    'enrichment' => 'badge-blue',
    'ai'         => 'badge-purple',
];
?>
<div class="page" data-page="ai-settings">
  <!-- Status Strip Card -->
  <div class="card" style="margin-bottom:20px;padding:18px 22px;">
    <div class="card-h" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
      <div>
        <h2 style="margin:0;font-size:19px;font-weight:700;">⚙️ Free Mode · AI & Data Provider Configuration</h2>
        <p style="margin:4px 0 0;font-size:12.5px;color:var(--muted);">Configure live AI models (FreeLLMAPI, 9Router, Gemini) and data enrichment providers (Hunter, Apollo).</p>
      </div>
      <span class="badge badge-purple" style="font-size:11px;padding:4px 10px;font-weight:700;">FREE-FIRST · Zero Paid Dependencies</span>
    </div>
    <div id="providerStatusStrip" style="display:flex;gap:12px;flex-wrap:wrap;font-size:12.5px;">
      <span class="badge <?= $aiAvailable ? 'badge-new' : 'badge-gray' ?>">
        <b>AI Engine:</b> <?= $aiAvailable ? ('Available · ' . htmlspecialchars($primaryAi)) : 'Unavailable' ?>
      </span>
      <span class="badge <?= $discoveryAvailable ? 'badge-new' : 'badge-gray' ?>">
        <b>Discovery & People:</b> <?= $discoveryAvailable ? ('Available · ' . htmlspecialchars($primaryDiscovery)) : 'Configure Hunter/Apollo' ?>
      </span>
      <span class="badge badge-new"><b>Mode:</b> FREE-FIRST ACTIVE</span>
    </div>
  </div>

  <!-- Provider Cards Grid -->
  <div id="providerCards" class="grid" style="grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:18px;">
    <?php foreach ($providers as $slug => $p): 
        $role = $p['role'] ?? 'ai';
        $roleTitle = $roleLabels[$role] ?? ucfirst($role);
        $roleBadge = $roleBadges[$role] ?? 'badge-gray';
        $status = $p['last_status'] ?? 'Not Configured';
        $statusCls = $status === 'Connected' ? 'badge-new' : ($status === 'Not Connected' || $status === 'Not Configured' ? 'badge-gray' : 'badge-lost');
        $hasKey = !empty($p['has_key']);
        $maskedKey = $p['api_key_masked'] ?? '';
        $enabled = !empty($p['enabled']);
    ?>
      <div class="card provider-card" data-slug="<?= htmlspecialchars($slug) ?>" style="border-radius:12px;padding:18px;display:flex;flex-direction:column;justify-content:space-between;">
        <div>
          <div class="card-h" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:8px;">
              <h3 style="margin:0;font-size:16px;font-weight:700;"><?= htmlspecialchars($p['name'] ?? ucfirst($slug)) ?></h3>
              <span class="badge <?= $roleBadge ?>" style="font-size:9.5px;padding:2px 6px;"><?= htmlspecialchars($roleTitle) ?></span>
            </div>
            <label class="switch" style="position:relative;display:inline-block;width:38px;height:22px;">
              <input type="checkbox" data-en <?= $enabled ? 'checked' : '' ?> style="opacity:0;width:0;height:0;">
              <span class="slider" style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background-color:var(--border);transition:.3s;border-radius:22px;"></span>
            </label>
          </div>

          <div class="detail-row" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:12.5px;">
            <span class="k" style="color:var(--muted);font-weight:500;">Connection Status</span>
            <span class="v"><span class="badge <?= $statusCls ?>"><?= htmlspecialchars($status) ?></span></span>
          </div>

          <div class="detail-row" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;font-size:12.5px;">
            <span class="k" style="color:var(--muted);font-weight:500;">Stored API Key</span>
            <span class="v">
              <?php if ($hasKey): ?>
                <span class="badge badge-new" style="font-size:11px;">🔒 Saved: <?= htmlspecialchars($maskedKey) ?></span>
              <?php else: ?>
                <span class="badge badge-gray" style="font-size:11px;">No Key Set</span>
              <?php endif; ?>
            </span>
          </div>

          <div class="field" style="margin:10px 0;">
            <label class="fld" style="display:block;font-size:11.5px;font-weight:600;color:var(--muted);margin-bottom:4px;">
              <?= $hasKey ? 'Update API Key (Leave blank to keep saved key)' : 'Enter API Key' ?>
            </label>
            <input class="fld form-input" type="password" data-key placeholder="<?= $hasKey ? '•••••••••••••••• (Leave blank to keep)' : 'Paste API Key here...' ?>" style="width:100%;font-size:12px;">
          </div>

          <?php if ($slug !== 'hunter' && $slug !== 'apollo'): ?>
            <div class="field" style="margin:10px 0;">
              <label class="fld" style="display:block;font-size:11.5px;font-weight:600;color:var(--muted);margin-bottom:4px;">Base URL</label>
              <input class="fld form-input" data-base value="<?= htmlspecialchars($p['base_url'] ?? '') ?>" style="width:100%;font-size:12px;">
            </div>
          <?php endif; ?>

          <?php if ($role === 'ai'): ?>
            <div class="field" style="margin:10px 0;">
              <label class="fld" style="display:block;font-size:11.5px;font-weight:600;color:var(--muted);margin-bottom:4px;">Model Name</label>
              <input class="fld form-input" data-model value="<?= htmlspecialchars($p['model'] ?? ($slug === 'freellmapi' ? 'auto' : '')) ?>" style="width:100%;font-size:12px;">
            </div>
            <div class="field" style="margin:10px 0;">
              <label class="fld" style="display:block;font-size:11.5px;font-weight:600;color:var(--muted);margin-bottom:4px;">Priority (1 = Tried First)</label>
              <input class="fld form-input" type="number" data-priority value="<?= htmlspecialchars((string) ($p['priority'] ?? 1)) ?>" style="width:100%;font-size:12px;">
            </div>
          <?php endif; ?>
        </div>

        <div style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px;">
          <div style="display:flex;gap:8px;">
            <button class="btn-primary btn-sm" data-save style="padding:6px 12px;font-size:12px;flex:1;">Save</button>
            <button class="btn-secondary btn-sm" data-test style="padding:6px 12px;font-size:12px;flex:1;">Test Connection</button>
          </div>
          <div class="test-out" style="margin-top:10px;font-size:11.5px;"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Audit and Rules -->
  <div class="grid" style="grid-template-columns:1.4fr 1fr;gap:18px;margin-top:20px;">
    <div class="card" style="padding:18px;">
      <div class="card-h" style="margin-bottom:12px;">
        <h3 style="margin:0;font-size:15px;font-weight:700;">📊 Provider Call Audit & Cost Protection</h3>
        <span class="sub" style="font-size:11.5px;color:var(--muted);">Live audit of recent requests, caching hits, and latencies</span>
      </div>
      <div id="usageList" style="max-height:300px;overflow:auto;">
        <div style="text-align:center;color:var(--muted);padding:20px;font-size:12px;">Loading recent provider calls...</div>
      </div>
    </div>

    <div class="card" style="padding:18px;">
      <div class="card-h" style="margin-bottom:12px;">
        <h3 style="margin:0;font-size:15px;font-weight:700;">🛡️ Free Mode Operational Rules</h3>
      </div>
      <ul style="margin:0;padding-left:18px;color:var(--muted);font-size:12.5px;line-height:1.9;">
        <li><b>Discovery:</b> Hunter domain search (free tier) and Google Maps intelligence.</li>
        <li><b>People & CSV:</b> Apollo free export & CSV import (70+ columns supported).</li>
        <li><b>AI Failover Chain:</b> FreeLLMAPI → 9Router → Gemini (first success wins).</li>
        <li><b>Zero Timeouts:</b> Parallel multi-cURL execution and instant AI fallbacks.</li>
        <li><b>Security:</b> API keys are stored securely server-side; browser only receives masked keys.</li>
      </ul>
    </div>
  </div>

  <!-- FULL SYSTEM BACKUP (IMPORT & EXPORT) -->
  <div class="card" style="margin-top:20px;padding:18px 22px;">
    <div class="card-h" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
      <div>
        <h3 style="font-size:16px;font-weight:700;margin:0;">📦 Full System Data Backup (Import & Export)</h3>
        <p style="margin:4px 0 0;color:var(--muted);font-size:12.5px;">Export all leads, companies, contacts, campaigns, settings, and users into a JSON package for 1-click backup and migration.</p>
      </div>
      <div style="display:flex;gap:10px;">
        <button class="btn-secondary" id="btnExportBackup" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;">
          <span>📥 Export Full Backup</span>
        </button>
        <label class="btn-primary" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;padding:8px 16px;">
          <span>📤 Import Backup Package</span>
          <input type="file" id="btnImportBackupFile" accept=".json" style="display:none;">
        </label>
      </div>
    </div>
  </div>
</div>
