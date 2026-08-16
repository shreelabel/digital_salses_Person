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

        $toStr = function ($v, string $glue = ', '): ?string {
            if ($v === null || $v === '') return null;
            if (is_array($v)) return implode($glue, array_map('strval', $v));
            return (string) $v;
        };

        $report = [
            'overview'                => $toStr($json['overview'] ?? null),
            'industry'                => $toStr($json['industry'] ?? ($company['industry'] ?? null)),
            'products'                => $toStr($json['products'] ?? null),
            'locations'               => $toStr($json['locations'] ?? null),
            'lead_category'           => $toStr($json['lead_category'] ?? null),
            'lead_category_reasoning' => $toStr($json['lead_category_reasoning'] ?? null),
            'key_insights'            => is_array($json['key_insights'] ?? null) ? $json['key_insights'] : ($json['key_insights'] ? [$json['key_insights']] : []),
            'relevance'               => $toStr($json['relevance'] ?? null),
            'label_requirements'      => $toStr($json['label_requirements'] ?? null, "\n• "),
            'suggested_department'    => $toStr($json['suggested_department'] ?? null),
            'recommended_service'     => $toStr($json['recommended_service'] ?? null),
            'pitch_strategy'          => $toStr($json['pitch_strategy'] ?? null),
            'outreach_angle'          => $toStr($json['outreach_angle'] ?? null),
            'why_relevant'            => $toStr($json['why_relevant'] ?? null),
            'decision_maker'          => $toStr($json['decision_maker'] ?? null),
            'email_outreach'          => $json['email_outreach'] ?? null,
            'whatsapp_message'        => $toStr($json['whatsapp_message'] ?? null),
            'cold_call_script'        => $json['cold_call_script'] ?? null,
            'confidence_score'        => isset($json['confidence_score']) ? max(0, min(100, (int) $json['confidence_score'])) : 88,
            'sources'                 => $sources,
            'full_report'             => !empty($json) ? json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $result->text,
            'model'                   => $this->provider->getModel(),
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
