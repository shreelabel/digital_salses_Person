<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

/**
 * 9Router — AI FALLBACK provider (OpenAI-compatible).
 */
final class NineRouterProvider extends OpenAiCompatibleProvider
{
    public function __construct(ProviderConfigRepository $config = new ProviderConfigRepository())
    {
        parent::__construct('9router', $config);
    }
}
