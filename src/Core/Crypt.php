<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * AES-256-CBC symmetric encryption using APP_KEY (sha256 derived).
 * Used ONLY to encrypt secrets stored in the DB (e.g. a Gemini key saved
 * via AI Settings). Includes master fallback key so backup imports across
 * environments decrypt smoothly even if APP_KEY differs.
 */
final class Crypt
{
    private const CIPHER = 'aes-256-cbc';
    private const DEFAULT_KEY = 'slc_digital_sales_master_encryption_key_2026';

    public static function encrypt(string $plain): string
    {
        $appKey = Config::appKey() ?: self::DEFAULT_KEY;
        $key = hash('sha256', $appKey, true);
        $iv  = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $ct  = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($ct === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return base64_encode($iv . $ct);
    }

    public static function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return $payload;
        }

        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($raw) <= $ivLen) {
            return $payload;
        }

        $iv = substr($raw, 0, $ivLen);
        $ct = substr($raw, $ivLen);

        // 1. Try decrypting with configured APP_KEY
        $appKey = Config::appKey();
        if ($appKey !== '') {
            $key = hash('sha256', $appKey, true);
            $pt  = openssl_decrypt($ct, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
            if ($pt !== false && $pt !== '') {
                return $pt;
            }
        }

        // 2. Fallback: Try decrypting with static DEFAULT_KEY
        $defaultKey = hash('sha256', self::DEFAULT_KEY, true);
        $ptDefault  = openssl_decrypt($ct, self::CIPHER, $defaultKey, OPENSSL_RAW_DATA, $iv);
        if ($ptDefault !== false && $ptDefault !== '') {
            return $ptDefault;
        }

        // 3. If unencrypted raw string, return as-is
        return $payload;
    }
}
