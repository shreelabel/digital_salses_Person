<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

use SLC\Core\HttpClient;

/**
 * Hunter.io provider.
 *  - domain-search  : the FREE company/domain discovery + public emails
 *  - email-finder   : used ONLY when a person's email is missing
 *  - email-verifier : optional verification
 *  - account        : connectivity probe + plan/credits (cost protection)
 *
 * All company/email/contact data comes from Hunter's response — nothing is
 * fabricated. Results are cached via ProviderContext.
 */
final class HunterProvider implements DiscoveryProviderInterface, EnrichmentProviderInterface
{
    public function __construct(private ProviderConfigRepository $config = new ProviderConfigRepository())
    {
    }

    public function slug(): string
    {
        return 'hunter';
    }

    public function isConfigured(): bool
    {
        return $this->config->isReady('hunter');
    }

    private function key(): ?string
    {
        return $this->config->getKey('hunter');
    }

    private function base(): string
    {
        return rtrim((string) ($this->config->get('hunter')?->baseUrl ?: 'https://api.hunter.io/v2'), '/');
    }

    /**
     * Discover real company data for a set of candidate domains. Hunter cannot
     * enumerate companies by industry (its free discovery is domain-based), so
     * the orchestrator passes candidate domains here. If none are passed, an
     * empty list is returned honestly (no fabrication).
     */
    public function discover(array $input, ProviderContext $ctx): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Hunter is not configured/enabled.'];
        }
        $domains = $input['domains'] ?? [];
        if (!is_array($domains) || empty($domains)) {
            return ['ok' => true, 'candidates' => [], 'note' => 'Hunter discovery is domain-based; no candidate domains were provided.'];
        }

        $out = [];
        foreach (array_slice($domains, 0, 25) as $domain) {
            $domain = $this->normalizeDomain((string) $domain);
            if (!$domain) {
                continue;
            }
            $res = $this->domainSearch($domain, $ctx);
            if (!empty($res['company'])) {
                $out[] = $res['company'] + ['_emails' => $res['emails'] ?? []];
            }
        }
        return ['ok' => true, 'candidates' => $out];
    }

    /** GET /domain-search — the core Hunter enrichment/discovery call. */
    public function domainSearch(string $domain, ProviderContext $ctx): array
    {
        $cacheKey = 'ds:' . $domain;
        $call = $ctx->call(
            'hunter', 'domain_search', $cacheKey,
            fn() => HttpClient::get($this->base() . '/domain-search?domain=' . urlencode($domain) . '&limit=10&api_key=' . urlencode((string) $this->key()), [], 5),
            fn($d) => $this->parseDomainSearch($d),
            86400,
            'domain=' . $domain
        );
        if (!$call['ok']) {
            return ['ok' => false, 'error' => $call['error']];
        }
        return ['ok' => true] + ($call['data'] ?? []);
    }

    public function findPeople(string $domain, array $titles, ProviderContext $ctx): array
    {
        // Hunter is not a people-search provider (that is Apollo's role).
        return ['ok' => false, 'error' => 'People search is handled by Apollo, not Hunter.'];
    }

    /** GET /email-finder — only called when an email is missing. */
    public function findEmail(string $domain, ?string $firstName, ?string $lastName, ?string $fullName, ProviderContext $ctx): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Hunter is not configured.'];
        }
        if ($fullName && (!$firstName || !$lastName)) {
            $parts = explode(' ', trim($fullName), 2);
            $firstName ??= $parts[0] ?? null;
            $lastName ??= $parts[1] ?? null;
        }
        $cacheKey = 'ef:' . $domain . ':' . $firstName . ':' . $lastName;
        $params = http_build_query(array_filter([
            'domain' => $domain, 'first_name' => $firstName, 'last_name' => $lastName, 'api_key' => $this->key(),
        ]));
        $call = $ctx->call(
            'hunter', 'email_finder', $cacheKey,
            fn() => HttpClient::get($this->base() . '/email-finder?' . $params),
            fn($d) => $this->parseEmailFinder($d),
            604800,
            'domain=' . $domain . ' name=' . $firstName . ' ' . $lastName
        );
        if (!$call['ok']) {
            return ['ok' => false, 'error' => $call['error']];
        }
        $data = $call['data'] ?? [];
        return ['ok' => true, 'email' => $data['email'] ?? null, 'score' => $data['score'] ?? null];
    }

    public function ping(ProviderContext $ctx): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'configured' => false, 'message' => 'Not Configured'];
        }
        $call = $ctx->call(
            'hunter', 'account', 'account',
            fn() => HttpClient::get($this->base() . '/account?api_key=' . urlencode((string) $this->key())),
            fn($d) => $this->parseAccount($d),
            300,
            'ping'
        );
        if (!$call['ok']) {
            return ['ok' => false, 'configured' => true, 'message' => $call['error']];
        }
        $data = $call['data'] ?? [];
        return ['ok' => true, 'configured' => true, 'message' => 'Connected', 'plan' => $data['plan'] ?? null, 'remaining' => $data['remaining'] ?? null];
    }

    // ---------- response parsers ----------

    private function parseDomainSearch(array $d): array
    {
        $data = $d['data'] ?? [];
        $emails = [];
        foreach (($data['emails'] ?? []) as $e) {
            if (!empty($e['value'])) {
                $emails[] = [
                    'email'      => $e['value'],
                    'confidence' => $e['confidence'] ?? null,
                    'first_name' => $e['first_name'] ?? null,
                    'last_name'  => $e['last_name'] ?? null,
                    'name'       => trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: null,
                    'position'   => $e['position'] ?? null,
                    'department' => $e['department'] ?? null,
                    'type'       => $e['type'] ?? null,
                    'sources'    => array_column($e['sources'] ?? [], 'uri'),
                ];
            }
        }
        $company = [
            'name'      => $data['organization'] ?? null,
            'website'   => $data['domain'] ?? null,
            'industry'  => $data['industry'] ?? null,
            'city'      => $data['city'] ?? null,
            'state'     => $data['state'] ?? null,
            'country'   => $data['country'] ?? null,
            'phone'     => $data['phone'] ?? null,
            'employee_count' => $data['employees'] ?? null,
            'linkedin_url' => $data['linkedin'] ?? null,
            'pattern'   => $data['pattern'] ?? null,
        ];
        return ['company' => $company, 'emails' => $emails];
    }

    private function parseEmailFinder(array $d): array
    {
        $data = $d['data'] ?? [];
        return [
            'email' => $data['email'] ?? null,
            'score' => $data['score'] ?? null,
        ];
    }

    private function parseAccount(array $d): array
    {
        $data = $d['data'] ?? [];
        return [
            'plan'      => $data['plan_name'] ?? $data['plan_level'] ?? null,
            'remaining' => $data['calls']['left'] ?? ($data['remaining'] ?? null),
        ];
    }

    private function normalizeDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') return null;
        $host = parse_url($domain, PHP_URL_HOST);
        $host = $host ?: (str_contains($domain, '.') ? $domain : null);
        if (!$host) return null;
        return preg_replace('/^www\./', '', $host);
    }
}
