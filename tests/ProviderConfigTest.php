<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Database;
use SLC\Services\Providers\ProviderConfigRepository;

class ProviderConfigTest extends TestCase
{
    protected function setUp(): void
    {
        // start each test with providers disabled + no keys
        Database::query("UPDATE slc_provider_config SET enabled = 0, api_key_enc = NULL");
    }

    public function testKeyIsEncryptedAtRestAndMaskedForBrowser(): void
    {
        $repo = new ProviderConfigRepository();
        $repo->setKey('hunter', 'hunter-secret-key-XYZ-12345');

        $row = Database::fetch("SELECT api_key_enc FROM slc_provider_config WHERE slug='hunter'");
        $this->assertNotNull($row);
        $this->assertStringNotContains('hunter-secret-key-XYZ-12345', $row['api_key_enc']);

        $this->assertEquals('hunter-secret-key-XYZ-12345', $repo->getKey('hunter'));

        $view = $repo->forBrowser()['hunter'];
        $this->assertTrue($view['has_key']);
        $this->assertStringNotContains('hunter-secret-key-XYZ-12345', json_encode($view));
        $this->assertStringNotContains('secret', $view['api_key_masked']);
    }

    public function testReadyRequiresBothEnabledAndKey(): void
    {
        $repo = new ProviderConfigRepository();
        $repo->setKey('freellmapi', 'fk-123');
        $this->assertFalse($repo->isReady('freellmapi')); // enabled=false
        $this->assertFalse($repo->get('freellmapi')->isReady());

        $repo->setEnabled('freellmapi', true);
        $this->assertTrue($repo->isReady('freellmapi'));
    }

    public function testIsAnyAiConfiguredReflectsEnabledProviders(): void
    {
        $repo = new ProviderConfigRepository();
        $this->assertFalse($repo->isAnyAiConfigured());
        $repo->setKey('9router', 'rk-1'); $repo->setEnabled('9router', true);
        $this->assertTrue($repo->isAnyAiConfigured());
    }

    public function testAllFiveProvidersSeeded(): void
    {
        $slugs = array_column((new ProviderConfigRepository())->all(), 'slug');
        foreach (['hunter', 'apollo', 'freellmapi', '9router', 'gemini'] as $s) {
            $this->assertContains($s, $slugs);
        }
    }

    public function testMaskingNeverRevealsRawKeyAfterUpdate(): void
    {
        $repo = new ProviderConfigRepository();
        $repo->setKey('apollo', 'apollo-super-secret-abc');
        $repo->setEnabled('apollo', true);
        $blob = json_encode($repo->forBrowser());
        $this->assertStringNotContains('apollo-super-secret-abc', $blob);
        $this->assertStringNotContains('super-secret', $blob);
    }
}
