<?php
declare(strict_types=1);

namespace SLC\Services\AI;

/**
 * Fresh Google-Search-grounded research for a single company.
 */
final class CompanyResearchService
{
    public function __construct(private AIProviderInterface $provider)
    {
    }

    /**
     * @return array{ok:bool,report?:array,error?:string,model?:string,queries?:array,elapsed_ms?:int}
     */
    public function research(array $company, ?int $userId = null): array
    {
        if (!$this->provider->isConfigured()) {
            return ['ok' => false, 'error' => 'Gemini is not configured. Add an API key in AI Settings.'];
        }
        if (empty($company['name'])) {
            return ['ok' => false, 'error' => 'A company name is required for research.'];
        }

        $prompt = PromptBuilder::researchPrompt($company);
        $result = $this->provider->generate($prompt, true, ['timeout' => 120]);
        AiRequestLogger::log('company_research', $result, $userId, '', 'research: ' . $company['name']);

        if ($result->failed()) {
            return ['ok' => false, 'error' => $result->error ?? 'Research failed.', 'elapsed_ms' => $result->latencyMs];
        }

        $json = GeminiProvider::extractJson($result->text) ?? [];
        $sources = array_values(array_unique(array_filter(array_map('strval', array_merge(
            $json['sources'] ?? [],
            array_column($result->citations, 'url')
        )))));

        $report = [
            'overview'            => $json['overview'] ?? null,
            'industry'            => $json['industry'] ?? ($company['industry'] ?? null),
            'products'            => $json['products'] ?? null,
            'locations'           => $json['locations'] ?? null,
            'relevance'           => $json['relevance'] ?? null,
            'label_requirements'  => $json['label_requirements'] ?? null,
            'suggested_department'=> $json['suggested_department'] ?? null,
            'outreach_angle'      => $json['outreach_angle'] ?? null,
            'why_relevant'        => $json['why_relevant'] ?? null,
            'decision_maker'      => $json['decision_maker'] ?? null,
            'confidence_score'    => isset($json['confidence_score']) ? max(0, min(100, (int) $json['confidence_score'])) : null,
            'sources'             => $sources,
            'full_report'         => $result->text,
            'model'               => $this->provider->getModel(),
        ];

        return [
            'ok'        => true,
            'model'     => $this->provider->getModel(),
            'queries'   => $result->queries,
            'report'    => $report,
            'elapsed_ms'=> $result->latencyMs,
        ];
    }
}
