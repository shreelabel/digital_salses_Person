<?php
declare(strict_types=1);

namespace SLC\Services\AI;

use SLC\Services\Providers\ProviderManager;
use SLC\Services\Providers\AIProviderRouter;

/**
 * Central access point. After the free-first refactor:
 *  - provider()  = AIProviderRouter (FreeLLMAPI → 9Router → Gemini-optional)
 *  - discovery   = multi-provider free-mode workflow (Hunter/Apollo + AI)
 * Grounding/search is NEVER used by the chain (no Google billing).
 */
final class AIServiceManager
{
    public static function manager(): ProviderManager
    {
        return new ProviderManager();
    }

    /** The AI fallback chain (implements AIProviderInterface). */
    public static function provider(): AIProviderInterface
    {
        return new AIProviderRouter();
    }

    public static function isConfigured(): bool
    {
        return (new AIProviderRouter())->isConfigured();
    }

    public static function leadDiscovery(): LeadDiscoveryService
    {
        return new LeadDiscoveryService();
    }

    public static function research(): CompanyResearchService
    {
        return new CompanyResearchService(self::provider());
    }

    public static function email(): EmailGenerationService
    {
        return new EmailGenerationService(self::provider());
    }
}
