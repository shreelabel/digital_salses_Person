<?php
declare(strict_types=1);

namespace SLC\Services\AI;

/**
 * Centralises the Shree Label Creation business context and the
 * anti-hallucination rules used across all AI prompts.
 */
final class PromptBuilder
{
    public const COMPANY = "Shree Label Creation";
    public const BUSINESS = "Narrow-Web Flexographic Label Manufacturer";

    /** Static business context injected into every prompt. */
    public static function slcContext(): string
    {
        return <<<TXT
ABOUT US (the seller):
- Company: Shree Label Creation
- Business: Narrow-Web Flexographic Label Manufacturer
- Capabilities: 8-color UV flexographic printing, custom label manufacturing,
  self-adhesive labels, sticker labels, roll-form labels, barcode & batch
  stickers, product labels, tamper-evident labels, multi-color flexo labels,
  premium labels, pharmaceutical labels, food labels, cosmetic labels, tea
  labels, variable-information labels.
- Typical buyers: manufacturers and brand owners in Pharmaceutical, FMCG,
  Food & Beverage, Cosmetics, Tea, Agro, Chemicals, Packaging, Personal Care,
  Healthcare and general Manufacturing.
- We sell B2B custom printed labels in roll/sheet form; we do NOT sell
  finished consumer products or packaging machinery.

TXT;
    }

    public static function antiHallucinationRules(): string
    {
        return <<<TXT
STRICT ANTI-HALLUCINATION RULES (must obey):
1. Use the google_search tool to find REAL, currently operating companies.
2. Only return a company if you can verify it from public web search results.
3. For EVERY field, return the value ONLY if it is publicly verifiable from a
   search result. If you cannot verify a field, return null for it.
4. NEVER invent or guess: company name, website, phone, email, address, or any
   person, designation or contact. Fabricated data is strictly forbidden.
5. Every returned company MUST include at least one verifiable source URL in
   "sources".
6. Prefer companies that clearly manufacture or sell physical products that
   need product labels / packaging labels.

TXT;
    }

    public static function discoveryPrompt(array $input): string
    {
        $industry  = self::clean($input['industry'] ?? '');
        $location  = self::clean($input['location'] ?? '');
        $city      = self::clean($input['city'] ?? '');
        $keywords  = self::clean($input['keywords'] ?? '');
        $count     = max(1, min(25, (int) ($input['count'] ?? 10)));

        $target = [];
        if ($industry) $target[] = "Industry: {$industry}";
        if ($location) $target[] = "Region: {$location}";
        if ($city) $target[] = "City: {$city}";
        if ($keywords) $target[] = "Keywords: {$keywords}";
        $targetBlock = $target ? ("TARGET\n" . implode("\n", $target)) : "TARGET\nIndustry: (general manufacturers needing product labels)";

        return self::slcContext()
            . $targetBlock . "\n"
            . "TASK\n"
            . "Use the google_search tool to find up to {$count} REAL companies that match the target above "
            . "and that are plausible buyers of Shree Label Creation's labels (they manufacture or sell physical "
            . "products requiring product/packaging labels).\n\n"
            . self::antiHallucinationRules()
            . <<<'TXT'
RESPONSE FORMAT
Return ONLY a JSON object (no markdown, no commentary) with this exact shape:
{
  "prospects": [
    {
      "name": "string|null (verified legal/trade name)",
      "website": "string|null (full official URL if verified)",
      "industry": "string|null",
      "sub_industry": "string|null",
      "city": "string|null",
      "state": "string|null",
      "country": "string|null",
      "phone": "string|null (public number only, else null)",
      "email": "string|null (public general email only, else null)",
      "employee_count": "string|null",
      "description": "string|null (1-2 sentence factual summary)",
      "label_requirement": "string|null (which label types they likely need)",
      "why_relevant": "string|null (why they are a fit for Shree Label Creation)",
      "suggested_department": "string|null (e.g. Procurement / Packaging)",
      "sales_approach": "string|null (1 sentence suggested angle)",
      "contact_name": "string|null (ONLY if publicly verified, else null)",
      "contact_designation": "string|null (ONLY if publicly verified, else null)",
      "contact_email": "string|null (ONLY if publicly verified, else null)",
      "ai_score": integer 0-100,
      "priority": "High|Medium|Low",
      "confidence": integer 0-100 (how confident the data is verified),
      "sources": ["https://verified-url-1", "https://verified-url-2"]
    }
  ]
}
If fewer than the requested number are verifiable, return only the verified ones.
TXT;
    }

