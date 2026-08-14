<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

/**
 * Immutable provider configuration. API keys are NEVER stored here in
 * plaintext for the browser — use forBrowser()/masked access only.
 */
final class ProviderConfig
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $role,        // discovery | enrichment | ai
        public readonly bool $enabled,
        public readonly bool $hasKey,
        public readonly ?string $baseUrl,
        public readonly ?string $model,
        public readonly int $priority,
        public readonly string $lastStatus = 'Not Connected',
        public readonly ?string $lastTestedAt = null,
    ) {
    }

    /** "Ready" = enabled AND (key is stored OR is a free provider). */
    public function isReady(): bool
    {
        if ($this->slug === 'freellmapi' || $this->slug === '9router') {
            return $this->enabled;
        }
        return $this->enabled && $this->hasKey;
    }

    /** Safe representation for the browser — never includes a raw key. */
    public function toBrowserArray(?string $maskedKey = null): array
    {
        return [
            'slug'          => $this->slug,
            'name'          => $this->name,
            'role'          => $this->role,
            'enabled'       => $this->enabled,
            'has_key'       => $this->hasKey,
            'api_key_masked'=> $maskedKey ?? '',
            'base_url'      => $this->baseUrl,
            'model'         => $this->model,
            'priority'      => $this->priority,
            'last_status'   => $this->lastStatus,
            'last_tested_at'=> $this->lastTestedAt,
            'ready'         => $this->isReady(),
        ];
    }
}
