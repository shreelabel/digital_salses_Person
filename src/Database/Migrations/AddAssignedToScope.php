<?php
declare(strict_types=1);

namespace SLC\Database\Migrations;

use SLC\Core\Database;

class AddAssignedToScope
{
    public static function run(): void
    {
        $tables = [
            'slc_companies',
            'slc_contacts',
            'slc_leads',
            'slc_opportunities',
            'slc_campaigns',
            'slc_followups',
        ];

        foreach ($tables as $table) {
            $cols = Database::fetchAll("DESCRIBE {$table}");
            $hasAssignedTo = false;
            foreach ($cols as $c) {
                if ($c['Field'] === 'assigned_to') {
                    $hasAssignedTo = true;
                    break;
                }
            }

            if (!$hasAssignedTo) {
                Database::query("ALTER TABLE {$table} ADD COLUMN assigned_to INT(10) UNSIGNED NULL AFTER id");
                Database::query("ALTER TABLE {$table} ADD INDEX idx_{$table}_assigned_to (assigned_to)");
                echo "Added assigned_to to {$table}\n";
            }
        }

        // Set default assigned_to = 1 (Admin) for existing records where assigned_to is NULL
        foreach ($tables as $table) {
            Database::query("UPDATE {$table} SET assigned_to = 1 WHERE assigned_to IS NULL");
        }

        echo "Migration AddAssignedToScope completed successfully.\n";
    }
}
