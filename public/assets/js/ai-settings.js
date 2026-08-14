/* ai-settings.js — multi-provider config cards (free-first) */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;

  const ROLES = { discovery: 'Discovery & Verification', enrichment: 'Decision Maker & People', ai: 'AI Intelligence' };

  async function load() {
    try {
      const res = await api.get('ai/providers');
      const data = res.providers ? res : (res.data || res);
      if (data && data.providers) {
        updateStatusStrip(data);
        updateCardStatuses(data.providers);
      }
      loadUsage();
    } catch (e) {
      console.warn('Could not refresh provider live status:', e);
    }
  }

  function updateStatusStrip(res) {
    const strip = document.getElementById('providerStatusStrip');
    if (!strip) return;
    strip.innerHTML =
      chip('AI Engine', res.ai_available ? 'Available · ' + (res.primary_ai || '—') : 'Unavailable', res.ai_available) +
      chip('Discovery & People', res.discovery_available ? 'Available · ' + (res.primary_discovery || '—') : 'Configure Hunter/Apollo', res.discovery_available) +
      chip('Mode', 'FREE-FIRST ACTIVE', true);
  }

  function chip(label, val, ok) {
    return '<span class="badge ' + (ok ? 'badge-new' : 'badge-gray') + '"><b>' + SLC.escape(label) + ':</b> ' + SLC.escape(val) + '</span>';
  }

  function updateCardStatuses(providers) {
    Object.values(providers || {}).forEach(p => {
      const card = document.querySelector('.provider-card[data-slug="' + p.slug + '"]');
      if (!card) return;
      
      const statusBadge = card.querySelector('.detail-row .badge');
      if (statusBadge) {
        const statusCls = p.last_status === 'Connected' ? 'badge-new' : (p.last_status === 'Not Connected' || p.last_status === 'Not Configured' ? 'badge-gray' : 'badge-lost');
        statusBadge.className = 'badge ' + statusCls;
        statusBadge.textContent = p.last_status || 'Not Configured';
      }

      const enCheckbox = card.querySelector('[data-en]');
      if (enCheckbox && p.enabled !== undefined) {
        enCheckbox.checked = !!p.enabled;
      }
    });
  }

  function bindCards() {
    document.querySelectorAll('.provider-card').forEach(function (card) {
      const slug = card.getAttribute('data-slug');
      if (!slug) return;

      const enToggle = card.querySelector('[data-en]');
      if (enToggle) {
        enToggle.addEventListener('change', () => {
          save(slug, { enabled: enToggle.checked });
        });
      }

      const saveBtn = card.querySelector('[data-save]');
      if (saveBtn) {
        saveBtn.addEventListener('click', () => {
          const data = {};
          const keyInput = card.querySelector('[data-key]');
          if (keyInput) {
            const key = keyInput.value.trim();
            if (key) data.api_key = key;
          }
          const baseInput = card.querySelector('[data-base]');
          if (baseInput) data.base_url = baseInput.value.trim();
          
          const modelInput = card.querySelector('[data-model]');
          if (modelInput) data.model = modelInput.value.trim();
          
          const prioInput = card.querySelector('[data-priority]');
          if (prioInput) data.priority = prioInput.value;

          save(slug, data);
        });
      }

      const testBtn = card.querySelector('[data-test]');
      if (testBtn) {
        testBtn.addEventListener('click', () => test(slug, card));
      }
    });
  }

  async function save(slug, data) {
    try {
      await api.put('ai/providers/' + slug, data);
      SLC.toast(slug + ' configuration saved successfully', 'success');
      load();
    } catch (e) {
      SLC.toast(e.message || 'Failed to save ' + slug, 'error');
    }
  }

  async function test(slug, card) {
    const out = card.querySelector('.test-out');
    const btn = card.querySelector('[data-test]');
    if (!out || !btn) return;

    btn.disabled = true;
    out.innerHTML = SLC.ui.spinner() + ' testing connection…';
    try {
      const r = await api.post('ai/providers/' + slug + '/test');
      const isConnected = !!(r && r.connected);
      const badge = isConnected ? 'badge-new' : 'badge-lost';
      out.innerHTML = '<span class="badge ' + badge + '">' + SLC.escape(r.status || (isConnected ? 'Connected' : 'Failed')) + '</span> ' + SLC.escape(r.message || '') +
        (r.latency_ms != null ? ' <span class="muted">(' + r.latency_ms + 'ms)</span>' : '') +
        (r.remaining != null ? ' · remaining credits: ' + SLC.escape(r.remaining) : '');
      
      const statusBadge = card.querySelector('.detail-row .badge');
      if (statusBadge) {
        statusBadge.className = 'badge ' + badge;
        statusBadge.textContent = r.status || (isConnected ? 'Connected' : 'Error');
      }

      SLC.toast(slug + ': ' + (r.status || (isConnected ? 'Connected' : 'Failed')), isConnected ? 'success' : 'error');
    } catch (e) {
      out.innerHTML = '<span class="badge badge-lost">Error</span> ' + SLC.escape(e.message);
      SLC.toast(e.message || 'Connection test failed', 'error');
    } finally {
      btn.disabled = false;
    }
  }

  async function loadUsage() {
    const wrap = document.getElementById('usageList');
    if (!wrap) return;
    try {
      const res = await api.get('ai/providers/usage');
      const rows = res.usage || [];
      wrap.innerHTML = rows.length ? rows.slice(0, 30).map(u =>
        '<div class="detail-row" style="padding:6px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-size:12px;"><span class="k"><b>' + SLC.escape(u.provider) + '</b> · ' + SLC.escape(u.operation) +
        (u.cache_hit ? ' <span class="badge badge-purple" style="font-size:9px">CACHE</span>' : '') +
        '<div class="muted" style="font-size:10px">' + SLC.rel(u.created_at) + (u.error ? ' · ' + SLC.escape(u.error) : '') + '</div></span>' +
        '<span class="v" style="text-align:right;">' + (u.status === 'success' ? '<span class="badge badge-new" style="font-size:10px;">OK</span>' : '<span class="badge badge-lost" style="font-size:10px;">ERR</span>') +
        '<div class="muted" style="font-size:10px">' + (u.latency_ms || 0) + 'ms</div></span></div>'
      ).join('') : '<div style="text-align:center;color:var(--muted);padding:14px;font-size:12px;">No provider calls recorded yet.</div>';
    } catch (e) {
      wrap.innerHTML = '<div style="text-align:center;color:var(--bad);padding:14px;font-size:12px;">Failed to load call audit log.</div>';
    }
  }

  function init() {
    bindCards();
    load();

    document.getElementById('btnExportBackup')?.addEventListener('click', function () {
      const baseUrl = SLC.base || '';
      const exportUrl = baseUrl + '/api/backup/export';
      
      const link = document.createElement('a');
      link.href = exportUrl;
      link.setAttribute('download', '');
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      SLC.toast('Exporting full database backup package…', 'info');
    });

    document.getElementById('btnImportBackupFile')?.addEventListener('change', async function (e) {
      const file = e.target.files && e.target.files[0];
      if (!file) return;

      if (!confirm('⚠️ WARNING: Importing a backup package will replace existing data in all tables with the backup contents. Are you sure you want to proceed?')) {
        e.target.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = async function (evt) {
        try {
          SLC.toast('Importing backup package… Please wait.', 'info');
          const jsonText = evt.target.result;
          const res = await api.post('backup/import', { json_data: jsonText });
          SLC.toast(res.message || 'Backup imported successfully!', 'success');
          setTimeout(() => {
            window.location.reload();
          }, 1200);
        } catch (err) {
          SLC.toast('Import failed: ' + (err.message || 'Invalid JSON file'), 'error');
        } finally {
          e.target.value = '';
        }
      };
      reader.readAsText(file);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
