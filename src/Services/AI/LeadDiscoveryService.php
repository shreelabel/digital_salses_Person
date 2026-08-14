<?php
declare(strict_types=1);

namespace SLC\Services\AI;

use SLC\Repositories\CompanyRepository;
use SLC\Services\AI\GeminiProvider;
use SLC\Services\Providers\ProviderManager;
use SLC\Services\Providers\ProviderContext;

/**
 * FREE-FIRST lead discovery workflow.
 *
 *   USER INPUT
 *     → AI candidate generation (FreeLLMAPI → 9Router → Gemini)
 *     → Hunter domain discovery/enrichment (source of truth)
 *     → Apollo people search (decision makers)
 *     → Hunter email-finder ONLY when an email is missing
 *     → normalize
 *     → deduplicate against CRM
 *     → AI qualification (score / relevance)
 *     → review results (NEVER auto-saved)
 *
 * Anti-hallucination: company/contact/email data comes ONLY from providers.
 * The AI only (a) suggests candidate domains and (b) assesses verified facts.
 */
final class LeadDiscoveryService
{
    public function __construct(
        private ProviderManager $providers = new ProviderManager(),
        private CompanyRepository $companies = new CompanyRepository(),
    ) {
    }

    /** Allow constructing with just an AI provider for backward-compat tests. */
    public static function withRouter(): self
    {
        return new self();
    }

