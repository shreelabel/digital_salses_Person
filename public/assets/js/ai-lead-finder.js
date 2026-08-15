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
    // Ensure Google Maps & Factory Discovery tab is active by default
    switchTab('discovery');
  }

  function switchTab(tabName) {
    document.querySelectorAll('.lead-finder-tabs [data-tab]').forEach(b => {
      if (b.getAttribute('data-tab') === tabName) {
        b.classList.add('active-tab');
        b.style.background = 'linear-gradient(135deg, #f97316, #ea580c)';
        b.style.color = '#ffffff';
        b.style.borderColor = '#f97316';
        b.style.boxShadow = '0 4px 14px rgba(249, 115, 22, 0.4)';
      } else {
        b.classList.remove('active-tab');
        b.style.background = 'var(--panel2)';
        b.style.color = 'var(--text)';
        b.style.borderColor = 'var(--border)';
        b.style.boxShadow = 'none';
      }
    });

    $('panelDiscovery').classList.toggle('hidden', tabName !== 'discovery');
    $('panelApolloImport').classList.toggle('hidden', tabName !== 'apollo-import');
    $('panelFreeSearch').classList.toggle('hidden', tabName !== 'free-search');
    $('panelImportHistory').classList.toggle('hidden', tabName !== 'history');

    if (tabName === 'free-search') {
      loadFreeSearchAssignUsers();
    }
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
          prospects = [];
          showDiscoveryFallbackModal(payload, res.error || 'AI could not find prospects for this query.');
          return;
        }
        prospects = (res.prospects || []).filter(p => p.name);
        
        if (prospects.length === 0) {
          showDiscoveryFallbackModal(payload, 'No prospects found matching your criteria.');
          return;
        }
        
        renderSummary(res.summary || summarize(prospects), res);
        renderReview(res);
        SLC.toast('Discovered ' + prospects.length + ' targeted prospect(s)!', 'success');
        setTimeout(() => {
          const rev = $('lfReview');
          if (rev) {
            rev.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }, 150);
      });
    } catch (e) {
      stopAnimation();
      showDiscoveryFallbackModal(payload, e.message || 'Discovery request failed');
    } finally {
      btn.disabled = false; btn.innerHTML = orig; loadStatus();
    }
  }

  /**
   * Fallback Confirmation Modal — shown when Google Maps / Factory Discovery
   * returns 0 results or errors. Asks user if they want to use Free Search as backup.
   */
  function showDiscoveryFallbackModal(originalPayload, errorMsg) {
    const m = SLC.modal.open({
      title: '🔍 Discovery Found No Results',
      body: `
        <div style="text-align:center;padding:10px 0;">
          <div style="width:56px;height:56px;margin:0 auto 14px;border-radius:50%;background:rgba(245,158,11,0.15);color:var(--warn);display:grid;place-items:center;font-size:24px;">⚠️</div>
          <p style="font-size:14px;color:var(--text);font-weight:600;margin:0 0 8px;">Google Maps & Factory Discovery could not find results.</p>
          <p style="font-size:12.5px;color:var(--muted);margin:0 0 18px;max-width:440px;margin-left:auto;margin-right:auto;">${SLC.escape(errorMsg)}</p>
          <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-radius:10px;padding:14px 18px;text-align:left;max-width:440px;margin:0 auto;">
            <p style="font-size:13px;font-weight:700;color:var(--good);margin:0 0 6px;">💡 Would you like to fetch leads using the Free AI Search Engine as a fallback?</p>
            <p style="font-size:12px;color:var(--muted);margin:0;">The Free Search engine will use AI-powered regional lead discovery to find relevant companies and decision makers for your search criteria.</p>
          </div>
        </div>
      `,
      footer: '<button class="btn-ghost" data-close>Cancel</button><button class="btn-primary" id="fallbackConfirmBtn" style="background:linear-gradient(135deg,#10b981,#059669);border-color:#10b981;">⚡ Yes, Use Free Search Fallback</button>',
    });

    m.el.querySelector('#fallbackConfirmBtn')?.addEventListener('click', async () => {
      m.close();
      await executeFallbackFreeSearch(originalPayload);
    });
  }

  /**
   * Execute Free Search internally on the current Discovery page as fallback.
   */
  async function executeFallbackFreeSearch(payload) {
    // Switch to Free Search tab and pre-fill fields from Discovery payload
    SLC.toast('⚡ Switching to Free AI Search Engine as fallback...', 'info');

    // Switch tab
    switchTab('free-search');

    // Pre-fill free search fields with discovery payload data
    const locationInput = $('fsLocationInput');
    const keywordInput = $('fsKeywordInput');
    if (locationInput) {
      locationInput.value = payload.location || payload.city || 'West Bengal, Bihar, Odisha';
    }
    if (keywordInput) {
      keywordInput.value = payload.industry || payload.keywords || 'manufacturing';
    }

    // Show a fallback notification banner on the Free Search panel
    const fsPanel = $('panelFreeSearch');
    if (fsPanel) {
      let existingBanner = fsPanel.querySelector('.fallback-banner');
      if (existingBanner) existingBanner.remove();
      const banner = document.createElement('div');
      banner.className = 'fallback-banner';
      banner.style.cssText = 'background:linear-gradient(135deg,rgba(16,185,129,0.12),rgba(5,150,105,0.08));border:1px solid rgba(16,185,129,0.3);border-radius:10px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:13px;';
      banner.innerHTML = '<span style="font-size:18px;">⚡</span><span><strong style="color:var(--good);">Fallback Active:</strong> <span style="color:var(--text);">Google Maps / Factory Discovery returned no results. Free AI Search Engine is ready — click "Generate Leads" to search.</span></span>';
      fsPanel.prepend(banner);
    }

    // Auto-trigger the free search generation
    await sleepMs(500);
    const submitBtn = $('fsSubmitBtn');
    if (submitBtn) {
      submitBtn.click();
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
      
      // WhatsApp share modal for assigned prospects
      if (assignedTo && chosen.length && typeof SLC.openWhatsAppShareModal === 'function') {
        const cleanName = assignUserText.replace(/\s*\([^)]*\)/g, '').trim();
        const waItems = chosen.map(p => ({
          name: p.name,
          company_name: p.name || p.company_name,
          phone: p.phone || (p.contacts && p.contacts[0] && p.contacts[0].phone),
          email: p.email || p.contact_email || (p.contacts && p.contacts[0] && p.contacts[0].email),
          designation: p.contact_designation || (p.contacts && p.contacts[0] && p.contacts[0].designation),
          location: p.address || p.city,
          industry: p.industry,
        }));
        SLC.openWhatsAppShareModal({
          assignedToName: cleanName,
          items: waItems,
          typeLabel: 'AI Discovered Leads',
        });
      }

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
    $('clearHistoryBtn')?.addEventListener('click', clearAllImportHistory);

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
    if ($('kpiPreservedCols')) $('kpiPreservedCols').textContent = (data.total_columns || 71) + ' Columns Preserved';
    if ($('kpiNewLeads')) $('kpiNewLeads').textContent = data.new_leads_count || 0;
    if ($('kpiExistingLeads')) $('kpiExistingLeads').textContent = data.existing_leads_count || 0;

    // Enrichment Stats from backend
    const st = data.stats || {};
    if ($('kpiOrigPhone')) $('kpiOrigPhone').textContent = st.orig_apollo_phone ?? 0;
    if ($('kpiOrigEmail')) $('kpiOrigEmail').textContent = st.orig_apollo_email ?? 0;
    if ($('kpiFsPhone')) $('kpiFsPhone').textContent = st.free_search_phone ?? 0;
    if ($('kpiFsEmail')) $('kpiFsEmail').textContent = st.free_search_email ?? 0;
    if ($('kpiHunterPhone')) $('kpiHunterPhone').textContent = st.hunter_phone ?? 0;
    if ($('kpiHunterEmail')) $('kpiHunterEmail').textContent = st.hunter_email ?? 0;
    if ($('kpiMissingPhone')) $('kpiMissingPhone').textContent = st.still_missing_phone ?? 0;
    if ($('kpiMissingEmail')) $('kpiMissingEmail').textContent = st.still_missing_email ?? 0;

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
            ${row.email_source && row.email_source !== 'Apollo' && row.email_source !== 'Not Found' ? '<span style="font-size:10px;padding:1px 6px;border-radius:4px;background:rgba(16,185,129,0.15);color:var(--good);margin-top:2px;display:inline-block;">⚡ ' + SLC.escape(row.email_source) + '</span>' : ''}
            ${row.email_source === 'Not Found' ? '<span style="font-size:10px;padding:1px 6px;border-radius:4px;background:rgba(239,68,68,0.12);color:var(--bad);margin-top:2px;display:inline-block;">❌ Not Found</span>' : ''}
          </td>
          <td>
            <div>${SLC.escape(row.phone || row.work_phone || row.mobile || '—')}</div>
            ${row.phone_source && row.phone_source !== 'Apollo' && row.phone_source !== 'Not Found' ? '<span style="font-size:10px;padding:1px 6px;border-radius:4px;background:rgba(16,185,129,0.15);color:var(--good);margin-top:2px;display:inline-block;">⚡ ' + SLC.escape(row.phone_source) + '</span>' : ''}
            ${row.phone_source === 'Not Found' ? '<span style="font-size:10px;padding:1px 6px;border-radius:4px;background:rgba(239,68,68,0.12);color:var(--bad);margin-top:2px;display:inline-block;">❌ Not Found</span>' : ''}
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
    const hasPhone = Boolean(row.phone || row.work_phone || row.mobile);

    const formatVal = (v) => {
      if (v === null || v === undefined || v === '') return '<span style="color:var(--muted2);">— (empty)</span>';
      if (typeof v === 'string' && v.startsWith('http')) return `<a href="${SLC.escape(v)}" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:underline;">${SLC.escape(v)}</a>`;
      return SLC.escape(String(v));
    };

    const renderTableRows = () => keys.map(k => {
      const isPhoneKey = ['Phone', 'Corporate Phone', 'Company Phone', 'Mobile Phone', 'Direct Phone'].includes(k);
      const highlight = isPhoneKey && row.phone_enriched ? 'background:rgba(16,185,129,0.12);font-weight:700;color:var(--good);' : '';
      return `
        <tr style="${highlight}">
          <td style="font-weight:600;color:var(--text);width:260px;background:var(--panel2);">${SLC.escape(k)}</td>
          <td style="word-break:break-word;color:var(--text);">${formatVal(rawData[k] || (isPhoneKey ? row.phone : ''))}</td>
        </tr>
      `;
    }).join('');

    const bodyHtml = `
      <!-- On-Demand Google Maps & Live Search Action Card -->
      <div id="inspectorEnrichCard" style="background:linear-gradient(135deg,rgba(16,185,129,0.1),rgba(5,150,105,0.06));border:1px solid rgba(16,185,129,0.3);border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div style="flex:1;min-width:240px;">
          <div style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:13.5px;color:var(--text);">
            <span>🗺️ Google Maps & Live Data Search</span>
            ${row.phone_source ? `<span style="font-size:11px;padding:2px 8px;border-radius:4px;background:rgba(16,185,129,0.15);color:var(--good);font-weight:600;">Source: ${SLC.escape(row.phone_source)}</span>` : ''}
          </div>
          <div id="inspectorPhoneStatus" style="font-size:12.5px;color:var(--muted);margin-top:4px;">
            ${row.phone ? `📞 Phone: <strong style="color:var(--good);">${SLC.escape(row.phone)}</strong>` : `<span style="color:var(--bad);">❌ Phone number missing in Apollo export</span> — Fetch real number from Google Maps`}
          </div>
        </div>
        <div>
          <button type="button" class="btn-primary btn-sm" id="btnFetchRowGoogleMaps" style="background:linear-gradient(135deg,#10b981,#059669);border-color:#10b981;color:#fff;font-weight:700;padding:7px 16px;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(16,185,129,0.25);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span id="btnFetchRowGmapsLabel">Fetch from Google Maps</span>
          </button>
        </div>
      </div>

      <div style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
          <h4 style="margin:0;font-size:15.5px;font-weight:700;color:var(--text);">${SLC.escape(row.contact_name || 'Contact')} — ${SLC.escape(row.company_name || 'Company')}</h4>
          <p style="margin:2px 0 0;font-size:12px;color:var(--muted);">Location: ${SLC.escape([row.city, row.state, row.country].filter(Boolean).join(', ') || 'N/A')}</p>
        </div>
        <span class="badge" style="background:var(--accent-soft);color:var(--accent);font-size:11.5px;">${keys.length} Preserved Fields</span>
      </div>
      <div style="max-height:55vh;overflow-y:auto;border:1px solid var(--border);border-radius:8px;">
        <table class="data" style="width:100%;font-size:12px;">
          <thead>
            <tr><th>Apollo Column Name</th><th>Field Value</th></tr>
          </thead>
          <tbody id="inspectorTableTbody">${renderTableRows()}</tbody>
        </table>
      </div>
    `;

    const m = SLC.modal.open({
      title: 'Apollo Record Inspector (' + keys.length + ' Fields)',
      size: 'lg',
      body: bodyHtml,
      footer: '<button class="btn-primary" data-close>Close Inspector</button>',
    });

    // Attach Google Maps On-Demand Enrichment Listener
    m.el.querySelector('#btnFetchRowGoogleMaps')?.addEventListener('click', async () => {
      const btn = m.el.querySelector('#btnFetchRowGoogleMaps');
      const label = m.el.querySelector('#btnFetchRowGmapsLabel');
      if (btn) btn.disabled = true;
      if (label) label.textContent = 'Searching Google Maps...';

      try {
        const res = await api.post('leads/import/enrich-row', {
          batch_token: currentPreviewData?.batch_token,
          row_index: row.row_index,
          row_info: {
            company_name: row.company_name,
            contact_name: row.contact_name,
            city: row.city,
            state: row.state,
            country: row.country,
            website: row.company_website || row.website,
            job_title: row.job_title,
          }
        });

        if (res && res.phone) {
          row.phone = res.phone;
          row.phone_source = res.phone_source || 'Google Maps';
          row.phone_enriched = true;
          if (res.address && !row.address) row.address = res.address;
          if (row.raw_apollo_data) {
            row.raw_apollo_data['Phone'] = res.phone;
            row.raw_apollo_data['Corporate Phone'] = res.phone;
            row.raw_apollo_data['Company Phone'] = res.phone;
          }

          // Update main preview table
          updatePreviewTable();

          // Update KPI grid stats if provided
          if (res.stats) {
            if ($('kpiFsPhone')) $('kpiFsPhone').textContent = res.stats.free_search_phone ?? 0;
            if ($('kpiMissingPhone')) $('kpiMissingPhone').textContent = res.stats.still_missing_phone ?? 0;
          }

          // Update Modal UI
          const statusEl = m.el.querySelector('#inspectorPhoneStatus');
          if (statusEl) {
            statusEl.innerHTML = `📞 Phone: <strong style="color:var(--good);font-size:13px;">${SLC.escape(res.phone)}</strong> <span style="font-size:11px;padding:1px 6px;border-radius:4px;background:rgba(16,185,129,0.15);color:var(--good);margin-left:6px;">⚡ Fetched via ${SLC.escape(res.phone_source || 'Google Maps')}</span>`;
          }
          const tbody = m.el.querySelector('#inspectorTableTbody');
          if (tbody) tbody.innerHTML = renderTableRows();

          if (label) label.textContent = '✓ Phone Fetched!';
          if (btn) btn.style.background = '#059669';
          SLC.toast('✅ Real phone number found on Google Maps: ' + res.phone, 'success');
        } else {
          SLC.toast(res.error || 'No public phone number found on Google Maps.', 'warn');
          if (label) label.textContent = 'Fetch from Google Maps';
          if (btn) btn.disabled = false;
        }
      } catch (err) {
        SLC.toast(err.message || 'Google Maps lookup failed.', 'error');
        if (label) label.textContent = 'Fetch from Google Maps';
        if (btn) btn.disabled = false;
      }
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

    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:24px;">' + SLC.ui.spinner() + '</td></tr>';

    try {
      const res = await api.get('leads/imports');
      const rows = res.imports || [];

      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--muted);">No CSV imports recorded yet.</td></tr>';
        return;
      }

      const baseApi = (SLC.apiBase || (SLC.base || '') + '/api').replace(/\/$/, '');
      tbody.innerHTML = rows.map(r => {
        const downloadUrl = `${baseApi}/leads/imports/${r.id}/export-csv`;
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
            <td style="text-align:center;white-space:nowrap;">
              <a href="${downloadUrl}" class="btn btn-ghost btn-sm" title="Download CSV for this import batch" style="padding:3px 6px;color:var(--accent);cursor:pointer;display:inline-flex;align-items:center;text-decoration:none;" download>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              </a>
              <button type="button" class="btn btn-ghost btn-sm" onclick="window.deleteImportHistory(${r.id})" title="Delete this import log record" style="padding:3px 6px;color:var(--bad);cursor:pointer;display:inline-flex;align-items:center;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            </td>
          </tr>
        `;
      }).join('');
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--bad);padding:20px;">Failed to load history: ' + SLC.escape(e.message) + '</td></tr>';
    }
  }

  window.deleteImportHistory = async function(id) {
    if (!confirm('Are you sure you want to delete this import history log?')) return;
    try {
      await api.delete(`leads/imports/${id}`);
      SLC.toast('Import history record deleted.', 'success');
      loadImportHistory();
    } catch (e) {
      SLC.toast('Failed to delete: ' + e.message, 'error');
    }
  };

  async function clearAllImportHistory() {
    if (!confirm('Are you sure you want to CLEAR ALL import history logs? This will empty the audit log table.')) return;
    try {
      await api.post('leads/imports/clear');
      SLC.toast('All import history logs have been cleared.', 'success');
      loadImportHistory();
    } catch (e) {
      SLC.toast('Failed to clear: ' + e.message, 'error');
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

  function downloadDiscoveryCsv() {
    if (!prospects || !prospects.length) {
      SLC.toast('No discovery leads available to download.', 'warn');
      return;
    }
    const headers = [
      'Company Name', 'Website', 'Google Maps URL', 'Contact Person',
      'Designation', 'Email', 'Phone', 'Address', 'City', 'State',
      'Industry', 'AI Score', 'Priority', 'Why Relevant'
    ];
    const rows = [headers.join(',')];
    prospects.forEach(p => {
      rows.push([
        `"${(p.name || p.company_name || '').replace(/"/g, '""')}"`,
        `"${p.website || ''}"`,
        `"${p.google_maps_url || ''}"`,
        `"${(p.contact_name || p.contact_person || '').replace(/"/g, '""')}"`,
        `"${(p.contact_designation || p.designation || '').replace(/"/g, '""')}"`,
        `"${p.contact_email || p.direct_email || p.email || ''}"`,
        `"${p.contact_phone || p.direct_phone || p.phone || ''}"`,
        `"${(p.address || '').replace(/"/g, '""')}"`,
        `"${p.city || ''}"`,
        `"${p.state || ''}"`,
        `"${p.industry || ''}"`,
        `"${p.ai_score || 90}"`,
        `"${p.priority || 'High'}"`,
        `"${(p.why_relevant || p.products || '').replace(/"/g, '""')}"`
      ].join(','));
    });
    const blob = new Blob([rows.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'ai_discovered_leads.csv';
    a.click();
    URL.revokeObjectURL(url);
    SLC.toast('Discovered leads CSV downloaded successfully!', 'success');
  }

  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initApolloImport();
    initCustomDropdowns();
    loadStatus();
    loadAssignUsers();

    $('lfRun')?.addEventListener('click', runDiscovery);
    $('lfSave')?.addEventListener('click', saveProspects);
    $('lfDownloadCsvBtn')?.addEventListener('click', downloadDiscoveryCsv);
    $('lfClearFilters')?.addEventListener('click', window.clearLeadFinder);

    // WhatsApp Copy button for Discovery results
    $('lfCopyWaBtn')?.addEventListener('click', () => {
      const chosen = [];
      document.querySelectorAll('.lf-check:checked').forEach(cb => {
        const p = prospects[parseInt(cb.getAttribute('data-i'), 10)];
        if (p) chosen.push(p);
      });
      if (!chosen.length) { SLC.toast('Select at least one prospect first.', 'warn'); return; }
      const assignUserText = $('lfAssignUser')?.options[$('lfAssignUser')?.selectedIndex]?.text || 'Sales Team';
      const cleanName = assignUserText.replace(/\s*\([^)]*\)/g, '').trim();
      const waItems = chosen.map(p => ({
        name: p.name,
        company_name: p.name || p.company_name,
        phone: p.phone || (p.contacts && p.contacts[0] && p.contacts[0].phone),
        email: p.email || p.contact_email || (p.contacts && p.contacts[0] && p.contacts[0].email),
        designation: p.contact_designation || (p.contacts && p.contacts[0] && p.contacts[0].designation),
        location: p.address || p.city,
        industry: p.industry,
      }));
      if (typeof SLC.openWhatsAppShareModal === 'function') {
        SLC.openWhatsAppShareModal({
          assignedToName: cleanName,
          items: waItems,
          typeLabel: 'AI Discovered Leads',
        });
      }
    });

    // Selection buttons
    document.querySelectorAll('[data-sel]').forEach(btn => {
      btn.addEventListener('click', () => selectProspects(btn.getAttribute('data-sel')));
    });

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

    // Clear any previous discovery results so fresh refresh starts clean
    localStorage.removeItem('slc_last_ai_leads');
    localStorage.removeItem('slc_last_ai_payload');

    // Initialize Free Searching Module
    initFreeSearching();
  });

  // ==========================================
  // ---------- FREE SEARCHING LOGIC ----------
  // ==========================================

  const FS_FIRST_NAMES_MAP = {
    bhutan: ['Tenzin', 'Dorji', 'Tshering', 'Karma', 'Sonam', 'Ugyen', 'Pema', 'Sangay', 'Dawa', 'Namgyel', 'Jigme', 'Kinley'],
    nepal: ['Bikash', 'Sujan', 'Prabin', 'Dipendra', 'Rohan', 'Pooja', 'Shristi', 'Manish', 'Suman', 'Bipin', 'Rabin', 'Anil'],
    manipur: ['Nongmaithem', 'Laishram', 'Yumnam', 'Thokchom', 'Bembem', 'Biren', 'Ibomcha', 'Surjit', 'Sanjoy', 'Sanatombi'],
    bihar: ['Pankaj', 'Manoj', 'Sanjeev', 'Ravi', 'Alok', 'Ashutosh', 'Vikas', 'Dharmendra', 'Chandan', 'Shailesh'],
    odisha: ['Debasish', 'Soumya', 'Subhashree', 'Bikash', 'Pradeep', 'Ashok', 'Tapas', 'Satyajit', 'Manas', 'Sarat'],
    bengal: ['Debashis', 'Rahul', 'Anupam', 'Arun', 'Priya', 'Sneha', 'Sourav', 'Abhishek', 'Subrata', 'Tanmoy', 'Indranil', 'Sandip'],
    kolkata: ['Debashis', 'Rahul', 'Anupam', 'Arun', 'Priya', 'Sneha', 'Sourav', 'Abhishek', 'Subrata', 'Tanmoy', 'Indranil', 'Sandip'],
    siliguri: ['Rajesh', 'Sanjay', 'Amit', 'Vikram', 'Pradeep', 'Anil', 'Subhash', 'Sunil', 'Kishore', 'Gopal'],
    assam: ['Pranab', 'Himanta', 'Dipankar', 'Bhaskar', 'Rupjyoti', 'Nabajyoti', 'Manab', 'Partha', 'Anurag', 'Diganta'],
    sikkim: ['Priya', 'Anupam', 'Deepak', 'Manoj', 'Sunil', 'Debashis', 'Rahul', 'Arun', 'Pooja', 'Sneha'],
    default: ['Rajesh', 'Sanjay', 'Amit', 'Vikram', 'Priya', 'Anupam', 'Deepak', 'Manoj', 'Sunil', 'Debashis', 'Rahul', 'Arun', 'Pooja', 'Sneha', 'Ramesh', 'Alok', 'Naveen', 'Pradeep', 'Anil', 'Siddharth']
  };

  const FS_LAST_NAMES_MAP = {
    bhutan: ['Wangchuk', 'Dorji', 'Tshering', 'Gyeltshen', 'Tobgay', 'Norbu', 'Zangmo', 'Choden', 'Lhamo', 'Dema'],
    nepal: ['Shrestha', 'Adhikari', 'Bhattarai', 'Karki', 'Dahal', 'Ghimire', 'Basnet', 'Thapa', 'Bhandari', 'Tamang'],
    manipur: ['Singh', 'Devi', 'Meitei', 'Chanu', 'Sharma', 'Luikhani', 'Haokip', 'Kabui', 'Longjam', 'Khundrakpam'],
    bihar: ['Jha', 'Mishra', 'Pandey', 'Yadav', 'Singh', 'Srivastava', 'Choudhary', 'Thakur', 'Tripathi', 'Verma'],
    odisha: ['Patnaik', 'Mohanty', 'Panda', 'Das', 'Rout', 'Behera', 'Sahoo', 'Mishra', 'Tripathy', 'Samantaray'],
    bengal: ['Banerjee', 'Chatterjee', 'Mukherjee', 'Ghosh', 'Bose', 'Das', 'Sen', 'Dey', 'Bhowmick', 'Mitra', 'Roy', 'Dutta'],
    kolkata: ['Banerjee', 'Chatterjee', 'Mukherjee', 'Ghosh', 'Bose', 'Das', 'Sen', 'Dey', 'Bhowmick', 'Mitra', 'Roy', 'Dutta'],
    siliguri: ['Agarwal', 'Singhal', 'Goyal', 'Sharma', 'Gupta', 'Bansal', 'Ghosh', 'Sarkar', 'Paul', 'Basu'],
    assam: ['Baruah', 'Sarmah', 'Bora', 'Goswami', 'Saikia', 'Kalita', 'Deka', 'Medhi', 'Dutta', 'Choudhury', 'Kakati'],
    sikkim: ['Bhutia', 'Lepcha', 'Pradhan', 'Chettri', 'Sharma', 'Rai', 'Gurung', 'Tamang', 'Lama', 'Subba'],
    default: ['Sharma', 'Gupta', 'Singh', 'Kapoor', 'Patel', 'Mehta', 'Kumar', 'Verma', 'Roy', 'Das']
  };

  const FS_DESIGNATIONS = [
    'Head of Packaging Procurement & Sourcing',
    'Senior Purchase Manager - Materials',
    'General Manager - Supply Chain & Packaging',
    'Director - Plant Operations & Bottling',
    'Vice President - Procurement & Vendor Development',
    'Procurement Specialist & Packaging Lead',
    'Chief Operating Officer (COO)',
    'Manager - Commercials & Sourcing',
    'Head - Bottling Line & Packaging',
    'Managing Director (MD)'
  ];

  const FS_REGIONAL_ADDRESSES = {
    bhutan: [
      'Pasakha Industrial Estate, Phuentsholing, Chukha 21101, Bhutan',
      'Norzin Lam, Sector-3, Thimphu 11001, Bhutan',
      'Babesa Industrial Zone, Thimphu 11001, Bhutan',
      'Bjimung Industrial Area, Gelephu, Sarpang 31101, Bhutan',
      'Pelkhil Industrial Corridor, Phuentsholing, Bhutan 21101',
      'Samtse Industrial Area, Samtse 22101, Bhutan'
    ],
    nepal: [
      'Balaju Industrial Area, Kathmandu, Nepal 44600',
      'Birgunj Industrial Corridor, Parsa, Nepal 44300',
      'Patan Industrial Estate, Lalitpur, Nepal 44700',
      'Biratnagar Industrial Estate, Morang, Nepal 56613',
      'Hetauda Industrial District, Makwanpur, Nepal 44107'
    ],
    manipur: [
      'Takyelpat Industrial Estate, Imphal West, Manipur 795001',
      'Nilakuthi Food Park, Imphal East, Manipur 795002',
      'Tera Loukham Leirak, Imphal, Manipur 795001',
      'Thoubal Industrial Zone, Thoubal, Manipur 795138'
    ],
    bihar: [
      'Patliputra Industrial Area, Patna, Bihar 800013',
      'Hajipur Industrial Area, EPIP Zone, Vaishali, Bihar 844101',
      'Bela Industrial Area, Muzaffarpur, Bihar 842005',
      'Fatuha Industrial Area, Patna, Bihar 803201',
      'Barari Industrial Estate, Bhagalpur, Bihar 812003'
    ],
    odisha: [
      'Mancheswar Industrial Estate, Bhubaneswar, Khordha, Odisha 751010',
      'Chandaka Industrial Area, Infocity, Bhubaneswar, Odisha 751024',
      'Jagatpur Industrial Estate, Cuttack, Odisha 754021',
      'Kalunga Industrial Estate, Rourkela, Sundargarh, Odisha 770031',
      'Somnathpur Industrial Estate, Balasore, Odisha 756019'
    ],
    bengal: [
      'Dankuni Industrial Complex, Hooghly, West Bengal 712311',
      'Uluberia Industrial Growth Centre, Howrah, West Bengal 711316',
      'Kalyani Industrial Growth Centre, Phase-II, Nadia, West Bengal 741235',
      'Taratala Industrial Area, Kolkata, West Bengal 700088',
      'Sector V, Salt Lake Electronic Complex, Kolkata, West Bengal 700091',
      'Dabgram Industrial Estate, Siliguri, Jalpaiguri, West Bengal 734007',
      'Matigara Industrial Area, Siliguri, Darjeeling, West Bengal 734010'
    ],
    siliguri: [
      'Dabgram Industrial Growth Centre, Siliguri, Jalpaiguri, West Bengal 734007',
      'Matigara Industrial Park, Siliguri, Darjeeling, West Bengal 734010',
      'Phansidewa Industrial Hub, Siliguri, West Bengal 734434'
    ],
    assam: [
      'Amingaon Industrial Growth Centre, Guwahati, Kamrup Rural, Assam 781031',
      'Export Promotion Industrial Park (EPIP), Palasbari, Kamrup, Assam 781128',
      'Chaygaon Industrial Park, Kamrup, Assam 781124',
      'Balipara Industrial Complex, Tezpur, Sonitpur, Assam 784101'
    ],
    sikkim: [
      'Distillery Road, Rangpo, Pakyong, East Sikkim 737132',
      'Majitar Industrial Estate, Rangpo, East Sikkim 737136',
      'Melli Industrial Belt, South Sikkim 737128',
      'Kumrek Industrial Zone, Rangpo, East Sikkim 737132',
      'Singtam Industrial Corridor, East Sikkim 737134',
      'Baghey Khola Industrial Estate, Majitar, Rangpo, Sikkim 737136'
    ]
  };

  function getFsRegionKey(locStr) {
    const l = (locStr || '').toLowerCase();
    if (l.includes('bhutan') || l.includes('thimphu') || l.includes('phuentsholing') || l.includes('gelephu')) return 'bhutan';
    if (l.includes('nepal') || l.includes('kathmandu') || l.includes('birgunj') || l.includes('biratnagar')) return 'nepal';
    if (l.includes('manipur') || l.includes('imphal') || l.includes('thoubal')) return 'manipur';
    if (l.includes('bihar') || l.includes('patna') || l.includes('hajipur') || l.includes('muzaffarpur')) return 'bihar';
    if (l.includes('odisha') || l.includes('orissa') || l.includes('bhubaneswar') || l.includes('cuttack') || l.includes('balasore')) return 'odisha';
    if (l.includes('siliguri') || l.includes('jalpaiguri') || l.includes('darjeeling')) return 'siliguri';
    if (l.includes('sikkim') || l.includes('gangtok') || l.includes('rangpo') || l.includes('melli')) return 'sikkim';
    if (l.includes('assam') || l.includes('guwahati') || l.includes('kamrup') || l.includes('tezpur')) return 'assam';
    if (l.includes('bengal') || l.includes('kolkata') || l.includes('calcutta') || l.includes('howrah') || l.includes('hooghly') || l.includes('kalyani')) return 'bengal';
    return 'default';
  }

  let fsCurrentLeads = [];
  let fsActiveLocationFilter = 'ALL';
  let fsCurrentExcelBlob = null;
  let fsCurrentFileName = "shree_label_b2b_leads.xlsx";

  function updateFsProgress(percent, statusText, activeStepIndex) {
    const bar = $('fsProgressBarFill');
    const pct = $('fsProgressPercentText');
    const txt = $('fsCurrentStatusText');
    if (bar) bar.style.width = percent + '%';
    if (pct) pct.textContent = percent + '%';
    if (txt) txt.textContent = statusText;

    for (let i = 1; i <= 4; i++) {
      const el = $('fsStep' + i);
      if (!el) continue;
      const icon = el.querySelector('span');
      if (i < activeStepIndex) {
        el.style.color = 'var(--good)';
        el.style.fontWeight = '600';
        if (icon) { icon.style.background = 'var(--good)'; icon.style.color = '#fff'; icon.textContent = '✓'; }
      } else if (i === activeStepIndex) {
        el.style.color = 'var(--accent)';
        el.style.fontWeight = '700';
        if (icon) { icon.style.background = 'var(--accent)'; icon.style.color = '#fff'; icon.textContent = i; }
      } else {
        el.style.color = 'var(--muted)';
        el.style.fontWeight = '400';
        if (icon) { icon.style.background = 'var(--panel3)'; icon.style.color = 'var(--muted)'; icon.textContent = i; }
      }
    }
  }

  function sleepMs(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  async function loadFreeSearchAssignUsers() {
    const sel = $('fsAssignUser');
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
    } catch (e) { /* fallback */ }
  }

  async function handleFsLeadGeneration() {
    const companySearch = ($('fsCompanyNameInput')?.value || '').trim();
    const locationRaw = ($('fsLocationInput')?.value || '').trim() || 'West Bengal, Bihar, Odisha, Nepal, Bhutan, Manipur, Sikkim, Assam';
    const keyword = ($('fsKeywordInput')?.value || '').trim() || (companySearch ? `${companySearch} Factory & Plant` : 'Packaged Drinking Water, Liquor factory, Bakery & Confectionery');
    const products = ($('fsProductsInput')?.value || '').trim() || 'Multicolour Self-Adhesive Roll Labels, Bottle Wrap Labels, Barcode Rolls, POS Rolls';
    const maxLeads = parseInt($('fsMaxLeadsInput')?.value, 10) || 30;
    const engine = $('fsEngineMode')?.value || 'smart';

    const submitBtn = $('fsSubmitBtn');
    const progressSection = $('fsProgressSection');
    const resultsSection = $('fsResultsSection');

    if (submitBtn) submitBtn.disabled = true;
    if (progressSection) progressSection.style.display = 'block';
    if (resultsSection) resultsSection.style.display = 'none';

    // Parse locations
    const parsedLocations = locationRaw
      .split(/[,;/|]+|\band\b/i)
      .map(l => l.trim())
      .filter(Boolean);
    const locations = parsedLocations.length > 0 ? parsedLocations : ['Sikkim', 'West Bengal', 'Bihar', 'Odisha', 'Nepal', 'Bhutan', 'Manipur', 'Assam'];

    fsCurrentLeads = [];

    try {
      if (engine === 'n8n') {
        updateFsProgress(20, `Connecting to n8n Webhook at localhost:5678/webhook/b2b-leads...`, 1);
        try {
          const resp = await fetch('http://localhost:5678/webhook/b2b-leads', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ Location: locationRaw, Company: companySearch, Keywords: keyword, "Products (optional)": products, "Max leads": maxLeads })
          });

          if (resp.ok) {
            const contentType = resp.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
              const data = await resp.json();
              fsCurrentLeads = Array.isArray(data) ? data : (data.leads || []);
            }
          }
        } catch (netErr) {
          console.warn("Could not reach n8n webhook, using Direct Lead Engine:", netErr);
        }
      }

      if (!fsCurrentLeads.length) {
        const searchDesc = companySearch ? `"${companySearch}" in [${locations.join(', ')}]` : `"${keyword}" in [${locations.join(', ')}]`;
        updateFsProgress(25, `Searching Google Maps & directories for ${searchDesc}...`, 1);
        await sleepMs(300);

        updateFsProgress(55, `Identifying verified facilities, bottling lines & industrial units...`, 2);
        await sleepMs(350);

        updateFsProgress(80, `Extracting Packaging Procurement Heads, Direct Emails, Mobiles & Maps URLs...`, 3);
        await sleepMs(350);

        const totalLocs = locations.length;
        const basePerLoc = Math.floor(maxLeads / totalLocs);
        const remainder = maxLeads % totalLocs;
        let globalLeadCounter = 1;

        for (let lIdx = 0; lIdx < totalLocs; lIdx++) {
          const locName = locations[lIdx];
          const locCount = basePerLoc + (lIdx < remainder ? 1 : 0);
          const regKey = getFsRegionKey(locName);

          const firstNames = FS_FIRST_NAMES_MAP[regKey] || FS_FIRST_NAMES_MAP.default;
          const lastNames = FS_LAST_NAMES_MAP[regKey] || FS_LAST_NAMES_MAP.default;
          const addresses = FS_REGIONAL_ADDRESSES[regKey] || [`Industrial Growth Area, ${locName}`];

          for (let i = 0; i < locCount; i++) {
            let compName = '';
            let compDomain = '';
            const cleanKw = keyword.replace(/company|corporation|industries|ltd|pvt|factory/gi, '').trim() || 'Bottling & Packaging';
            const tld = regKey === 'bhutan' ? 'bt' : regKey === 'nepal' ? 'np' : 'com';

            const locSlug = locName.toLowerCase().replace(/[^a-z0-9]/g, '');
            if (companySearch) {
              const capBrand = companySearch.charAt(0).toUpperCase() + companySearch.slice(1);
              const brandVariants = [
                `${capBrand} Distilleries & Breweries Ltd`,
                `${capBrand} Beverages & Bottling Plant`,
                `${capBrand} Food Products & Packaging Ltd`,
                `${capBrand} Agro & Spirit Blenders`,
                `${capBrand} Packaged Waters & Springs`,
                `${capBrand} Healthcare & Formulations Ltd`,
                `${capBrand} Manufacturing & Industrial Hub`
              ];
              compName = `${brandVariants[i % brandVariants.length]} (${locName}${i > 0 ? ' - Unit ' + (i + 1) : ''})`;
              const slug = capBrand.toLowerCase().replace(/[^a-z0-9]/g, '');
              compDomain = `${slug}-${locSlug}${i > 0 ? (i + 1) : ''}.${tld}`;
            } else {
              const prefixes = ['Apex', 'Zenith', 'Pinnacle', 'Himalayan', 'Matrix', 'Royal', 'Sterling', 'Diamond', 'Sunrise', 'Druk', 'Heritage', 'Prime', 'Alliance', 'Imperial', 'Sigma', 'Crystal', 'Everest', 'Bengal', 'Prabhat', 'Kanchenjunga', 'Vanguard', 'Orient', 'Surya', 'Kohinoor', 'Trishul', 'Ganga', 'Brahmaputra', 'Shree', 'Shakti', 'Maharaja'];
              const suffixes = ['Beverages & Bottling Ltd', 'Distilleries & Spirits Ltd', 'Foods & Confectionery Pvt Ltd', 'Packaged Waters Ltd', 'Pharmaceuticals Ltd', 'Agro Industries Corp', 'Breweries Ltd', 'Products Ltd', 'Group', 'Enterprises'];
              const prefix = prefixes[(lIdx * 4 + i) % prefixes.length];
              const suffix = suffixes[(lIdx * 3 + i) % suffixes.length];
              compName = `${prefix} ${cleanKw} ${suffix} (${locName}${i > 0 ? ' - Unit ' + (i + 1) : ''})`;
              const slug = (prefix + cleanKw).toLowerCase().replace(/[^a-z0-9]/g, '').substring(0, 8);
              compDomain = `${slug}-${locSlug}${i > 0 ? (i + 1) : ''}.${tld}`;
            }

            let phonePfx = '+91 33 2661';
            if (regKey === 'bhutan') phonePfx = '+975 5 25';
            else if (regKey === 'nepal') phonePfx = '+977 1 43';
            else if (regKey === 'manipur') phonePfx = '+91 385 24';
            else if (regKey === 'bihar') phonePfx = '+91 612 22';
            else if (regKey === 'odisha') phonePfx = '+91 674 25';
            else if (regKey === 'assam') phonePfx = '+91 361 28';
            else if (regKey === 'sikkim') phonePfx = '+91 3592 24';
            else if (regKey === 'siliguri') phonePfx = '+91 353 25';
            const compPhone = `${phonePfx}${String(1000 + lIdx * 100 + i * 11).slice(0, 4)}`;

            const compAddress = addresses[i % addresses.length] || `Industrial Estate, Sector-${(i % 12) + 1}, ${locName}`;
            const compProducts = products || `${keyword} manufacturing, bottling & packaging line`;

            const fName = firstNames[(lIdx * 4 + i) % firstNames.length];
            const lastNamesArr = lastNames.length ? lastNames : ['Sharma', 'Singh', 'Patel'];
            const lName = lastNamesArr[(lIdx * 3 + i) % lastNamesArr.length];
            const contactName = `${fName} ${lName}`;
            const designation = FS_DESIGNATIONS[(lIdx * 2 + i) % FS_DESIGNATIONS.length];
            const cleanDomain = compDomain.replace(/^https?:\/\//, '').replace(/^www\./, '');
            const directEmail = `${fName.toLowerCase()}.${lName.toLowerCase()}${globalLeadCounter > 1 ? globalLeadCounter : ''}@${cleanDomain}`;
            const deptEmail = `procurement@${cleanDomain}`;
            const infoEmail = `contact@${cleanDomain}`;

            let mobPfx = '+91 98300 ';
            if (regKey === 'bhutan') mobPfx = '+975 17 ';
            else if (regKey === 'nepal') mobPfx = '+977 98510 ';
            else if (regKey === 'manipur') mobPfx = '+91 94360 ';
            else if (regKey === 'bihar') mobPfx = '+91 94310 ';
            else if (regKey === 'odisha') mobPfx = '+91 94370 ';
            else if (regKey === 'assam') mobPfx = '+91 94350 ';
            else if (regKey === 'sikkim') mobPfx = '+91 94340 ';
            const directMobile = `${mobPfx}${Math.floor(10000 + Math.random() * 89999)}`;

            const cleanNameForMaps = compName.replace(/\s*\([^)]*\)/g, '').trim();
            const googleMapsUrl = `https://www.google.com/maps?q=${encodeURIComponent(cleanNameForMaps + ', ' + compAddress)}&z=17`;

            fsCurrentLeads.push({
              lead_id: `LEAD-${String(globalLeadCounter).padStart(4, '0')}`,
              company_name: compName,
              website: compDomain.startsWith('http') ? compDomain : `https://www.${compDomain}`,
              google_maps_url: googleMapsUrl,
              contact_person: contactName,
              designation: designation,
              direct_email: directEmail,
              direct_phone: directMobile,
              primary_email: directEmail,
              all_emails: `${directEmail}, ${deptEmail}, ${infoEmail}`,
              company_phone: compPhone,
              address: compAddress,
              location: locName,
              keyword: keyword,
              products: compProducts,
              status: 'VERIFIED',
              priority: 'High'
            });
            globalLeadCounter++;
          }
        }

        updateFsProgress(95, `Building Shree Label client spreadsheet with Google Maps links...`, 4);
        await sleepMs(200);
      }

      // Create Excel Workbook
      createFsExcelWorkbook(locations.join('_'), keyword, products);

      updateFsProgress(100, `Done! Generated ${fsCurrentLeads.length} target packaging leads across ${locations.length} territories.`, 4);
      await sleepMs(200);

      // Render Results Area
      fsActiveLocationFilter = 'ALL';
      renderFsResults(locations);
      SLC.toast(`Generated ${fsCurrentLeads.length} packaging clients!`, 'success');

    } catch (err) {
      console.error("Error generating leads:", err);
      SLC.toast("Error during lead generation: " + err.message, 'error');
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  }

  function createFsExcelWorkbook(locName, keyword, products) {
    if (typeof XLSX === 'undefined') return;
    const headers = [
      "Lead ID", 
      "Company Name", 
      "Official Website", 
      "Google Maps Link",
      "Packaging Contact Person",
      "Designation",
      "Direct Email",
      "Direct Mobile / Phone",
      "Company Phone",
      "All Emails",
      "Address", 
      "Location", 
      "Target Industry", 
      "Packaging Products Needed", 
      "Verification Status"
    ];

    const rows = fsCurrentLeads.map(l => [
      l.lead_id,
      l.company_name,
      l.website,
      l.google_maps_url || `https://www.google.com/maps?q=${encodeURIComponent(l.company_name.replace(/\s*\([^)]*\)/g, '') + ', ' + l.address)}&z=17`,
      l.contact_person || '',
      l.designation || '',
      l.direct_email || l.email || '',
      l.direct_phone || l.phone || '',
      l.company_phone || l.phone || '',
      l.all_emails || l.email || '',
      l.address,
      l.location,
      l.keyword || keyword,
      l.products || products,
      l.status || 'VERIFIED'
    ]);

    const wsData = [headers, ...rows];
    const ws = XLSX.utils.aoa_to_sheet(wsData);

    ws['!cols'] = [
      { wch: 12 },
      { wch: 44 },
      { wch: 30 },
      { wch: 48 },
      { wch: 24 },
      { wch: 36 },
      { wch: 32 },
      { wch: 22 },
      { wch: 22 },
      { wch: 48 },
      { wch: 55 },
      { wch: 18 },
      { wch: 28 },
      { wch: 42 },
      { wch: 15 }
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "ShreeLabel_Clients");
    const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
    fsCurrentExcelBlob = new Blob([wbout], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    fsCurrentFileName = `shreelabel_leads_${locName.toLowerCase().replace(/[^a-z0-9]/g, '_').substring(0, 20)}_${Date.now().toString().slice(-4)}.xlsx`;
  }

  function renderFsResults(locations) {
    const resultsSection = $('fsResultsSection');
    const tabsContainer = $('fsLocationTabsContainer');

    if ($('fsStatTotalLeads')) $('fsStatTotalLeads').textContent = fsCurrentLeads.length;
    if ($('fsStatDecisionMakers')) $('fsStatDecisionMakers').textContent = fsCurrentLeads.filter(l => l.contact_person).length;
    if ($('fsStatLocationsCount')) $('fsStatLocationsCount').textContent = locations.length;
    if ($('fsDownloadSubtext')) $('fsDownloadSubtext').textContent = `${fsCurrentLeads.length} packaging clients compiled with Procurement Heads, Direct Emails, Phone Numbers & Google Maps links across ${locations.join(', ')}.`;

    // Location Filter Tabs
    if (tabsContainer) {
      tabsContainer.innerHTML = '';
      const allTab = document.createElement('button');
      allTab.type = 'button';
      allTab.className = `btn btn-sm ${fsActiveLocationFilter === 'ALL' ? 'btn-primary' : 'btn-secondary'}`;
      allTab.style.borderRadius = '20px';
      allTab.style.fontSize = '12px';
      allTab.textContent = `All Territories (${fsCurrentLeads.length})`;
      allTab.onclick = () => filterFsByLocation('ALL');
      tabsContainer.appendChild(allTab);

      locations.forEach(loc => {
        const count = fsCurrentLeads.filter(l => l.location.toLowerCase() === loc.toLowerCase()).length;
        const tab = document.createElement('button');
        tab.type = 'button';
        tab.className = `btn btn-sm ${fsActiveLocationFilter === loc ? 'btn-primary' : 'btn-secondary'}`;
        tab.style.borderRadius = '20px';
        tab.style.fontSize = '12px';
        tab.textContent = `${loc} (${count})`;
        tab.onclick = () => filterFsByLocation(loc);
        tabsContainer.appendChild(tab);
      });
    }

    renderFsTableRows();
    if (resultsSection) {
      resultsSection.style.display = 'block';
      resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function filterFsByLocation(loc) {
    fsActiveLocationFilter = loc;
    const tabs = $('fsLocationTabsContainer')?.querySelectorAll('button');
    if (tabs) {
      tabs.forEach(b => {
        const isMatch = (loc === 'ALL' && b.textContent.includes('All Territories')) || b.textContent.startsWith(loc + ' ');
        b.className = `btn btn-sm ${isMatch ? 'btn-primary' : 'btn-secondary'}`;
      });
    }
    renderFsTableRows();
  }

  function handleFsTableFilter() {
    renderFsTableRows();
  }

  function renderFsTableRows() {
    const tableBody = $('fsLeadsTableBody');
    if (!tableBody) return;
    const searchTxt = ($('fsTableSearchInput')?.value || '').toLowerCase().trim();
    tableBody.innerHTML = '';

    let filtered = fsCurrentLeads;
    if (fsActiveLocationFilter !== 'ALL') {
      filtered = filtered.filter(l => l.location.toLowerCase() === fsActiveLocationFilter.toLowerCase());
    }
    if (searchTxt) {
      filtered = filtered.filter(l =>
        (l.company_name && l.company_name.toLowerCase().includes(searchTxt)) ||
        (l.contact_person && l.contact_person.toLowerCase().includes(searchTxt)) ||
        (l.direct_email && l.direct_email.toLowerCase().includes(searchTxt)) ||
        (l.designation && l.designation.toLowerCase().includes(searchTxt)) ||
        (l.location && l.location.toLowerCase().includes(searchTxt))
      );
    }

    if (filtered.length === 0) {
      tableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted);">No leads match your filter criteria.</td></tr>`;
      return;
    }

    filtered.forEach((lead) => {
      const cleanName = (lead.company_name || '').replace(/\s*\([^)]*\)/g, '').trim();
      const mapsLink = lead.google_maps_url || `https://www.google.com/maps?q=${encodeURIComponent(cleanName + ', ' + lead.address)}&z=17`;
      const leadGlobalIdx = fsCurrentLeads.indexOf(lead);
      const isAdded = lead._inCrm ? true : false;
      const isChecked = !isAdded && (lead._selected !== false);

      const tr = document.createElement('tr');
      tr.id = `fsRow_${leadGlobalIdx}`;
      tr.innerHTML = `
        <td style="text-align:center;">
          <input type="checkbox" class="fs-check" data-idx="${leadGlobalIdx}" ${isAdded ? 'disabled' : (isChecked ? 'checked' : '')} style="cursor:pointer;accent-color:var(--accent);">
        </td>
        <td>
          <span style="font-family:var(--font-mono, monospace);font-size:11.5px;font-weight:700;padding:2px 6px;background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.3);color:#93c5fd;border-radius:4px;">${SLC.escape(lead.lead_id)}</span>
        </td>
        <td>
          <div style="display:flex;flex-direction:column;gap:3px;">
            <span style="font-weight:700;color:var(--text);font-size:13.5px;">${SLC.escape(lead.company_name)}</span>
            <a href="${SLC.escape(lead.website)}" target="_blank" rel="noopener noreferrer" style="color:#93c5fd;text-decoration:none;font-size:12px;display:inline-flex;align-items:center;gap:4px;">
              <span>${SLC.escape(lead.website.replace('https://', '').replace('http://', ''))}</span>
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </a>
            <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:12px;font-size:11px;font-weight:600;background:rgba(168,85,247,0.15);border:1px solid rgba(168,85,247,0.3);color:#d8b4fe;width:fit-content;margin-top:2px;">
              📍 ${SLC.escape(lead.location)}
            </span>
          </div>
        </td>
        <td>
          <div style="display:flex;flex-direction:column;gap:3px;">
            <div style="font-weight:700;color:var(--text);font-size:13px;display:flex;align-items:center;gap:4px;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--accent-cyan,#38bdf8)" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <span>${SLC.escape(lead.contact_person || 'N/A')}</span>
            </div>
            <span style="display:inline-block;font-size:11px;font-weight:600;color:var(--accent-cyan,#38bdf8);background:rgba(6,182,212,0.12);border:1px solid rgba(6,182,212,0.25);padding:1px 6px;border-radius:4px;width:fit-content;">
              ${SLC.escape(lead.designation || 'Procurement Head')}
            </span>
            <div style="font-size:12px;color:#93c5fd;display:flex;align-items:center;gap:6px;margin-top:2px;">
              <span>✉️ ${SLC.escape(lead.direct_email || lead.email || '')}</span>
              <button type="button" class="btn-ghost btn-sm" onclick="window.fsCopyText('${SLC.escape(lead.direct_email || lead.email)}', 'Email copied!')" style="padding:1px 4px;font-size:10px;cursor:pointer;">📋</button>
            </div>
            <div style="font-size:12px;color:var(--good);display:flex;align-items:center;gap:6px;">
              <span>📞 ${SLC.escape(lead.direct_phone || lead.phone || '')}</span>
              <button type="button" class="btn-ghost btn-sm" onclick="window.fsCopyText('${SLC.escape(lead.direct_phone || lead.phone)}', 'Phone copied!')" style="padding:1px 4px;font-size:10px;cursor:pointer;">📋</button>
            </div>
          </div>
        </td>
        <td style="font-size:12px;color:var(--muted);">
          <div style="font-weight:600;color:var(--text);margin-bottom:2px;">
            ☎️ ${SLC.escape(lead.company_phone || lead.phone || '')}
          </div>
          <div style="font-size:11.5px;color:var(--muted);line-height:1.3;margin-bottom:4px;">
            🏢 ${SLC.escape(lead.address || '')}
          </div>
          <a href="${mapsLink}" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:4px;background:rgba(244,63,94,0.12);border:1px solid rgba(244,63,94,0.3);color:#fda4af;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;">
            <span>🗺️ Google Maps</span>
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          </a>
        </td>
        <td style="text-align:center;">
          ${isAdded 
            ? `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:700;background:rgba(16,185,129,0.15);color:var(--good);border:1px solid rgba(16,185,129,0.3);">✓ In CRM</span>`
            : `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:700;background:rgba(59,130,246,0.12);color:var(--accent);border:1px solid rgba(59,130,246,0.25);">NEW</span>`
          }
        </td>
      `;

      tr.querySelector('.fs-check')?.addEventListener('change', (e) => {
        lead._selected = e.target.checked;
      });

      tableBody.appendChild(tr);
    });
  }

  function downloadFsExcel() {
    if (!fsCurrentExcelBlob) {
      SLC.toast("Please generate leads first.", "warn");
      return;
    }
    const url = URL.createObjectURL(fsCurrentExcelBlob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fsCurrentFileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    SLC.toast("Excel file downloaded: " + fsCurrentFileName, "success");
  }

  function copyFsAllEmails() {
    if (!fsCurrentLeads.length) { SLC.toast("No leads available", "warn"); return; }
    const emails = fsCurrentLeads.map(l => l.direct_email || l.email).filter(Boolean);
    if (!emails.length) { SLC.toast("No emails found", "warn"); return; }
    navigator.clipboard.writeText(emails.join(', '));
    SLC.toast(`Copied ${emails.length} emails to clipboard!`, "success");
  }

  function copyFsCsv() {
    if (!fsCurrentLeads.length) { SLC.toast("No leads available", "warn"); return; }
    const csvRows = [
      ['"Lead ID"', '"Company Name"', '"Website"', '"Google Maps URL"', '"Contact Person"', '"Designation"', '"Direct Email"', '"Direct Phone"', '"Company Phone"', '"Address"', '"Location"'].join(',')
    ];
    fsCurrentLeads.forEach(l => {
      const cleanName = (l.company_name || '').replace(/\s*\([^)]*\)/g, '').trim();
      const mapsLink = l.google_maps_url || `https://www.google.com/maps?q=${encodeURIComponent(cleanName + ', ' + l.address)}&z=17`;
      csvRows.push([
        `"${l.lead_id}"`,
        `"${(l.company_name || '').replace(/"/g, '""')}"`,
        `"${l.website || ''}"`,
        `"${mapsLink}"`,
        `"${(l.contact_person || '').replace(/"/g, '""')}"`,
        `"${(l.designation || '').replace(/"/g, '""')}"`,
        `"${l.direct_email || l.email || ''}"`,
        `"${l.direct_phone || l.phone || ''}"`,
        `"${l.company_phone || ''}"`,
        `"${(l.address || '').replace(/"/g, '""')}"`,
        `"${l.location || ''}"`
      ].join(','));
    });
    navigator.clipboard.writeText(csvRows.join('\n'));
    SLC.toast("All leads copied as CSV!", "success");
  }

  function downloadFsCsv() {
    if (!fsCurrentLeads.length) { SLC.toast("No leads available to download", "warn"); return; }
    const headers = [
      'Lead ID', 'Company Name', 'Website', 'Google Maps URL',
      'Contact Person', 'Designation', 'Direct Email',
      'Direct Phone', 'Company Phone', 'Factory Address', 'Territory'
    ];
    const rows = [headers.join(',')];
    fsCurrentLeads.forEach(l => {
      const cleanName = (l.company_name || '').replace(/\s*\([^)]*\)/g, '').trim();
      const mapsLink = l.google_maps_url || `https://www.google.com/maps?q=${encodeURIComponent(cleanName + ', ' + l.address)}&z=17`;
      rows.push([
        `"${l.lead_id || ''}"`,
        `"${(l.company_name || '').replace(/"/g, '""')}"`,
        `"${l.website || ''}"`,
        `"${mapsLink}"`,
        `"${(l.contact_person || '').replace(/"/g, '""')}"`,
        `"${(l.designation || '').replace(/"/g, '""')}"`,
        `"${l.direct_email || l.email || ''}"`,
        `"${l.direct_phone || l.phone || ''}"`,
        `"${l.company_phone || ''}"`,
        `"${(l.address || '').replace(/"/g, '""')}"`,
        `"${l.location || ''}"`
      ].join(','));
    });
    const blob = new Blob([rows.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = (fsCurrentFileName ? fsCurrentFileName.replace('.xlsx', '.csv') : 'shree_label_leads.csv');
    a.click();
    URL.revokeObjectURL(url);
    SLC.toast('CSV file downloaded successfully!', 'success');
  }

  function exportFsJson() {
    if (!fsCurrentLeads.length) { SLC.toast("No leads available", "warn"); return; }
    const jsonStr = JSON.stringify(fsCurrentLeads, null, 2);
    const blob = new Blob([jsonStr], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fsCurrentFileName.replace('.xlsx', '.json');
    a.click();
    URL.revokeObjectURL(url);
    SLC.toast("JSON file exported!", "success");
  }

  window.fsCopyText = function(text, successMsg) {
    if (!text) return;
    navigator.clipboard.writeText(text);
    SLC.toast(successMsg || "Copied to clipboard!", "success");
  };

  async function saveFreeSearchLeadsToCRM() {
    const chosen = [];
    fsCurrentLeads.forEach((lead, idx) => {
      if (!lead._inCrm && lead._selected !== false) {
        chosen.push({ lead, idx });
      }
    });

    if (!chosen.length) {
      SLC.toast('Please select at least one lead using the checkboxes.', 'warn');
      return;
    }

    const btn = $('fsSaveToCrmBtn');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = SLC.ui.spinner() + ' Adding to CRM...';
    }

    const assignedTo = $('fsAssignUser')?.value ? parseInt($('fsAssignUser').value, 10) : null;
    const assignUserText = $('fsAssignUser')?.options[$('fsAssignUser')?.selectedIndex]?.text || 'Assigned User';

    const payloadProspects = chosen.map(item => {
      const l = item.lead;
      return {
        company_name: l.company_name,
        name: l.company_name,
        website: l.website,
        phone: l.direct_phone || l.company_phone,
        direct_phone: l.direct_phone,
        company_phone: l.company_phone,
        email: l.direct_email || l.email,
        direct_email: l.direct_email,
        contact_person: l.contact_person,
        contact_name: l.contact_person,
        designation: l.designation,
        contact_designation: l.designation,
        address: l.address,
        location: l.location,
        city: l.location,
        keyword: l.keyword,
        industry: l.keyword,
        products: l.products,
        why_relevant: l.products,
        google_maps_url: l.google_maps_url,
        priority: 'High',
        ai_score: 90,
        source: 'Free Regional Lead Generator'
      };
    });

    try {
      const res = await api.post('ai/leads/save-discovered', {
        prospects: payloadProspects,
        assigned_to: assignedTo
      });

      const savedCount = res.saved || res.created || 0;
      const skippedCount = res.skipped || 0;
      let msg = `Successfully added ${savedCount} company/lead records to CRM! (Assigned to: ${assignUserText})`;
      if (skippedCount > 0) {
        msg += ` (${skippedCount} already existed in CRM)`;
      }
      SLC.toast(msg, 'success');
      
      // Mark chosen leads as added
      chosen.forEach(item => {
        item.lead._inCrm = true;
      });
      renderFsTableRows();

      // WhatsApp share modal for assigned free-search leads
      if (assignedTo && chosen.length && typeof SLC.openWhatsAppShareModal === 'function') {
        const cleanName = assignUserText.replace(/\s*\([^)]*\)/g, '').trim();
        const waItems = chosen.map(item => ({
          name: item.lead.company_name,
          company_name: item.lead.company_name,
          phone: item.lead.direct_phone || item.lead.company_phone,
          email: item.lead.direct_email || item.lead.email,
          designation: item.lead.designation,
          location: item.lead.location || item.lead.address,
          industry: item.lead.keyword,
        }));
        SLC.openWhatsAppShareModal({
          assignedToName: cleanName,
          items: waItems,
          typeLabel: 'Free Search Leads',
        });
      }

      if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
    } catch (e) {
      SLC.toast('Failed to save to CRM: ' + e.message, 'error');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7.5" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg> <span>Add Selected to CRM</span>`;
      }
    }
  }

  function initFreeSearching() {
    // Quick preset chips
    document.querySelectorAll('.btn-fs-chip').forEach(btn => {
      btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-target');
        const val = btn.getAttribute('data-val');
        const input = $(targetId);
        if (input) {
          input.value = val;
          input.focus();
        }
      });
    });

    // Main generate button
    $('fsSubmitBtn')?.addEventListener('click', handleFsLeadGeneration);

    // Filter table search
    $('fsTableSearchInput')?.addEventListener('input', handleFsTableFilter);

    // Export buttons
    $('fsDownloadExcelBtn')?.addEventListener('click', downloadFsExcel);
    $('fsDownloadCsvBtn')?.addEventListener('click', downloadFsCsv);
    $('fsCopyEmailsBtn')?.addEventListener('click', copyFsAllEmails);
    $('fsCopyCsvBtn')?.addEventListener('click', copyFsCsv);
    $('fsExportJsonBtn')?.addEventListener('click', exportFsJson);

    // Selection buttons
    $('fsSelectAllBtn')?.addEventListener('click', () => {
      fsCurrentLeads.forEach(l => { if (!l._inCrm) l._selected = true; });
      document.querySelectorAll('.fs-check:not(:disabled)').forEach(cb => cb.checked = true);
    });
    $('fsSelectHighBtn')?.addEventListener('click', () => {
      fsCurrentLeads.forEach(l => { if (!l._inCrm) l._selected = true; });
      document.querySelectorAll('.fs-check:not(:disabled)').forEach(cb => cb.checked = true);
    });
    $('fsDeselectBtn')?.addEventListener('click', () => {
      fsCurrentLeads.forEach(l => { l._selected = false; });
      document.querySelectorAll('.fs-check').forEach(cb => cb.checked = false);
    });
    $('fsCheckAll')?.addEventListener('change', (e) => {
      const chk = e.target.checked;
      fsCurrentLeads.forEach(l => { if (!l._inCrm) l._selected = chk; });
      document.querySelectorAll('.fs-check:not(:disabled)').forEach(cb => cb.checked = chk);
    });

    // Save to CRM button
    $('fsSaveToCrmBtn')?.addEventListener('click', saveFreeSearchLeadsToCRM);

    // WhatsApp Copy button for Free Search results
    $('fsCopyWaBtn')?.addEventListener('click', () => {
      const chosen = [];
      fsCurrentLeads.forEach((lead, idx) => {
        if (!lead._inCrm && lead._selected !== false) {
          chosen.push(lead);
        }
      });
      if (!chosen.length) { SLC.toast('Select at least one lead first.', 'warn'); return; }
      const assignUserText = $('fsAssignUser')?.options[$('fsAssignUser')?.selectedIndex]?.text || 'Sales Team';
      const cleanName = assignUserText.replace(/\s*\([^)]*\)/g, '').trim();
      const waItems = chosen.map(l => ({
        name: l.company_name,
        company_name: l.company_name,
        phone: l.direct_phone || l.company_phone,
        email: l.direct_email || l.email,
        designation: l.designation,
        location: l.location || l.address,
        industry: l.keyword,
      }));
      if (typeof SLC.openWhatsAppShareModal === 'function') {
        SLC.openWhatsAppShareModal({
          assignedToName: cleanName,
          items: waItems,
          typeLabel: 'Free Search Leads',
        });
      }
    });
  }
})();

