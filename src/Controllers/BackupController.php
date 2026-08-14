<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Auth;
use SLC\Core\Database;
use SLC\Core\Response;

class BackupController extends Controller
{
    private const TABLES = [
        'slc_users',
        'slc_companies',
        'slc_contacts',
        'slc_leads',
        'slc_opportunities',
        'slc_campaigns',
        'slc_campaign_leads',
        'slc_followups',
        'slc_research_reports',
        'slc_email_templates',
        'slc_email_messages',
        'slc_ai_settings',
        'slc_provider_config',
        'slc_provider_usage',
        'slc_provider_cache',
        'slc_integrations',
        'slc_activities',
        'slc_imports',
        'slc_ai_requests',
    ];

    private const TABLE_MAP = [
        'slc_ai_usage' => 'slc_provider_usage',
        'slc_activity_logs' => 'slc_activities',
    ];

    public function export(): void
    {
        if (!Auth::isAdmin()) {
            Response::error('Access denied. Administrator role required for backup export.', 403);
            return;
        }

        $backup = [
            'app' => 'Shree Label Digital Sales Person',
            'version' => '2.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'exported_by' => Auth::current()['email'] ?? 'admin',
            'tables' => [],
        ];

        foreach (self::TABLES as $table) {
            try {
                $rows = Database::fetchAll("SELECT * FROM {$table}");
                $backup['tables'][$table] = $rows;
            } catch (\Throwable $e) {
                $backup['tables'][$table] = [];
            }
        }

        $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $filename = 'shree_label_backup_' . date('Y-m-d_H-i-s') . '.json';

        if (ob_get_length()) {
            ob_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $json;
        exit;
    }

    public function import(): void
    {
        if (!Auth::isAdmin()) {
            Response::error('Access denied. Administrator role required for backup import.', 403);
            return;
        }

        $inputData = null;
        if (!empty($_FILES['file']['tmp_name'])) {
            $raw = file_get_contents($_FILES['file']['tmp_name']);
            $inputData = json_decode($raw, true);
        } else {
            $input = $this->input();
            if (!empty($input['json_data'])) {
                $inputData = json_decode($input['json_data'], true);
            }
        }

        if (!$inputData || !is_array($inputData) || empty($inputData['tables'])) {
            Response::error('Invalid backup file structure. Ensure you uploaded a valid JSON backup file.', 422);
            return;
        }

        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $importedCounts = [];

        // Fetch all existing tables in current database
        $existingTablesRaw = Database::fetchAll('SHOW TABLES');
        $existingTables = [];
        foreach ($existingTablesRaw as $row) {
            $existingTables[] = strtolower((string) array_values($row)[0]);
        }

        try {
            foreach ($inputData['tables'] as $jsonTableKey => $rows) {
                if (!is_array($rows)) {
                    continue;
                }

                // Map legacy table names if needed
                $targetTable = self::TABLE_MAP[$jsonTableKey] ?? $jsonTableKey;

                // Skip if table does not exist in target database
                if (!in_array(strtolower($targetTable), $existingTables, true)) {
                    continue;
                }

                try {
                    $pdo->exec("TRUNCATE TABLE `{$targetTable}`");
                } catch (\Throwable $e) {
                    // ignore truncate error if table empty or virtual
                }

                if (empty($rows)) {
                    $importedCounts[$targetTable] = 0;
                    continue;
                }

                $count = 0;
                foreach ($rows as $row) {
                    if (!is_array($row) || empty($row)) {
                        continue;
                    }
                    try {
                        if ($targetTable === 'slc_provider_config' && !empty($row['api_key_enc'])) {
                            $plain = \SLC\Core\Crypt::decrypt((string) $row['api_key_enc']);
                            if ($plain !== null && $plain !== '') {
                                $row['api_key_enc'] = \SLC\Core\Crypt::encrypt($plain);
                            }
                        }

                        $cols = array_keys($row);
                        $colsSql = implode('`, `', array_map('addslashes', $cols));
                        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                        $sql = "INSERT INTO `{$targetTable}` (`{$colsSql}`) VALUES ({$placeholders})";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(array_values($row));
                        $count++;
                    } catch (\Throwable $e) {
                        // ignore row insert failures for deprecated columns
                    }
                }
                $importedCounts[$targetTable] = $count;
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $this->activity('backup_import', 'Restored database backup cleanly');
            Response::success([
                'imported' => true,
                'counts' => $importedCounts,
                'message' => 'Successfully restored database from backup file!',
            ]);
        } catch (\Throwable $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            Response::error('Import failed: ' . $e->getMessage(), 500);
        }
    }
}