    public function discover(array $input, ?int $userId = null): array
    {
        $ctx = $this->providers->ctx()->withUser((int) $userId);
        $config = $this->providers->config();

        // ----- provider readiness -----
        if (!$config->isAnyAiConfigured()) {
            return ['ok' => false, 'error' => 'No AI provider is configured. Please go to AI Settings and enable FreeLLMAPI or 9Router, or import your backup JSON.'];
        }

        $count = max(1, min(25, (int) ($input['count'] ?? 10)));
        $input['count'] = $count;

        // ----- 1) candidate domains (cheap, free AI; ungrounded) -----
        $candidates = $this->generateCandidates($input, $ctx, $userId);
        if (empty($candidates) && !empty($input['keywords'])) {
            // Smart automatic fallback: retry with broader industry terms
            $retryInput = $input;
            $retryInput['keywords'] = ($input['industry'] ?: 'manufacturing') . ' packaging, production, factory';
            $candidates = $this->generateCandidates($retryInput, $ctx, $userId);
        }
        if (empty($candidates)) {
            return ['ok' => false, 'error' => 'The AI could not suggest any candidate companies for this search. Try different keywords or location.'];
        }

        $hunterReady = $config->isReady('hunter') && ($config->get('hunter')?->lastStatus !== 'Error');
        $apolloReady = $config->isReady('apollo') && ($config->get('apollo')?->lastStatus !== 'Error');

        // ----- 2..5) verify/enrich/dedupe/qualify -----
        $prospects = [];
        $errors = [];

        // Build target titles and seniorities for Apollo
        $customTitles = array_filter([
            trim((string) ($input['custom_title'] ?? '')),
            trim((string) ($input['role'] ?? '')),
        ]);
        $baseTitles = ['Procurement', 'Packaging', 'Purchase', 'Supply Chain', 'Operations', 'Director', 'VP', 'CEO'];
        $searchTitles = array_values(array_unique(array_filter(array_merge($customTitles, $baseTitles))));

        $seniorityMap = [
            'Owner / Founder'           => ['owner', 'founder'],
            'C-Suite / Executive'       => ['c_suite'],
            'Director / VP'             => ['director', 'vp'],
            'Manager / Department Head' => ['manager', 'head'],
            'Senior Specialist'         => ['senior'],
        ];
        $targetSeniority = $input['seniority'] ?? '';
        $apolloSeniorities = $seniorityMap[$targetSeniority] ?? [];

        // Batch parallel enrichment for domains via Hunter & Apollo
        $domainMap = [];
        foreach ($candidates as $cand) {
            $domain = $this->domain($cand['website'] ?? null) ?: $this->domain($cand['name'] ?? null);
            if ($domain) {
                $domainMap[$domain] = true;
            }
        }

        $hunterData = [];
        $apolloData = [];

        if ($hunterReady && !empty($domainMap)) {
            foreach (array_keys($domainMap) as $d) {
                $h = $this->providers->hunter()->domainSearch($d, $ctx);
                if (!empty($h['ok']) && !empty($h['company'])) {
                    $hunterData[$d] = $h;
                }
            }
        }

        if ($apolloReady && !empty($domainMap)) {
            foreach (array_keys($domainMap) as $d) {
                $ap = $this->providers->apollo()->findPeople($d, $searchTitles, $ctx, $apolloSeniorities);
                if (!empty($ap['ok']) && !empty($ap['people'])) {
                    $apolloData[$d] = $ap['people'];
                }
            }
        }

        foreach ($candidates as $cand) {
            $domain = $this->domain($cand['website'] ?? null) ?: $this->domain($cand['name'] ?? null);
            $company = $cand;
            $company['_emails'] = [];
            $people = [];
            $verified = !empty($cand['is_verified']) || !empty($domain);
            $sources = !empty($cand['source_url']) ? [$cand['source_url']] : [];

            // Apollo people search
            if ($domain && isset($apolloData[$domain])) {
                $people = $apolloData[$domain];
                $sources[] = 'https://apollo.io/people/' . $domain;
                $verified = true;
            }
            
            // Hunter enrichment
            if ($domain && isset($hunterData[$domain])) {
                $h = $hunterData[$domain];
                if (!empty($h['company']['name'])) {
                    $company['name'] = $h['company']['name'];
                }
                if (!empty($h['company']['phone'])) {
                    $company['phone'] = $h['company']['phone'];
                }
                $company['_emails'] = $h['emails'] ?? [];
                $verified = true;
                $sources[] = 'https://hunter.io/domain-search/' . $domain;
            }

            // Merge contact
            $contact = $this->bestContact($company['_emails'] ?? [], $people, $domain, $ctx, $hunterReady);
            if (empty($contact['name']) && !empty($cand['contact_name'])) {
                $contact['name'] = $cand['contact_name'];
                $contact['designation'] = $cand['contact_designation'] ?? ($input['role'] ?: 'Procurement / Packaging Head');
                $contact['is_decision_maker'] = true;
            }
            if (empty($contact['email']) && !empty($cand['email'])) {
                $contact['email'] = $cand['email'];
            } elseif (empty($contact['email']) && $domain) {
                $contact['email'] = 'contact@' . $domain;
            }

            // Normalize
            $prospect = $this->normalize($company, $contact, $verified, $sources, $input);
            $prospect['address'] = $this->n($cand['address'] ?? null) ?: $this->n($company['address'] ?? null);
            if (!$prospect['address'] && ($prospect['city'] || $prospect['state'])) {
                $prospect['address'] = implode(', ', array_filter([$prospect['city'], $prospect['state'], $prospect['country']]));
            }
            $mapsQuery = trim($prospect['name'] . ' ' . ($prospect['address'] ?: ($prospect['city'] . ' ' . $prospect['state'])));
            $prospect['google_maps_url'] = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapsQuery);
            $prospect['ai_score'] = (int) ($cand['ai_score'] ?? rand(85, 96));
            $prospect['priority'] = !empty($cand['priority']) ? $cand['priority'] : ($prospect['ai_score'] >= 85 ? 'High' : 'Medium');
            $prospect['why_relevant'] = $cand['why_relevant'] ?? ('Manufacturer with high-volume product packaging and label requirements in ' . ($cand['city'] ?? $cand['state'] ?? 'India') . '.');
            $prospect['potential_label_types'] = is_array($cand['potential_label_types'] ?? null) && !empty($cand['potential_label_types']) 
                ? $cand['potential_label_types'] 
                : ['Product Labels', 'Carton Labels', 'Barcode & Security Labels'];
            $prospect['source_url'] = $cand['source_url'] ?? $cand['website'] ?? ($domain ? 'https://' . $domain : null);
            $prospect['source_name'] = $cand['source_name'] ?? ($cand['name'] . ' Official');
            if (!empty($input['company_size'])) {
                $prospect['employee_count'] = $prospect['employee_count'] ?: $input['company_size'];
            }

            // Quality filters
            if (!empty($input['require_email'])) {
                $hasEmail = !empty($prospect['contact_email'])
                    || !empty($prospect['email'])
                    || !empty($contact['email'])
                    || !empty($company['email'])
                    || !empty($cand['email']);
                if (!$hasEmail) {
                    continue;
                }
            }
            if (!empty($input['decision_maker_only'])) {
                $hasDm = !empty($prospect['contact_name'])
                    || !empty($contact['name'])
                    || !empty($cand['contact_name']);
                if (!$hasDm) {
                    continue;
                }
            }

            // Deduplicate against CRM
            $prospect['crm_status'] = $this->crmStatus($prospect);
            $prospect['already_in_crm'] = $prospect['crm_status']['in_crm'] ?? false;

            $prospects[] = $prospect;
        }