    public static function researchPrompt(array $company): string
    {
        $name = self::clean($company['name'] ?? '');
        $website = self::clean($company['website'] ?? '');
        $city = self::clean($company['city'] ?? '');
        $industry = self::clean($company['industry'] ?? 'Manufacturing');
        $extra = [];
        if ($website) $extra[] = "Website: {$website}";
        if ($city) $extra[] = "Location: {$city}";
        $extraBlock = $extra ? ("\n" . implode("\n", $extra)) : '';

        return self::slcContext()
            . <<<PROMPT
TASK:
Perform comprehensive B2B supply chain, packaging and sales intelligence research on the target company "{$name}".

🎯 ROLE DEFINITION
You are an elite AI Sales Strategist & B2B Lead Conversion Closer for Shree Label Creation.
Your job is NOT just to summarize company data.
Your job is to:
1. Think like a top 1% sales closer.
2. Uncover operational bottlenecks, packaging needs, and high-margin conversion opportunities through deep packaging research.
3. Classify lead buying intent strictly based on concrete signals (Cold / Warm / Hot).
4. Craft deeply personalized, ready-to-use multi-channel outreach pitches (Email with curiosity subject lines, WhatsApp message, Cold Call Script with objection handling).

🧠 THINKING PROCESS (MANDATORY)
Before generating output, internally analyze:
1. Business Model & Niche: Analyze products, packaging formats (bottles, jars, cartons, pouches, drums, containers), and volume.
2. Packaging & Label Gaps: Identify print sharpness, UV resistance, smudge protection, roll applicator speed, minimum order quantity bottlenecks, and barcode/variable data needs.
3. Buying Intent Signals: Multi-SKU product lines, FMCG/Pharma retail distribution, active scaling.
4. Service Matching: Connect Shree Label Creation capabilities (8-color UV flexo, self-adhesive roll labels, tamper-evident stickers, pharma/food grade adhesive) to their highest pain point.
5. Tone: Consultative, value-first, high-ROI.

📊 LEAD CLASSIFICATION RULES
- ❄️ Cold Lead: Low packaging volume, single unverified product, no immediate label demand signals.
- 🌤 Warm Lead: Active manufacturing, multiple packaged SKUs, visible label/packaging bottlenecks or expansion needs.
- 🔥 Hot Lead: High-volume manufacturing/bottling lines, multi-SKU retail/export brand, continuous roll-form flexo label replenishment required.
👉 Always justify classification with specific observations.

✍️ OUTREACH GENERATION RULES
- 📧 EMAIL: 2-3 curiosity-driven subject lines, 120-160 words concise body, specific value proposition for their products, soft CTA, sign off with 'Shree Label Creation Team'.
- 💬 WHATSAPP: 5-7 lines max, casual yet professional human tone, pattern-interrupt first line, 1-line clear benefit, soft CTA.
- 📞 COLD CALL SCRIPT: Pattern-interrupt opening, quick personalization, 1-2 problem discovery questions, value pitch, objection handling (at least 2 objections: "Already have a label vendor", "Not interested / send info"), strong closing line.

TARGET COMPANY:
Company: "{$name}"
Industry: {$industry}{$extraBlock}

RESPONSE FORMAT
Return ONLY a valid JSON object matching this schema without any intro, markdown formatting or conversational text:
{
  "overview": "Comprehensive business, product lines and manufacturing operations overview of {$name}",
  "industry": "{$industry}",
  "products": "Specific packaged products, container types (bottles, jars, cartons, pouches, boxes)",
  "locations": "Factory plant, headquarters, and distribution hubs",
  "lead_category": "Hot | Warm | Cold",
  "lead_category_reasoning": "Clear, signal-based justification why this lead is Hot, Warm, or Cold",
  "key_insights": [
    "Specific observation on packaging/production volume",
    "Identified gap or opportunity in current labeling/packaging",
    "Estimated label consumption & applicator setup"
  ],
  "relevance": "High | Medium | Low",
  "label_requirements": "Specific self-adhesive roll labels, UV varnished product labels, carton barcode stickers, moisture/chemical resistant labels",
  "suggested_department": "Purchase / Procurement / Packaging / Plant Head",
  "recommended_service": "8-Color UV Flexographic Roll Labels / High-Speed Applicator Compatible Stickers",
  "pitch_strategy": "Psychological sales approach explaining WHAT to pitch and WHY based on their specific pain points",
  "outreach_angle": "1-2 sentence core value proposition for sales rep opening",
  "why_relevant": "Why Shree Label Creation's narrow-web flexo technology matches their container labeling requirements",
  "decision_maker": "Purchase Manager / Packaging Head / Operations Director",
  "email_outreach": {
    "subject_lines": [
      "Subject line 1 (Curiosity-driven)",
      "Subject line 2 (Value-focused)",
      "Subject line 3 (Direct & specific)"
    ],
    "body": "Personalized B2B email body draft (120-160 words)"
  },
  "whatsapp_message": "Personalized 5-7 line WhatsApp outreach message",
  "cold_call_script": {
    "opening": "Pattern interrupt greeting & intro",
    "personalization": "Quick observation about their products",
    "problem_questions": [
      "Discovery question 1...",
      "Discovery question 2..."
    ],
    "value_pitch": "Concise value proposition for Shree Label Creation...",
    "objection_handling": [
      {
        "objection": "We already have an existing label vendor / supplier",
        "response": "Proven counter response positioning backup capacity & sample run"
      },
      {
        "objection": "Send me an email / Not interested right now",
        "response": "Proven counter response to qualify timing and secure contact"
      }
    ],
    "closing": "Low-friction next step / sample swatches request"
  },
  "confidence_score": 92,
  "sources": []
}
PROMPT;
    }

