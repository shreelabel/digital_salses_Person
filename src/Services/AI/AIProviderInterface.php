<?php
declare(strict_types=1);

namespace SLC\Services\AI;

/**
 * Contract for any AI provider. Currently implemented by GeminiProvider.
 * Switching providers only requires implementing this interface.
 */
interface AIProviderInterface
{
    public function isConfigured(): bool;

    /**
     * Run a generation. When $grounded is true, Google Search grounding is
     * enabled so the model can query the live web (current google_search tool).
     */
    public function generate(string $prompt, bool $grounded = true, array $options = []): AiResult;

    /** Lightweight connectivity probe used by "Test Connection". */
    public function ping(): AiResult;

    public function getModel(): string;
}
