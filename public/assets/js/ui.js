/* ============================================================
   ui.js — shared rendering helpers: badges, score rings, tables,
   empty states, formatters. Pure DOM helpers, no fetch logic.
   ============================================================ */
(function () {
  'use strict';
  const SLC = window.SLC || (window.SLC = {});

  SLC.ui = {
    priorityBadge(p) {
      const cls = { High: 'badge-high', Medium: 'badge-medium', Low: 'badge-low' }[p] || 'badge-gray';
      return '<span class="badge ' + cls + '">' + SLC.escape(p || '—') + '</span>';
    },

    statusBadge(status) {
      const map = {
        Active: 'badge-active', New: 'badge-new', Interested: 'badge-new', Won: 'badge-won', Lost: 'badge-lost',
        Contacted: 'badge-purple', Requirement: 'badge-purple', Quotation: 'badge-purple', Negotiation: 'badge-medium',
      };
      const cls = map[status] || 'badge-gray';
      return '<span class="badge ' + cls + '">' + SLC.escape(status || '—') + '</span>';
    },

    statusPill(status) {
      const safe = SLC.escape(status || '');
      const cls = 'status-' + safe.replace(/[^a-zA-Z]/g, '');
      return '<span class="status-pill ' + cls + '">' + safe + '</span>';
    },

    scoreColor(s) {
      if (s >= 70) return '#22c55e';
      if (s >= 40) return '#f59e0b';
      return '#ef4444';
    },

    scoreRing(score, size) {
      size = size || 34;
      if (score === null || score === undefined || score === '') {
        return '<span class="muted">—</span>';
      }
      const s = Math.max(0, Math.min(100, parseInt(score, 10)));
      const col = SLC.ui.scoreColor(s);
      const r = (size / 2) - 3;
      const circ = 2 * Math.PI * r;
      const off = circ * (1 - s / 100);
      return '<span class="score-ring" style="width:' + size + 'px;height:' + size + 'px;background:conic-gradient(' + col + ' ' + (s * 3.6) + 'deg,#2a3050 0)">' +
        '<span style="width:' + (size - 6) + 'px;height:' + (size - 6) + 'px;border-radius:50%;background:var(--panel);display:grid;place-items:center;font-size:' + Math.max(9, size / 3) + 'px;color:' + col + '">' + s + '</span>' +
        '</span>';
    },

    scoreBar(score) {
      if (score === null || score === undefined || score === '') return '<span class="muted">—</span>';
      const s = Math.max(0, Math.min(100, parseInt(score, 10)));
      const col = SLC.ui.scoreColor(s);
      return '<span class="score"><span class="score-bar"><i style="width:' + s + '%;background:' + col + '"></i></span><span class="score-num" style="color:' + col + '">' + s + '</span></span>';
    },

    empty(title, subtitle) {
      return '<div class="empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-6l-2-3H5a2 2 0 0 0-2 2Z"/></svg>' +
        '<div class="strong">' + SLC.escape(title || 'Nothing here yet') + '</div>' +
        (subtitle ? '<div style="margin-top:4px">' + SLC.escape(subtitle) + '</div>' : '') + '</div>';
    },

    spinner() { return '<span class="spinner"></span>'; },

    sources(list) {
      if (!list || !list.length) return '<span class="muted">No sources</span>';
      return '<div class="tag-row">' + list.slice(0, 3).map(function (u) {
        const safe = SLC.escape(u);
        return '<a class="src-link" target="_blank" rel="noopener" href="' + safe + '" title="' + safe + '">🔗 ' + SLC.escape(SLC.ui.host(u)) + '</a>';
      }).join('') + '</div>';
    },

    host(url) {
      try { return new URL(url).host.replace(/^www\./, ''); } catch (e) { return url; }
    },

    confirmDialog(message) {
      return window.confirm(message);
    },

    field(label, value) {
      return '<div class="detail-row"><span class="k">' + SLC.escape(label) + '</span><span class="v">' + (value !== null && value !== undefined && value !== '' ? SLC.escape(value) : '<span class="muted">—</span>') + '</span></div>';
    },
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

  /**
   * Reusable multi-select and bulk deletion controller.
   */
  SLC.ui.bindBulkActions = function (opts) {
    const selectAll = document.getElementById(opts.selectAllId);
    const bulkBar = document.getElementById(opts.bulkBarId);
    const countEl = document.getElementById(opts.countId);
    const deleteBtn = document.getElementById(opts.deleteBtnId);
    const clearBtn = document.getElementById(opts.clearBtnId);
    const rowSelector = opts.rowSelector || '.row-cb';
    const resource = opts.resource;
    const entityName = opts.entityName || 'items';
    const onDeleted = opts.onDeleted;

    let selectedIds = new Set();

    function updateUi() {
      const allRowCbs = document.querySelectorAll(rowSelector);
      const totalRows = allRowCbs.length;
      let checkedInView = 0;

      allRowCbs.forEach(function (cb) {
        const id = cb.getAttribute('data-id');
        const isChecked = selectedIds.has(id);
        cb.checked = isChecked;
        const tr = cb.closest('tr');
        if (tr) {
          tr.classList.toggle('selected', isChecked);
        }
        if (isChecked) checkedInView++;
      });

      if (selectAll) {
        selectAll.checked = totalRows > 0 && checkedInView === totalRows;
        selectAll.indeterminate = checkedInView > 0 && checkedInView < totalRows;
      }

      if (countEl) {
        countEl.textContent = selectedIds.size;
      }

      if (bulkBar) {
        bulkBar.classList.toggle('active', selectedIds.size > 0);
      }
    }

    selectAll?.addEventListener('change', function () {
      const allRowCbs = document.querySelectorAll(rowSelector);
      const isChecked = this.checked;
      allRowCbs.forEach(function (cb) {
        const id = cb.getAttribute('data-id');
        if (!id) return;
        if (isChecked) {
          selectedIds.add(id);
        } else {
          selectedIds.delete(id);
        }
      });
      updateUi();
    });

    document.addEventListener('change', function (e) {
      if (e.target && e.target.matches(rowSelector)) {
        const id = e.target.getAttribute('data-id');
        if (!id) return;
        if (e.target.checked) {
          selectedIds.add(id);
        } else {
          selectedIds.delete(id);
        }
        updateUi();
      }
    });

    clearBtn?.addEventListener('click', function () {
      selectedIds.clear();
      updateUi();
    });

    deleteBtn?.addEventListener('click', async function () {
      const count = selectedIds.size;
      if (!count) return;
      const msg = 'Are you sure you want to delete the ' + count + ' selected ' + entityName + '? This action cannot be undone.';
      if (!confirm(msg)) return;

      const ids = Array.from(selectedIds).map(Number);
      deleteBtn.disabled = true;
      deleteBtn.textContent = 'Deleting…';

      try {
        if (resource && typeof resource.bulkDelete === 'function') {
          await resource.bulkDelete(ids);
        } else if (typeof opts.customDelete === 'function') {
          await opts.customDelete(ids);
        }
        SLC.toast('Successfully deleted ' + count + ' ' + entityName + '.', 'success');
        selectedIds.clear();
        updateUi();
        if (typeof onDeleted === 'function') {
          onDeleted();
        }
      } catch (err) {
        SLC.toast(err.message || ('Failed to delete ' + entityName + '.'), 'error');
      } finally {
        deleteBtn.disabled = false;
        deleteBtn.textContent = '🗑️ Delete Selected';
      }
    });

    return {
      update: updateUi,
      clear: function () {
        selectedIds.clear();
        updateUi();
      },
      getSelected: function () {
        return Array.from(selectedIds);
      }
    };
  };

  SLC.initBulkActions = SLC.ui.bindBulkActions;
  window.SLC = SLC;
})();
