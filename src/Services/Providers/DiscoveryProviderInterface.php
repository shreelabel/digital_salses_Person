<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

use SLC\Services\AI\AiResult;

/**
 * Providers that discover real companies/domains (Hunter). Data returned here
 * is the ONLY source of truth for company facts (anti-hallucination).
 */
interface DiscoveryProviderInterface
{
    public function slug(): string;

    public function isConfigured(): bool;

    /**
     * Discover companies for a target. Returns a list of raw candidate arrays
     * with fields provided by the provider (no fabricated values).
     * @return array{ok:bool,candidates?:array,error?:string,credits?:?float}
     */
    public function discover(array $input, ProviderContext $ctx): array;

    /** Connectivity probe (one request). */
    public function ping(ProviderContext $ctx): array;
}
