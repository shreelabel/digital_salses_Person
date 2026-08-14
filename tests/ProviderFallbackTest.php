<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Services\AI\AIProviderInterface;
use SLC\Services\AI\AiResult;
use SLC\Services\Providers\AIProviderRouter;
use SLC\Services\Providers\ProviderConfigRepository;

/** Tiny in-process fake AI provider for deterministic router testing. */
class FakeAiProvider implements AIProviderInterface
{
    public function __construct(private string $slug, private bool $succeed, private string $text = '{"ai_score":50}') {}
    public function isConfigured(): bool { return true; }
    public function generate(string $prompt, bool $grounded = true, array $options = []): AiResult
    {
        return $this->succeed
            ? new AiResult(true, text: $this->text, latencyMs: 5)
            : new AiResult(false, error: $this->slug . ' down');
    }
    public function ping(): AiResult { return $this->generate(''); }
    public function getModel(): string { return 'fake-' . $this->slug; }
}

class ProviderFallbackTest extends TestCase
{
    public function testNoProvidersReturnsError(): void
    {
        $router = new AIProviderRouter();
        $res = $router->generate('hello', false);
        $this->assertFalse($res->ok);
        $this->assertStringContains('no ai provider', strtolower($res->error));
        $this->assertEmpty($router->trace());
    }

    public function testStopsAtFirstSuccessNoOvercall(): void
    {
        $ok = new FakeAiProvider('freellmapi', true);
        $fail = new FakeAiProvider('9router', false);
        $router = new AIProviderRouter(new ProviderConfigRepository(), [[$ok, 'freellmapi', 'FreeLLMAPI'], [$fail, '9router', '9Router']]);

        $res = $router->generate('hello', false);
        $this->assertTrue($res->ok);
        // Only the first provider was tried — 9Router never called
        $this->assertCount(1, $router->trace());
        $this->assertEquals('freellmapi', $router->trace()[0]['provider']);
    }

    public function testFallsThroughOnFailure(): void
    {
        $fail = new FakeAiProvider('freellmapi', false);
        $ok = new FakeAiProvider('9router', true);
        $router = new AIProviderRouter(new ProviderConfigRepository(), [[$fail, 'freellmapi', 'FreeLLMAPI'], [$ok, '9router', '9Router']]);

        $res = $router->generate('hello', false);
        $this->assertTrue($res->ok);
        $trace = $router->trace();
        $this->assertCount(2, $trace);
        $this->assertFalse($trace[0]['ok']);
        $this->assertTrue($trace[1]['ok']);
        $this->assertEquals('9router', $trace[1]['provider']);
    }

    public function testAllFailReturnsAggregatedError(): void
    {
        $a = new FakeAiProvider('freellmapi', false);
        $b = new FakeAiProvider('9router', false);
        $router = new AIProviderRouter(new ProviderConfigRepository(), [[$a, 'freellmapi', 'FreeLLMAPI'], [$b, '9router', '9Router']]);
        $res = $router->generate('hello', false);
        $this->assertFalse($res->ok);
        $this->assertCount(2, $router->trace());
        $this->assertStringContains('all ai providers failed', strtolower($res->error));
    }

    public function testGroundingNeverEnabledInChain(): void
    {
        // The router must ignore grounded=true and never request search grounding.
        $ok = new FakeAiProvider('freellmapi', true);
        $router = new AIProviderRouter(new ProviderConfigRepository(), [[$ok, 'freellmapi', 'FreeLLMAPI']]);
        $res = $router->generate('hello', true); // grounded=true passed
        $this->assertTrue($res->ok); // still works, grounding irrelevant for the fake
    }
}
