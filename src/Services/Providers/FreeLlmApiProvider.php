<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

/**
 * FreeLLMAPI — PRIMARY free AI provider (OpenAI-compatible).
 */
final class FreeLlmApiProvider extends OpenAiCompatibleProvider
{
    public function __construct(ProviderConfigRepository $config = new ProviderConfigRepository())
    {
        parent::__construct('freellmapi', $config);
    }
}
