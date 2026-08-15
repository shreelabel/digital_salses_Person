/* ============================================================
   app.js — shared bootstrap: sidebar toggle, logout, data-method,
   toasts, auth-state awareness. Loaded on every page before others.
   ============================================================ */
(function () {
  'use strict';
  const SLC = window.SLC || {};

  // ---- Sidebar Desktop Collapse & Mobile Drawer ----
  function initSidebar() {
    const isCollapsed = localStorage.getItem('slc_sidebar_collapsed') === '1';
    if (isCollapsed && window.innerWidth > 768) {
      document.body.classList.add('sidebar-collapsed');
      document.documentElement.classList.add('sidebar-collapsed');
    } else {
      document.body.classList.remove('sidebar-collapsed');
      document.documentElement.classList.remove('sidebar-collapsed');
    }
  }

  function toggleDesktopSidebar() {
    const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
    document.documentElement.classList.toggle('sidebar-collapsed', isCollapsed);
    localStorage.setItem('slc_sidebar_collapsed', isCollapsed ? '1' : '0');
    window.dispatchEvent(new Event('resize'));
  }

  function closeMobileSidebar() {
    document.getElementById('sidebar')?.classList.remove('open');
    document.getElementById('sidebarBackdrop')?.classList.remove('active');
    document.body.classList.remove('sidebar-locked');
  }

  function openMobileSidebar() {
    document.getElementById('sidebar')?.classList.add('open');
    document.getElementById('sidebarBackdrop')?.classList.add('active');
    document.body.classList.add('sidebar-locked');
  }

  // Initialize on load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebar);
  } else {
    initSidebar();
  }

  document.addEventListener('click', function (e) {
    // Sidebar toggle button (in topbar)
    if (e.target.closest('#sidebarToggleBtn, #menuToggle')) {
      e.preventDefault();
      e.stopPropagation();
      if (window.innerWidth <= 768) {
        const sb = document.getElementById('sidebar');
        if (sb && sb.classList.contains('open')) {
          closeMobileSidebar();
        } else {
          openMobileSidebar();
        }
      } else {
        toggleDesktopSidebar();
      }
      return;
    }

    // Backdrop or nav item on mobile
    if (e.target.closest('#sidebarBackdrop') || (window.innerWidth <= 768 && e.target.closest('#sidebar .nav-item'))) {
      closeMobileSidebar();
    }
  });

  // Keyboard shortcut Alt+S to toggle sidebar on desktop
  document.addEventListener('keydown', function (e) {
    if (e.altKey && (e.key === 's' || e.key === 'S')) {
      if (window.innerWidth > 768) {
        e.preventDefault();
        toggleDesktopSidebar();
      }
    }
  });

  // ---- Logout handler ----
  document.addEventListener('click', async function (e) {
    const link = e.target.closest('.logout-link, #logoutBtn, [data-logout]');
    if (!link) return;
    e.preventDefault();
    e.stopPropagation();

    const base = (window.SLC && SLC.base !== undefined) ? SLC.base : '';
    const apiBase = (window.SLC && SLC.apiBase) ? SLC.apiBase : (base + '/api');
    const apiLogoutUrl = apiBase.replace(/\/$/, '') + '/auth/logout';
    const loginUrl = base + '/login.php?logged_out=1';

    try {
      await fetch(apiLogoutUrl, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': (window.SLC && SLC.csrfToken) || ''
        },
        credentials: 'same-origin'
      });
    } catch (err) {
      // Continue to redirect even on network error
    }

    window.location.href = loginUrl;
  });

  // ---- data-method links (for other REST action links e.g. DELETE) ----
  document.addEventListener('click', function (e) {
    const link = e.target.closest('[data-method]');
    if (!link || link.closest('.logout-link, #logoutBtn, [data-logout]')) return;
    e.preventDefault();
    const method = (link.getAttribute('data-method') || 'POST').toUpperCase();
    const action = link.getAttribute('href');
    if (!action) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
    const m = document.createElement('input');
    m.type = 'hidden'; m.name = '_method'; m.value = method; form.appendChild(m);
    const csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_csrf'; csrf.value = (SLC.csrfToken || ''); form.appendChild(csrf);
    document.body.appendChild(form);
    if (method === 'DELETE') {
      if (!confirm('Are you sure?')) { form.remove(); return; }
    }
    form.submit();
  });

  // ---- Toast helper ----
  SLC.toast = function (message, type) {
    type = type || 'info';
    const wrap = document.getElementById('toasts');
    if (!wrap) { return; }
    const el = document.createElement('div');
    el.className = 'toast ' + (type === 'success' ? 'ok' : type === 'error' ? 'err' : 'info');
    el.textContent = message;
    wrap.appendChild(el);
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transition = 'opacity .3s';
      setTimeout(function () { el.remove(); }, 300);
    }, 3800);
  };

  SLC.escape = function (s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  SLC.rel = function (iso) {
    if (!iso) return '—';
    const cleanIso = String(iso).replace(/-/g, '/');
    const t = new Date(cleanIso).getTime();
    if (isNaN(t)) return iso;
    const diff = (Date.now() - t) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
    return new Date(t).toLocaleDateString();
  };

  SLC.formatDate = function (iso, withTime = true) {
    if (!iso) return '—';
    const cleanIso = String(iso).replace(/-/g, '/');
    const d = new Date(cleanIso);
    if (isNaN(d.getTime())) return String(iso);

    const day = d.getDate();
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const month = months[d.getMonth()];
    const year = d.getFullYear();

    if (!withTime) {
      return `${day} ${month} ${year}`;
    }

    let hours = d.getHours();
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const formattedHours = String(hours).padStart(2, '0');

    return `${day} ${month} ${year}, ${formattedHours}:${minutes} ${ampm}`;
  };

  SLC.dateBadge = function (iso) {
    if (!iso) return '<span class="muted">—</span>';
    const full = SLC.formatDate(iso, true);
    const rel = SLC.rel(iso);
    return `<div class="date-badge" style="font-size:12px;color:var(--text);white-space:nowrap;" title="${SLC.escape(full)}">📅 ${SLC.escape(full)} <span class="muted" style="font-size:10.5px;">(${SLC.escape(rel)})</span></div>`;
  };

  SLC.assigneeBadge = function (userName, assignedAt) {
    if (!userName) return '<span class="muted" style="white-space:nowrap;">— Unassigned</span>';
    let timeHtml = '';
    if (assignedAt) {
      const full = SLC.formatDate(assignedAt, true);
      const rel = SLC.rel(assignedAt);
      timeHtml = `<div class="muted" style="font-size:10px;margin-top:2px;white-space:nowrap;" title="Assigned on: ${SLC.escape(full)}">📅 Assigned: ${SLC.escape(full)} <span style="opacity:0.85">(${SLC.escape(rel)})</span></div>`;
    }
    return `<div class="assignee-wrap" style="white-space:nowrap;"><span class="badge" style="background:var(--panel2);border:1px solid var(--border);color:var(--text);font-weight:600;font-size:11px;white-space:nowrap;">👤 ${SLC.escape(userName)}</span>${timeHtml}</div>`;
  };

  SLC.money = function (v) {
    const n = parseFloat(v || 0);
    return '₹' + n.toLocaleString('en-IN');
  };

  SLC.aiConfigured = function () {
    return !!(SLC.ai && SLC.ai.configured);
  };

  // ---- Global fetch error guard ----
  SLC.handleFetchError = function (resp) {
    if (resp.status === 401) {
      SLC.toast('Session expired. Redirecting to login…', 'error');
      setTimeout(function () { window.location.href = (SLC.base || '') + '/login.php'; }, 900);
      return true;
    }
    if (resp.status === 419) {
      SLC.toast('Security token expired. Reload the page.', 'error');
      return true;
    }
    return false;
  };

  // ---- Sidebar live counter formatting & refresh ----
  SLC.formatCounter = function (num) {
    if (num === null || num === undefined || isNaN(num)) return '—';
    const n = parseInt(num, 10);
    if (n >= 1000000) return (n / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (n >= 10000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
    return n.toLocaleString();
  };

  SLC.refreshSidebarCounters = async function () {
    try {
      const base = (SLC.apiBase || (SLC.base || '') + '/api').replace(/\/$/, '');
      const resp = await fetch(base + '/sidebar/counts', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      if (!resp.ok) throw new Error('Status ' + resp.status);
      const data = await resp.json();
      const counts = (data && data.counts) ? data.counts : (data || {});

      document.querySelectorAll('.nav-counter[data-count-key]').forEach(function (el) {
        const key = el.getAttribute('data-count-key');
        if (key && Object.prototype.hasOwnProperty.call(counts, key)) {
          const raw = parseInt(counts[key], 10) || 0;
          el.textContent = SLC.formatCounter(raw);
          el.setAttribute('title', raw.toLocaleString() + ' ' + (key.replace(/-/g, ' ')));
        } else {
          el.textContent = '0';
          el.setAttribute('title', '0 ' + key);
        }
      });
    } catch (err) {
      document.querySelectorAll('.nav-counter[data-count-key]').forEach(function (el) {
        if (el.textContent === '…' || el.textContent === '') {
          el.textContent = '—';
          el.setAttribute('title', 'Unable to load count');
        }
      });
    }
  };

  SLC.pagerRender = function (id, res, curPage, reload, go) {
    const el = document.getElementById(id);
    if (!el) return;
    const total = res.total || 0;
    const per = res.per_page || 20;
    const pages = Math.max(1, Math.ceil(total / per));
    el.innerHTML = '<span>Showing ' + ((res.data || []).length) + ' of ' + total + '</span>' +
      '<span><button class="btn-icon btn-sm" ' + (curPage <= 1 ? 'disabled' : '') + ' data-prev>‹</button> ' + curPage + '/' + pages + ' <button class="btn-icon btn-sm" ' + (curPage >= pages ? 'disabled' : '') + ' data-next>›</button></span>';
    el.querySelector('[data-prev]')?.addEventListener('click', function () {
      if (curPage > 1 && typeof go === 'function') go(curPage - 1);
    });
    el.querySelector('[data-next]')?.addEventListener('click', function () {
      if (curPage < pages && typeof go === 'function') go(curPage + 1);
    });
  };

  // ---- Universal WhatsApp Assignment Message Generator & Modal ----
  SLC.generateWhatsAppText = function (opts) {
    opts = opts || {};
    const assignedTo = opts.assignedToName || 'Sales Executive';
    const adminName = opts.adminName || (SLC.user && SLC.user.name) || 'Admin';
    const items = opts.items || [];
    const typeLabel = opts.typeLabel || 'Companies';
    const now = new Date();
    const dateStr = SLC.formatDate(now.toISOString(), true);

    // Compute CRM URL
    const baseUrl = (window.SLC && SLC.base !== undefined) ? (window.location.origin + SLC.base) : window.location.origin;
    let targetPath = '/companies';
    const tLower = (typeLabel || '').toLowerCase();
    if (tLower.includes('contact')) targetPath = '/contacts';
    else if (tLower.includes('lead')) targetPath = '/leads';
    else if (tLower.includes('discover') || tLower.includes('free search')) targetPath = '/ai-lead-finder';
    const crmUrl = opts.crmUrl || (baseUrl.replace(/\/$/, '') + targetPath);

    let text = `📢 *NEW ASSIGNMENT NOTICE*\n`;
    text += `━━━━━━━━━━━━━━━━━━━━━━━━\n`;
    text += `👤 *Assigned To:* ${assignedTo}\n`;
    text += `💼 *Assigned By (Admin):* ${adminName}\n`;
    text += `📅 *Date:* ${dateStr}\n`;
    text += `🏢 *Total ${typeLabel}:* ${items.length}\n`;
    text += `━━━━━━━━━━━━━━━━━━━━━━━━\n\n`;

    text += `📋 *Assigned List:*\n`;
    items.forEach((item, idx) => {
      const num = idx + 1;
      const compName = item.company_name || item.name || item.title || 'Company Record';
      const location = item.location || [item.city, item.state, item.country].filter(Boolean).join(', ') || item.address || '—';
      const ind = item.industry ? ` [${item.industry}]` : '';

      text += `${num}. *${compName}*${ind} — 📍 ${location}\n`;
    });

    text += `\n━━━━━━━━━━━━━━━━━━━━━━━━\n`;
    text += `🔗 *CRM Access Link:*\n${crmUrl}\n\n`;
    text += `⚡ *Note:* Please log in to the CRM to view full contact numbers, verified emails, decision makers, and manage your follow-ups.\n`;
    return text.trim();
  };

  SLC.openWhatsAppShareModal = function (opts) {
    opts = opts || {};
    const text = SLC.generateWhatsAppText(opts);
    const assignedTo = opts.assignedToName || 'Sales Executive';
    const totalCount = (opts.items || []).length;
    const typeLabel = opts.typeLabel || 'Records';

    const bodyHtml = `
      <div style="margin-bottom:14px;padding:12px 14px;background:rgba(37,211,102,0.1);border:1px solid rgba(37,211,102,0.25);border-radius:10px;display:flex;align-items:center;gap:12px;">
        <div style="font-size:24px;">💬</div>
        <div style="flex:1;">
          <div style="font-weight:700;color:var(--text);font-size:13.5px;">WhatsApp Assignment Message Ready for ${SLC.escape(assignedTo)}</div>
          <div style="font-size:12px;color:var(--muted);margin-top:2px;">
            ${totalCount} ${SLC.escape(typeLabel)} formatted for WhatsApp. Click <strong>Copy WhatsApp Message</strong> below, then paste it directly to the recipient in WhatsApp.
          </div>
        </div>
      </div>

      <div style="position:relative;">
        <label style="display:block;font-size:11.5px;font-weight:600;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em;">Ready-to-Paste WhatsApp Message:</label>
        <textarea id="slcWaPreviewText" readonly style="width:100%;height:220px;font-family:monospace;font-size:12px;background:var(--panel2);color:var(--text);border:1px solid var(--border);border-radius:8px;padding:12px;resize:vertical;white-space:pre-wrap;line-height:1.45;">${SLC.escape(text)}</textarea>
      </div>
    `;

    const footerHtml = `
      <button class="btn btn-secondary" data-close>Close</button>
      <button class="btn btn-primary" id="slcCopyWaBtn" style="background:linear-gradient(135deg, #25D366, #128C7E);border-color:#25D366;color:#fff;font-weight:700;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(37,211,102,0.35);">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        <span id="slcCopyWaBtnLabel">📋 Copy WhatsApp Message</span>
      </button>
    `;

    if (SLC.modal && typeof SLC.modal.open === 'function') {
      SLC.modal.open({
        title: `💬 WhatsApp Assignment Share (${totalCount} ${typeLabel})`,
        size: 'lg',
        body: bodyHtml,
        footer: footerHtml,
      });
    }

    setTimeout(() => {
      const copyBtn = document.getElementById('slcCopyWaBtn');
      if (copyBtn) {
        copyBtn.addEventListener('click', async () => {
          const textarea = document.getElementById('slcWaPreviewText');
          const content = textarea ? textarea.value : text;
          try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
              await navigator.clipboard.writeText(content);
            } else if (textarea) {
              textarea.select();
              document.execCommand('copy');
            }
            const label = document.getElementById('slcCopyWaBtnLabel');
            if (label) label.textContent = '✓ Copied to Clipboard!';
            copyBtn.style.background = '#059669';
            SLC.toast('✅ WhatsApp message copied to clipboard! You can now paste it into WhatsApp.', 'success');
            setTimeout(() => {
              if (label) label.textContent = '📋 Copy WhatsApp Message';
              copyBtn.style.background = 'linear-gradient(135deg, #25D366, #128C7E)';
            }, 3000);
          } catch (err) {
            SLC.toast('Failed to auto-copy. Please select and copy the text manually.', 'warn');
          }
        });
      }
    }, 50);
  };

  window.SLC = SLC;
})();