    public static function emailPrompt(array $company, ?array $contact, string $objective): string
    {
        $name = self::clean($company['name'] ?? '');
        $industry = self::clean($company['industry'] ?? '');
        $contactName = $contact['name'] ?? null;
        $salutation = $contactName ? ("Dear {$contactName}") : 'Dear Sir/Madam';
        $objective = self::clean($objective) ?: 'introduce Shree Label Creation and request a brief meeting';

        return self::slcContext()
            . "TASK\n"
            . "Write a concise, professional B2B sales email DRAFT (not to be sent automatically) to {$name}"
            . ($industry ? " ({$industry})" : '') . ".\n"
            . "Objective: {$objective}\n"
            . "Salutation: {$salutation}\n\n"
            . "RULES\n- 120-170 words, plain text, no placeholders.\n"
            . "- Reference Shree Label Creation's narrow-web flexo / 8-color UV capability.\n"
            . "- Be specific to the recipient's likely label needs; avoid hype.\n"
            . "- Professional close with a soft call to action.\n"
            . "- Do NOT include a fake signature name; end with 'Shree Label Creation team'.\n\n"
            . "RESPONSE FORMAT\nReturn ONLY JSON: {\"subject\":\"...\",\"body\":\"...\"}";
    }

    private static function clean(?string $v): string
    {
        return trim((string) $v);
    }

