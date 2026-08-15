<!-- SheetJS for pure client-side Excel generation in Free Searching tab -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<div class="page" id="page-lead-finder" data-page="ai-lead-finder">
  <!-- Page Header -->
  <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div>
      <h1 class="page-title" style="margin:0;font-size:24px;font-weight:700;">📍 AI & Google Maps Lead Finder</h1>
      <p class="page-subtitle" style="margin:4px 0 0 0;color:var(--muted);">Discover verified manufacturing units, factory addresses, direct phone numbers, emails, and decision makers</p>
    </div>
    <div id="lfStatus" style="font-size:12px;color:var(--muted);background:var(--panel2);border:1px solid var(--border);padding:6px 14px;border-radius:20px;">Checking AI Status...</div>
  </div>

  <!-- Top Tab Navigation -->
  <div class="lead-finder-tabs" style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:12px;">
    <button class="btn btn-secondary active-tab" id="tabBtnDiscovery" data-tab="discovery" style="display:flex;align-items:center;gap:8px;padding:9px 18px;border-radius:10px;font-weight:600;transition:all .15s;background:linear-gradient(135deg, #f97316, #ea580c);color:#fff;border:1px solid #f97316;box-shadow:0 4px 12px rgba(249,115,22,0.35);">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      Google Maps & Factory Discovery
    </button>
    <button class="btn btn-secondary" id="tabBtnApolloImport" data-tab="apollo-import" style="display:flex;align-items:center;gap:8px;padding:9px 18px;border-radius:10px;font-weight:600;transition:all .15s;background:var(--panel2);color:var(--text);border:1px solid var(--border);">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      Manual Apollo CSV Import
      <span style="font-size:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;padding:2px 7px;border-radius:6px;font-weight:700;">70+ Cols</span>
    </button>
    <button class="btn btn-secondary" id="tabBtnFreeSearch" data-tab="free-search" style="display:flex;align-items:center;gap:8px;padding:9px 18px;border-radius:10px;font-weight:600;transition:all .15s;background:var(--panel2);color:var(--text);border:1px solid var(--border);">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      Free Searching
      <span style="font-size:10px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:2px 7px;border-radius:6px;font-weight:700;">Direct & n8n</span>
    </button>
    <button class="btn btn-secondary" id="tabBtnHistory" data-tab="history" style="display:flex;align-items:center;gap:8px;padding:9px 18px;border-radius:10px;font-weight:600;transition:all .15s;background:var(--panel2);color:var(--text);border:1px solid var(--border);">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Import History
    </button>
  </div>

  <!-- TAB 1: AI & GOOGLE MAPS PROSPECT DISCOVERY -->
  <div class="tab-panel" id="panelDiscovery">
    <div class="lead-finder-container" style="background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:22px;">
      
      <!-- Tier 1: Geography & Industry -->
      <div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;">
        <span style="font-size:12px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.05em;">📍 1. Market & Factory Location</span>
      </div>
      <div class="lead-finder-form" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label" style="display:block;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--muted);">Target Industry</label>
          <select class="form-select fld" id="lfIndustry" style="width:100%;">
            <option value="">Any Industry</option>
            <option value="Pharmaceutical">Pharmaceutical & Formulations</option>
            <option value="Food & Beverage">Food & Beverage Packaging</option>
            <option value="Cosmetics">Cosmetics & Personal Care</option>
            <option value="FMCG">FMCG & Consumer Goods</option>
            <option value="Ayurvedic">Ayurvedic & Herbal Medicine</option>
            <option value="Chemical">Chemicals & Lubricants</option>
            <option value="Agro">Agro & Fertilizers</option>
            <option value="Tea">Tea & Agro Processing</option>
            <option value="Packaging">Packaging & Industrial</option>
            <option value="__custom__">✏️ Custom Industry...</option>
          </select>
          <input type="text" class="form-input fld" id="lfIndustryCustom" placeholder="Type custom industry (e.g. Nutraceuticals)..." style="display:none;margin-top:6px;width:100%;">
        </div>
        <div class="form-group">
          <label class="form-label" style="display:block;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--muted);">Target Country</label>
          <select class="form-select fld" id="lfCountry" style="width:100%;">
            <option value="India" selected>India (Domestic)</option>
            <option value="United States">United States (US Exports)</option>
            <option value="United Kingdom">United Kingdom (UK / Europe)</option>
            <option value="United Arab Emirates">United Arab Emirates (UAE / GCC)</option>
            <option value="">Global / Any Country</option>
            <option value="__custom__">✏️ Custom Country / Region...</option>
          </select>
          <input type="text" class="form-input fld" id="lfCountryCustom" placeholder="Type custom country (e.g. Germany, Australia)..." style="display:none;margin-top:6px;width:100%;">
        </div>
        <div class="form-group">
          <label class="form-label" style="display:block;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--muted);">State / Region</label>
          <input type="text" class="form-input fld" id="lfLocation" placeholder="e.g. West Bengal, Maharashtra, Gujarat" style="width:100%;">
        </div>
        <div class="form-group">
          <label class="form-label" style="display:block;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--muted);">City / Industrial Zone</label>
          <input type="text" class="form-input fld" id="lfCity" placeholder="e.g. Kolkata, Howrah, Ahmedabad, Mumbai" style="width:100%;">
        </div>
      </div>

      <!-- Quick Factory Zone Chips -->
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:18px;">
        <span style="font-size:11px;color:var(--muted);font-weight:600;">🏭 Popular Hubs:</span>
        <button type="button" class="btn-zone-chip" data-loc="West Bengal" data-city="Kolkata" data-kw="Taratala Industrial Area" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:3px 9px;border-radius:12px;cursor:pointer;">Taratala (Kolkata)</button>
        <button type="button" class="btn-zone-chip" data-loc="West Bengal" data-city="Howrah" data-kw="Jalan Industrial Complex" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:3px 9px;border-radius:12px;cursor:pointer;">Howrah (Jalan Complex)</button>
        <button type="button" class="btn-zone-chip" data-loc="Gujarat" data-city="Ahmedabad" data-kw="GIDC Industrial Estate" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:3px 9px;border-radius:12px;cursor:pointer;">GIDC (Ahmedabad)</button>
        <button type="button" class="btn-zone-chip" data-loc="Maharashtra" data-city="Mumbai" data-kw="MIDC Industrial Area" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:3px 9px;border-radius:12px;cursor:pointer;">MIDC (Mumbai)</button>
        <button type="button" class="btn-zone-chip" data-loc="West Bengal" data-city="Siliguri" data-kw="Tea Packaging Estate" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:3px 9px;border-radius:12px;cursor:pointer;">Siliguri (Tea Hub)</button>
        <button type="button" class="btn-zone-chip" data-loc="Himachal Pradesh" data-city="Baddi" data-kw="Baddi Industrial Area" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:3px 9px;border-radius:12px;cursor:pointer;">Baddi (Pharma Hub)</button>
      </div>

      <!-- Tier 2: Apollo Persona & Company Sizing -->
      <div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;border-top:1px solid var(--border);padding-top:16px;">
        <span style="font-size:12px;font-weight:700;color:var(--accent2);text-transform:uppercase;letter-spacing:.05em;">🎯 2. Apollo Decision Maker & Company Sizing</span>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:14px;margin-bottom:20px;">
        <div class="form-group">
          <label class="form-label" style="display:block;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--muted);">Decision Maker Role</label>
          <select class="form-select fld" id="lfRole" style="width:100%;">
            <option value="">Any Decision Maker</option>
            <option value="Procurement / Purchase Head">Procurement / Purchase Head</option>
            <option value="Packaging Development Head">Packaging Development / Technologist</option>
            <option value="Supply Chain & Operations">Supply Chain & Operations Head</option>
            <option value="Founder / Managing Director / CEO">Founder / Managing Director / CEO</option>
            <option value="Brand & Marketing Head">Brand & Product Marketing Head</option>
            <option value="Plant / Factory General Manager">Plant / Factory General Manager</option>
            <option value="__custom__">✏️ Custom Role (e.g. Purchase Manager)...</option>
          </select>
          <input type="text" class="form-input fld" id="lfRoleCustom" placeholder="Type custom role (e.g. Purchase Manager)..." style="display:none;margin-top:6px;width:100%;">
        </div>
        <div class="form-group">
          <label class="form-label" style="display:block;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--muted);">Seniority Level</label>
          <select class="form-select fld" id="lfSeniority" style="width:100%;">
            <option value="">All Seniorities</option>
            <option value="Owner / Founder">Owner / Founder / Partner</option>
            <option value="C-Suite / Executive">C-Suite / Executive (CEO, CXO)</option>
            <option value="Director / VP">Director / Vice President</option>
            <option value="Manager / Department Head">Manager / Department Head</option>
            <option value="Senior Specialist">Senior Specialist / Lead</option>
            <option value="__custom__">✏️ Custom Seniority...</option>
          </select>
          <input type="text" class="form-input fld" id="lfSeniorityCustom" placeholder="Type custom seniority (e.g. Partner, Lead)..." style="display:none;margin-top:6px;width:100%;">
        </div>
        <div class="form-group">
          <label class="form-label" style="display:block;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--muted);">Company Headcount Size</label>
          <select class="form-select fld" id="lfCompanySize" style="width:100%;">
            <option value="">Any Size</option>
            <option value="1-10">1 - 10 Employees (Micro / Startup)</option>
            <option value="11-50">11 - 50 Employees (Small Business)</option>
            <option value="51-200">51 - 200 Employees (Mid-Sized Manufacturer)</option>
            <option value="201-500">201 - 500 Employees (Upper Mid-Market)</option>
            <option value="501-1000">501 - 1,000 Employees (Large Corporate)</option>
            <option value="1000+">1,000+ Employees (Enterprise / MNC)</option>
            <option value="__custom__">✏️ Custom Size...</option>
          </select>
          <input type="text" class="form-input fld" id="lfCompanySizeCustom" placeholder="Type custom size (e.g. 50-100 or 1500+)..." style="display:none;margin-top:6px;width:100%;">
        </div>
        <div class="form-group">
          <label class="form-label" style="display:block;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--muted);">Exact Job Title (Custom)</label>
          <input type="text" class="form-input fld" id="lfCustomTitle" placeholder="e.g. SCM Head, Packaging Buyer" style="width:100%;">
        </div>
      </div>

      <!-- Tier 3: Keywords, Quick Chips & Data Quality -->
      <div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;border-top:1px solid var(--border);padding-top:16px;">
        <span style="font-size:12px;font-weight:700;color:var(--good);text-transform:uppercase;letter-spacing:.05em;">📦 3. Packaging Focus & Data Quality</span>
      </div>
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start;margin-bottom:16px;" class="grid-tier3">
        <div class="form-group">
          <label class="form-label" style="display:block;margin-bottom:6px;font-size:12px;font-weight:600;color:var(--muted);">Product & Label Keywords</label>
          <input type="text" class="form-input fld" id="lfKeywords" placeholder="e.g. pharmaceutical syrup, cosmetic jars, mono cartons, roll labels" style="width:100%;margin-bottom:8px;">
          <!-- Quick Tag Chips -->
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button type="button" class="btn-tag-chip" data-tag="Pharma Formulations & Vials" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--muted);padding:3px 9px;border-radius:12px;cursor:pointer;">+ Pharma Vials</button>
            <button type="button" class="btn-tag-chip" data-tag="Cosmetics & Personal Care Jars" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--muted);padding:3px 9px;border-radius:12px;cursor:pointer;">+ Cosmetic Jars</button>
            <button type="button" class="btn-tag-chip" data-tag="Food & Beverage Bottles" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--muted);padding:3px 9px;border-radius:12px;cursor:pointer;">+ Food & Beverage</button>
            <button type="button" class="btn-tag-chip" data-tag="Self Adhesive Roll Labels" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--muted);padding:3px 9px;border-radius:12px;cursor:pointer;">+ Roll Form Labels</button>
            <button type="button" class="btn-tag-chip" data-tag="Security & Barcode Stickers" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--muted);padding:3px 9px;border-radius:12px;cursor:pointer;">+ Barcode Stickers</button>
            <button type="button" class="btn-tag-chip" data-tag="Chemicals & Lubricants Drum" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--muted);padding:3px 9px;border-radius:12px;cursor:pointer;">+ Chemical Drums</button>
          </div>
        </div>

        <div style="background:var(--panel2);border:1px solid var(--border);border-radius:10px;padding:12px;">
          <label style="display:block;margin-bottom:8px;font-size:12px;font-weight:600;color:var(--muted);">Quality Filters</label>
          <label class="checkbox-row" style="margin-bottom:8px;display:flex;align-items:center;font-size:12.5px;cursor:pointer;">
            <input type="checkbox" id="lfRequireEmail" style="margin-right:8px;accent-color:var(--accent);">
            <span>Strict Verified Email Only</span>
          </label>
          <label class="checkbox-row" style="margin-bottom:8px;display:flex;align-items:center;font-size:12.5px;cursor:pointer;">
            <input type="checkbox" id="lfDecisionMakerOnly" style="margin-right:8px;accent-color:var(--accent);">
            <span>Strict Decision Maker Only</span>
          </label>
          <div style="display:flex;align-items:center;gap:8px;margin-top:6px;">
            <label style="font-size:12px;color:var(--muted);white-space:nowrap;">Prospect Count:</label>
            <input type="number" class="form-input fld" id="lfCount" min="1" max="25" value="5" style="width:70px;padding:5px 8px;font-size:12px;">
          </div>
        </div>
      </div>

      <div class="form-actions" style="margin-top: 20px; display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap:wrap;">
        <button class="btn-primary" id="lfRun" style="display: flex; align-items: center; gap: 8px; font-size:14px; padding:12px 24px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg> Discover Targeted Leads with Apollo & AI
        </button>
        <button class="btn-secondary" id="lfClearFilters">Clear All Filters</button>
      </div>
    </div>

    <!-- AI Processing & Animated Progress Bar -->
    <div class="ai-processing" id="aiProcessing" style="display: none; margin: 24px 0; background:rgba(124,92,255,0.06); border:1px solid rgba(124,92,255,0.25); border-radius:var(--radius); padding:28px 24px; text-align:center; box-shadow:0 8px 32px rgba(0,0,0,0.2);">
      <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
        <div class="spin" style="width:24px;height:24px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;"></div>
        <h3 id="aiProgressTitle" style="margin:0;font-size:18px;font-weight:700;color:var(--text);">AI is discovering live prospects...</h3>
        <span id="aiPercentBadge" style="font-size:13px;font-weight:800;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;padding:3px 10px;border-radius:20px;letter-spacing:.02em;">0%</span>
      </div>
      <p id="aiProgressSubtitle" style="margin:0 0 16px 0;color:var(--muted);font-size:13.5px;">Searching live manufacturing units and verifying decision maker contacts...</p>
      
      <!-- Progress Bar Track -->
      <div style="max-width:560px;margin:0 auto 18px;background:var(--panel2);border:1px solid var(--border2);border-radius:10px;height:12px;overflow:hidden;padding:2px;">
        <div id="aiProgressBar" style="width:0%;height:100%;border-radius:8px;background:linear-gradient(90deg,var(--accent),#a855f7,#3b82f6);transition:width .25s ease;box-shadow:0 0 10px rgba(124,92,255,.5);"></div>
      </div>

      <div class="ai-steps" style="display:flex;justify-content:center;gap:20px;font-size:12px;color:var(--muted);flex-wrap:wrap;">
        <div class="ai-step" id="step1"><span class="step-icon" style="display:inline-block;width:18px;height:18px;border-radius:50%;text-align:center;line-height:18px;margin-right:6px;font-weight:700;background:var(--panel3);color:var(--muted);">1</span> Market Discovery</div>
        <div class="ai-step" id="step2"><span class="step-icon" style="display:inline-block;width:18px;height:18px;border-radius:50%;text-align:center;line-height:18px;margin-right:6px;font-weight:700;background:var(--panel3);color:var(--muted);">2</span> Apollo Decision Makers</div>
        <div class="ai-step" id="step3"><span class="step-icon" style="display:inline-block;width:18px;height:18px;border-radius:50%;text-align:center;line-height:18px;margin-right:6px;font-weight:700;background:var(--panel3);color:var(--muted);">3</span> Data Verification</div>
        <div class="ai-step" id="step4"><span class="step-icon" style="display:inline-block;width:18px;height:18px;border-radius:50%;text-align:center;line-height:18px;margin-right:6px;font-weight:700;background:var(--panel3);color:var(--muted);">4</span> AI Lead Qualification</div>
      </div>
    </div>

    <!-- Discovery Review Results Section -->
    <div class="section hidden" id="lfReview" style="margin-top: 24px;">
      <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:16px;">
        <div>
          <h2 class="section-title" style="margin:0;font-size:18px;font-weight:700;">Discovery Results</h2>
          <p style="font-size:13px;color:var(--muted);margin:4px 0 0 0;">Review discovered prospects before adding to CRM</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
          <div style="display:flex;align-items:center;gap:6px;background:var(--panel2);border:1px solid var(--border);border-radius:8px;padding:4px 10px;">
            <span style="font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;">👤 Assign To:</span>
            <select class="form-select btn-sm" id="lfAssignUser" style="padding:4px 8px;font-size:12px;border:none;background:var(--panel2);color:var(--text);font-weight:600;cursor:pointer;border-radius:6px;">
              <option value="">Admin (Me)</option>
            </select>
          </div>
          <button class="btn btn-secondary btn-sm" data-sel="high">Select High Priority</button>
          <button class="btn btn-secondary btn-sm" data-sel="all">Select All</button>
          <button class="btn btn-secondary btn-sm" data-sel="none">Deselect All</button>
          <button class="btn btn-secondary btn-sm" id="lfDownloadCsvBtn" title="Download Leads as CSV file" style="display:inline-flex;align-items:center;gap:5px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download CSV
          </button>
          <button class="btn-primary btn-sm" id="lfSave"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:middle;"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7.5" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg> Add Selected to CRM</button>
        </div>
      </div>

      <!-- Summary Pills -->
      <div id="lfSummary" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;"></div>

      <!-- Prospects Review Grid -->
      <div id="prospectsReviewContainer" style="display:flex;flex-direction:column;gap:14px;"></div>
    </div>
  </div>

  <!-- TAB 2: MANUAL APOLLO CSV IMPORT -->
  <div class="tab-panel hidden" id="panelApolloImport">
    <!-- Drag & Drop Upload Container -->
    <div id="apolloUploadDropzone" style="background:var(--panel);border:2px dashed var(--border2);border-radius:var(--radius);padding:40px 24px;text-align:center;transition:all 0.2s ease;cursor:pointer;position:relative;">
      <input type="file" id="apolloFileInput" accept=".csv,text/csv" style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;z-index:5;">
      <div style="width:56px;height:56px;margin:0 auto 16px;border-radius:14px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      </div>
      <h3 style="margin:0 0 6px 0;font-size:18px;font-weight:700;color:var(--text);">Select or Drag Apollo CSV Export File</h3>
      <p style="margin:0 auto;max-width:540px;font-size:13px;color:var(--muted);">
        Upload your <strong style="color:var(--text);">.csv</strong> file directly exported from Apollo.io Dashboard. All 70+ columns are dynamically recognized and 100% of original attributes are preserved.
      </p>
      <div style="margin-top:18px;display:inline-flex;align-items:center;gap:8px;padding:6px 14px;background:var(--panel2);border:1px solid var(--border);border-radius:20px;font-size:12px;color:var(--muted);">
        <span>✓ UTF-8 & BOM Safe</span> • <span>✓ 4-Tier Duplicate Prevention</span> • <span>✓ Full 70+ Field Preservation</span>
      </div>
    </div>

    <!-- Uploading / Processing State Indicator -->
    <div id="apolloParsingIndicator" class="hidden" style="margin:20px 0;background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:30px;text-align:center;">
      <div class="spin" style="width:36px;height:36px;margin:0 auto 14px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;"></div>
      <h4 style="margin:0 0 6px 0;font-size:16px;font-weight:700;">Parsing & Validating Apollo CSV...</h4>
      <p style="margin:0;font-size:13px;color:var(--muted);">Detecting 70+ headers, checking UTF-8 encoding, and running database duplicate detection</p>
    </div>

    <!-- Pre-Import Review Container -->
    <div id="apolloPreviewSection" class="hidden" style="margin-top:20px;">
      <!-- File Metadata Header Card -->
      <div style="background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:18px 22px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
        <div style="display:flex;align-items:center;gap:14px;">
          <div style="width:42px;height:42px;background:rgba(34,197,94,0.12);color:var(--good);border-radius:10px;display:grid;place-items:center;font-weight:800;font-size:16px;">
            CSV
          </div>
          <div>
            <div style="display:flex;align-items:center;gap:10px;">
              <h3 id="apolloFileName" style="margin:0;font-size:16px;font-weight:700;color:var(--text);">apollo-contacts-export.csv</h3>
              <span id="apolloFormatBadge" style="background:var(--accent-soft);color:var(--accent);padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;">Apollo Contacts Export CSV</span>
            </div>
            <div style="font-size:12.5px;color:var(--muted);margin-top:4px;display:flex;gap:14px;flex-wrap:wrap;">
              <span>File Size: <strong id="apolloFileSize" style="color:var(--text);">72.8 KB</strong></span>
              <span>Total Columns: <strong id="apolloColCount" style="color:var(--text);">71</strong></span>
              <span>Total Rows: <strong id="apolloRowCount" style="color:var(--text);">25</strong></span>
            </div>
          </div>
        </div>
        <div style="display:flex;gap:10px;">
          <button class="btn-ghost btn-sm" id="apolloChangeFileBtn" style="display:flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/></svg> Replace File
          </button>
        </div>
      </div>

      <!-- Statistics KPI Grid -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:12px;margin-bottom:18px;">
        <div style="background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:14px;">
          <div style="font-size:11.5px;color:var(--muted);font-weight:600;text-transform:uppercase;">Rows Detected</div>
          <div id="kpiTotalRows" style="font-size:24px;font-weight:800;color:var(--text);margin-top:4px;">25</div>
          <div style="font-size:11px;color:var(--muted2);margin-top:2px;">From CSV file</div>
        </div>
        <div style="background:rgba(34,197,94,0.06);border:1px solid rgba(34,197,94,0.25);border-radius:12px;padding:14px;">
          <div style="font-size:11.5px;color:var(--good);font-weight:600;text-transform:uppercase;">New Leads</div>
          <div id="kpiNewLeads" style="font-size:24px;font-weight:800;color:var(--good);margin-top:4px;">25</div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px;">Ready to create</div>
        </div>
        <div style="background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.25);border-radius:12px;padding:14px;">
          <div style="font-size:11.5px;color:var(--warn);font-weight:600;text-transform:uppercase;">Existing in CRM</div>
          <div id="kpiExistingLeads" style="font-size:24px;font-weight:800;color:var(--warn);margin-top:4px;">0</div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px;">Will be skipped</div>
        </div>
        <div style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.25);border-radius:12px;padding:14px;">
          <div style="font-size:11.5px;color:#ff8e8e;font-weight:600;text-transform:uppercase;">In-File Duplicates</div>
          <div id="kpiInFileDup" style="font-size:24px;font-weight:800;color:#ff8e8e;margin-top:4px;">0</div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px;">Repeated rows</div>
        </div>
        <div style="background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:14px;">
          <div style="font-size:11.5px;color:var(--muted);font-weight:600;text-transform:uppercase;">Preserved Columns</div>
          <div id="kpiPreservedCols" style="font-size:24px;font-weight:800;color:var(--accent);margin-top:4px;">71</div>
          <div style="font-size:11px;color:var(--muted2);margin-top:2px;">100% full dataset</div>
        </div>
      </div>

      <!-- Preserved Fields Notification Banner -->
      <div style="background:var(--panel2);border:1px solid var(--border2);border-radius:10px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;font-size:13px;">
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="font-size:16px;">🛡️</span>
          <span><strong>100% Apollo Data Preservation:</strong> All original 71 Apollo fields (Seniority, Departments, Phone numbers, Funding, Technologies, SIC/NAICS, Apollo IDs) will be stored and viewable for every record.</span>
        </div>
      </div>

      <!-- Preview Table of Rows -->
      <div class="card" style="margin-bottom:20px;padding:0;overflow:hidden;">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <div>
            <h3 style="margin:0;font-size:15px;font-weight:700;">Data Preview (<span id="apolloPreviewCountDisplay">25 Records</span>)</h3>
            <p style="margin:2px 0 0 0;font-size:12px;color:var(--muted);">Verify field mappings before committing import</p>
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <input type="text" id="apolloPreviewSearch" placeholder="Search preview rows..." class="fld" style="padding:6px 12px;font-size:12px;width:200px;margin:0;">
            <select id="apolloPreviewPageSize" class="fld" style="padding:6px 10px;font-size:12px;margin:0;">
              <option value="10">10 per page</option>
              <option value="25" selected>25 per page</option>
              <option value="50">50 per page</option>
              <option value="all">Show All</option>
            </select>
          </div>
        </div>
        <div style="overflow-x:auto;">
          <table class="data" style="width:100%;font-size:12.5px;">
            <thead>
              <tr>
                <th style="width:90px;">Status</th>
                <th>Contact Name</th>
                <th>Job Title / Seniority</th>
                <th>Company</th>
                <th>Email & Verification</th>
                <th>Phone</th>
                <th>Location</th>
                <th style="text-align:right;">Apollo Fields</th>
              </tr>
            </thead>
            <tbody id="apolloPreviewTableBody">
              <!-- Dynamically populated -->
            </tbody>
          </table>
        </div>
        <!-- Preview Table Pagination Footer -->
        <div id="apolloPreviewPagerWrap" style="padding:12px 20px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;background:var(--panel2);">
          <span id="apolloPreviewPagerInfo" style="font-size:12.5px;color:var(--muted);">Showing 1–25 of 25 records</span>
          <div id="apolloPreviewPagerButtons" style="display:flex;gap:6px;align-items:center;">
            <!-- Prev / Next / Page pills -->
          </div>
        </div>
      </div>

      <!-- Confirmation Buttons Footer -->
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:18px 24px;">
        <div>
          <span style="font-size:13px;color:var(--muted);">Ready to import. No records have been written to the database yet.</span>
        </div>
        <div style="display:flex;gap:12px;">
          <button class="btn-ghost" id="apolloCancelBtn">Cancel</button>
          <button class="btn-primary btn-lg" id="apolloExecuteImportBtn" style="display:flex;align-items:center;gap:8px;padding:12px 24px;font-size:14px;font-weight:700;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <span id="apolloConfirmBtnText">Import 25 Records</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Final Import Result Summary Card -->
    <div id="apolloResultSection" class="hidden" style="margin-top:20px;">
      <div style="background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:30px 26px;text-align:center;">
        <div style="width:54px;height:54px;margin:0 auto 16px;border-radius:50%;background:rgba(34,197,94,0.15);color:var(--good);display:grid;place-items:center;">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h2 style="margin:0 0 6px 0;font-size:22px;font-weight:800;color:var(--text);">Apollo CSV Import Completed!</h2>
        <p style="margin:0 auto 24px;max-width:500px;font-size:13.5px;color:var(--muted);">
          Records were safely imported into CRM leads and contacts with complete Apollo attribute retention.
        </p>

        <!-- Result Stats Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:12px;max-width:760px;margin:0 auto 28px;">
          <div style="background:var(--panel2);border:1px solid var(--border);border-radius:10px;padding:14px;">
            <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;">Total Rows</div>
            <div id="resTotalRows" style="font-size:22px;font-weight:800;margin-top:4px;color:var(--text);">25</div>
          </div>
          <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.3);border-radius:10px;padding:14px;">
            <div style="font-size:11px;color:var(--good);font-weight:700;text-transform:uppercase;">Imported</div>
            <div id="resImported" style="font-size:22px;font-weight:800;margin-top:4px;color:var(--good);">25</div>
          </div>
          <div style="background:rgba(91,140,255,0.08);border:1px solid rgba(91,140,255,0.3);border-radius:10px;padding:14px;">
            <div style="font-size:11px;color:var(--accent2);font-weight:700;text-transform:uppercase;">Updated</div>
            <div id="resUpdated" style="font-size:22px;font-weight:800;margin-top:4px;color:var(--accent2);">0</div>
          </div>
          <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.3);border-radius:10px;padding:14px;">
            <div style="font-size:11px;color:var(--warn);font-weight:700;text-transform:uppercase;">Duplicates</div>
            <div id="resDuplicates" style="font-size:22px;font-weight:800;margin-top:4px;color:var(--warn);">0</div>
          </div>
          <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);border-radius:10px;padding:14px;">
            <div style="font-size:11px;color:var(--bad);font-weight:700;text-transform:uppercase;">Errors</div>
            <div id="resErrors" style="font-size:22px;font-weight:800;margin-top:4px;color:var(--bad);">0</div>
          </div>
        </div>

        <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap;">
          <a href="<?= e($slcJs['base']) ?>/leads" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:11px 22px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
            View Leads in CRM Pipeline
          </a>
          <button class="btn-secondary" id="resImportAgainBtn" style="padding:11px 22px;">
            Import Another CSV File
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB 3: FREE SEARCHING (DIRECT MULTI-LOCATION & N8N LEAD GENERATOR) -->
  <div class="tab-panel hidden" id="panelFreeSearch">
    
    <!-- Free Searching Card & Input Form -->
    <div style="background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:24px;position:relative;margin-bottom:24px;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
          <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;color:var(--good);margin-bottom:6px;">
            <span style="width:7px;height:7px;border-radius:50%;background:var(--good);display:inline-block;box-shadow:0 0 8px var(--good);"></span>
            <span>Shree Label Creation • Multi-Territory Lead Extractor & CRM Pusher</span>
          </div>
          <h2 style="margin:0;font-size:20px;font-weight:700;color:var(--text);">B2B Lead Generator & Regional Factory Finder</h2>
          <p style="margin:4px 0 0 0;font-size:13px;color:var(--muted);">
            Extract verified decision makers, procurement heads, direct emails, mobile numbers, and Google Maps links across West Bengal, Bihar, Odisha, Nepal, Bhutan, Manipur, Sikkim & Assam.
          </p>
        </div>
      </div>

      <form id="fsLeadForm" onsubmit="event.preventDefault(); return false;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;margin-bottom:16px;">
          
          <!-- 1. Location -->
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:6px;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--text);">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--bad)" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <span>Target Region(s) / Location</span>
              <span style="color:var(--bad);">*</span>
            </label>
            <input type="text" id="fsLocationInput" class="fld" placeholder="e.g. Sikkim, West Bengal, Bhutan, Bihar, Nepal, Odisha, Manipur..." value="West Bengal, Bihar, Odisha, Nepal, Bhutan, Manipur, Sikkim, Assam" style="width:100%;">
            <div style="font-size:11.5px;color:var(--muted);margin-top:4px;">
              💡 Tip: Type single or multiple locations separated by commas, or click quick presets below.
            </div>
            <!-- Target Markets Quick Presets -->
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:6px;">
              <span style="font-size:11px;color:var(--muted2);font-weight:600;align-self:center;">Presets:</span>
              <button type="button" class="btn-fs-chip" data-target="fsLocationInput" data-val="Kolkata, Siliguri, West Bengal" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🏛️ West Bengal</button>
              <button type="button" class="btn-fs-chip" data-target="fsLocationInput" data-val="Patna, Muzaffarpur, Bihar" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🌾 Bihar</button>
              <button type="button" class="btn-fs-chip" data-target="fsLocationInput" data-val="Bhubaneswar, Cuttack, Odisha" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🌊 Odisha</button>
              <button type="button" class="btn-fs-chip" data-target="fsLocationInput" data-val="Guwahati, Tezpur, Assam" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🌿 Assam</button>
              <button type="button" class="btn-fs-chip" data-target="fsLocationInput" data-val="Imphal, Thoubal, Manipur" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🏔️ Manipur</button>
              <button type="button" class="btn-fs-chip" data-target="fsLocationInput" data-val="Rangpo, Melli, Sikkim" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🍃 Sikkim</button>
              <button type="button" class="btn-fs-chip" data-target="fsLocationInput" data-val="Phuentsholing, Thimphu, Bhutan" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🇧🇹 Bhutan</button>
              <button type="button" class="btn-fs-chip" data-target="fsLocationInput" data-val="Kathmandu, Birgunj, Nepal" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🇳🇵 Nepal</button>
              <button type="button" class="btn-fs-chip" data-target="fsLocationInput" data-val="West Bengal, Bihar, Odisha, Assam, Sikkim, Bhutan, Nepal, Manipur" style="font-size:11px;background:rgba(59,130,246,0.15);border:1px solid var(--accent);color:#93c5fd;padding:2px 8px;border-radius:12px;cursor:pointer;font-weight:700;">⭐ All 8 Core Territories</button>
            </div>
          </div>

          <!-- 2. Company Name / Brand Partial Search -->
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:6px;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--text);">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--warn)" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
              <span>Company / Brand Name (Optional Fuzzy Search)</span>
              <span style="font-size:10px;background:var(--panel2);color:var(--muted);padding:1px 6px;border-radius:4px;margin-left:auto;">Optional</span>
            </label>
            <input type="text" id="fsCompanyNameInput" class="fld" placeholder="e.g. Savi, Bisleri, Old Monk, IFB, Yuksom, Mio Amore (or leave blank)" style="width:100%;">
            <div style="font-size:11.5px;color:var(--muted);margin-top:4px;">
              💡 Tip: Enter brand name (e.g. 'savi' in Sikkim finds all matching distilleries/units).
            </div>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:6px;">
              <span style="font-size:11px;color:var(--muted2);font-weight:600;align-self:center;">Brands:</span>
              <button type="button" class="btn-fs-chip" data-target="fsCompanyNameInput" data-val="Savi" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🔍 Savi (Sikkim)</button>
              <button type="button" class="btn-fs-chip" data-target="fsCompanyNameInput" data-val="Bisleri" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">💧 Bisleri</button>
              <button type="button" class="btn-fs-chip" data-target="fsCompanyNameInput" data-val="Old Monk" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🥃 Old Monk</button>
              <button type="button" class="btn-fs-chip" data-target="fsCompanyNameInput" data-val="Mio Amore" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🍰 Mio Amore</button>
              <button type="button" class="btn-fs-chip" data-target="fsCompanyNameInput" data-val="Yuksom Breweries" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🍺 Yuksom Breweries</button>
              <button type="button" class="btn-fs-chip" data-target="fsCompanyNameInput" data-val="" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--muted);padding:2px 8px;border-radius:12px;cursor:pointer;">✖ Clear</button>
            </div>
          </div>

          <!-- 3. Target Buyer Industry -->
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:6px;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--text);">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="9" y1="22" x2="9" y2="2"/><line x1="15" y1="22" x2="15" y2="2"/><line x1="4" y1="12" x2="20" y2="12"/></svg>
              <span>Target Buyer Industry</span>
              <span style="color:var(--bad);">*</span>
            </label>
            <input type="text" id="fsKeywordInput" class="fld" placeholder="e.g. Packaged Drinking Water, Liquor factory, Bakery, Pharma..." value="Packaged Drinking Water, Liquor factory, Bakery & Confectionery" style="width:100%;">
            <div style="font-size:11.5px;color:var(--muted);margin-top:4px;">
              💡 Tip: Buyer category requiring packaging, labels, or bottle wrapping.
            </div>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:6px;">
              <span style="font-size:11px;color:var(--muted2);font-weight:600;align-self:center;">Industries:</span>
              <button type="button" class="btn-fs-chip" data-target="fsKeywordInput" data-val="Packaged Drinking Water & Mineral Water Bottling" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">💧 Packaged Water</button>
              <button type="button" class="btn-fs-chip" data-target="fsKeywordInput" data-val="Distilleries, Breweries & Liquor Bottlers" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🍸 Liquor & Beer</button>
              <button type="button" class="btn-fs-chip" data-target="fsKeywordInput" data-val="Bakery, Confectionery & FMCG (Mio Amore style)" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🍰 Bakery & Snacks</button>
              <button type="button" class="btn-fs-chip" data-target="fsKeywordInput" data-val="Pharmaceutical & Healthcare Formulations" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">💊 Pharma</button>
              <button type="button" class="btn-fs-chip" data-target="fsKeywordInput" data-val="Cosmetics, Toiletries & Personal Care" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🧴 Cosmetics</button>
              <button type="button" class="btn-fs-chip" data-target="fsKeywordInput" data-val="Edible Oil, Mustard Oil & Lubricant Mills" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🛢️ Oils & Lubricants</button>
            </div>
          </div>

          <!-- 4. Target Products / Offerings -->
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:6px;margin-bottom:6px;font-size:12.5px;font-weight:600;color:var(--text);">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--good)" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
              <span>Shree Label Offerings & Products</span>
              <span style="font-size:10px;background:var(--panel2);color:var(--muted);padding:1px 6px;border-radius:4px;margin-left:auto;">Optional</span>
            </label>
            <input type="text" id="fsProductsInput" class="fld" placeholder="e.g. Multicolour Self-Adhesive Roll Labels, Bottle Wrap Labels, Barcode Rolls..." value="Multicolour Self-Adhesive Roll Labels, Bottle Wrap Labels, Barcode Rolls, POS Rolls" style="width:100%;">
            <div style="font-size:11.5px;color:var(--muted);margin-top:4px;">
              💡 Tip: Specific label formats or thermal roll requirements you pitch.
            </div>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:6px;">
              <span style="font-size:11px;color:var(--muted2);font-weight:600;align-self:center;">Labels:</span>
              <button type="button" class="btn-fs-chip" data-target="fsProductsInput" data-val="Multicolour Self-Adhesive Roll Labels & Stickers" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🏷️ Roll Labels</button>
              <button type="button" class="btn-fs-chip" data-target="fsProductsInput" data-val="Water Bottle Wrap-Around Labels & Shrink Sleeves" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">💧 Bottle Wrap Labels</button>
              <button type="button" class="btn-fs-chip" data-target="fsProductsInput" data-val="Metallic, Gold & Silver Foil Embossed Liquor Labels" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">✨ Foil Liquor Labels</button>
              <button type="button" class="btn-fs-chip" data-target="fsProductsInput" data-val="Transparent No-Look Clear Labels for Bottles" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🔍 Clear Labels</button>
              <button type="button" class="btn-fs-chip" data-target="fsProductsInput" data-val="Chromo, Polyester Barcode Labels & TTR Ribbons" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">📦 Barcode & TTR</button>
              <button type="button" class="btn-fs-chip" data-target="fsProductsInput" data-val="POS Thermal Billing Rolls (Plain & Pre-Printed)" style="font-size:11px;background:var(--panel2);border:1px solid var(--border);color:var(--text);padding:2px 8px;border-radius:12px;cursor:pointer;">🧾 POS Thermal Rolls</button>
            </div>
          </div>

          <!-- 5. Max Leads Count -->
          <div class="form-group" style="grid-column: 1 / -1;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
              <label style="display:flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;color:var(--text);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent2)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Max Leads to Extract (1 - 100)</span>
                <span style="color:var(--bad);">*</span>
              </label>
              <span style="font-size:12px;color:var(--muted);">Default: 30 leads</span>
            </div>
            <input type="number" id="fsMaxLeadsInput" class="fld" placeholder="30" min="1" max="100" value="30" style="width:100%;max-width:200px;">
          </div>
        </div>

        <!-- Engine Mode Switcher -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:var(--panel2);border:1px solid var(--border);border-radius:10px;margin-bottom:18px;font-size:13px;flex-wrap:wrap;gap:10px;">
          <div style="display:flex;align-items:center;gap:8px;color:var(--muted);font-weight:600;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
            <span>Execution Engine:</span>
          </div>
          <select id="fsEngineMode" class="fld" style="padding:6px 12px;font-size:12.5px;margin:0;width:auto;min-width:320px;">
            <option value="smart">⚡ Direct Dynamic Multi-Location Engine (Instant & Accurate)</option>
            <option value="n8n">🔗 n8n Docker Webhook (http://localhost:5678/webhook/b2b-leads)</option>
          </select>
        </div>

        <!-- Submit Button -->
        <button type="button" id="fsSubmitBtn" class="btn-primary" style="width:100%;padding:14px 20px;font-size:15px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 8px 25px rgba(59,130,246,0.35);">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          <span>Generate Targeted B2B Leads & Google Maps Links</span>
        </button>
      </form>

      <!-- Live Animated Progress Section -->
      <div id="fsProgressSection" style="display:none;margin-top:20px;padding:20px;background:rgba(124,92,255,0.06);border:1px solid rgba(124,92,255,0.25);border-radius:var(--radius);text-align:center;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <div class="spin" style="width:20px;height:20px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;"></div>
            <span id="fsCurrentStatusText" style="font-size:14px;font-weight:700;color:var(--text);">Initializing regional lead scanner...</span>
          </div>
          <div id="fsProgressPercentText" style="font-size:14px;font-weight:800;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;padding:3px 10px;border-radius:20px;">0%</div>
        </div>

        <div style="max-width:100%;background:var(--panel2);border:1px solid var(--border2);border-radius:10px;height:10px;overflow:hidden;padding:2px;margin-bottom:14px;">
          <div id="fsProgressBarFill" style="width:0%;height:100%;border-radius:6px;background:linear-gradient(90deg,var(--accent),#a855f7,#3b82f6,#10b981);transition:width .25s ease;"></div>
        </div>

        <div style="display:flex;justify-content:center;gap:16px;font-size:12px;color:var(--muted);flex-wrap:wrap;">
          <div id="fsStep1" style="display:flex;align-items:center;gap:6px;"><span style="width:18px;height:18px;border-radius:50%;background:var(--panel3);color:var(--muted);display:inline-block;text-align:center;line-height:18px;font-weight:700;">1</span> Regional Maps Scan</div>
          <div id="fsStep2" style="display:flex;align-items:center;gap:6px;"><span style="width:18px;height:18px;border-radius:50%;background:var(--panel3);color:var(--muted);display:inline-block;text-align:center;line-height:18px;font-weight:700;">2</span> Factory Matching</div>
          <div id="fsStep3" style="display:flex;align-items:center;gap:6px;"><span style="width:18px;height:18px;border-radius:50%;background:var(--panel3);color:var(--muted);display:inline-block;text-align:center;line-height:18px;font-weight:700;">3</span> Decision Makers & Contacts</div>
          <div id="fsStep4" style="display:flex;align-items:center;gap:6px;"><span style="width:18px;height:18px;border-radius:50%;background:var(--panel3);color:var(--muted);display:inline-block;text-align:center;line-height:18px;font-weight:700;">4</span> Excel & CRM Ready</div>
        </div>
      </div>

      <!-- Results Section -->
      <div id="fsResultsSection" style="display:none;margin-top:28px;">
        
        <!-- Prominent Download Excel Banner -->
        <div style="background:linear-gradient(135deg,rgba(16,185,129,0.12),rgba(5,150,105,0.08));border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius);padding:18px 22px;display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:14px;">
          <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:46px;height:46px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border-radius:12px;display:grid;place-items:center;box-shadow:0 0 15px rgba(16,185,129,0.4);">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            </div>
            <div>
              <h3 style="margin:0;font-size:16px;font-weight:700;color:var(--text);">Excel Spreadsheet Ready (.xlsx)</h3>
              <p id="fsDownloadSubtext" style="margin:2px 0 0 0;font-size:12.5px;color:var(--muted);">Leads compiled with decision maker contact details, direct emails, and Google Maps links.</p>
            </div>
          </div>
          
          <button id="fsDownloadExcelBtn" class="btn-primary" style="background:linear-gradient(135deg,#10b981,#059669);border-color:#10b981;padding:10px 20px;font-size:13.5px;display:inline-flex;align-items:center;gap:8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <span>Download Excel (.xlsx)</span>
          </button>
        </div>

        <!-- Metric Stat Cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:12px;margin-bottom:20px;">
          <div style="background:var(--panel2);border:1px solid var(--border);border-radius:12px;padding:14px;text-align:center;">
            <div id="fsStatTotalLeads" style="font-size:24px;font-weight:800;color:var(--text);">30</div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-top:2px;">Total Leads Found</div>
          </div>
          <div style="background:rgba(6,182,212,0.06);border:1px solid rgba(6,182,212,0.25);border-radius:12px;padding:14px;text-align:center;">
            <div id="fsStatDecisionMakers" style="font-size:24px;font-weight:800;color:var(--accent-cyan,#38bdf8);">30</div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-top:2px;">Decision Makers</div>
          </div>
          <div style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.25);border-radius:12px;padding:14px;text-align:center;">
            <div id="fsStatEmailsFound" style="font-size:24px;font-weight:800;color:var(--good);">100%</div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-top:2px;">Direct Emails Verified</div>
          </div>
          <div style="background:rgba(168,85,247,0.06);border:1px solid rgba(168,85,247,0.25);border-radius:12px;padding:14px;text-align:center;">
            <div id="fsStatLocationsCount" style="font-size:24px;font-weight:800;color:var(--accent2,#c084fc);">8</div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-top:2px;">Territories Scanned</div>
          </div>
        </div>

        <!-- Results Toolbar: Location Filter Tabs + Search -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
          <div id="fsLocationTabsContainer" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <!-- Dynamically injected location filter tabs -->
          </div>
          
          <div style="position:relative;">
            <input type="text" id="fsTableSearchInput" class="fld" placeholder="Filter company, contact, city..." style="padding:6px 12px;font-size:12px;width:220px;margin:0;border-radius:20px;">
          </div>
        </div>

        <!-- Lead Table Card with CRM Action Header -->
        <div class="card" style="padding:0;overflow:hidden;border:1px solid var(--border);border-radius:var(--radius);">
          
          <!-- Table Header / CRM Action Bar -->
          <div style="padding:14px 20px;border-bottom:1px solid var(--border);background:var(--panel2);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
              <h3 style="margin:0;font-size:15px;font-weight:700;color:var(--text);">Target Packaging Clients, Decision Makers & Maps</h3>
              <p style="margin:2px 0 0 0;font-size:12px;color:var(--muted);">Select leads to push directly to CRM Companies & Leads pipeline</p>
            </div>
            
            <!-- CRM Integration Controls -->
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <!-- Assign Sales User Dropdown -->
              <div style="display:flex;align-items:center;gap:6px;background:var(--panel);border:1px solid var(--border);border-radius:8px;padding:3px 10px;">
                <span style="font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;">👤 Assign To:</span>
                <select class="form-select btn-sm" id="fsAssignUser" style="padding:3px 6px;font-size:12px;border:none;background:transparent;color:var(--text);font-weight:600;cursor:pointer;">
                  <option value="">Admin (Me)</option>
                </select>
              </div>

              <!-- Quick Selection Buttons -->
              <button type="button" class="btn btn-secondary btn-sm" id="fsSelectHighBtn" style="font-size:12px;padding:5px 10px;">Select High</button>
              <button type="button" class="btn btn-secondary btn-sm" id="fsSelectAllBtn" style="font-size:12px;padding:5px 10px;">Select All</button>
              <button type="button" class="btn btn-secondary btn-sm" id="fsDeselectBtn" style="font-size:12px;padding:5px 10px;">Deselect</button>

              <!-- Main Action: Push to CRM -->
              <button type="button" class="btn-primary btn-sm" id="fsSaveToCrmBtn" style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;padding:6px 14px;font-weight:700;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7.5" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                <span>Add Selected to CRM</span>
              </button>

              <!-- Extra Exports -->
              <button type="button" class="btn btn-ghost btn-sm" id="fsDownloadCsvBtn" title="Download CSV File" style="font-size:12px;padding:5px 8px;display:inline-flex;align-items:center;gap:4px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Download CSV</span>
              </button>
              <button type="button" class="btn btn-ghost btn-sm" id="fsCopyEmailsBtn" title="Copy All Emails" style="font-size:12px;padding:5px 8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <span>Copy Emails</span>
              </button>
              <button type="button" class="btn btn-ghost btn-sm" id="fsCopyCsvBtn" title="Copy CSV to clipboard" style="font-size:12px;padding:5px 8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <span>Copy CSV</span>
              </button>
              <button type="button" class="btn btn-ghost btn-sm" id="fsExportJsonBtn" title="Export JSON" style="font-size:12px;padding:5px 8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                <span>JSON</span>
              </button>
            </div>
          </div>

          <!-- Lead Preview Table -->
          <div class="table-scroll-hint"><span>⇄ Swipe / Scroll horizontally to view all columns & phone numbers</span></div>
          <div class="table-wrap" style="overflow-x:auto;max-height:560px;overflow-y:auto;">
            <table class="data" style="width:100%;min-width:1050px;font-size:12.5px;border-collapse:collapse;">
              <thead>
                <tr style="position:sticky;top:0;background:var(--panel);z-index:2;">
                  <th style="width:40px;text-align:center;">
                    <input type="checkbox" id="fsCheckAll" style="cursor:pointer;accent-color:var(--accent);">
                  </th>
                  <th style="width:90px;">Lead ID</th>
                  <th style="width:240px;">Company & Website</th>
                  <th style="width:250px;">👤 Packaging Decision Maker</th>
                  <th style="width:280px;">🏢 Location, Address & Maps</th>
                  <th style="width:110px;text-align:center;">Status</th>
                </tr>
              </thead>
              <tbody id="fsLeadsTableBody">
                <!-- Dynamically Populated Rows -->
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- TAB 4: IMPORT HISTORY -->
  <div class="tab-panel hidden" id="panelImportHistory">
    <div class="card" style="padding:0;overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
          <h3 style="margin:0;font-size:15px;font-weight:700;">CSV Import History</h3>
          <p style="margin:2px 0 0 0;font-size:12px;color:var(--muted);">Audit log of all manual Apollo CSV import batches</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <button class="btn-ghost btn-sm" id="refreshHistoryBtn" style="display:flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Refresh
          </button>
          <button class="btn-ghost btn-sm" id="clearHistoryBtn" style="display:flex;align-items:center;gap:6px;color:var(--bad);" title="Clear all import log history">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Clear History
          </button>
        </div>
      </div>
      <div class="table-scroll-hint" style="margin: 8px 16px 0;"><span>⇄ Swipe / Scroll horizontally to view all history details</span></div>
      <div class="table-wrap" style="overflow-x:auto;">
        <table class="data" style="width:100%;min-width:1050px;font-size:13px;">
          <thead>
            <tr>
              <th>Date / Time</th>
              <th>File Name</th>
              <th>Source</th>
              <th>Total Rows</th>
              <th>Imported</th>
              <th>Duplicates</th>
              <th>Errors</th>
              <th>User</th>
              <th style="text-align:center;width:70px;">Action</th>
            </tr>
          </thead>
          <tbody id="importHistoryTableBody">
            <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--muted);">Loading history...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
