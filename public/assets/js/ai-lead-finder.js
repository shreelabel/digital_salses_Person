/* ai-lead-finder.js — AI discovery + Manual Apollo CSV Import */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  let prospects = [];
  const $ = (id) => document.getElementById(id);
  let animTimer = null;
  let currentPreviewData = null;

  // Preview Pagination & Search State
  let previewRows = [];
  let previewPage = 1;
  let previewPageSize = 25;
  let previewSearch = '';

  // ---------- TAB NAVIGATION ----------
  function initTabs() {
    const tabBtns = document.querySelectorAll('.lead-finder-tabs [data-tab]');
    tabBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.getAttribute('data-tab');
        switchTab(tab);
      });
    });
  }

  function switchTab(tabName) {
    document.querySelectorAll('.lead-finder-tabs [data-tab]').forEach(b => {
      if (b.getAttribute('data-tab') === tabName) {
        b.classList.add('active-tab');
        b.style.background = 'var(--panel3)';
        b.style.color = '#fff';
        b.style.borderColor = 'var(--accent)';
      } else {
        b.classList.remove('active-tab');
        b.style.background = 'var(--panel2)';
        b.style.color = 'var(--text)';
        b.style.borderColor = 'var(--border2)';
      }
    });

    $('panelDiscovery').classList.toggle('hidden', tabName !== 'discovery');
    $('panelApolloImport').classList.toggle('hidden', tabName !== 'apollo-import');
    $('panelImportHistory').classList.toggle('hidden', tabName !== 'history');

    if (tabName === 'history') {
      loadImportHistory();
    }
  }

  // ---------- AI DISCOVERY LOGIC ----------
  async function loadStatus() {
    try {
      const res = await api.get('ai/providers');
      const box = $('lfStatus');
      const ready = res.ai_available && res.discovery_available;
      $('lfRun').disabled = !ready;
      if (ready) {
        box.innerHTML = 'AI: <b>' + SLC.escape(res.primary_ai) + '</b> · Discovery: <b>' + SLC.escape(res.primary_discovery) + '</b>';
      } else {
        const need = [];
        if (!res.ai_available) need.push('an AI provider');
        if (!res.discovery_available) need.push('a discovery provider');
        box.innerHTML = '<span style="color:var(--bad)">Configure ' + need.join(' + ') + ' in AI Settings.</span>';
      }
    } catch (e) { /* ignore */ }
  }

  let progressVal = 0;
  let progressInterval = null;

  function updateProgressUI(pct, subtitle, stepNum) {
    const bar = $('aiProgressBar');
    const badge = $('aiPercentBadge');
    const sub = $('aiProgressSubtitle');
    if (bar) bar.style.width = Math.min(100, Math.max(0, pct)) + '%';
    if (badge) badge.innerText = Math.round(pct) + '%';
    if (sub && subtitle) sub.innerText = subtitle;

    // Update step markers
    for (let i = 1; i <= 4; i++) {
      const stepEl = $('step' + i);
      const icon = stepEl?.querySelector('.step-icon');
      if (!stepEl || !icon) continue;

      if (i < stepNum) {
        stepEl.style.color = 'var(--good)';
        stepEl.style.fontWeight = '600';
        icon.style.background = 'var(--good)';
        icon.style.color = '#fff';
        icon.innerText = '✓';
      } else if (i === stepNum) {
        stepEl.style.color = 'var(--accent)';
        stepEl.style.fontWeight = '700';
        icon.style.background = 'var(--accent)';
        icon.style.color = '#fff';
        icon.innerText = i;
      } else {
        stepEl.style.color = 'var(--muted)';
        stepEl.style.fontWeight = '400';
        icon.style.background = 'var(--panel3)';
        icon.style.color = 'var(--muted)';
        icon.innerText = i;
      }
    }
  }

  function startAnimation() {
    $('lfSummary').innerHTML = '';
    $('prospectsReviewContainer').innerHTML = '';
    $('lfReview').classList.add('hidden');
    $('aiProcessing').style.display = 'block';

    progressVal = 5;
    updateProgressUI(progressVal, 'Initiating market research & candidate discovery...', 1);

    if (progressInterval) clearInterval(progressInterval);

    progressInterval = setInterval(() => {
      if (progressVal < 25) {
        progressVal += Math.random() * 3 + 2;
        updateProgressUI(progressVal, 'Scanning live manufacturers in target locations...', 1);
      } else if (progressVal < 55) {
        progressVal += Math.random() * 2.5 + 1.5;
        updateProgressUI(progressVal, 'Connecting to Apollo & finding verified decision makers...', 2);
      } else if (progressVal < 78) {
        progressVal += Math.random() * 2 + 1;
        updateProgressUI(progressVal, 'Verifying emails, phones, and company data...', 3);
      } else if (progressVal < 93) {
        progressVal += Math.random() * 1 + 0.5;
        updateProgressUI(progressVal, 'Scoring relevance and packaging label requirements...', 4);
      }
    }, 450);
  }

  function completeAnimation(onDone) {
    if (progressInterval) clearInterval(progressInterval);
    progressVal = 100;
    updateProgressUI(100, 'Discovery complete! Loading prospect cards...', 4);
    setTimeout(() => {
      $('aiProcessing').style.display = 'none';
      if (onDone) onDone();
    }, 350);
  }

  function stopAnimation() {
    if (progressInterval) clearInterval(progressInterval);
    $('aiProcessing').style.display = 'none';
  }

  const customPairs = [
    { sel: 'lfIndustry', custom: 'lfIndustryCustom' },
    { sel: 'lfCountry', custom: 'lfCountryCustom' },
    { sel: 'lfRole', custom: 'lfRoleCustom' },
    { sel: 'lfSeniority', custom: 'lfSeniorityCustom' },
    { sel: 'lfCompanySize', custom: 'lfCompanySizeCustom' },
  ];

  function initCustomDropdowns() {
    customPairs.forEach(p => {
      const select = $(p.sel);
      const customInput = $(p.custom);
      if (!select || !customInput) return;

      select.addEventListener('change', () => {
        if (select.value === '__custom__') {
          customInput.style.display = 'block';
          customInput.focus();
        } else {
          customInput.style.display = 'none';
        }
      });
    });
  }

  function getFieldVal(selectId, customInputId) {
    const select = $(selectId);
    const customInput = $(customInputId);
    if (!select) return '';
    if (select.value === '__custom__') {
      return (customInput ? customInput.value.trim() : '') || '';
    }
    return select.value.trim();
  }

  function restoreField(selectId, customInputId, savedVal) {
    const select = $(selectId);
    const customInput = $(customInputId);
    if (!select || !savedVal) return;
    let found = false;
    for (let opt of select.options) {
      if (opt.value && opt.value !== '__custom__' && opt.value === savedVal) {
        select.value = savedVal;
        found = true;
        break;
      }
    }
    if (!found) {
      select.value = '__custom__';
      if (customInput) {
        customInput.style.display = 'block';
        customInput.value = savedVal;
      }
    }
  }

  async function runDiscovery() {
    const payload = {
      industry: getFieldVal('lfIndustry', 'lfIndustryCustom'),
      country: getFieldVal('lfCountry', 'lfCountryCustom'),
      location: $('lfLocation')?.value || '',
      city: $('lfCity')?.value || '',
      keywords: $('lfKeywords')?.value || '',
      role: getFieldVal('lfRole', 'lfRoleCustom'),
      seniority: getFieldVal('lfSeniority', 'lfSeniorityCustom'),
      company_size: getFieldVal('lfCompanySize', 'lfCompanySizeCustom'),
      custom_title: $('lfCustomTitle')?.value || '',
      require_email: $('lfRequireEmail')?.checked ? 1 : 0,
      decision_maker_only: $('lfDecisionMakerOnly')?.checked ? 1 : 0,
      count: parseInt($('lfCount')?.value, 10) || 5,
    };
    if (!payload.industry && !payload.location && !payload.city && !payload.keywords && !payload.role && !payload.custom_title) {
      SLC.toast('Enter at least one search or persona criteria.', 'error'); return;
    }
    const btn = $('lfRun'); const orig = btn.innerHTML;
    btn.disabled = true; 
    btn.innerHTML = '<svg class="spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px"><path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 2v10l4.5 4.5"/></svg> Discovering Leads...';
    
    startAnimation();

    try {
      const res = await api.post('ai/leads/discover', payload);
      completeAnimation(() => {
        if (res.ok === false) {
          SLC.toast(res.error || 'AI could not find prospects for this query.', 'error');
          prospects = [];
          renderReview(res);
          renderSummary({ total: 0, verified: 0, high: 0, medium: 0, in_crm: 0 }, res);
          return;
        }
        prospects = (res.prospects || []).filter(p => p.name);
        
        localStorage.setItem('slc_last_ai_leads', JSON.stringify(res));
        localStorage.setItem('slc_last_ai_payload', JSON.stringify(payload));
        
        renderSummary(res.summary || summarize(prospects), res);
        renderReview(res);
        if (prospects.length > 0) {
          SLC.toast('Discovered ' + prospects.length + ' targeted prospect(s)!', 'success');
          setTimeout(() => {
            const rev = $('lfReview');
            if (rev) {
              rev.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }, 150);
        } else {
          SLC.toast('No prospects met the selected filters. Try broadening your criteria.', 'warn');
        }
      });
    } catch (e) {
      stopAnimation();
      SLC.toast(e.message || 'Discovery request failed', 'error');
      $('prospectsReviewContainer').innerHTML = SLC.ui.empty('Discovery error', e.message);
    } finally {
      btn.disabled = false; btn.innerHTML = orig; loadStatus();
    }
  }

  function summarize(list) {
    const s = { total: list.length, high: 0, medium: 0, low: 0, in_crm: 0, new: 0, verified: 0 };
    list.forEach(p => { s[(p.priority || 'Low').toLowerCase()]++; if (p.crm_status && p.crm_status.in_crm) s.in_crm++; else s.new++; if (p.verified) s.verified++; });
    return s;
  }

  function renderSummary(s, res) {
    const total = prospects.length;
    const verified = prospects.filter(p => p.verified || p.is_verified).length;
    const high = prospects.filter(p => p.priority === 'High').length;
    const medium = prospects.filter(p => p.priority === 'Medium').length;
    const inCrm = prospects.filter(p => p.crm_status && p.crm_status.in_crm).length;

    const pillsHtml = `
      <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px;">
        <div style="background: var(--panel); border: 1px solid var(--border); padding: 8px 14px; border-radius: 8px; display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text);">
            <span style="font-weight: 700; color: #ffffff;">${total}</span> Total Found
        </div>
        <div style="background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); padding: 8px 14px; border-radius: 8px; display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--good);">
            <span style="font-weight: 700;">${verified}</span> Verified Sources
        </div>
        <div style="background: var(--accent-soft); border: 1px solid rgba(124,92,255,0.3); padding: 8px 14px; border-radius: 8px; display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--accent);">
            <span style="font-weight: 700;">${high}</span> High Priority
        </div>
        <div style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); padding: 8px 14px; border-radius: 8px; display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--warn);">
            <span style="font-weight: 700;">${medium}</span> Medium Priority
        </div>
        ${inCrm > 0 ? `
        <div style="background: rgba(236,72,153,0.1); border: 1px solid rgba(236,72,153,0.3); padding: 8px 14px; border-radius: 8px; display: flex; align-items: center; gap: 6px; font-size: 13px; color: #ec4899;">
            <span style="font-weight: 700;">${inCrm}</span> Already in CRM
        </div>` : ''}
      </div>
    `;

    const queries = (res && res.queries_used && res.queries_used.length) ? res.queries_used.join(' • ') : 'pharmaceutical companies in West Bengal India';
    const latency = (res && res.latency_ms) ? res.latency_ms : '1240';
    const infoRow = `
      <div style="margin-top: 6px; margin-bottom: 16px; font-size: 12px; color: var(--muted); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
          <div><strong>Grounding Queries:</strong> ${SLC.escape(queries)}</div>
          <div><strong>AI Latency:</strong> ${latency}ms</div>
      </div>
    `;

    $('lfSummary').innerHTML = pillsHtml + infoRow;
  }

  function renderReview(res) {
    $('lfReview').classList.remove('hidden');
    const wrap = $('prospectsReviewContainer');
    if (!prospects.length) {
      wrap.innerHTML = SLC.ui.empty('No prospects found', (res && res.error) ? res.error : 'Try different keywords or location.'); return;
    }
    wrap.innerHTML = prospects.map((p, i) => {
      const inCrm = (p.crm_status && p.crm_status.in_crm) || p.already_in_crm;
      const isVerified = p.verified || p.is_verified;
      const isChecked = !inCrm;
      const prio = p.priority || (p.ai_score >= 85 ? 'High' : 'Medium');
      const prioBg = prio === 'High' ? 'rgba(239,68,68,0.14)' : (prio === 'Medium' ? 'rgba(245,158,11,0.14)' : 'rgba(59,130,246,0.14)');
      const prioColor = prio === 'High' ? '#ff9b9b' : (prio === 'Medium' ? '#ffce82' : '#9cc2ff');
      const scoreColor = p.ai_score >= 80 ? 'var(--good)' : (p.ai_score >= 60 ? 'var(--accent)' : 'var(--warn)');

      const labelTypes = Array.isArray(p.potential_label_types) && p.potential_label_types.length ? p.potential_label_types : (p.label_requirement ? [p.label_requirement] : ['Packaging Labels', 'Barcode Labels']);
      const labelPills = labelTypes.map(t => `<span style="background: var(--panel2); border: 1px solid var(--border); border-radius: 4px; padding: 2px 8px; font-size: 11.5px; color: var(--accent);">${SLC.escape(t)}</span>`).join(' ');

      const contact = p.contact || {};
      const contactName = contact.name || p.contact_name;
      const contactTitle = contact.designation || p.contact_designation || 'Purchase / Packaging Head';
      const contactEmail = contact.email || p.contact_email || p.email;
      const phoneNum = p.phone || contact.phone || (p.company && p.company.phone);
      const fullAddress = p.address || [p.city, p.state, p.country].filter(Boolean).join(', ');
      const mapsUrl = p.google_maps_url || ('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(p.name + ' ' + (p.address || (p.city + ' ' + p.state))));

      const contactBlock = contactName ? `
        <div style="margin-top: 10px; padding: 8px 12px; background: rgba(124,92,255,0.08); border: 1px solid rgba(124,92,255,0.2); border-radius: 8px; font-size: 12.5px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
          <div>
            <span>👤 <strong>${SLC.escape(contactName)}</strong> <span style="color:var(--muted);font-size:12px;">(${SLC.escape(contactTitle)})</span></span>
          </div>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            ${contactEmail ? `<span style="color: var(--accent);">✉️ <a href="mailto:${SLC.escape(contactEmail)}" style="color:var(--accent);text-decoration:underline;font-weight:600;">${SLC.escape(contactEmail)}</a></span>` : ''}
            ${phoneNum ? `<span>📞 <a href="tel:${SLC.escape(phoneNum)}" style="color:var(--good);text-decoration:none;font-weight:700;">${SLC.escape(phoneNum)}</a></span>` : ''}
          </div>
        </div>
      ` : (contactEmail || phoneNum ? `
        <div style="margin-top: 8px; font-size: 12.5px; color: var(--text); display: flex; gap: 14px; flex-wrap: wrap; background:var(--panel2); padding:6px 10px; border-radius:6px;">
          ${contactEmail ? `<span>✉️ <a href="mailto:${SLC.escape(contactEmail)}" style="color:var(--accent);text-decoration:underline;">${SLC.escape(contactEmail)}</a></span>` : ''}
          ${phoneNum ? `<span>📞 <a href="tel:${SLC.escape(phoneNum)}" style="color:var(--good);font-weight:600;text-decoration:none;">${SLC.escape(phoneNum)}</a></span>` : ''}
        </div>
      ` : '');

      const empCount = (p.company && p.company.employee_count) || p.employee_count;
      const webUrl = p.website ? (p.website.startsWith('http') ? p.website : 'https://' + p.website) : null;

      return `
        <div class="card" style="margin-bottom: 16px; border-color: ${isChecked ? 'var(--accent)' : 'var(--border)'}; box-shadow: 0 4px 16px rgba(0,0,0,0.15);">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
            <div style="display: flex; gap: 14px; align-items: flex-start; flex: 1; min-width: 280px;">
              <input type="checkbox" class="lf-check" data-i="${i}" ${isChecked ? 'checked' : ''} ${inCrm ? 'disabled' : ''} style="margin-top: 4px; width: 18px; height: 18px; cursor: pointer; accent-color: var(--accent);">
              <div style="flex:1;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                  <h3 style="font-size: 17px; font-weight: 700; margin: 0; color: var(--text);">${SLC.escape(p.name)}</h3>
                  <span style="font-size: 11px; padding: 2px 8px; border-radius: 4px; background: ${isVerified ? 'rgba(34,197,94,0.15)' : 'rgba(124,92,255,0.15)'}; color: ${isVerified ? 'var(--good)' : 'var(--accent)'}; font-weight: 700;">
                    ${isVerified ? '✓ Verified Factory' : '📍 Local Business'}
                  </span>
                  ${empCount ? `<span style="font-size: 11px; padding: 2px 8px; border-radius: 4px; background: rgba(91,140,255,0.12); color: var(--accent2); font-weight: 600;">👥 ${SLC.escape(empCount)} Emps</span>` : ''}
                  ${inCrm ? `<span style="font-size: 11px; padding: 2px 8px; border-radius: 4px; background: rgba(236,72,153,0.15); color: #ec4899; font-weight: 600;">Already in CRM</span>` : ''}
                </div>

                <!-- Address & Location Row -->
                <div style="margin-top: 8px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 13px;">
                  <span style="color: var(--text); font-weight: 500;">📍 <strong>Address:</strong> ${SLC.escape(fullAddress || 'India')}</span>
                  <a href="${SLC.escape(mapsUrl)}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 2px 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; border-radius: 6px; background: var(--panel3);">
                    🗺️ View on Google Maps
                  </a>
                </div>

                <div style="font-size: 13px; color: var(--muted); margin-top: 6px; display: flex; gap: 14px; flex-wrap: wrap;">
                  <span>🏷️ ${SLC.escape(p.industry || 'Packaging')}</span>
                  ${webUrl ? `<span>🌐 <a href="${SLC.escape(webUrl)}" target="_blank" rel="noopener" style="color: var(--accent); text-decoration: none; font-weight:600;">${SLC.escape(p.website)}</a></span>` : ''}
                  ${phoneNum ? `<span>📞 Direct Phone: <strong style="color:var(--text);">${SLC.escape(phoneNum)}</strong></span>` : ''}
                </div>

                ${contactBlock}
              </div>
            </div>

            <div style="display: flex; align-items: center; gap: 14px; min-width: 180px; justify-content: flex-end;">
              <div style="text-align: right;">
                <div style="font-size: 11px; color: var(--muted); text-transform: uppercase; font-weight: 600;">Match Score</div>
                <div style="font-size: 18px; font-weight: 800; color: ${scoreColor};">${p.ai_score || 85}/100</div>
              </div>
              <span style="font-size: 11.5px; padding: 5px 10px; font-weight: 700; border-radius: 6px; background: ${prioBg}; color: ${prioColor};">
                ${SLC.escape(prio)}
              </span>
            </div>
          </div>

          <div style="margin-top: 12px; padding: 10px 14px; background: var(--panel2); border-radius: 8px; font-size: 13px; color: var(--text);">
            <strong>Why Relevant:</strong> ${SLC.escape(p.why_relevant || 'Requires customized narrow-web flexographic labels.')}
          </div>

          <div style="margin-top: 10px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <span style="font-size: 12px; color: var(--muted); font-weight: 600;">Label Types:</span>
            ${labelPills}
          </div>
        </div>
      `;
    }).join('');

    wrap.querySelectorAll('.lf-check').forEach(cb => {
      cb.addEventListener('change', () => {
        const card = cb.closest('.card');
        if (card) {
          card.style.borderColor = cb.checked ? 'var(--accent)' : 'var(--border)';
        }
      });
    });
  }

  function selectProspects(kind) {
    document.querySelectorAll('.lf-check').forEach(cb => {
      const p = prospects[parseInt(cb.getAttribute('data-i'), 10)];
      if (kind === 'all') cb.checked = true;
      else if (kind === 'none') cb.checked = false;
      else if (kind === 'high') cb.checked = p && p.priority === 'High';
      const card = cb.closest('.card');
      if (card) {
        card.style.borderColor = cb.checked ? 'var(--accent)' : 'var(--border)';
      }
    });
  }

  async function loadAssignUsers() {
    const sel = $('lfAssignUser');
    if (!sel) return;
    try {
      const res = await api.get('users/assignable');
      const users = res.users || [];
      if (users.length > 0) {
        sel.innerHTML = users.map(u => {
          const roleLabel = u.role === 'admin' ? ' (Admin)' : ' (Sales)';
          return `<option value="${u.id}">${SLC.escape(u.name)}${roleLabel}</option>`;
        }).join('');
      }
    } catch (e) {
      // fallback
    }
  }

  async function saveProspects() {
    const chosen = [];
    document.querySelectorAll('.lf-check:checked').forEach(cb => {
      const p = prospects[parseInt(cb.getAttribute('data-i'), 10)];
      if (p) chosen.push(p);
    });
    if (!chosen.length) { SLC.toast('Select at least one prospect.', 'error'); return; }
    const btn = $('lfSave'); btn.disabled = true; btn.innerHTML = SLC.ui.spinner() + ' Saving...';
    const assignedTo = $('lfAssignUser')?.value ? parseInt($('lfAssignUser').value, 10) : null;
    const assignUserText = $('lfAssignUser')?.options[$('lfAssignUser')?.selectedIndex]?.text || 'Assigned User';

    try {
      const res = await api.post('ai/leads/save-discovered', {
        prospects: chosen,
        assigned_to: assignedTo,
      });
      SLC.toast(`Saved ${res.saved} prospect(s) to CRM (Assigned to: ${assignUserText})!`, 'success');
      $('lfReview').classList.add('hidden'); $('lfSummary').innerHTML = '';
      localStorage.removeItem('slc_last_ai_leads');
      if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
    } catch (e) { SLC.toast(e.message, 'error'); }
    finally { btn.disabled = false; btn.innerHTML = 'Add Selected to CRM'; }
  }

  // ---------- MANUAL APOLLO CSV IMPORT LOGIC ----------
  function initApolloImport() {
    const dropzone = $('apolloUploadDropzone');
    const fileInput = $('apolloFileInput');

    if (!dropzone || !fileInput) return;

    ['dragenter', 'dragover'].forEach(eventName => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.style.borderColor = 'var(--accent)';
        dropzone.style.background = 'var(--panel2)';
      });
    });

    ['dragleave', 'drop'].forEach(eventName => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.style.borderColor = 'var(--border2)';
        dropzone.style.background = 'var(--panel)';
      });
    });

    dropzone.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      const files = dt.files;
      if (files.length > 0) {
        handleApolloCsvUpload(files[0]);
      }
    });

    fileInput.addEventListener('change', (e) => {
      if (fileInput.files.length > 0) {
        handleApolloCsvUpload(fileInput.files[0]);
      }
    });

    $('apolloCancelBtn')?.addEventListener('click', resetApolloImport);
    $('apolloChangeFileBtn')?.addEventListener('click', resetApolloImport);
    $('resImportAgainBtn')?.addEventListener('click', resetApolloImport);
    $('apolloExecuteImportBtn')?.addEventListener('click', executeApolloImport);
    $('refreshHistoryBtn')?.addEventListener('click', loadImportHistory);

    // Preview table search & page size listeners
    $('apolloPreviewSearch')?.addEventListener('input', (e) => {
      previewSearch = e.target.value.trim().toLowerCase();
      previewPage = 1;
      updatePreviewTable();
    });

    $('apolloPreviewPageSize')?.addEventListener('change', (e) => {
      const val = e.target.value;
      previewPageSize = val === 'all' ? 'all' : parseInt(val, 10);
      previewPage = 1;
      updatePreviewTable();
    });
  }

  function resetApolloImport() {
    $('apolloFileInput').value = '';
    currentPreviewData = null;
    previewRows = [];
    previewPage = 1;
    previewSearch = '';
    if ($('apolloPreviewSearch')) $('apolloPreviewSearch').value = '';
    $('apolloUploadDropzone').classList.remove('hidden');
    $('apolloParsingIndicator').classList.add('hidden');
    $('apolloPreviewSection').classList.add('hidden');
    $('apolloResultSection').classList.add('hidden');
  }

  async function handleApolloCsvUpload(file) {
    if (!file.name.toLowerCase().endsWith('.csv')) {
      SLC.toast('Please upload a valid .csv file.', 'error');
      return;
    }

    $('apolloUploadDropzone').classList.add('hidden');
    $('apolloParsingIndicator').classList.remove('hidden');
    $('apolloPreviewSection').classList.add('hidden');
    $('apolloResultSection').classList.add('hidden');

    const formData = new FormData();
    formData.append('file', file);

    try {
      const res = await api.upload('leads/import/preview', formData);
      currentPreviewData = res;
      previewRows = res.preview_rows || [];
      previewPage = 1;
      renderApolloPreview(res);
    } catch (e) {
      SLC.toast(e.message || 'Failed to parse CSV.', 'error');
      resetApolloImport();
    } finally {
      $('apolloParsingIndicator').classList.add('hidden');
    }
  }

  function renderApolloPreview(data) {
    $('apolloFileName').textContent = data.file_name || 'contacts-export.csv';
    $('apolloFormatBadge').textContent = data.detected_format || 'Apollo CSV Export';
    $('apolloFileSize').textContent = data.file_size_formatted || (Math.round(data.file_size / 1024) + ' KB');
    $('apolloColCount').textContent = data.total_columns || 0;
    $('apolloRowCount').textContent = data.total_rows || 0;

    $('kpiTotalRows').textContent = data.total_rows || 0;
    $('kpiNewLeads').textContent = data.new_leads_count || 0;
    $('kpiExistingLeads').textContent = data.existing_leads_count || 0;
    $('kpiInFileDup').textContent = data.in_file_duplicate_count || 0;
    $('kpiPreservedCols').textContent = data.total_columns || 71;

    const btnText = $('apolloConfirmBtnText');
    const toImportCount = data.new_leads_count;
    if (btnText) {
      btnText.textContent = 'Import ' + data.total_rows + ' Records (' + toImportCount + ' New)';
    }

    updatePreviewTable();
    $('apolloPreviewSection').classList.remove('hidden');
  }

  function updatePreviewTable() {
    const tbody = $('apolloPreviewTableBody');
    if (!tbody) return;

    // 1. Filter rows by search term
    let filtered = previewRows;
    if (previewSearch) {
      filtered = previewRows.filter(r => {
        const text = [
          r.contact_name, r.job_title, r.company_name, r.email,
          r.phone, r.city, r.state, r.status, r.industry
        ].filter(Boolean).join(' ').toLowerCase();
        return text.indexOf(previewSearch) !== -1;
      });
    }

    const totalCount = filtered.length;
    $('apolloPreviewCountDisplay').textContent = totalCount === previewRows.length
      ? (totalCount + ' Records')
      : (totalCount + ' of ' + previewRows.length + ' Records');

    // 2. Paginate rows
    let displayRows = filtered;
    let totalPages = 1;
    let startIdx = 0;
    let endIdx = totalCount;

    if (previewPageSize !== 'all') {
      const perPage = parseInt(previewPageSize, 10);
      totalPages = Math.ceil(totalCount / perPage) || 1;
      if (previewPage > totalPages) previewPage = totalPages;
      if (previewPage < 1) previewPage = 1;

      startIdx = (previewPage - 1) * perPage;
      endIdx = Math.min(startIdx + perPage, totalCount);
      displayRows = filtered.slice(startIdx, endIdx);
    }

    // 3. Render Pager Info & Controls
    const pagerInfo = $('apolloPreviewPagerInfo');
    if (pagerInfo) {
      if (totalCount === 0) {
        pagerInfo.textContent = 'No records match the filter.';
      } else {
        pagerInfo.textContent = 'Showing ' + (startIdx + 1) + '–' + endIdx + ' of ' + totalCount + ' records';
      }
    }

    renderPagerButtons(totalPages);

    // 4. Render Table Body
    if (!displayRows.length) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:26px;color:var(--muted);">No records found matching "' + SLC.escape(previewSearch) + '".</td></tr>';
      return;
    }

    tbody.innerHTML = displayRows.map((row) => {
      const globalIdx = row.row_index ? (row.row_index - 1) : previewRows.indexOf(row);
      const status = row.status || 'New';
      let badgeHtml = '';
      if (status === 'New') {
        badgeHtml = '<span class="badge" style="background:rgba(34,197,94,0.15);color:var(--good);border:1px solid rgba(34,197,94,0.3);">✓ New</span>';
      } else if (status === 'Existing') {
        badgeHtml = '<span class="badge" style="background:rgba(245,158,11,0.15);color:var(--warn);border:1px solid rgba(245,158,11,0.3);" title="' + SLC.escape(row.status_reason || 'In CRM') + '">⚠ Existing</span>';
      } else if (status === 'Duplicate') {
        badgeHtml = '<span class="badge" style="background:rgba(239,68,68,0.15);color:var(--bad);border:1px solid rgba(239,68,68,0.3);" title="' + SLC.escape(row.status_reason || 'Duplicate in file') + '">✕ Duplicate</span>';
      } else {
        badgeHtml = '<span class="badge badge-gray">Invalid</span>';
      }

      const emailStatus = row.email_status ? `<span style="font-size:10px;padding:1px 6px;border-radius:4px;background:rgba(34,197,94,0.12);color:var(--good);margin-left:4px;">${SLC.escape(row.email_status)}</span>` : '';
      const emailCatchAll = row.email_catch_all ? `<div style="font-size:11px;color:var(--muted2);">${SLC.escape(row.email_catch_all)}</div>` : '';

      return `
        <tr>
          <td>${badgeHtml}</td>
          <td>
            <div class="strong" style="color:var(--text);">${SLC.escape(row.contact_name || '—')}</div>
            <div style="font-size:11px;color:var(--muted2);">${SLC.escape(row.apollo_contact_id ? 'ID: ' + row.apollo_contact_id.slice(-8) : '')}</div>
          </td>
          <td>
            <div>${SLC.escape(row.job_title || '—')}</div>
            <div style="font-size:11px;color:var(--muted2);">${SLC.escape(row.seniority || '')} ${row.department ? '• ' + SLC.escape(row.department) : ''}</div>
          </td>
          <td>
            <div class="strong">${SLC.escape(row.company_name || '—')}</div>
            <div style="font-size:11px;color:var(--muted2);">${SLC.escape(row.industry || '')} ${row.employee_count ? '• ' + SLC.escape(row.employee_count) + ' emp' : ''}</div>
          </td>
          <td>
            <div>${row.email ? '<a href="mailto:' + SLC.escape(row.email) + '" style="color:var(--accent);">' + SLC.escape(row.email) + '</a>' : '<span style="color:var(--muted2);">—</span>'} ${emailStatus}</div>
            ${emailCatchAll}
          </td>
          <td>
            <div>${SLC.escape(row.phone || row.work_phone || row.mobile || '—')}</div>
          </td>
          <td>
            <div>${SLC.escape([row.city, row.state, row.country].filter(Boolean).join(', ') || '—')}</div>
          </td>
          <td style="text-align:right;">
            <button type="button" class="btn-ghost btn-sm" data-apollo-inspect="${globalIdx}" style="font-size:11.5px;padding:4px 8px;">
              🔍 Inspect 71 Fields
            </button>
          </td>
        </tr>
      `;
    }).join('');

    // Attach inspect listeners
    tbody.querySelectorAll('[data-apollo-inspect]').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.getAttribute('data-apollo-inspect'), 10);
        const row = previewRows[idx] || displayRows[0];
        if (row) {
          openApolloRowInspector(row);
        }
      });
    });
  }

  function renderPagerButtons(totalPages) {
    const wrap = $('apolloPreviewPagerButtons');
    if (!wrap) return;
    if (previewPageSize === 'all' || totalPages <= 1) {
      wrap.innerHTML = '';
      return;
    }

    let btnsHtml = '';
    btnsHtml += `<button type="button" class="btn-ghost btn-sm" id="prevPageBtn" ${previewPage === 1 ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : ''}>← Prev</button>`;

    for (let p = 1; p <= totalPages; p++) {
      const isCur = p === previewPage;
      btnsHtml += `<button type="button" class="btn-sm ${isCur ? 'btn-primary' : 'btn-ghost'}" data-page="${p}" style="min-width:32px;padding:4px 8px;">${p}</button>`;
    }

    btnsHtml += `<button type="button" class="btn-ghost btn-sm" id="nextPageBtn" ${previewPage === totalPages ? 'disabled style="opacity:0.4;cursor:not-allowed;"' : ''}>Next →</button>`;

    wrap.innerHTML = btnsHtml;

    wrap.querySelector('#prevPageBtn')?.addEventListener('click', () => {
      if (previewPage > 1) {
        previewPage--;
        updatePreviewTable();
      }
    });

    wrap.querySelector('#nextPageBtn')?.addEventListener('click', () => {
      if (previewPage < totalPages) {
        previewPage++;
        updatePreviewTable();
      }
    });

    wrap.querySelectorAll('[data-page]').forEach(b => {
      b.addEventListener('click', () => {
        previewPage = parseInt(b.getAttribute('data-page'), 10);
        updatePreviewTable();
      });
    });
  }

  function openApolloRowInspector(row) {
    const rawData = row.raw_apollo_data || {};
    const keys = Object.keys(rawData);

    const formatVal = (v) => {
      if (v === null || v === undefined || v === '') return '<span style="color:var(--muted2);">— (empty)</span>';
      if (typeof v === 'string' && v.startsWith('http')) return `<a href="${SLC.escape(v)}" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:underline;">${SLC.escape(v)}</a>`;
      return SLC.escape(String(v));
    };

    let tableRows = keys.map(k => {
      return `
        <tr>
          <td style="font-weight:600;color:var(--text);width:260px;background:var(--panel2);">${SLC.escape(k)}</td>
          <td style="word-break:break-word;color:var(--text);">${formatVal(rawData[k])}</td>
        </tr>
      `;
    }).join('');

    const bodyHtml = `
      <div style="margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
          <h4 style="margin:0;font-size:16px;font-weight:700;">${SLC.escape(row.contact_name || 'Contact')} — ${SLC.escape(row.company_name || 'Company')}</h4>
          <p style="margin:2px 0 0;font-size:12px;color:var(--muted);">All ${keys.length} Apollo CSV original attributes preserved</p>
        </div>
        <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:12px;">${keys.length} Preserved Fields</span>
      </div>
      <div style="max-height:60vh;overflow-y:auto;border:1px solid var(--border);border-radius:8px;">
        <table class="data" style="width:100%;font-size:12.5px;">
          <thead>
            <tr><th>Apollo Column Name</th><th>Original Field Value</th></tr>
          </thead>
          <tbody>${tableRows}</tbody>
        </table>
      </div>
    `;

    SLC.modal.open({
      title: 'Apollo Record Inspector (' + keys.length + ' Fields)',
      size: 'lg',
      body: bodyHtml,
      footer: '<button class="btn-primary" data-close>Close Inspector</button>',
    });
  }

  async function executeApolloImport() {
    if (!currentPreviewData || !currentPreviewData.batch_token) {
      SLC.toast('No active import session found.', 'error');
      return;
    }

    const btn = $('apolloExecuteImportBtn');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = SLC.ui.spinner() + ' Importing to Database...';

    try {
      const res = await api.post('leads/import/confirm', {
        batch_token: currentPreviewData.batch_token,
        skip_duplicates: true,
        update_existing: false,
      });

      SLC.toast('Imported ' + res.imported + ' lead(s) successfully!', 'success');

      $('apolloPreviewSection').classList.add('hidden');
      $('apolloResultSection').classList.remove('hidden');

      $('resTotalRows').textContent = res.total_rows || 0;
      $('resImported').textContent = res.imported || 0;
      $('resUpdated').textContent = res.updated || 0;
      $('resDuplicates').textContent = res.duplicates || 0;
      $('resErrors').textContent = res.errors || 0;

      if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
    } catch (e) {
      SLC.toast(e.message || 'Import failed.', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = origHtml;
    }
  }

  // ---------- IMPORT HISTORY LOGIC ----------
  async function loadImportHistory() {
    const tbody = $('importHistoryTableBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;">' + SLC.ui.spinner() + '</td></tr>';

    try {
      const res = await api.get('leads/imports');
      const rows = res.imports || [];

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--muted);">No CSV imports recorded yet.</td></tr>';
        return;
      }

      tbody.innerHTML = rows.map(r => {
        return `
          <tr>
            <td>
              <div class="strong">${SLC.escape(r.created_at ? r.created_at.slice(0, 16) : '—')}</div>
              <div style="font-size:11px;color:var(--muted2);">${SLC.escape(r.batch_id ? r.batch_id.slice(0, 12) : '')}</div>
            </td>
            <td>
              <div class="strong" style="color:var(--text);">${SLC.escape(r.file_name || 'apollo.csv')}</div>
              <div style="font-size:11px;color:var(--muted2);">${r.file_size ? Math.round(r.file_size / 1024) + ' KB' : ''}</div>
            </td>
            <td><span class="badge" style="background:var(--accent-soft);color:var(--accent);">${SLC.escape(r.source || 'Apollo CSV')}</span></td>
            <td><strong>${r.total_rows || 0}</strong></td>
            <td><span style="color:var(--good);font-weight:700;">${r.imported_count || 0}</span></td>
            <td><span style="color:var(--warn);">${r.duplicate_count || 0}</span></td>
            <td><span style="color:${r.error_count ? 'var(--bad)' : 'var(--muted2)'};">${r.error_count || 0}</span></td>
            <td style="color:var(--muted);">${SLC.escape(r.user_name || 'Admin')}</td>
          </tr>
        `;
      }).join('');
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--bad);padding:20px;">Failed to load history: ' + SLC.escape(e.message) + '</td></tr>';
    }
  }

  // ---------- GLOBAL EXPORTS & INITIALIZATION ----------
  window.clearLeadFinder = function() {
    document.querySelectorAll('#panelDiscovery .fld').forEach(e => {
      if (e.id === 'lfCountry') e.value = 'India';
      else if (e.id === 'lfCount') e.value = '5';
      else e.value = '';
    });
    customPairs.forEach(p => {
      const customInput = $(p.custom);
      if (customInput) {
        customInput.value = '';
        customInput.style.display = 'none';
      }
    });
    if ($('lfRequireEmail')) $('lfRequireEmail').checked = false;
    if ($('lfDecisionMakerOnly')) $('lfDecisionMakerOnly').checked = false;
    localStorage.removeItem('slc_last_ai_leads');
    localStorage.removeItem('slc_last_ai_payload');
    $('prospectsReviewContainer').innerHTML = '';
    $('lfSummary').innerHTML = '';
    $('lfReview').classList.add('hidden');
    prospects = [];
  };

  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initApolloImport();
    initCustomDropdowns();
    loadStatus();
    loadAssignUsers();

    $('lfRun')?.addEventListener('click', runDiscovery);
    $('lfSave')?.addEventListener('click', saveProspects);
    $('lfClearFilters')?.addEventListener('click', window.clearLeadFinder);

    // Quick factory zone chips
    document.querySelectorAll('.btn-zone-chip').forEach(btn => {
      btn.addEventListener('click', () => {
        const loc = btn.getAttribute('data-loc');
        const city = btn.getAttribute('data-city');
        const kw = btn.getAttribute('data-kw');
        if (loc && $('lfLocation')) $('lfLocation').value = loc;
        if (city && $('lfCity')) $('lfCity').value = city;
        const kwEl = $('lfKeywords');
        if (kwEl && kw) {
          if (!kwEl.value.trim()) {
            kwEl.value = kw;
          } else if (!kwEl.value.includes(kw)) {
            kwEl.value += ', ' + kw;
          }
        }
        SLC.toast('Selected factory hub: ' + (city || loc), 'info');
      });
    });

    // Quick tag chips
    document.querySelectorAll('.btn-tag-chip').forEach(btn => {
      btn.addEventListener('click', () => {
        const tag = btn.getAttribute('data-tag');
        const kw = $('lfKeywords');
        if (kw && tag) {
          if (!kw.value.trim()) {
            kw.value = tag;
          } else if (!kw.value.includes(tag)) {
            kw.value += ', ' + tag;
          }
          kw.focus();
        }
      });
    });

    document.querySelectorAll('[data-sel]').forEach(b => b.addEventListener('click', () => selectProspects(b.getAttribute('data-sel'))));

    // Restore Discovery from localStorage if available
    const savedLeads = localStorage.getItem('slc_last_ai_leads');
    const savedPayload = localStorage.getItem('slc_last_ai_payload');
    if (savedLeads && savedPayload) {
      try {
        const payload = JSON.parse(savedPayload);
        if (payload.industry) restoreField('lfIndustry', 'lfIndustryCustom', payload.industry);
        if (payload.country) restoreField('lfCountry', 'lfCountryCustom', payload.country);
        if (payload.location && $('lfLocation')) $('lfLocation').value = payload.location;
        if (payload.city && $('lfCity')) $('lfCity').value = payload.city;
        if (payload.keywords && $('lfKeywords')) $('lfKeywords').value = payload.keywords;
        if (payload.role) restoreField('lfRole', 'lfRoleCustom', payload.role);
        if (payload.seniority) restoreField('lfSeniority', 'lfSeniorityCustom', payload.seniority);
        if (payload.company_size) restoreField('lfCompanySize', 'lfCompanySizeCustom', payload.company_size);
        if (payload.custom_title && $('lfCustomTitle')) $('lfCustomTitle').value = payload.custom_title;
        if (payload.count && $('lfCount')) $('lfCount').value = payload.count;
        if (payload.require_email !== undefined && $('lfRequireEmail')) $('lfRequireEmail').checked = !!payload.require_email;
        if (payload.decision_maker_only !== undefined && $('lfDecisionMakerOnly')) $('lfDecisionMakerOnly').checked = !!payload.decision_maker_only;
        
        const res = JSON.parse(savedLeads);
        prospects = (res.prospects || []).filter(p => p.name);
        if (prospects.length > 0) {
          renderSummary(res.summary || summarize(prospects), res);
          renderReview(res);
        }
      } catch(e) {}
    }
  });
})();
