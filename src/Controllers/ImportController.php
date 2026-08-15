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
     * DELETE /api/leads/imports/{id}
     * Deletes a specific import log entry.
     */
    public function destroyHistory(string $id): void
    {
        \SLC\Core\Auth::requirePermission('ai_lead_finder.use');
        $importId = (int)$id;
        Database::query('DELETE FROM slc_imports WHERE id = :id', ['id' => $importId]);
        Response::success(['deleted' => true, 'id' => $importId]);
    }

    /**
     * POST /api/leads/imports/clear
     * Clears all import history logs.
     */
    public function clearHistory(): void
    {
        \SLC\Core\Auth::requirePermission('ai_lead_finder.use');
        Database::query('DELETE FROM slc_imports');
        Response::success(['cleared' => true]);
    }

    /**
     * GET /api/leads/imports/{id}/export-csv
     * Streams/downloads the CSV for the specified import batch.
     */
    public function exportBatchCsv(string $id): void
    {
        \SLC\Core\Auth::requirePermission('ai_lead_finder.view');
        $importId = (int)$id;
        $import = Database::fetch('SELECT * FROM slc_imports WHERE id = :id', ['id' => $importId]);
        if (!$import) {
            Response::notFound('Import record not found.');
            return;
        }

        $batchId = $import['batch_id'];
        $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$import['file_name']);
        if (empty($fileName) || $fileName === '.csv') {
            $fileName = 'imported_leads_' . $batchId . '.csv';
        }
        if (!str_ends_with(strtolower($fileName), '.csv')) {
            $fileName .= '.csv';
        }

        // Fetch leads linked to this batch
        $leads = Database::fetchAll(
            'SELECT l.*, c.name as company_name, c.website as company_website, c.phone as company_phone,
                    c.email as company_email, c.city as company_city, c.state as company_state,
                    c.country as company_country, c.address as company_address, c.industry as company_industry,
                    ct.name as contact_name, ct.first_name, ct.last_name, ct.designation,
                    ct.email as contact_email, ct.phone as contact_phone, ct.linkedin_url
             FROM slc_leads l
             LEFT JOIN slc_companies c ON c.id = l.company_id
             LEFT JOIN slc_contacts ct ON ct.id = l.contact_id
             WHERE l.import_batch_id = :batch_id
             ORDER BY l.id ASC',
            ['batch_id' => $batchId]
        );

        // Send CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // Headers
        fputcsv($out, [
            'Lead ID',
            'First Name',
            'Last Name',
            'Contact Person',
            'Designation / Title',
            'Company Name',
            'Direct Email',
            'Company Email',
            'Direct Phone',
            'Company Phone',
            'Website',
            'City',
            'State / Province',
            'Country',
            'Address',
            'Industry',
            'Lead Status',
            'Priority',
            'AI Score',
            'LinkedIn URL',
            'Source',
            'Batch ID'
        ]);

        foreach ($leads as $l) {
            fputcsv($out, [
                $l['id'],
                $l['first_name'] ?: ($l['contact_name'] ? explode(' ', (string)$l['contact_name'])[0] : ''),
                $l['last_name'] ?: ($l['contact_name'] && count(explode(' ', (string)$l['contact_name'])) > 1 ? implode(' ', array_slice(explode(' ', (string)$l['contact_name']), 1)) : ''),
                $l['contact_name'] ?: '',
                $l['designation'] ?: '',
                $l['company_name'] ?: '',
                $l['contact_email'] ?: '',
                $l['company_email'] ?: '',
                $l['contact_phone'] ?: '',
                $l['company_phone'] ?: '',
                $l['company_website'] ?: '',
                $l['company_city'] ?: '',
                $l['company_state'] ?: '',
                $l['company_country'] ?: '',
                $l['company_address'] ?: '',
                $l['industry'] ?: ($l['company_industry'] ?: ''),
                $l['status'] ?: 'New',
                $l['priority'] ?: 'Medium',
                $l['ai_score'] ?: '',
                $l['linkedin_url'] ?: '',
                $l['source'] ?: 'Apollo CSV',
                $batchId
            ]);
        }

        fclose($out);
        exit;
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
