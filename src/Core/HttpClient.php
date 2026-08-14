<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * Minimal cURL wrapper used ONLY by the server-side AI services to call the
 * Gemini API. No AI calls are ever made from the browser.
 */
final class HttpClient
{
    /**
     * @return array{status:int, body:string, latency_ms:int, error:?string}
     */
    public static function post(string $url, array $headers, mixed $body, int $timeout = 60): array
    {
        $ch = curl_init($url);
        $payload = is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $body;

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => self::headerLines($headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        return self::exec($ch);
    }

    public static function get(string $url, array $headers = [], int $timeout = 60): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => self::headerLines($headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        return self::exec($ch);
    }

    /**
     * Execute multiple HTTP requests concurrently (parallel cURL).
     *
     * @param array<string, array{method?: string, url: string, headers?: array, body?: mixed, timeout?: int}> $requests
     * @return array<string, array{status:int, body:string, latency_ms:int, error:?string}>
     */
    public static function multi(array $requests, int $defaultTimeout = 8): array
    {
        if (empty($requests)) {
            return [];
        }

        $mh = curl_multi_init();
        $handles = [];
        $starts = [];

        foreach ($requests as $key => $req) {
            $url = $req['url'] ?? '';
            if (!$url) continue;

            $method = strtoupper($req['method'] ?? 'GET');
            $headers = $req['headers'] ?? [];
            $timeout = (int) ($req['timeout'] ?? $defaultTimeout);
            $ch = curl_init($url);

            $opts = [
                CURLOPT_HTTPHEADER     => self::headerLines($headers),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ];

            if ($method === 'POST') {
                $opts[CURLOPT_POST] = true;
                $payload = is_array($req['body'] ?? null)
                    ? json_encode($req['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : (string) ($req['body'] ?? '');
                $opts[CURLOPT_POSTFIELDS] = $payload;
            } else {
                $opts[CURLOPT_HTTPGET] = true;
            }

            curl_setopt_array($ch, $opts);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
            $starts[$key] = microtime(true);
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc === CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc === CURLM_OK) {
            if (curl_multi_select($mh, 0.1) !== -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc === CURLM_CALL_MULTI_PERFORM);
            }
        }

        $results = [];
        foreach ($handles as $key => $ch) {
            $content = curl_multi_getcontent($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch) ?: null;
            $latency = (int) round((microtime(true) - ($starts[$key] ?? microtime(true))) * 1000);

            if ($content === false || ($content === '' && $status === 0)) {
                $results[$key] = ['status' => 0, 'body' => '', 'latency_ms' => $latency, 'error' => $error ?: 'Request failed'];
            } else {
                $results[$key] = ['status' => $status, 'body' => (string) $content, 'latency_ms' => $latency, 'error' => $error];
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        return $results;
    }

    private static function headerLines(array $headers): array
    {
        return array_map(fn($k, $v) => $k . ': ' . $v, array_keys($headers), $headers);
    }

    private static function exec(\CurlHandle $ch): array
    {
        $start = microtime(true);
        $resp  = curl_exec($ch);
        $latency = (int) round((microtime(true) - $start) * 1000);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch) ?: null;
        curl_close($ch);

        if ($resp === false) {
            return ['status' => 0, 'body' => '', 'latency_ms' => $latency, 'error' => $error];
        }
        return ['status' => (int) $status, 'body' => (string) $resp, 'latency_ms' => $latency, 'error' => $error];
    }
}
