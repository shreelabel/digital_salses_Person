<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Auth;
use SLC\Core\Config;
use SLC\Core\Database;
use SLC\Core\Response;
use SLC\Core\Validator;
use SLC\Repositories\CompanyRepository;
use SLC\Repositories\SettingsRepository;
use SLC\Services\AI\AIServiceManager;
use SLC\Services\AI\AiRequestLogger;
use SLC\Services\AI\GeminiProvider;

class AiController extends Controller
{
    public function __construct(private SettingsRepository $settings = new SettingsRepository())
    {
    }

    /** GET /api/ai/settings — never returns the raw key. */
    public function getSettings(): void
    {
        Auth::requirePermission('ai_settings.view');
        $view = $this->settings->forBrowser();
        $view['api_base'] = 'Multi-provider (Hunter / Apollo / FreeLLMAPI / 9Router / Gemini)';
        $status = (new \SLC\Services\Providers\ProviderManager())->status();
        $view['free_mode'] = true;
        $view['providers'] = $status['providers'];
        $view['ai_available'] = $status['ai_available'];
        $view['discovery_available'] = $status['discovery_available'];
        $view['primary_ai'] = $status['primary_ai'];
        $view['primary_discovery'] = $status['primary_discovery'];
        Response::success(['settings' => $view]);
    }

    /** GET /api/ai/providers — browser-safe provider statuses (no raw keys). */
    public function providers(): void
    {
        Auth::requirePermission('ai_settings.view');
        Response::success((new \SLC\Services\Providers\ProviderManager())->status());
    }

    /** PUT /api/ai/providers/{slug} — enable/disable, base_url, model, priority; key only if typed. */
    public function updateProvider(string $slug): void
    {
        Auth::requirePermission('ai_settings.manage');
        $repo = new \SLC\Services\Providers\ProviderConfigRepository();
        if (!$repo->exists($slug)) {
            Response::notFound('Unknown provider.');
            return;
        }
        if (!in_array($slug, ['hunter', 'apollo', 'freellmapi', '9router', 'gemini'], true)) {
            Response::error('Unknown provider.', 422);
            return;
        }
        $data = $this->input();
        if (array_key_exists('enabled', $data)) {
            $repo->setEnabled($slug, filter_var($data['enabled'], FILTER_VALIDATE_BOOL));
        }
        if (array_key_exists('base_url', $data) && is_string($data['base_url']) && trim($data['base_url']) !== '') {
            $repo->setField($slug, 'base_url', trim($data['base_url']));
        }
        if (array_key_exists('model', $data) && is_string($data['model'])) {
            $repo->setField($slug, 'model', trim($data['model']) ?: null);
        }
        if (array_key_exists('priority', $data)) {
            $repo->setField($slug, 'priority', (string) (int) $data['priority']);
        }
        if (array_key_exists('api_key', $data)) {
            $key = trim((string) $data['api_key']);
            if ($key !== '' && stripos($key, '*') === false) {
                $repo->setKey($slug, $key);
            }
        }
        $this->activity('provider_settings', 'Updated provider: ' . $slug);
        Response::success(['provider' => $repo->get($slug)?->toBrowserArray()]);
    }

    /** POST /api/ai/providers/{slug}/test — ONE connection test, never retries. */
    public function testProvider(string $slug): void
    {
        Auth::requirePermission('ai_settings.manage');
        $result = (new \SLC\Services\Providers\ProviderManager())->testConnection($slug);
        $this->activity('provider_test', 'Tested provider ' . $slug . ': ' . $result['status']);
        Response::success($result);
    }

    /** GET /api/ai/providers/usage — recent provider call audit (cost/credit log). */
    public function providerUsage(): void
    {
        Auth::requirePermission('ai_settings.view');
        Response::success(['usage' => \SLC\Services\Providers\ProviderUsageLogger::recent(100)]);
    }