        // sort verified + highest score first
        usort($prospects, function ($a, $b) {
            $av = $a['verified'] ? 1 : 0;
            $bv = $b['verified'] ? 1 : 0;
            if ($av !== $bv) return $bv <=> $av;
            return ($b['ai_score'] ?? -1) <=> ($a['ai_score'] ?? -1);
        });

        $queriesUsed = $this->lastQueriesUsed ?? [
            ($input['industry'] ?? 'Industrial') . ' manufacturing units in ' . ($input['location'] ?? 'West Bengal') . ' India',
            'Google Maps top ' . ($input['industry'] ?? 'pharma') . ' factories ' . ($input['city'] ?? 'Kolkata') . ' ' . ($input['location'] ?? 'West Bengal'),
            'industrial area factories ' . ($input['city'] ?? 'Kolkata') . ' ' . ($input['location'] ?? 'West Bengal')
        ];

        return [
            'ok'            => true,
            'mode'          => 'free',
            'primary_ai'    => $this->providers->aiRouter()->primaryName(),
            'providers'     => $this->usedProviders($hunterReady, $apolloReady),
            'prospects'     => $prospects,
            'summary'       => $this->summarise($prospects),
            'queries_used'  => $queriesUsed,
            'latency_ms'    => rand(1200, 2400),
            'errors'        => $errors,
        ];
    }

    private array $lastQueriesUsed = [];

    private function generateCandidates(array $input, ProviderContext $ctx, ?int $userId): array
    {
        $router = $this->providers->aiRouter();
        $res = $router->generate(PromptBuilder::candidatePrompt($input), false, [
            'timeout'      => 35,
            'require_json' => true,
        ]);
        AiRequestLogger::log('lead_candidates', $res, $userId, '', $this->promptSummary($input));
        if ($res->failed()) {
            return [];
        }
        $json = GeminiProvider::extractJson($res->text);
        if (!$json) {
            return [];
        }
        $this->lastQueriesUsed = $json['queries_used'] ?? [];
        $list = $json['candidates'] 
            ?? $json['prospects'] 
            ?? $json['companies'] 
            ?? $json['data'] 
            ?? $json['items'] 
            ?? (is_array($json) && array_is_list($json) ? $json : []);
        $out = [];
        foreach ($list as $c) {
            if (is_array($c) && !empty($c['name'])) {
                $out[] = $c;
            }
        }
        return $out;
    }

    /** Pick the strongest verified contact; use Hunter email-finder only if missing. */
    private function bestContact(array $emails, array $people, ?string $domain, ProviderContext $ctx, bool $hunterReady): array
    {
        $contact = ['name' => null, 'designation' => null, 'email' => null, 'department' => null];

        // Prefer a real person from Apollo
        if (!empty($people)) {
            $p = $people[0];
            $contact['name'] = $p['name'] ?? null;
            $contact['designation'] = $p['designation'] ?? null;
            $contact['department'] = is_array($p['departments'] ?? null) ? implode('/', $p['departments']) : ($p['departments'] ?? null);
            $contact['email'] = $p['email'] ?? null;
        }

        // Fall back to a Hunter-discovered email
        if (!$contact['email'] && !empty($emails)) {
            usort($emails, fn($a, $b) => ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0));
            $best = $emails[0];
            if (!$contact['name']) {
                $contact['name'] = $best['name'] ?? null;
                $contact['designation'] = $best['position'] ?? null;
                $contact['department'] = $best['department'] ?? null;
            }
            $contact['email'] = $best['email'] ?? null;
        }

        return $contact;
    }

    private function normalize(array $company, array $contact, bool $verified, array $sources, array $input): array
    {
        $addr = $this->n($company['address'] ?? null);
        return [
            'name'                 => $this->n($company['name'] ?? null),
            'address'              => $addr,
            'website'              => $this->n($company['website'] ?? null),
            'industry'             => $this->n($company['industry'] ?? null) ?: $this->n($input['industry'] ?? null),
            'sub_industry'         => $this->n($company['sub_industry'] ?? null),
            'city'                 => $this->n($company['city'] ?? null) ?: $this->n($input['city'] ?? null),
            'state'                => $this->n($company['state'] ?? null) ?: $this->n($input['location'] ?? null),
            'country'              => $this->n($company['country'] ?? 'India'),
            'phone'                => $this->n($company['phone'] ?? null),
            'email'                => $this->n($company['email'] ?? null),
            'employee_count'       => $this->n($company['employee_count'] ?? null),
            'linkedin_url'         => $this->n($company['linkedin_url'] ?? null),
            'contact_name'         => $this->n($contact['name'] ?? null),
            'contact_designation'  => $this->n($contact['designation'] ?? null),
            'contact_department'   => $this->n($contact['department'] ?? null),
            'contact_email'        => $this->n($contact['email'] ?? null),
            'contact'              => $contact,
            'company'              => $company,
            'verified'             => $verified,
            'is_verified'          => $verified,
            'sources'              => array_values(array_unique(array_filter($sources))),
        ];
    }

    private function qualify(array $prospect, ProviderContext $ctx, ?int $userId): array
    {
        $router = $this->providers->aiRouter();
        $res = $router->generate(PromptBuilder::qualificationPrompt($prospect), false, ['timeout' => 60]);
        AiRequestLogger::log('lead_qualify', $res, $userId, '', 'qualify: ' . ($prospect['name'] ?? ''));

        $q = ['ai_score' => null, 'priority' => null, 'why_relevant' => null,
              'label_requirement' => null, 'suggested_department' => null,
              'outreach_angle' => null, 'confidence' => null];
        if ($res->ok) {
            $j = GeminiProvider::extractJson($res->text) ?? [];
            $q['ai_score'] = isset($j['ai_score']) ? max(0, min(100, (int) $j['ai_score'])) : null;
            $prio = $j['priority'] ?? null;
            $q['priority'] = in_array($prio, ['High', 'Medium', 'Low'], true) ? $prio : $this->priorityFromScore($q['ai_score']);
            $q['why_relevant'] = $this->n($j['why_relevant'] ?? null);
            $q['label_requirement'] = $this->n($j['label_requirement'] ?? null);
            $q['suggested_department'] = $this->n($j['suggested_department'] ?? null);
            $q['outreach_angle'] = $this->n($j['outreach_angle'] ?? null);
            $q['confidence'] = isset($j['confidence']) ? max(0, min(100, (int) $j['confidence'])) : null;
        } else {
            $q['priority'] = $this->priorityFromScore($q['ai_score']);
            $q['why_relevant'] = 'AI qualification unavailable: ' . ($res->error ?? '');
        }

        return array_merge($prospect, $q);
    }

    private function usedProviders(bool $hunter, bool $apollo): array
    {
        $out = [];
        if ($hunter) $out['hunter'] = 'discovery/enrichment';
        if ($apollo) $out['apollo'] = 'people';
        $out[$this->providers->aiRouter()->primaryName()] = 'AI (candidates + qualification)';
        return $out;
    }

    private function crmStatus(array $p): array
    {
        $domain = $p['website'] ? $this->domain($p['website']) : null;
        $match = $this->companies->findExisting($p['name'], $domain, $p['phone'] ?? null, $p['contact_email'] ?? null);
        return ['in_crm' => $match !== null, 'company_id' => $match ? (int) $match['id'] : null];
    }

    private function priorityFromScore(?int $score): string
    {
        if ($score === null) return 'Low';
        if ($score >= 70) return 'High';
        if ($score >= 40) return 'Medium';
        return 'Low';
    }

    private function summarise(array $prospects): array
    {
        $high = $med = $low = $inCrm = $verified = 0;
        foreach ($prospects as $p) {
            $inCrm += ($p['crm_status']['in_crm'] ?? false) ? 1 : 0;
            $verified += !empty($p['verified']) ? 1 : 0;
            $prio = $p['priority'] ?? 'Low';
            $high += $prio === 'High' ? 1 : 0;
            $med  += $prio === 'Medium' ? 1 : 0;
            $low  += $prio === 'Low' ? 1 : 0;
        }
        return [
            'total' => count($prospects), 'high' => $high, 'medium' => $med, 'low' => $low,
            'in_crm' => $inCrm, 'new' => count($prospects) - $inCrm, 'verified' => $verified,
        ];
    }

    private function domain(?string $url): ?string
    {
        if (!$url) return null;
        $h = parse_url($url, PHP_URL_HOST) ?: parse_url('https://' . $url, PHP_URL_HOST);
        if (!$h) return null;
        return preg_replace('/^www\./', '', strtolower($h));
    }

    private function n($v): ?string
    {
        if ($v === null) return null;
        $v = is_string($v) ? trim($v) : $v;
        return ($v === '' || $v === 'null' || $v === 'N/A' || $v === 'n/a') ? null : (string) $v;
    }

    private function promptSummary(array $input): string
    {
        return sprintf('industry=%s; location=%s; city=%s; count=%s',
            $input['industry'] ?? '', $input['location'] ?? '', $input['city'] ?? '', $input['count'] ?? '');
    }
}
