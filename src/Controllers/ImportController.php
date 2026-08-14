<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Database;
use SLC\Core\Response;
use SLC\Services\Import\ApolloCsvImporter;

class ImportController extends Controller
{
    private ApolloCsvImporter $importer;

    public function __construct(?ApolloCsvImporter $importer = null)
    {
        $this->importer = $importer ?? new ApolloCsvImporter();
    }

    /**
     * POST /api/leads/import/preview
     * Uploads CSV file, performs dynamic validation, duplicate checking, and returns a 10-row preview.
     */
    public function preview(): void
    {
        \SLC\Core\Auth::requirePermission('ai_lead_finder.use');
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $msg = match ($errCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds max upload size.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No CSV file was uploaded.',
                default => 'Failed to upload CSV file.',
            };
            Response::error($msg, 422);
            return;
        }

        $file = $_FILES['file'];
        $tmpPath = $file['tmp_name'];
        $origName = $file['name'];

        $result = $this->importer->preview($tmpPath, $origName);
        if (!$result['ok']) {
            Response::error($result['error'] ?? 'CSV validation failed.', 422);
            return;
        }

        Response::success($result['preview']);
    }

    /**
     * POST /api/leads/import/confirm
     * Takes batch_token and commits the staged records into the CRM database.
     */
    public function confirm(): void
    {
        \SLC\Core\Auth::requirePermission('ai_lead_finder.use');
        $data = $this->input();
        $batchToken = trim((string)($data['batch_token'] ?? ''));

        if ($batchToken === '') {
            Response::error('Missing import batch token.', 422);
            return;
        }

        $options = [
            'skip_duplicates' => !empty($data['skip_duplicates']),
            'update_existing' => !empty($data['update_existing']),
        ];

        $result = $this->importer->executeImport($batchToken, $options, $this->userId());
        if (!$result['ok']) {
            Response::error($result['error'] ?? 'Import execution failed.', 422);
            return;
        }

        $res = $result['result'];
        $this->activity('apollo_csv_import', "Imported {$res['imported']} leads from Apollo CSV ({$res['duplicates']} duplicates, {$res['errors']} errors)");

        Response::success($res);
    }

    /**
     * GET /api/leads/imports
     * Returns history of previous CSV imports.
     */
    public function history(): void
    {
        \SLC\Core\Auth::requirePermission('ai_lead_finder.view');
        $rows = Database::fetchAll(
            'SELECT i.*, u.name as user_name FROM slc_imports i
             LEFT JOIN slc_users u ON u.id = i.created_by
             ORDER BY i.id DESC LIMIT 50'
        );

        $formatted = array_map(function ($row) {
            if (!empty($row['summary']) && is_string($row['summary'])) {
                $row['summary'] = json_decode($row['summary'], true);
            }
            if (!empty($row['error_log']) && is_string($row['error_log'])) {
                $row['error_log'] = json_decode($row['error_log'], true);
            }
            return $row;
        }, $rows);

        Response::success(['imports' => $formatted]);
    }

    /**
     * GET /api/leads/{id}/apollo-details
     * Returns the full original Apollo attributes preserved for a lead.
     */
    public function apolloDetails(string $id): void
    {
        $leadId = (int)$id;
        $lead = Database::fetch(
            'SELECT l.*, c.name as company_name, c.raw_data as company_raw,
                    ct.name as contact_name, ct.email as contact_email, ct.phone as contact_phone,
                    ct.designation as contact_designation, ct.linkedin_url as contact_linkedin,
                    ct.raw_data as contact_raw
             FROM slc_leads l
             LEFT JOIN slc_companies c ON c.id = l.company_id
             LEFT JOIN slc_contacts ct ON ct.id = l.contact_id
             WHERE l.id = :id AND l.deleted_at IS NULL',
            ['id' => $leadId]
        );

        if (!$lead) {
            Response::notFound('Lead not found.');
            return;
        }

        $leadRaw = !empty($lead['raw_data']) ? json_decode((string)$lead['raw_data'], true) : [];
        $contactRaw = !empty($lead['contact_raw']) ? json_decode((string)$lead['contact_raw'], true) : [];
        $companyRaw = !empty($lead['company_raw']) ? json_decode((string)$lead['company_raw'], true) : [];

        $originalApolloData = $leadRaw['original_apollo_data'] ?? ($contactRaw['original_apollo_data'] ?? $contactRaw);

        Response::success([
            'lead' => [
                'id' => $lead['id'],
                'title' => $lead['title'],
                'source' => $lead['source'],
                'source_file' => $leadRaw['source_file'] ?? null,
                'import_batch_id' => $lead['import_batch_id'],
                'company_name' => $lead['company_name'],
                'contact_name' => $lead['contact_name'],
                'contact_email' => $lead['contact_email'],
                'contact_phone' => $lead['contact_phone'],
                'contact_designation' => $lead['contact_designation'],
                'contact_linkedin' => $lead['contact_linkedin'],
                'industry' => $lead['industry'],
                'location' => $lead['location'],
                'status' => $lead['status'],
                'priority' => $lead['priority'],
                'ai_score' => $lead['ai_score'],
                'created_at' => $lead['created_at'],
            ],
            'original_apollo_data' => $originalApolloData,
            'total_apollo_fields' => is_array($originalApolloData) ? count($originalApolloData) : 0,
        ]);
    }
}
