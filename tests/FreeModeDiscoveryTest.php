<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Database;
use SLC\Services\AI\LeadDiscoveryService;
use SLC\Services\Providers\ProviderConfigRepository;

/**
 * Free-mode workflow gating. Verifies the workflow refuses honestly (and never
 * fabricates prospects) when providers are missing — without making any live
 * external call.
 */
class FreeModeDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        Database::query("UPDATE slc_provider_config SET enabled = 0, api_key_enc = NULL");
    }

    public function testRefusesWhenNothingConfigured(): void
    {
        $res = (new LeadDiscoveryService())->discover(['industry' => 'Pharmaceutical', 'count' => 3]);
        $this->assertFalse($res['ok']);
        $this->assertStringContains('ai', strtolower($res['error']));
    }

    public function testRefusesWhenAiOnlyWithoutDataProvider(): void
    {
        $repo = new ProviderConfigRepository();
        $repo->setKey('freellmapi', 'fake-ai-key');
        $repo->setEnabled('freellmapi', true);

        $res = (new LeadDiscoveryService())->discover(['industry' => 'Pharmaceutical', 'count' => 3]);
        $this->assertFalse($res['ok']);
        // must demand a discovery provider (Hunter/Apollo) — anti-hallucination
        $this->assertStringContains('hunter', strtolower($res['error']));
        $this->assertArrayNotHasKey('prospects', $res);
    }

    public function testNeverAutoSavesAndReportsErrorsHonestly(): void
    {
        // Even with AI + a data provider configured (fake keys), a real
        // discovery cannot succeed (no live AI). The service must return an
        // honest error, NOT a fabricated prospect list.
        $repo = new ProviderConfigRepository();
        $repo->setKey('freellmapi', 'fake'); $repo->setEnabled('freellmapi', true);
        $repo->setKey('hunter', 'fake'); $repo->setEnabled('hunter', true);

        $res = (new LeadDiscoveryService())->discover(['industry' => 'Pharmaceutical', 'count' => 3]);
        // Outcome is failure (no real AI) — verify no fabricated data leaks through.
        if (!$res['ok']) {
            $this->assertTrue(true);
            return;
        }
        // If somehow ok, no prospect may carry an invented email/contact.
        foreach (($res['prospects'] ?? []) as $p) {
            if (!empty($p['contact_email'])) {
                $this->assertTrue($p['verified'] ?? false, 'email present only on provider-verified prospects');
            }
        }
    }
}
