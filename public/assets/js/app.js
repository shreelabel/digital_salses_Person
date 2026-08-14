/* ============================================================
   app.js — shared bootstrap: sidebar toggle, logout, data-method,
   toasts, auth-state awareness. Loaded on every page before others.
   ============================================================ */
(function () {
  'use strict';
  const SLC = window.SLC || {};

  // ---- Sidebar toggle (mobile) ----
  document.addEventListener('click', function (e) {
    if (e.target.closest('#menuToggle')) {
      document.getElementById('sidebar')?.classList.toggle('open');
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
    return `<div style="font-size:12px;color:var(--text);" title="${SLC.escape(full)}">📅 ${SLC.escape(full)} <span class="muted" style="font-size:10.5px;">(${SLC.escape(rel)})</span></div>`;
  };

  SLC.assigneeBadge = function (userName, assignedAt) {
    if (!userName) return '<span class="muted">— Unassigned</span>';
    let timeHtml = '';
    if (assignedAt) {
      const full = SLC.formatDate(assignedAt, true);
      const rel = SLC.rel(assignedAt);
      timeHtml = `<div class="muted" style="font-size:10px;margin-top:2px;" title="Assigned on: ${SLC.escape(full)}">📅 Assigned: ${SLC.escape(full)} <span style="opacity:0.85">(${SLC.escape(rel)})</span></div>`;
    }
    return `<div><span class="badge" style="background:var(--panel2);border:1px solid var(--border);color:var(--text);font-weight:600;font-size:11px;">👤 ${SLC.escape(userName)}</span>${timeHtml}</div>`;
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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', SLC.refreshSidebarCounters);
  } else {
    SLC.refreshSidebarCounters();
  }

  window.SLC = SLC;
})();
