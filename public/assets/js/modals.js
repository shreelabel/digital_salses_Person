/* ============================================================
   modals.js — modal dialog + slide-over panel helpers.
   Supports both SLC.modal(opts) and SLC.modal.open(opts).
   ============================================================ */
(function () {
  'use strict';
  const SLC = window.SLC || (window.SLC = {});

  function openModal(opts) {
    opts = opts || {};
    const root = document.getElementById('modalRoot');
    if (!root) return { el: null, close: () => {} };

    const back = document.createElement('div');
    back.className = 'modal-backdrop';
    const sizeCls = opts.size === 'lg' ? 'modal-lg' : (opts.size === 'sm' ? 'modal-sm' : '');

    let footerHtml = opts.footer || '';
    if (!footerHtml && Array.isArray(opts.actions) && opts.actions.length) {
      footerHtml = opts.actions.map((act, idx) => {
        const cls = act.danger ? 'btn-danger' : (act.primary ? 'btn-primary' : (act.ghost ? 'btn-ghost' : 'btn-secondary'));
        return `<button type="button" class="${cls}" data-action-idx="${idx}" ${act.close ? 'data-close' : ''}>${SLC.escape(act.label || 'Action')}</button>`;
      }).join(' ');
    }

    back.innerHTML =
      '<div class="modal ' + sizeCls + '">' +
        '<div class="modal-head"><h3>' + SLC.escape(opts.title || '') + '</h3>' +
          '<button type="button" class="btn-icon" data-close title="Close">✕</button></div>' +
        '<div class="modal-body">' + (opts.body || '') + '</div>' +
        (footerHtml ? '<div class="modal-foot">' + footerHtml + '</div>' : '') +
      '</div>';

    root.appendChild(back);

    const modalObj = {
      el: back,
      close: () => back.remove()
    };

    // Bind action callbacks
    if (Array.isArray(opts.actions)) {
      opts.actions.forEach((act, idx) => {
        if (typeof act.onClick === 'function') {
          const btn = back.querySelector(`[data-action-idx="${idx}"]`);
          if (btn) {
            btn.addEventListener('click', () => act.onClick(modalObj));
          }
        }
      });
    }

    back.addEventListener('click', (e) => {
      if (e.target === back || e.target.closest('[data-close]')) {
        modalObj.close();
        if (typeof opts.onClose === 'function' && e.target.closest('[data-close]')) {
          opts.onClose();
        }
      }
    });

    if (typeof opts.onOpen === 'function') {
      try {
        opts.onOpen(back.querySelector('.modal'));
      } catch (err) {
        console.error('modal onOpen error:', err);
      }
    }

    return modalObj;
  }

  // Callable modal function with static methods
  const modalFn = function (opts) {
    return openModal(opts);
  };

  modalFn.open = function (opts) {
    return openModal(opts);
  };

  modalFn.confirm = function (opts) {
    return new Promise((resolve) => {
      const m = openModal({
        title: opts.title || 'Confirm Action',
        body: '<p style="color:var(--muted);font-size:13.5px;margin:8px 0;">' + SLC.escape(opts.message || 'Are you sure you want to proceed?') + '</p>',
        footer: '<button class="btn-ghost" data-close>Cancel</button><button class="btn-danger" data-confirm>Confirm</button>',
      });
      m.el.addEventListener('click', (e) => {
        if (e.target.closest('[data-confirm]')) {
          m.close();
          resolve(true);
        }
      });
    });
  };

  SLC.modal = modalFn;

  // Slide-over helper
  SLC.slideover = {
    open(opts) {
      opts = opts || {};
      const root = document.getElementById('slideoverRoot');
      if (!root) return { el: null, close: () => {} };

      const wrap = document.createElement('div');
      wrap.innerHTML =
        '<div class="slideover-backdrop"></div>' +
        '<aside class="slideover"><div class="slideover-head"><h3>' + SLC.escape(opts.title || '') + '</h3>' +
        '<button type="button" class="btn-icon" data-close title="Close">✕</button></div>' +
        '<div class="slideover-body">' + (opts.body || '') + '</div></aside>';

      root.appendChild(wrap);
      requestAnimationFrame(() => wrap.querySelector('.slideover')?.classList.add('open'));

      const close = () => {
        const s = wrap.querySelector('.slideover');
        if (s) s.classList.remove('open');
        setTimeout(() => wrap.remove(), 250);
      };

      wrap.addEventListener('click', (e) => {
        if (e.target.classList.contains('slideover-backdrop') || e.target.closest('[data-close]')) {
          close();
          if (typeof opts.onClose === 'function') opts.onClose();
        }
      });

      return { el: wrap, close: close };
    },
  };

  // Form utilities
  SLC.formCollect = function (container) {
    const root = (container && container.el) ? container.el : (container || document);
    const data = {};
    root.querySelectorAll('[name]').forEach((el) => {
      const name = el.getAttribute('name');
      if (!name) return;
      if (el.type === 'checkbox') {
        data[name] = el.checked ? 1 : 0;
      } else if (el.type === 'radio') {
        if (el.checked) data[name] = el.value;
      } else {
        data[name] = el.value;
      }
    });
    return data;
  };

  SLC.formPopulate = function (container, data) {
    if (!data || typeof data !== 'object') return;
    const root = (container && container.el) ? container.el : (container || document);
    Object.keys(data).forEach((key) => {
      const el = root.querySelector(`[name="${key}"]`);
      if (!el) return;
      if (el.type === 'checkbox') {
        el.checked = (data[key] == 1 || data[key] === true);
      } else if (el.type === 'radio') {
        const radio = root.querySelector(`[name="${key}"][value="${data[key]}"]`);
        if (radio) radio.checked = true;
      } else {
        el.value = data[key] !== null && data[key] !== undefined ? data[key] : '';
      }
    });
  };

  window.SLC = SLC;
})();
