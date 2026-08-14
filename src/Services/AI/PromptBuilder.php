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
            . "TASK\n"
            . "Perform comprehensive B2B supply chain and packaging research on the company \"{$name}\" ({$industry}).\n"
            . "Analyze their manufacturing operations, product portfolio, packaging types (bottles, jars, cartons), and specific flexographic label requirements for Shree Label Creation.{$extraBlock}\n\n"
            . "RESPONSE FORMAT\n"
            . "Return ONLY a valid JSON object matching this schema without any intro, conversational text or markdown:\n"
            . "{\n"
            . "  \"overview\": \"Comprehensive business and manufacturing overview of {$name}\",\n"
            . "  \"industry\": \"{$industry}\",\n"
            . "  \"products\": \"Packaged beverage, food, or industrial products produced\",\n"
            . "  \"locations\": \"Factory plant, headquarters, and distribution hubs\",\n"
            . "  \"relevance\": \"High/Medium relevance as a narrow-web flexographic label buyer\",\n"
            . "  \"label_requirements\": \"Specific self-adhesive roll labels, bottle stickers, carton barcode labels, UV varnished product labels\",\n"
            . "  \"suggested_department\": \"Purchase / Procurement / Packaging Head\",\n"
            . "  \"outreach_angle\": \"Tailored sales angle emphasizing fast turnaround, food-grade adhesive, and 8-color UV flexo print quality\",\n"
            . "  \"why_relevant\": \"Why Shree Label Creation's flexo narrow-web printing matches their container labeling requirements\",\n"
            . "  \"decision_maker\": \"Purchase Manager / Packaging Head\",\n"
            . "  \"confidence_score\": 92,\n"
            . "  \"sources\": []\n"
            . "}";
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
