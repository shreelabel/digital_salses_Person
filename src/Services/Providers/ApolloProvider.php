<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

use SLC\Core\HttpClient;

/**
 * Apollo.io provider.
 *  - people search (POST /mixed_people/search) : decision-maker discovery
 *  - api_usage (GET /api_usage)                : connectivity + credit probe
 *
 * Apollo Organization Search is intentionally NOT auto-used in free mode
 * (it consumes credits). Email Finder is not provided by Apollo — Hunter does
 * that. Data comes only from Apollo responses; nothing is fabricated.
 */
final class ApolloProvider implements EnrichmentProviderInterface
{
    public function __construct(private ProviderConfigRepository $config = new ProviderConfigRepository())
    {
    }

    public function slug(): string
    {
        return 'apollo';
    }

    public function isConfigured(): bool
    {
        return $this->config->isReady('apollo');
    }

    private function key(): ?string
    {
        return $this->config->getKey('apollo');
    }

    private function base(): string
    {
        return rtrim((string) ($this->config->get('apollo')?->baseUrl ?: 'https://api.apollo.io/v1'), '/');
    }

    private static bool $peopleApiDisabled = false;

    /**
     * Find decision-maker people for a domain.
     * @param string[] $titles e.g. ["Procurement","Packaging","VP"]
     * @param string[] $seniorities e.g. ["owner","founder","c_suite","vp","director","manager"]
     */
    public function findPeople(string $domain, array $titles, ProviderContext $ctx, array $seniorities = []): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Apollo is not configured/enabled.'];
        }
        if (self::$peopleApiDisabled) {
            return ['ok' => false, 'error' => 'Apollo people search is not included in current plan.'];
        }
        $domain = $this->normalizeDomain($domain);
        if (!$domain) {
            return ['ok' => false, 'error' => 'Invalid domain.'];
        }
        $titles = array_values(array_filter(array_map('strval', $titles)));
        $seniorities = array_values(array_filter(array_map('strval', $seniorities)));
        $cacheKey = 'ppl:' . $domain . ':' . implode(',', array_slice($titles, 0, 5)) . ':' . implode(',', $seniorities);
        $body = array_filter([
            'organization_domains' => [$domain],
            'person_titles'        => $titles,
            'person_seniorities'   => $seniorities,
            'per_page'             => 10,
            'contact_statuses'     => ['open'],
        ], fn($v) => $v !== [] && $v !== null);

        $call = $ctx->call(
            'apollo', 'people_search', $cacheKey,
            fn() => HttpClient::post(
                $this->base() . '/mixed_people/search',
                ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache', 'X-Api-Key' => (string) $this->key()],
                $body,
                4
            ),
            fn($d) => $this->parsePeople($d),
            86400,
            'domain=' . $domain
        );
        if (!$call['ok']) {
            if ($call['status'] === 403 || str_contains((string)($call['error'] ?? ''), 'Free plan') || str_contains((string)($call['error'] ?? ''), 'API_INACCESSIBLE')) {
                self::$peopleApiDisabled = true;
            }
            return ['ok' => false, 'error' => $call['error']];
        }
        return ['ok' => true, 'people' => $call['data']['people'] ?? []];
    }

    /** Apollo has no standalone email-finder endpoint — defer to Hunter. */
    public function findEmail(string $domain, ?string $firstName, ?string $lastName, ?string $fullName, ProviderContext $ctx): array
    {
        return ['ok' => false, 'error' => 'Email finder is handled by Hunter, not Apollo.'];
    }

    public function ping(ProviderContext $ctx): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'configured' => false, 'message' => 'Not Configured'];
        }
        $call = $ctx->call(
            'apollo', 'auth_health', 'health',
            fn() => HttpClient::get($this->base() . '/auth/health', ['Cache-Control' => 'no-cache', 'X-Api-Key' => (string) $this->key()]),
            fn($d) => ['is_logged_in' => $d['is_logged_in'] ?? false],
            300,
            'ping'
        );
        if (!$call['ok'] || empty($call['data']['is_logged_in'])) {
            return ['ok' => false, 'configured' => true, 'message' => $call['error'] ?? 'Invalid API Key'];
        }
        return ['ok' => true, 'configured' => true, 'message' => 'Connected'];
    }

    private function parsePeople(array $d): array
    {
        $people = [];
        foreach (($d['people'] ?? []) as $p) {
            $email = $p['email'] ?? null;
            $people[] = array_filter([
                'name'        => $p['name'] ?? trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: null,
                'first_name'  => $p['first_name'] ?? null,
                'last_name'   => $p['last_name'] ?? null,
                'designation' => $p['title'] ?? null,
                'email'       => $email !== '' ? $email : null,
                'linkedin_url'=> $p['linkedin_url'] ?? null,
                'departments' => $p['departments'] ?? [],
                'seniority'   => $p['seniority'] ?? null,
            ], fn($v) => $v !== null && $v !== '' && $v !== []);
        }
        return ['people' => $people];
    }

    private function normalizeDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        $host = parse_url($domain, PHP_URL_HOST);
        $host = $host ?: (str_contains($domain, '.') ? $domain : null);
        if (!$host) return null;
        return preg_replace('/^www\./', '', $host);
    }
}
