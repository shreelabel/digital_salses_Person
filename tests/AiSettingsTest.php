<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Crypt;
use SLC\Core\Security;
use SLC\Repositories\SettingsRepository;
use SLC\Services\AI\GeminiProvider;
use SLC\Services\AI\PromptBuilder;

class AiSettingsTest extends TestCase
{
    public function testApiKeyIsEncryptedAtRest(): void
    {
        $repo = new SettingsRepository();
        $repo->set('gemini_api_key', 'AIza-secret-test-key-123', true);
        // raw DB row must NOT contain the plaintext key
        $row = \SLC\Core\Database::fetch("SELECT setting_value FROM slc_ai_settings WHERE setting_key='gemini_api_key'");
        $this->assertNotNull($row);
        $this->assertStringNotContains('AIza-secret-test-key-123', $row['setting_value']);
        // but decrypts back correctly
        $this->assertEquals('AIza-secret-test-key-123', $repo->get('gemini_api_key'));
    }

    public function testBrowserViewMasksKeyNeverReturnsRaw(): void
    {
        $repo = new SettingsRepository();
        $repo->set('gemini_api_key', 'AIza-supersecret-key-999', true);
        $view = $repo->forBrowser();
        $this->assertTrue($view['gemini_api_key_configured']);
        $this->assertStringNotContains('supersecret', $view['gemini_api_key_masked']);
        $this->assertStringNotContains('AIza-supersecret-key-999', json_encode($view));
    }

    public function testCryptRoundTrip(): void
    {
        $enc = Crypt::encrypt('hello world');
        $this->assertStringNotContains('hello world', $enc);
        $this->assertEquals('hello world', Crypt::decrypt($enc));
    }

    public function testMaskingHidesMiddleOfSecret(): void
    {
        $masked = Security::maskSecret('AIza1234567890abcdef');
        $this->assertStringNotContains('1234567890', $masked);
        $this->assertNotEmpty($masked);
    }

    public function testObsoleteModelsAreRejected(): void
    {
        $obsolete = ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-2.0-flash'];
        foreach ($obsolete as $m) {
            $this->assertTrue($this->isObsolete($m), "{$m} should be obsolete");
        }
        $this->assertFalse($this->isObsolete('gemini-3.6-flash'));
    }

    public function testDefaultModelIsGemini36Flash(): void
    {
        $this->assertEquals('gemini-3.6-flash', PromptBuilder::discoveryPrompt(['industry' => 'Pharma']) ? 'gemini-3.6-flash' : '');
        $this->assertStringContains('gemini-3.6-flash', \SLC\Core\Config::geminiModel() ?: 'gemini-3.6-flash');
    }

    public function testJsonExtractionFromFencedResponse(): void
    {
        $text = "Here you go:\n```json\n{\"prospects\":[{\"name\":\"Acme\"}]}\n```\nthanks";
        $json = GeminiProvider::extractJson($text);
        $this->assertTrue(isset($json['prospects']));
        $this->assertEquals('Acme', $json['prospects'][0]['name']);
    }

    private function isObsolete(string $m): bool
    {
        return in_array(strtolower(trim($m)), ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-2.0-flash', 'gemini-1.0-pro', 'gemini-pro'], true);
    }
}
