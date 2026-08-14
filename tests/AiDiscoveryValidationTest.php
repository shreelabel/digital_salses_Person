<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Services\AI\LeadDiscoveryService;
use SLC\Services\AI\GeminiProvider;
use SLC\Services\AI\PromptBuilder;

/**
 * Validates the discovery request + prompt WITHOUT making a live Gemini call
 * (no key is configured in the test environment).
 */
class AiDiscoveryValidationTest extends TestCase
{
    public function testDiscoveryRefusesWithoutProviders(): void
    {
        // Explicitly clear providers for this test
        \SLC\Core\Database::query("UPDATE slc_provider_config SET enabled = 0, api_key_enc = NULL");

        // No providers are configured in the test DB → discovery must refuse
        // and never fabricate prospects.
        $service = new LeadDiscoveryService();
        $res = $service->discover(['industry' => 'Pharmaceutical', 'count' => 5]);
        $this->assertFalse($res['ok']);
        $this->assertTrue(
            str_contains(strtolower($res['error']), 'configur') || str_contains(strtolower($res['error']), 'provider'),
            'Error message should indicate missing configuration or providers'
        );
    }

    public function testCandidatePromptForbidsInventingContacts(): void
    {
        $prompt = PromptBuilder::candidatePrompt(['industry' => 'Tea', 'count' => 3]);
        $this->assertStringContains('do not invent', strtolower($prompt));
        $this->assertStringContains('candidate', strtolower($prompt));
    }

    public function testQualificationPromptOnlyAssessesFacts(): void
    {
        $prompt = PromptBuilder::qualificationPrompt(['name' => 'Acme', 'industry' => 'Pharma']);
        $this->assertStringContains('only on the facts provided', strtolower($prompt));
        $this->assertStringContains('ai_score', $prompt);
        $this->assertStringContains('confidence', $prompt);
    }

    public function testPromptContainsAntiHallucinationRules(): void
    {
        $prompt = PromptBuilder::discoveryPrompt(['industry' => 'Pharmaceutical', 'city' => 'Kolkata', 'count' => 5]);
        $this->assertStringContains('google_search', $prompt);
        $this->assertStringContains('NEVER invent', $prompt);
        $this->assertStringContains('verifiable source', $prompt);
        $this->assertStringContains('Shree Label Creation', $prompt);
        $this->assertStringContains('narrow-web', strtolower($prompt));
        $this->assertStringContains('json', strtolower($prompt));
    }

    public function testPromptForbidsFakeContacts(): void
    {
        $prompt = PromptBuilder::discoveryPrompt(['industry' => 'Tea']);
        $this->assertStringContains('null', $prompt);
        $this->assertStringContains('publicly verified', $prompt);
    }

    public function testResearchPromptRequiresRealDataAndCitations(): void
    {
        $prompt = PromptBuilder::researchPrompt(['name' => 'Acme']);
        $this->assertStringContains('google_search', $prompt);
        $this->assertStringContains('sources', $prompt);
        $this->assertStringContains('confidence_score', $prompt);
    }

    public function testEmailPromptIsDraftAware(): void
    {
        $prompt = PromptBuilder::emailPrompt(['name' => 'Acme', 'industry' => 'Pharma'], ['name' => 'Mr X'], 'intro');
        $this->assertStringContains('draft', strtolower($prompt));
        $this->assertStringContains('8-color', strtolower($prompt));
    }

    public function testDiscoveryCountClamped(): void
    {
        $prompt = PromptBuilder::discoveryPrompt(['industry' => 'X', 'count' => 999]);
        // count is clamped to 25 max in the builder
        $this->assertStringContains('25', $prompt);
    }
}
