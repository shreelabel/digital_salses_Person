<?php
declare(strict_types=1);

namespace SLC\Services\AI;

/**
 * Immutable result of one AI call.
 */
final class AiResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $text = '',
        public readonly array $citations = [],   // [['url'=>..,'title'=>..], ...]
        public readonly array $queries = [],     // search queries the model ran
        public readonly int $latencyMs = 0,
        public readonly int $httpStatus = 0,
        public readonly ?string $error = null,
        public readonly ?array $raw = null,
    ) {
    }

    public function failed(): bool
    {
        return !$this->ok;
    }
}
