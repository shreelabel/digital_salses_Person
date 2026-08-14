<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

use SLC\Core\Database;
use SLC\Core\Crypt;
use SLC\Core\Security;

/**
 * Persistent provider configuration. API keys are AES-256-CBC encrypted at
 * rest (via APP_KEY) and ONLY ever decrypted server-side for outbound calls.
 * No method here returns a raw key to the browser.
 */
class ProviderConfigRepository
{
    /** List of the known provider slugs + roles. */
    public const PROVIDERS = [
        'hunter'     => ['name' => 'Hunter',      'role' => 'discovery'],
        'apollo'     => ['name' => 'Apollo',      'role' => 'enrichment'],
        'freellmapi' => ['name' => 'FreeLLMAPI',  'role' => 'ai'],
        '9router'    => ['name' => '9Router',     'role' => 'ai'],
        'gemini'     => ['name' => 'Gemini',      'role' => 'ai'],
    ];

    public function exists(string $slug): bool
    {
        return Database::fetch(
            'SELECT id FROM slc_provider_config WHERE slug = :s',
            ['s' => $slug]
        ) !== null;
    }

    public function get(string $slug): ?ProviderConfig
    {
        $row = Database::fetch(
            'SELECT * FROM slc_provider_config WHERE slug = :s LIMIT 1',
            ['s' => $slug]
        );
        if (!$row) {
            return null;
        }
        return $this->hydrate($row);
    }

    /** @return ProviderConfig[] */
    public function all(): array
    {
        $rows = Database::fetchAll('SELECT * FROM slc_provider_config ORDER BY priority, id');
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }
        return $out;
    }

    /** Decrypted API key — SERVER-SIDE ONLY. Never expose to the browser. */
    public function getKey(string $slug): ?string
    {
        $row = Database::fetch('SELECT api_key_enc FROM slc_provider_config WHERE slug = :s', ['s' => $slug]);
        if (!$row || empty($row['api_key_enc'])) {
            return null;
        }
        return Crypt::decrypt($row['api_key_enc']);
    }

    public function setKey(string $slug, ?string $key): void
    {
        if ($key === null || $key === '') {
            $enc = null;
        } else {
            $enc = Crypt::encrypt($key);
        }
        $this->upsertField($slug, 'api_key_enc', $enc);
    }

    public function setEnabled(string $slug, bool $enabled): void
    {
        $this->upsertField($slug, 'enabled', $enabled ? 1 : 0);
    }

    public function setField(string $slug, string $field, ?string $value): void
    {
        $allowed = ['base_url', 'model', 'priority', 'last_status', 'last_tested_at'];
        if (!in_array($field, $allowed, true)) {
            throw new \InvalidArgumentException("Field {$field} is not editable.");
        }
        $this->upsertField($slug, $field, $value);
    }

    public function markTested(string $slug, string $status): void
    {
        Database::query(
            'UPDATE slc_provider_config SET last_status = :s, last_tested_at = NOW() WHERE slug = :slug',
            ['s' => $status, 'slug' => $slug]
        );
    }

    public function isReady(string $slug): bool
    {
        $c = $this->get($slug);
        return $c !== null && $c->isReady();
    }

    /** Any usable discovery/enrichment provider configured & enabled? */
    public function isAnyDataConfigured(): bool
    {
        return $this->isReady('hunter') || $this->isReady('apollo');
    }

    /** Any usable AI provider configured & enabled? */
    public function isAnyAiConfigured(): bool
    {
        foreach (['freellmapi', '9router', 'gemini'] as $s) {
            if ($this->isReady($s)) {
                return true;
            }
        }
        return false;
    }

    /** Browser-safe list of all providers (masked keys only). */
    public function forBrowser(): array
    {
        $out = [];
        foreach ($this->all() as $cfg) {
            $masked = '';
            if ($cfg->hasKey) {
                $key = $this->getKey($cfg->slug);
                $masked = $key ? Security::maskSecret($key) : '••••';
            }
            $out[$cfg->slug] = $cfg->toBrowserArray($masked);
        }
        return $out;
    }

    private function hydrate(array $row): ProviderConfig
    {
        return new ProviderConfig(
            slug: $row['slug'],
            name: $row['name'],
            role: $row['role'],
            enabled: (int) $row['enabled'] === 1,
            hasKey: !empty($row['api_key_enc']),
            baseUrl: $row['base_url'],
            model: $row['model'],
            priority: (int) $row['priority'],
            lastStatus: $row['last_status'] ?? 'Not Connected',
            lastTestedAt: $row['last_tested_at'] ?? null,
        );
    }

    private function upsertField(string $slug, string $field, mixed $value): void
    {
        $exists = $this->exists($slug);
        if ($exists) {
            Database::query(
                "UPDATE slc_provider_config SET {$field} = :v WHERE slug = :s",
                ['v' => $value, 's' => $slug]
            );
        } else {
            $info = self::PROVIDERS[$slug] ?? ['name' => ucfirst($slug), 'role' => 'ai'];
            Database::insert('slc_provider_config', [
                'slug' => $slug, 'name' => $info['name'], 'role' => $info['role'],
                'enabled' => 0, $field => $value, 'priority' => 99,
            ]);
        }
    }
}
