<?php
require __DIR__ . '/../src/bootstrap.php';

use SLC\Core\Database;

echo "=== EXPORTING FRESH DATABASE BACKUP JSON ===\n";

$tables = [
    'slc_users',
    'slc_companies',
    'slc_contacts',
    'slc_leads',
    'slc_campaigns',
    'slc_campaign_sequences',
    'slc_followups',
    'slc_opportunities',
    'slc_email_templates',
    'slc_email_messages',
    'slc_research_reports',
    'slc_integrations',
    'slc_provider_config',
    'slc_ai_requests',
    'slc_activities',
    'slc_settings',
];

$backup = [
    'version'     => '2.0.0',
    'app'         => 'Shree Label Digital Sales Person',
    'milestone'   => 'M-001',
    'exported_at' => date('Y-m-d H:i:s'),
    'tables'      => [],
];

$totalRows = 0;
foreach ($tables as $table) {
    try {
        $rows = Database::fetchAll("SELECT * FROM {$table}");
        $backup['tables'][$table] = $rows;
        $count = count($rows);
        $totalRows += $count;
        echo "  - {$table}: {$count} records\n";
    } catch (\Throwable $e) {
        $backup['tables'][$table] = [];
        echo "  - {$table}: 0 records (or error: {$e->getMessage()})\n";
    }
}

$json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$targetFile = dirname(__DIR__) . '/shree_label_backup_latest.json';
file_put_contents($targetFile, $json);

echo "\n✅ Successfully exported {$totalRows} records to:\n{$targetFile} (" . round(strlen($json) / 1024, 1) . " KB)\n\n";