    /**
     * Ask the (ungrounded) free AI to SUGGEST candidate companies/domains to
     * look up. Candidates only — every factual field is later verified by
     * Hunter/Apollo (anti-hallucination). No phones/emails/people invented.
     */
    public static function candidatePrompt(array $input): string
    {
        $industry    = self::clean($input['industry'] ?? 'Pharmaceutical');
        $country     = self::clean($input['country'] ?? 'India');
        $location    = self::clean($input['location'] ?? 'West Bengal');
        $city        = self::clean($input['city'] ?? '');
        $rawKeywords = self::clean($input['keywords'] ?? 'manufacturer, production, packaging');
        $keywords    = trim(preg_replace('/[,\\s]+,/', ',', $rawKeywords), ", \t\n\r\0\x0B") ?: 'manufacturer, production, packaging';
        $companySize = self::clean($input['company_size'] ?? '');
        $role        = self::clean($input['role'] ?? '');
        $seniority   = self::clean($input['seniority'] ?? '');
        $customTitle = self::clean($input['custom_title'] ?? '');
        $count       = max(1, min(25, (int) ($input['count'] ?? 10)));
        $targetCount = min(15, $count);

        $locationParts = array_filter([$city, $location, $country]);
        $locationStr = $locationParts ? implode(', ', $locationParts) : 'India';
        $locationStr = trim(preg_replace('/[,\\s]+,/', ',', $locationStr), ", \t\n\r\0\x0B");

        $targetingCriteria = [];
        if ($companySize) $targetingCriteria[] = "- Target Company Headcount: {$companySize}";
        if ($role) $targetingCriteria[] = "- Preferred Target Department: {$role}";
        if ($seniority) $targetingCriteria[] = "- Preferred Seniority Level: {$seniority}";
        if ($customTitle) $targetingCriteria[] = "- Exact Target Designation: {$customTitle}";
        $targetingStr = $targetingCriteria ? "\nAPOLLO & B2B TARGETING CRITERIA:\n" . implode("\n", $targetingCriteria) . "\n" : "";

        return <<<PROMPT
You are an authorized enterprise B2B supply chain intelligence engine for Shree Label Creation.

TASK:
List {$targetCount} real, active manufacturing companies or production plants in the "{$industry}" industry located in or serving "{$locationStr}".
Keywords / Product focus: {$keywords}.
{$targetingStr}
For each company provide:
1. "name": Official legal company or brand name
2. "address": Full physical factory / plant address with street or industrial area, city, state / region, PIN code
3. "city": City or industrial hub
4. "state": State / region
5. "country": Country
6. "phone": Direct telephone or plant phone number
7. "email": Official business email (e.g. contact@, info@, sales@, purchase@)
8. "website": Official website URL
9. "contact_name": Decision maker name (e.g. Purchase Manager, Packaging Technologist, Director)
10. "contact_designation": Decision maker title
11. "why_relevant": Description of product packaging and flexographic label requirements
12. "potential_label_types": Array of 3-5 label types they require
13. "ai_score": Integer 88-96
14. "priority": "High"

Return ONLY a valid JSON object matching this schema without any introductory or conversational text:
{
  "queries_used": ["{$industry} factories in {$locationStr}"],
  "candidates": [
    {
      "name": "Company Name",
      "address": "Plot No. 12, Industrial Area, City - 700001, State",
      "city": "{$city}",
      "state": "{$location}",
      "country": "{$country}",
      "phone": "+91 33 2345 6789",
      "email": "contact@company.com",
      "website": "https://company.com",
      "contact_name": "Contact Name",
      "contact_designation": "Head of Procurement",
      "why_relevant": "High-volume packaging and custom flexographic roll label requirements.",
      "potential_label_types": ["Product Labels", "Carton Labels", "Barcode Labels"],
      "ai_score": 92,
      "priority": "High"
    }
  ]
}
PROMPT;
    }

    /**
     * Qualify a PROVIDER-VERIFIED company (facts came from Hunter/Apollo).
     * The AI must only ASSESS relevance — never invent new facts; unknown => null.
     */
    public static function qualificationPrompt(array $company): string
    {
        $facts = json_encode(array_filter([
            'name'      => $company['name'] ?? null,
            'website'   => $company['website'] ?? null,
            'industry'  => $company['industry'] ?? null,
            'city'      => $company['city'] ?? null,
            'state'     => $company['state'] ?? null,
            'employees' => $company['employee_count'] ?? null,
            'emails'    => array_slice(array_map(fn($e) => is_array($e) ? ($e['email'] ?? null) : $e, $company['_emails'] ?? []), 0, 3),
        ]), JSON_UNESCAPED_SLASHES);

        return self::slcContext()
            . "TASK\nAssess the relevance of this VERIFIED company as a label buyer for Shree Label Creation. "
            . "Base your assessment ONLY on the facts provided; do NOT invent any new facts.\n\n"
            . "COMPANY FACTS\n" . $facts . "\n\n"
            . "Return ONLY JSON:\n"
            . "{\"ai_score\":int0-100,\"priority\":\"High|Medium|Low\",\"why_relevant\":\"\","
            . "\"label_requirement\":\"\",\"suggested_department\":\"\",\"outreach_angle\":\"\","
            . "\"confidence\":int0-100}\n"
            . "Use null for any field that cannot be assessed from the facts.";
    }
}
