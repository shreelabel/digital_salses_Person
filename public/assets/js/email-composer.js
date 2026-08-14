/* email-composer.js — drafts only, never sent. */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const Companies = api.resource('companies');
  let companies = [];
  async function loadCompanies() { try { const r = await Companies.list({ per_page: 300 }); companies = r.data || []; } catch (e) {} }
  function companyOpts(sel) { return '<option value="">Select company…</option>' + companies.map(c => '<option value="' + c.id + '" ' + (sel == c.id ? 'selected' : '') + '>' + SLC.escape(c.name) + '</option>').join(''); }

  function composer(subject, body, objective, aiMode) {
    return (aiMode ? '<div class="field full" style="margin-bottom:12px"><label class="fld">Sales objective</label><input class="fld" name="objective" value="' + SLC.escape(objective || '') + '" placeholder="e.g. request a 10-min intro / share pharma label samples"></div>' : '') +
      '<div class="field full" style="margin-bottom:12px"><label class="fld">Company *</label><select class="fld" name="company_id">' + companyOpts() + '</select></div>' +
      '<div class="field full" style="margin-bottom:12px"><label class="fld">Subject</label><input class="fld" name="subject" value="' + SLC.escape(subject || '') + '"></div>' +
      '<div class="field full"><label class="fld">Body</label><textarea class="fld" name="body" style="min-height:220px">' + SLC.escape(body || '') + '</textarea></div>' +
      (aiMode ? '<p class="muted" style="font-size:11px">AI will draft this email; it is saved as a draft and never sent.</p>' : '');
  }

  async function loadDrafts() {
    const wrap = document.getElementById('draftList');
    try {
      const res = await api.get('email-messages');
      const rows = res.messages || [];
      wrap.innerHTML = rows.length ? rows.map(m => '<div class="detail-row"><span class="k"><div class="strong">' + SLC.escape(m.subject || '(no subject)') + '</div><div class="muted" style="font-size:11px">' + SLC.escape(m.company_name || '') + ' · ' + SLC.rel(m.created_at) + (m.ai_generated == 1 ? ' · ✨AI' : '') + '</div></span><span class="v"><button class="btn-icon btn-sm" data-view="' + m.id + '">👁️</button> <button class="btn-icon btn-sm" data-del="' + m.id + '">🗑️</button></span></div>').join('') : SLC.ui.empty('No drafts yet', '');
    } catch (e) { wrap.innerHTML = SLC.ui.empty('Failed to load', ''); }
  }
  async function loadTemplates() {
    const wrap = document.getElementById('tplList');
    try {
      const res = await api.get('email-templates');
      const rows = res.templates || [];
      wrap.innerHTML = rows.length ? rows.map(t => '<div class="detail-row"><span class="k"><div class="strong">' + SLC.escape(t.name) + '</div><div class="muted" style="font-size:11px">' + SLC.escape(t.subject || '') + '</div></span><span class="v"><button class="btn-icon btn-sm" data-use="' + t.id + '">↩</button> <button class="btn-icon btn-sm" data-delt="' + t.id + '">🗑️</button></span></div>').join('') : SLC.ui.empty('No templates', '');
    } catch (e) { wrap.innerHTML = SLC.ui.empty('Failed to load', ''); }
  }

  function open(subject, body, objective, aiMode) {
    const m = SLC.modal.open({ title: aiMode ? '✨ AI Email Draft' : '✍️ Compose Draft', body: composer(subject, body, objective, aiMode), footer: '<button class="btn-ghost" data-close>Cancel</button><button class="btn-primary" data-save>' + (aiMode ? 'Generate & Save Draft' : 'Save Draft') + '</button>' });
    m.el.querySelector('[data-save]').addEventListener('click', async () => {
      const data = {}; m.el.querySelectorAll('[name]').forEach(el => data[el.name] = el.value);
      if (!data.company_id) { SLC.toast('Select a company', 'error'); return; }
      const btn = m.el.querySelector('[data-save]'); btn.disabled = true; btn.innerHTML = SLC.ui.spinner();
      try {
        if (aiMode) {
          if (!SLC.aiConfigured()) { SLC.toast('Gemini not configured', 'error'); btn.disabled = false; btn.textContent = 'Generate & Save Draft'; return; }
          const res = await api.post('ai/generate-email', { company_id: data.company_id, objective: data.objective || '' });
          SLC.toast('AI draft saved', 'success'); m.close(); loadDrafts();
        } else {
          if (!data.subject) { SLC.toast('Subject required', 'error'); btn.disabled = false; btn.textContent = 'Save Draft'; return; }
          await api.post('email-messages', data); SLC.toast('Draft saved', 'success'); m.close(); loadDrafts();
        }
      } catch (e) { SLC.toast(e.message, 'error'); btn.disabled = false; btn.textContent = aiMode ? 'Generate & Save Draft' : 'Save Draft'; }
    });
  }

  document.addEventListener('DOMContentLoaded', async function () {
    await loadCompanies(); loadDrafts(); loadTemplates();
    document.getElementById('composeBtn').addEventListener('click', () => open('', '', '', false));
    document.getElementById('aiEmailBtn').addEventListener('click', () => { if (!SLC.aiConfigured()) { SLC.toast('Configure Gemini in AI Settings first', 'error'); return; } open('', '', '', true); });
    document.getElementById('addTpl').addEventListener('click', () => {
      const m = SLC.modal.open({ title: 'Email Template', body: '<div class="field" style="margin-bottom:10px"><label class="fld">Name</label><input class="fld" name="name"></div><div class="field" style="margin-bottom:10px"><label class="fld">Subject</label><input class="fld" name="subject"></div><div class="field"><label class="fld">Body</label><textarea class="fld" name="body"></textarea></div>', footer: '<button class="btn-ghost" data-close>Cancel</button><button class="btn-primary" data-save>Save</button>' });
      m.el.querySelector('[data-save]').addEventListener('click', async () => { const d = {}; m.el.querySelectorAll('[name]').forEach(e => d[e.name] = e.value); if (!d.name) { SLC.toast('Name required', 'error'); return; } try { await api.post('email-templates', d); SLC.toast('Saved', 'success'); m.close(); loadTemplates(); } catch (e) { SLC.toast(e.message, 'error'); } });
    });
    document.body.addEventListener('click', async (e) => {
      const del = e.target.closest('[data-del]'); if (del) { try { await api.del('email-messages/' + del.getAttribute('data-del')); SLC.toast('Deleted', 'success'); loadDrafts(); } catch (er) { SLC.toast(er.message, 'error'); } return; }
      const delt = e.target.closest('[data-delt]'); if (delt) { try { await api.del('email-templates/' + delt.getAttribute('data-delt')); SLC.toast('Deleted', 'success'); loadTemplates(); } catch (er) { SLC.toast(er.message, 'error'); } return; }
      const use = e.target.closest('[data-use]'); if (use) { try { const r = await api.get('email-templates'); const t = (r.templates || []).find(x => x.id == use.getAttribute('data-use')); if (t) open(t.subject, t.body, '', false); } catch (er) {} return; }
      const view = e.target.closest('[data-view]'); if (view) { try { const r = await api.get('email-messages'); const m2 = (r.messages || []).find(x => x.id == view.getAttribute('data-view')); if (m2) { const so = SLC.slideover.open({ title: m2.subject || 'Draft', body: '<div class="muted" style="margin-bottom:10px">' + SLC.escape(m2.company_name || '') + '</div><div style="white-space:pre-wrap;font-size:13px;line-height:1.7">' + SLC.escape(m2.body) + '</div>' }); } } catch (er) {} }
    });
  });
})();
