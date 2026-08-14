/* integrations.js */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  async function load() {
    const wrap = document.getElementById('intList');
    try {
      const res = await api.get('integrations');
      const rows = res.integrations || [];
      wrap.innerHTML = rows.map(it => {
        const isActive = it.status === 'Active' || it.configured;
        const isWarning = it.status === 'Disabled' || it.status === 'Standby';
        const badgeClass = isActive ? 'badge-active' : (isWarning ? 'badge-medium' : 'badge-gray');
        const configBtn = it.is_provider
          ? '<a href="' + (SLC.base || '.') + '/ai-settings" class="btn-ghost btn-sm" style="margin-right:8px;font-size:11px;padding:3px 8px;">Configure ⚙️</a>'
          : '';

        return '<div class="detail-row"><span class="k"><div class="strong">' + SLC.escape(it.name) + '</div><div class="muted" style="font-size:11px">' + SLC.escape(it.description || '') + '</div></span>' +
          '<span class="v" style="display:inline-flex;align-items:center;">' + configBtn + '<span class="badge ' + badgeClass + '">' + SLC.escape(it.status) + '</span></span></div>';
      }).join('');
    } catch (e) { wrap.innerHTML = SLC.ui.empty('Failed to load', ''); }
  }
  document.addEventListener('DOMContentLoaded', load);
})();