    /** PUT /api/ai/settings — stores model always; stores key only if provided. */
    public function updateSettings(): void
    {
        Auth::requirePermission('ai_settings.manage');
        $data = $this->input();
        $v = new Validator($data);
        $v->maxLength('gemini_model', 80);
        if (isset($data['gemini_model']) && $data['gemini_model'] !== '' && $this->isObsoleteModel($data['gemini_model'])) {
            Response::error('That Gemini model is obsolete and not supported.', 422);
            return;
        }
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }

        if (isset($data['gemini_model']) && $data['gemini_model'] !== '') {
            $this->settings->set('gemini_model', $data['gemini_model'], false);
        }
        // Only update the key when the user actually typed a new one.
        if (array_key_exists('gemini_api_key', $data)) {
            $key = trim((string) $data['gemini_api_key']);
            if ($key !== '' && stripos($key, '*') === false) {
                $this->settings->set('gemini_api_key', $key, true);
            }
        }
        $this->activity('ai_settings', 'Updated AI settings');
        Response::success(['settings' => $this->settings->forBrowser()]);
    }

    /** POST /api/ai/test-connection — only called on explicit user click. */
    public function testConnection(): void
    {
        Auth::requirePermission('ai_settings.view');
        $provider = AIServiceManager::provider();
        if (!$provider->isConfigured()) {
            Response::success(['configured' => false, 'connected' => false, 'message' => 'No API key configured.']);
            return;
        }
        $result = $provider->ping();
        AiRequestLogger::log('test_connection', $result, $this->userId());
        Response::success([
            'configured' => true,
            'connected'  => $result->ok,
            'model'      => $provider->getModel(),
            'latency_ms' => $result->latencyMs,
            'message'    => $result->ok ? 'Connected' : ($result->error ?? 'Not Connected'),
            'status'     => $result->ok ? 'Connected' : 'Error',
        ]);
    }

    /** POST /api/ai/status — quick configured? check (no Gemini call). */
    public function status(): void
    {
        Auth::requirePermission('ai_settings.view');
        Response::success([
            'configured' => AIServiceManager::isConfigured(),
            'model'      => AIServiceManager::provider()->getModel(),
            'api'        => 'Gemini Interactions API',
        ]);
    }

    /** POST /api/ai/leads/discover */
    public function discoverLeads(): void
    {
        Auth::requirePermission('ai_lead_finder.use');
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');
        try {
            $data = $this->input();
            $v = new Validator($data);
            $v->integer('count', 1, 25);
            if ($v->fails()) {
                Response::validationError($v->errors());
                return;
            }
            if (empty($data['industry']) && empty($data['keywords']) && empty($data['location']) && empty($data['city'])) {
                Response::error('Provide at least an industry, location, city, or keywords.', 422);
                return;
            }
            $data['count'] ??= 10;
            $result = AIServiceManager::leadDiscovery()->discover($data, $this->userId());
            if (!$result['ok']) {
                $code = str_contains((string) ($result['error'] ?? ''), 'not configured') ? 503 : 502;
                Response::error($result['error'], $code);
                return;
            }
            Response::success($result);
        } catch (\Throwable $e) {
            Response::error('Lead Discovery error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/ai/leads/save-discovered
     * Saves selected prospects: company + contact (only if verified) + lead.
     * Deduplicates so no duplicate companies are created.
     */
    public function saveDiscovered(): void
    {
        Auth::requirePermission('ai_lead_finder.use');
        $data = $this->input();
        $prospects = $data['prospects'] ?? ($data['selected'] ?? []);
        if (!is_array($prospects) || empty($prospects)) {
            Response::error('No prospects selected.', 422);
            return;
        }

        $assignedTo = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : (Auth::id() ?? 1);
        $repo = new CompanyRepository();
        $saved = $created = $skipped = 0;
        $details = [];

        Database::transaction(function () use ($prospects, $repo, $assignedTo, &$saved, &$created, &$skipped, &$details) {
            foreach ($prospects as $p) {
                if (!is_array($p) || empty($p['name'])) {
                    continue;
                }
                $domain = !empty($p['website']) ? self::domain($p['website']) : null;
                $existing = $repo->findExisting($p['name'], $domain, $p['phone'] ?? null, $p['email'] ?? null);

                if ($existing) {
                    $companyId = (int) $existing['id'];
                    $skipped++;
                    $details[] = ['name' => $p['name'], 'action' => 'already_in_crm', 'company_id' => $companyId];
                    continue;
                }

                $fullDesc = trim(
                    (!empty($p['address']) ? ("Factory Address: " . $p['address'] . "\n") : '') .
                    (!empty($p['why_relevant']) ? ("Packaging Requirement: " . $p['why_relevant'] . "\n") : '') .
                    (!empty($p['potential_label_types']) ? ("Label Types: " . (is_array($p['potential_label_types']) ? implode(', ', $p['potential_label_types']) : $p['potential_label_types'])) : '')
                );

                $companyId = $repo->create([
                    'assigned_to'   => $assignedTo,
                    'name'          => $p['name'],
                    'industry'      => $p['industry'] ?? null,
                    'sub_industry'  => $p['sub_industry'] ?? null,
                    'city'          => $p['city'] ?? null,
                    'state'         => $p['state'] ?? null,
                    'country'       => $p['country'] ?? null,
                    'website'       => $p['website'] ?? null,
                    'phone'         => $p['phone'] ?? null,
                    'email'         => $p['email'] ?? $p['contact_email'] ?? null,
                    'employee_count'=> $p['employee_count'] ?? null,
                    'description'   => $fullDesc ?: ($p['description'] ?? null),
                    'ai_score'      => isset($p['ai_score']) ? (int) $p['ai_score'] : null,
                    'ai_priority'   => $p['priority'] ?? null,
                    'source'        => 'Google Maps & AI Discovery',
                ]);
                $created++;

                // Contact ONLY when there is verified contact information.
                $contactId = null;
                if (!empty($p['contact_name']) || !empty($p['contact_email']) || !empty($p['phone'])) {
                    $contactId = Database::insert('slc_contacts', [
                        'assigned_to' => $assignedTo,
                        'company_id'  => $companyId,
                        'name'        => $p['contact_name'] ?? 'Purchase / Factory Manager',
                        'designation' => $p['contact_designation'] ?? 'Procurement / Packaging Head',
                        'email'       => $p['contact_email'] ?? $p['email'] ?? null,
                        'phone'       => $p['phone'] ?? null,
                        'source'      => 'Google Maps & AI Discovery',
                        'importance'  => 'Medium',
                    ]);
                }

                // Lead
                $leadId = Database::insert('slc_leads', [
                    'assigned_to' => $assignedTo,
                    'company_id'  => $companyId,
                    'contact_id'  => $contactId,
                    'title'       => 'Prospect: ' . $p['name'],
                    'industry'    => $p['industry'] ?? null,
                    'location'    => trim(($p['city'] ?? '') . ', ' . ($p['state'] ?? ''), ', '),
                    'status'      => 'New',
                    'priority'    => $p['priority'] ?? 'High',
                    'ai_score'    => isset($p['ai_score']) ? (int) $p['ai_score'] : 85,
                    'notes'       => $fullDesc ?: ($p['why_relevant'] ?? null),
                    'source'      => 'Google Maps & AI Discovery',
                ]);

                // activity log
                Database::insert('slc_activities', [
                    'user_id'     => $this->userId(),
                    'company_id'  => $companyId,
                    'lead_id'     => $leadId,
                    'type'        => 'ai_discovery',
                    'description' => 'Saved AI-discovered prospect: ' . $p['name'],
                    'meta'        => json_encode(['sources' => $p['sources'] ?? [], 'priority' => $p['priority'] ?? null]),
                ]);

                $saved++;
                $details[] = ['name' => $p['name'], 'action' => 'created', 'company_id' => $companyId, 'lead_id' => $leadId];
            }
        });

        $this->activity('ai_save', "Saved {$saved} AI prospects ({$created} new, {$skipped} already in CRM)");
        Response::success([
            'saved'   => $saved,
            'created' => $created,
            'skipped' => $skipped,
            'details' => $details,
        ]);
    }

    /** POST /api/ai/research — generates and persists a research report. */
    public function research(): void
    {
        try {
            $data = $this->input();
            $companyId = (int) ($data['company_id'] ?? 0);
            $company = $companyId ? Database::fetch('SELECT * FROM slc_companies WHERE id = :id AND deleted_at IS NULL', ['id' => $companyId]) : null;
            if (!$company) {
                Response::error('Company not found.', 404);
                return;
            }
            $result = AIServiceManager::research()->research($company, $this->userId());
            if (!$result['ok']) {
                Response::error($result['error'], str_contains((string) $result['error'], 'not configured') ? 503 : 502);
                return;
            }
            $report = $result['report'];
            $report['company_id'] = $companyId;
            $id = (new \SLC\Repositories\ResearchRepository())->create($report);
            $this->activity('ai_research', 'Generated research report for ' . $company['name'], $companyId);
            Response::success(['report_id' => $id, 'report' => $report, 'queries' => $result['queries'] ?? [], 'elapsed_ms' => $result['elapsed_ms'] ?? 0]);
        } catch (\Throwable $e) {
            Response::error('Research error: ' . $e->getMessage(), 500);
        }
    }

    /** POST /api/ai/generate-email — draft only (never sent). */
    public function generateEmail(): void
    {
        try {
            $data = $this->input();
            $companyId = (int) ($data['company_id'] ?? 0);
            $company = $companyId ? Database::fetch('SELECT * FROM slc_companies WHERE id = :id AND deleted_at IS NULL', ['id' => $companyId]) : null;
            if (!$company) {
                Response::error('Company not found.', 404);
                return;
            }
            $contact = null;
            if (!empty($data['contact_id'])) {
                $contact = Database::fetch('SELECT * FROM slc_contacts WHERE id = :id', ['id' => (int) $data['contact_id']]);
            }
            $objective = (string) ($data['objective'] ?? '');
            $result = AIServiceManager::email()->generate($company, $contact, $objective, $this->userId());
            if (!$result['ok']) {
                Response::error($result['error'], str_contains((string) $result['error'], 'not configured') ? 503 : 502);
                return;
            }

            // persist as DRAFT (never sent)
            $msgId = Database::insert('slc_email_messages', [
                'company_id'   => $companyId,
                'contact_id'   => $contact['id'] ?? null,
                'lead_id'      => $data['lead_id'] ?? null,
                'subject'      => $result['subject'],
                'body'         => $result['body'],
                'status'       => 'draft',
                'ai_generated' => 1,
            ]);
            $this->activity('ai_email', 'Generated email draft for ' . $company['name'], $companyId);
            Response::success(['message_id' => $msgId, 'subject' => $result['subject'], 'body' => $result['body']]);
        } catch (\Throwable $e) {
            Response::error('Email generation error: ' . $e->getMessage(), 500);
        }
    }

    public function requests(): void
    {
        Auth::requirePermission('ai_settings.view');
        $rows = Database::fetchAll('SELECT * FROM slc_ai_requests ORDER BY id DESC LIMIT 100');
        Response::success(['requests' => $rows]);
    }

    public static function domain(?string $url): ?string
    {
        if (!$url) return null;
        $h = parse_url($url, PHP_URL_HOST) ?: parse_url('https://' . $url, PHP_URL_HOST);
        if (!$h) return null;
        return preg_replace('/^www\./', '', strtolower($h));
    }

    /** Reject obsolete Gemini model names. */
    private function isObsoleteModel(string $model): bool
    {
        $obsolete = ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-2.0-flash', 'gemini-1.0-pro', 'gemini-pro'];
        return in_array(strtolower(trim($model)), $obsolete, true);
    }
}
