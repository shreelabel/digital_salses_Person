/* ============================================================
   ai-research.js — AI Sales Intelligence & Outreach Closer
   Powered by SKILL.md lead-filter & qualification engine
   ============================================================ */
(function () {
  'use strict';
  const SLC = window.SLC || {};
  const api = SLC.api;
  const Companies = api.resource('companies');

  function formatVal(v) {
    if (v === null || v === undefined || v === '') return '';
    if (Array.isArray(v)) return v.join(', ');
    return String(v);
  }

  function renderIntelCard(title, val, icon, accentColor) {
    const str = formatVal(val);
    if (!str) return '';
    const isMultiLine = str.includes('\n');
    const content = isMultiLine 
      ? '<div style="white-space:pre-line;line-height:1.7;color:var(--text);font-size:13.5px;">' + SLC.escape(str) + '</div>'
      : '<div style="line-height:1.7;color:var(--text);font-size:13.5px;">' + SLC.escape(str) + '</div>';
    
    return '<div class="intel-block" style="background:var(--panel2);border:1px solid var(--border);border-radius:10px;padding:16px 18px;margin-bottom:14px;">' +
      '<div style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:13.5px;color:' + (accentColor || 'var(--accent2)') + ';margin-bottom:8px;letter-spacing:0.01em;">' +
        '<span>' + (icon || '📌') + '</span> ' + SLC.escape(title) +
      '</div>' +
      content +
    '</div>';
  }

  function copyText(text, btn) {
    if (!navigator.clipboard) {
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    } else {
      navigator.clipboard.writeText(text);
    }
    if (btn) {
      const orig = btn.innerHTML;
      btn.innerHTML = '✓ Copied!';
      btn.style.borderColor = 'var(--good)';
      btn.style.color = 'var(--good)';
      setTimeout(() => {
        btn.innerHTML = orig;
        btn.style.borderColor = '';
        btn.style.color = '';
      }, 2000);
    }
    SLC.toast('Copied to clipboard!', 'success');
  }

  function getLeadBadge(category) {
    const cat = String(category || '').toLowerCase();
    if (cat.includes('hot')) {
      return '<span class="badge" style="background:rgba(239,68,68,0.18);color:#f87171;border:1px solid rgba(239,68,68,0.4);font-weight:800;padding:6px 14px;font-size:13px;border-radius:20px;">🔥 HOT LEAD (High Intent)</span>';
    }
    if (cat.includes('warm')) {
      return '<span class="badge" style="background:rgba(245,158,11,0.18);color:#fbbf24;border:1px solid rgba(245,158,11,0.4);font-weight:800;padding:6px 14px;font-size:13px;border-radius:20px;">🌤 WARM LEAD (Active Opportunity)</span>';
    }
    if (cat.includes('cold')) {
      return '<span class="badge" style="background:rgba(59,130,246,0.18);color:#60a5fa;border:1px solid rgba(59,130,246,0.4);font-weight:800;padding:6px 14px;font-size:13px;border-radius:20px;">❄️ COLD LEAD (Nurture / Education)</span>';
    }
    return '<span class="badge badge-purple" style="font-weight:700;padding:6px 14px;font-size:13px;">🎯 QUALIFIED LEAD</span>';
  }

  function renderReportCard(r, companyName, elapsedMs) {
    const out = document.getElementById('resOutput');
    if (!out) return;

    let src = [];
    if (Array.isArray(r.sources)) {
      src = r.sources;
    } else if (typeof r.sources === 'string' && r.sources) {
      try { src = JSON.parse(r.sources); } catch (e) { src = [r.sources]; }
    }

    const confScore = r.confidence_score !== null && r.confidence_score !== undefined ? r.confidence_score : 88;
    const leadCategory = r.lead_category || (confScore >= 90 ? 'Hot' : (confScore >= 75 ? 'Warm' : 'Cold'));
    const reasoning = r.lead_category_reasoning || r.relevance || '';

    // Parse Key Insights
    let insights = [];
    if (Array.isArray(r.key_insights)) {
      insights = r.key_insights;
    } else if (typeof r.key_insights === 'string' && r.key_insights) {
      try { insights = JSON.parse(r.key_insights); } catch (e) { insights = [r.key_insights]; }
    }

    // Parse Email Outreach
    let emailData = null;
    if (r.email_outreach && typeof r.email_outreach === 'object') {
      emailData = r.email_outreach;
    } else if (typeof r.email_outreach === 'string' && r.email_outreach) {
      try { emailData = JSON.parse(r.email_outreach); } catch (e) {}
    }

    // Parse Cold Call Script
    let callData = null;
    if (r.cold_call_script && typeof r.cold_call_script === 'object') {
      callData = r.cold_call_script;
    } else if (typeof r.cold_call_script === 'string' && r.cold_call_script) {
      try { callData = JSON.parse(r.cold_call_script); } catch (e) {}
    }

    let insightsHtml = '';
    if (insights.length > 0) {
      insightsHtml = '<div style="background:rgba(124,92,255,0.06);border:1px solid rgba(124,92,255,0.22);border-radius:10px;padding:16px;margin-bottom:20px;">' +
        '<div style="font-weight:700;font-size:13.5px;color:var(--accent2);margin-bottom:8px;display:flex;align-items:center;gap:6px;">' +
          '⚡ Key Strategic Insights & Bottlenecks' +
        '</div>' +
        '<ul style="margin:0;padding-left:20px;line-height:1.6;color:var(--text);font-size:13px;">' +
          insights.map(item => '<li style="margin-bottom:5px;">' + SLC.escape(String(item)) + '</li>').join('') +
        '</ul>' +
      '</div>';
    }

    // Email section HTML
    let emailHtml = '';
    if (emailData && (emailData.body || emailData.subject_lines)) {
      const subjects = Array.isArray(emailData.subject_lines) ? emailData.subject_lines : (emailData.subject ? [emailData.subject] : []);
      const emailBody = emailData.body || '';
      const fullEmailToCopy = (subjects.length ? 'Subject: ' + subjects[0] + '\n\n' : '') + emailBody;

      emailHtml = '<div class="card" style="margin-top:16px;background:var(--panel2);border:1px solid var(--border);border-radius:12px;padding:20px;">' +
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">' +
          '<div style="font-weight:700;font-size:14.5px;color:var(--text);display:flex;align-items:center;gap:8px;">' +
            '📧 High-Converting Email Pitch' +
          '</div>' +
          '<button class="btn-secondary btn-sm copy-email-btn" data-text="' + SLC.escape(fullEmailToCopy) + '">📋 Copy Email Draft</button>' +
        '</div>' +
        (subjects.length ? '<div style="margin-bottom:12px;"><span style="font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Curiosity Subject Line Options:</span>' +
          '<div style="display:flex;flex-direction:column;gap:5px;margin-top:6px;">' +
            subjects.map((s, idx) => '<div style="font-size:12.5px;background:var(--panel);padding:6px 12px;border-radius:6px;border:1px solid var(--border);font-weight:600;color:var(--accent2);">Option ' + (idx + 1) + ': ' + SLC.escape(s) + '</div>').join('') +
          '</div>' +
        '</div>' : '') +
        '<div style="background:var(--panel);border:1px solid var(--border);border-radius:8px;padding:14px;white-space:pre-line;line-height:1.6;font-size:13px;color:var(--text);">' +
          SLC.escape(emailBody) +
        '</div>' +
      '</div>';
    }

    // WhatsApp section HTML
    let whatsappHtml = '';
    const waText = r.whatsapp_message || '';
    if (waText) {
      whatsappHtml = '<div class="card" style="margin-top:16px;background:var(--panel2);border:1px solid var(--border);border-radius:12px;padding:20px;">' +
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">' +
          '<div style="font-weight:700;font-size:14.5px;color:var(--text);display:flex;align-items:center;gap:8px;">' +
            '💬 Direct WhatsApp Pitch (5-7 Lines, Human Tone)' +
          '</div>' +
          '<div style="display:flex;gap:8px;">' +
            '<button class="btn-secondary btn-sm copy-wa-btn" data-text="' + SLC.escape(waText) + '">📋 Copy WhatsApp</button>' +
            '<a class="btn-primary btn-sm" href="https://web.whatsapp.com/send?text=' + encodeURIComponent(waText) + '" target="_blank" rel="noopener">📲 Open WhatsApp</a>' +
          '</div>' +
        '</div>' +
        '<div style="background:#0b201a;border:1px solid rgba(34,197,94,0.25);border-radius:8px;padding:14px;white-space:pre-line;line-height:1.6;font-size:13px;color:#86efac;">' +
          SLC.escape(waText) +
        '</div>' +
      '</div>';
    }

    // Cold Call Script section HTML
    let callHtml = '';
    if (callData && typeof callData === 'object') {
      const opening = callData.opening || '';
      const questions = Array.isArray(callData.problem_questions) ? callData.problem_questions : [];
      const valuePitch = callData.value_pitch || '';
      const objections = Array.isArray(callData.objection_handling) ? callData.objection_handling : [];
      const closing = callData.closing || '';

      let fullCallScript = 'COLD CALL SCRIPT FOR ' + companyName.toUpperCase() + '\n\n';
      if (opening) fullCallScript += '1. PATTERN INTERRUPT OPENING:\n' + opening + '\n\n';
      if (questions.length) fullCallScript += '2. DISCOVERY QUESTIONS:\n' + questions.map(q => '• ' + q).join('\n') + '\n\n';
      if (valuePitch) fullCallScript += '3. VALUE POSITIONING:\n' + valuePitch + '\n\n';
      if (objections.length) {
        fullCallScript += '4. OBJECTION HANDLING:\n';
        objections.forEach(obj => {
          fullCallScript += '• When they say: "' + (obj.objection || '') + '"\n  -> Respond: ' + (obj.response || '') + '\n';
        });
        fullCallScript += '\n';
      }
      if (closing) fullCallScript += '5. CLOSING ASK:\n' + closing;

      callHtml = '<div class="card" style="margin-top:16px;background:var(--panel2);border:1px solid var(--border);border-radius:12px;padding:20px;">' +
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">' +
          '<div style="font-weight:700;font-size:14.5px;color:var(--text);display:flex;align-items:center;gap:8px;">' +
            '📞 Cold Call Script & Objection Handling' +
          '</div>' +
          '<button class="btn-secondary btn-sm copy-call-btn" data-text="' + SLC.escape(fullCallScript) + '">📋 Copy Call Script</button>' +
        '</div>' +
        '<div style="display:flex;flex-direction:column;gap:12px;">' +
          (opening ? '<div style="background:var(--panel);border:1px solid var(--border);padding:12px;border-radius:8px;"><div style="font-size:11px;font-weight:700;color:var(--accent2);text-transform:uppercase;margin-bottom:4px;">1. Pattern Interrupt Opening</div><div style="font-size:13px;line-height:1.5;">' + SLC.escape(opening) + '</div></div>' : '') +
          (questions.length ? '<div style="background:var(--panel);border:1px solid var(--border);padding:12px;border-radius:8px;"><div style="font-size:11px;font-weight:700;color:var(--accent2);text-transform:uppercase;margin-bottom:4px;">2. Problem Discovery Questions</div><ul style="margin:0;padding-left:18px;font-size:13px;line-height:1.5;">' + questions.map(q => '<li>' + SLC.escape(q) + '</li>').join('') + '</ul></div>' : '') +
          (valuePitch ? '<div style="background:var(--panel);border:1px solid var(--border);padding:12px;border-radius:8px;"><div style="font-size:11px;font-weight:700;color:var(--accent2);text-transform:uppercase;margin-bottom:4px;">3. Core Value Pitch (Shree Label Creation)</div><div style="font-size:13px;line-height:1.5;">' + SLC.escape(valuePitch) + '</div></div>' : '') +
          (objections.length ? '<div style="background:var(--panel);border:1px solid var(--border);padding:12px;border-radius:8px;"><div style="font-size:11px;font-weight:700;color:var(--warn);text-transform:uppercase;margin-bottom:6px;">4. Objection Handling Battlecards</div>' +
            objections.map(obj => '<div style="margin-bottom:8px;padding:8px 10px;background:var(--panel2);border-radius:6px;border-left:3px solid var(--warn);"><div style="font-weight:700;font-size:12.5px;color:var(--text);">❌ "' + SLC.escape(obj.objection || '') + '"</div><div style="font-size:12.5px;color:#a7f3d0;margin-top:3px;line-height:1.4;">👉 <strong>Response:</strong> ' + SLC.escape(obj.response || '') + '</div></div>').join('') +
          '</div>' : '') +
          (closing ? '<div style="background:var(--panel);border:1px solid var(--border);padding:12px;border-radius:8px;"><div style="font-size:11px;font-weight:700;color:var(--good);text-transform:uppercase;margin-bottom:4px;">5. Closing Ask / Sample Swatches Offer</div><div style="font-size:13px;line-height:1.5;font-weight:600;color:var(--text);">' + SLC.escape(closing) + '</div></div>' : '') +
        '</div>' +
      '</div>';
    }

    out.innerHTML = '<div class="card" style="box-shadow:0 8px 30px rgba(0,0,0,0.25);border-radius:14px;padding:24px;">' +
      // Header Bar
      '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px;border-bottom:1px solid var(--border);padding-bottom:16px;flex-wrap:wrap;gap:12px;">' +
        '<div>' +
          '<h3 style="margin:0 0 4px;font-size:20px;font-weight:800;color:var(--text);">🏢 ' + SLC.escape(companyName) + '</h3>' +
          '<div class="muted" style="font-size:13px;">' + SLC.escape(r.industry || 'Manufacturing & Packaging') + (r.locations ? ' · ' + SLC.escape(r.locations) : '') + '</div>' +
        '</div>' +
        '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">' +
          getLeadBadge(leadCategory) +
          '<span class="badge badge-purple" style="font-weight:700;padding:6px 12px;font-size:12.5px;">Confidence ' + confScore + '%</span>' +
        '</div>' +
      '</div>' +

      // Lead Classification Reasoning
      (reasoning ? '<div style="background:var(--panel2);border-left:4px solid var(--accent);border-radius:6px;padding:12px 16px;margin-bottom:20px;font-size:13px;line-height:1.5;">' +
        '<strong style="color:var(--accent2);">🎯 Lead Qualification Rationale:</strong> ' + SLC.escape(reasoning) +
      '</div>' : '') +

      // Key Insights Box
      insightsHtml +

      // Pitch Strategy Card
      ((r.recommended_service || r.pitch_strategy || r.outreach_angle) ?
        '<div style="background:linear-gradient(135deg,rgba(124,92,255,0.12),rgba(91,140,255,0.06));border:1px solid rgba(124,92,255,0.3);border-radius:12px;padding:18px;margin-bottom:20px;">' +
          '<div style="font-weight:700;font-size:14px;color:var(--accent);margin-bottom:10px;display:flex;align-items:center;gap:6px;">' +
            '🎯 Recommended Service & Pitch Angle' +
          '</div>' +
          (r.recommended_service ? '<div style="font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:6px;">Product/Service to Pitch: <span style="color:var(--accent2);">' + SLC.escape(r.recommended_service) + '</span></div>' : '') +
          (r.pitch_strategy ? '<div style="font-size:13px;line-height:1.6;color:var(--text);margin-bottom:6px;"><strong>Strategic Approach:</strong> ' + SLC.escape(r.pitch_strategy) + '</div>' : '') +
          (r.outreach_angle && r.outreach_angle !== r.pitch_strategy ? '<div style="font-size:13px;line-height:1.6;color:var(--muted);"><strong>Core Angle:</strong> ' + SLC.escape(r.outreach_angle) + '</div>' : '') +
        '</div>' : '') +

      // Detailed specs
      '<div style="font-weight:800;font-size:15px;color:var(--text);margin-top:22px;margin-bottom:14px;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--border);padding-bottom:8px;">' +
        '🏭 Packaging & Manufacturing Intelligence' +
      '</div>' +
      renderIntelCard('Company Overview', r.overview, '📋', '#818cf8') +
      renderIntelCard('Packaged Products', r.products, '📦', '#fbbf24') +
      renderIntelCard('Likely Label Requirements', r.label_requirements, '🏷️', '#34d399') +
      ((r.suggested_department || r.decision_maker) ? 
        '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:14px;margin-bottom:14px;">' +
          (r.suggested_department ? 
            '<div style="background:var(--panel2);border:1px solid var(--border);border-radius:10px;padding:14px 16px;">' +
              '<div style="font-weight:700;font-size:13px;color:#60a5fa;margin-bottom:6px;display:flex;align-items:center;gap:6px;">🏢 Target Department</div>' +
              '<div style="font-size:13.5px;font-weight:600;color:var(--text);">' + SLC.escape(r.suggested_department) + '</div>' +
            '</div>' : '') +
          (r.decision_maker ? 
            '<div style="background:var(--panel2);border:1px solid var(--border);border-radius:10px;padding:14px 16px;">' +
              '<div style="font-weight:700;font-size:13px;color:#f472b6;margin-bottom:6px;display:flex;align-items:center;gap:6px;">👤 Key Decision Maker</div>' +
              '<div style="font-size:13.5px;font-weight:600;color:var(--text);">' + SLC.escape(r.decision_maker) + '</div>' +
            '</div>' : '') +
        '</div>' : '') +
      renderIntelCard('Why Shree Label Creation Fits', r.why_relevant, '✨', '#c084fc') +

      // Ready to use outreach kit
      '<div style="margin-top:28px;margin-bottom:12px;font-weight:800;font-size:15px;color:var(--text);display:flex;align-items:center;gap:8px;">' +
        '🚀 Ready-To-Use Multi-Channel Sales Outreach Kit' +
      '</div>' +
      emailHtml +
      whatsappHtml +
      callHtml +

      // Sources & Engine metadata
      '<div class="section-title" style="margin-top:24px;font-size:12.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;">Verified Reference Sources</div>' +
      (SLC.ui && SLC.ui.sources ? SLC.ui.sources(src) : ('<div class="muted">' + (src.length ? src.join(', ') : 'Direct Industry Intelligence Data') + '</div>')) +
      '<div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">' +
        '<div class="muted" style="font-size:11.5px;">Engine: <strong>' + SLC.escape(r.model || 'Gemini Pro Sales Strategist') + '</strong>' + (elapsedMs ? ' · ' + elapsedMs + 'ms' : '') + '</div>' +
        '<div><a class="btn-secondary btn-sm" href="' + (SLC.base || '') + '/research-reports">View All Saved Reports →</a></div>' +
      '</div>' +
    '</div>';

    // Bind clipboard copy buttons
    out.querySelectorAll('.copy-email-btn').forEach(btn => {
      btn.addEventListener('click', () => copyText(btn.getAttribute('data-text'), btn));
    });
    out.querySelectorAll('.copy-wa-btn').forEach(btn => {
      btn.addEventListener('click', () => copyText(btn.getAttribute('data-text'), btn));
    });
    out.querySelectorAll('.copy-call-btn').forEach(btn => {
      btn.addEventListener('click', () => copyText(btn.getAttribute('data-text'), btn));
    });
  }

  async function loadCompanies() {
    try {
      const res = await Companies.list({ per_page: 500 });
      const sel = document.getElementById('resCompany');
      if (!sel) return;
      (res.data || []).forEach(c => {
        const opt = new Option(c.name + (c.city ? ' — ' + c.city : ''), c.id);
        sel.appendChild(opt);
      });
    } catch (e) {
      SLC.toast('Failed to load companies list', 'error');
    }
  }

  async function run() {
    const sel = document.getElementById('resCompany');
    const id = sel ? sel.value : null;
    if (!id) {
      SLC.toast('Please select a company from the dropdown first.', 'error');
      return;
    }

    const companyName = sel.selectedOptions[0]?.text || 'Company';
    const btn = document.getElementById('resRun');
    const origHtml = btn ? btn.innerHTML : 'Run Research';
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = SLC.ui.spinner() + ' Analyzing company, qualifying lead & crafting pitch scripts...';
    }

    const out = document.getElementById('resOutput');
    if (out) {
      out.innerHTML = '<div class="card" style="text-align:center;padding:44px;"><div class="empty">' + SLC.ui.spinner() + '<div style="margin-top:14px;font-weight:700;font-size:15px;color:var(--text);">Analyzing business model, classifying lead & drafting outreach scripts…</div><p class="muted" style="font-size:12.5px;margin-top:6px;">Evaluating 8-color UV flexographic label fit, packaging bottlenecks, and conversion strategy...</p></div></div>';
    }

    try {
      const res = await api.post('ai/research', { company_id: id });
      const r = res.report || {};
      renderReportCard(r, companyName, res.elapsed_ms);
      SLC.toast('AI Sales Intelligence & Outreach Kit generated!', 'success');
      if (SLC.refreshSidebarCounters) SLC.refreshSidebarCounters();
    } catch (e) {
      if (out) {
        out.innerHTML = '<div class="card">' + SLC.ui.empty('Research Generation Failed', e.message) + '</div>';
      }
      SLC.toast(e.message || 'Research failed', 'error');
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
      }
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadCompanies();
    document.getElementById('resRun')?.addEventListener('click', run);

    // Auto-load existing report on company change
    document.getElementById('resCompany')?.addEventListener('change', async function () {
      const id = this.value;
      const out = document.getElementById('resOutput');
      if (!id) {
        if (out) out.innerHTML = '';
        return;
      }
      const compName = this.selectedOptions[0]?.text || 'Selected Company';
      try {
        const res = await Companies.get(id);
        const reports = (res && res.company && res.company.research_reports) || [];
        const hasValidReport = reports.length > 0 && (reports[0].overview || reports[0].full_report || reports[0].lead_category);
        if (hasValidReport) {
          renderReportCard(reports[0], compName);
        } else if (out) {
          out.innerHTML = '<div class="card" style="text-align:center;padding:36px 20px;border:1px dashed var(--border2);background:var(--panel2);border-radius:12px;margin-top:16px;">' +
            '<div style="font-size:28px;margin-bottom:10px;">🔬</div>' +
            '<div style="font-weight:700;font-size:15px;color:var(--text);margin-bottom:6px;">No research generated yet for ' + SLC.escape(compName) + '</div>' +
            '<p class="muted" style="font-size:13px;max-width:540px;margin:0 auto 18px;line-height:1.5;">Click the <strong>✨ Run Deep Sales Intelligence</strong> button above to qualify this company, identify label bottlenecks, and generate complete Email, WhatsApp, and Cold Call outreach scripts.</p>' +
            '<button class="btn-primary" onclick="document.getElementById(\'resRun\')?.click()">✨ Run Deep Sales Intelligence Now</button>' +
          '</div>';
        }
      } catch (e) {
        if (out) out.innerHTML = '';
      }
    });
  });
})();
