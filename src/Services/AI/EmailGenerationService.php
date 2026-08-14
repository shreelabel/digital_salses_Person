<?php
declare(strict_types=1);

namespace SLC\Services\AI;

/**
 * Generates personalised B2B sales email DRAFTS. Output is draft-only and is
 * never sent — there is no email transport anywhere in the app.
 */
final class EmailGenerationService
{
    public function __construct(private AIProviderInterface $provider)
    {
    }

    /**
     * @return array{ok:bool,subject?:string,body?:string,error?:string,elapsed_ms?:int}
     */
    public function generate(array $company, ?array $contact, string $objective, ?int $userId = null): array
    {
        if (!$this->provider->isConfigured()) {
            return ['ok' => false, 'error' => 'Gemini is not configured. Add an API key in AI Settings.'];
        }

        $prompt = PromptBuilder::emailPrompt($company, $contact, $objective);
        // Email writing does not need live web search; keep it ungrounded + fast.
        $result = $this->provider->generate($prompt, false, ['timeout' => 90]);
        AiRequestLogger::log('email_generation', $result, $userId, '', 'email: ' . ($company['name'] ?? ''));

        if ($result->failed()) {
            return ['ok' => false, 'error' => $result->error ?? 'Email generation failed.', 'elapsed_ms' => $result->latencyMs];
        }

        $json = GeminiProvider::extractJson($result->text);
        $subject = is_array($json) ? ($json['subject'] ?? null) : null;
        $body = is_array($json) ? ($json['body'] ?? null) : null;

        if (!$subject || !$body) {
            // fallback: treat whole text as body
            $body = $result->text;
            $subject = 'Custom label solutions from Shree Label Creation';
        }

        return [
            'ok'       => true,
            'subject'  => trim($subject),
            'body'     => trim($body),
            'elapsed_ms' => $result->latencyMs,
        ];
    }
}
