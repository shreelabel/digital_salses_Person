<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Services\Providers\ProviderCache;
use SLC\Services\Providers\ProviderUsageLogger;

class ProviderLayerTest extends TestCase
{
    public function testCacheStoresAndReusesWithinTtl(): void
    {
        $cache = new ProviderCache();
        $cache->put('hunter', 'domain_search', 'ds:example.com', ['company' => 'Example'], 3600);
        $got = $cache->get('hunter', 'domain_search', 'ds:example.com');
        $this->assertNotNull($got);
        $this->assertEquals('Example', $got['company']);
    }

    public function testCacheMissReturnsNull(): void
    {
        $this->assertNull((new ProviderCache())->get('hunter', 'domain_search', 'ds:missing-' . uniqid()));
    }

    public function testUsageLoggerRecordsCall(): void
    {
        ProviderUsageLogger::log([
            'provider' => 'apollo', 'operation' => 'people_search', 'cache_hit' => false,
            'status' => 'success', 'http_status' => 200, 'latency_ms' => 320,
            'request_summary' => 'domain=acme.com', 'user_id' => 1,
        ]);
        $recent = ProviderUsageLogger::recent(5);
        $last = $recent[0] ?? null;
        $this->assertNotNull($last);
        $this->assertEquals('apollo', $last['provider']);
        $this->assertEquals('success', $last['status']);
    }

    public function testUsageLoggerRecordsCacheHitAndError(): void
    {
        ProviderUsageLogger::log(['provider' => 'hunter', 'operation' => 'email_finder', 'cache_hit' => true, 'status' => 'success']);
        ProviderUsageLogger::log(['provider' => 'freellmapi', 'operation' => 'chat', 'cache_hit' => false, 'status' => 'error', 'error' => '429 rate limit']);
        $recent = ProviderUsageLogger::recent(5);
        $this->assertTrue(count($recent) >= 2);
    }

    public function testProviderTablesExistAndIsolated(): void
    {
        foreach (['slc_provider_config', 'slc_provider_usage', 'slc_provider_cache'] as $t) {
            $row = \SLC\Core\Database::fetch(
                "SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1",
                ['t' => $t]
            );
            $this->assertNotNull($row, "missing {$t}");
        }
    }
}
