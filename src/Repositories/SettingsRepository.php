<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;
use SLC\Core\Crypt;

/**
 * Persistent AI settings (key/value). Secret values are encrypted at rest.
 * Resolution order for the effective key: DB setting -> .env fallback.
 */
class SettingsRepository
{
    public function all(): array
    {
        return Database::fetchAll('SELECT * FROM slc_ai_settings ORDER BY id');
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $row = Database::fetch(
            'SELECT * FROM slc_ai_settings WHERE setting_key = :k LIMIT 1',
            ['k' => $key]
        );
        if (!$row || $row['setting_value'] === null || $row['setting_value'] === '') {
            return $default;
        }
        if ((int) $row['is_secret'] === 1) {
            return Crypt::decrypt($row['setting_value']);
        }
        return $row['setting_value'];
    }

    public function set(string $key, ?string $value, bool $secret = false): void
    {
        $stored = $value;
        if ($secret && $value !== null && $value !== '') {
            $stored = Crypt::encrypt($value);
        }
        $row = Database::fetch('SELECT id FROM slc_ai_settings WHERE setting_key = :k', ['k' => $key]);
        if ($row) {
            Database::update('slc_ai_settings', (int) $row['id'], [
                'setting_value' => $stored,
                'is_secret' => $secret ? 1 : 0,
            ]);
        } else {
            Database::insert('slc_ai_settings', [
                'setting_key' => $key,
                'setting_value' => $stored,
                'is_secret' => $secret ? 1 : 0,
            ]);
        }
    }

    /** Returns safe (masked) view of settings for the browser — never the raw key. */
    public function forBrowser(): array
    {
        $key = $this->get('gemini_api_key');
        $model = $this->get('gemini_model') ?: 'gemini-3.6-flash';
        return [
            'gemini_api_key_configured' => $key !== null && $key !== '',
            'gemini_api_key_masked'     => $key ? \SLC\Core\Security::maskSecret($key) : '',
            'gemini_model'              => $model,
            'configured'                => $key !== null && $key !== '',
        ];
    }
}
