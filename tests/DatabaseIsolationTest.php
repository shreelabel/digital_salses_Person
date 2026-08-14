<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Config;
use SLC\Core\Database;

/**
 * Proves the app only ever talks to its own slc_ai_sales(*) database and that
 * the schema references no ERP tables.
 */
class DatabaseIsolationTest extends TestCase
{
    public function testOnlyUsesSlcDatabase(): void
    {
        $name = Config::db()['name'];
        $this->assertTrue(str_contains($name, 'slc') || str_contains($name, 'digital_sales_person'), "DB name must be dedicated app DB: {$name}");
        $this->assertFalse(str_contains($name, 'erp'), "DB name must NEVER be ERP DB: {$name}");
        // the actual connection target
        $actual = Database::pdo()->query('SELECT DATABASE()')->fetchColumn();
        $this->assertEquals($name, $actual);
    }

    public function testSchemaReferencesNoErpTables(): void
    {
        $sql = file_get_contents(SLC_ROOT . '/database/schema.sql');
        // every CREATE TABLE must be a slc_ table
        preg_match_all('/CREATE TABLE[^`]*`(\w+)`/', $sql, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $table) {
            $this->assertStringContains('slc_', $table, "non-slc table found: {$table}");
        }
    }

    public function testAllRequiredTablesExist(): void
    {
        $required = [
            'slc_users', 'slc_companies', 'slc_contacts', 'slc_leads',
            'slc_campaigns', 'slc_campaign_leads', 'slc_followups', 'slc_opportunities',
            'slc_activities', 'slc_email_templates', 'slc_email_messages',
            'slc_ai_settings', 'slc_integrations', 'slc_notifications',
            'slc_research_reports', 'slc_ai_requests', 'slc_sessions',
        ];
        $rows = Database::fetchAll("SHOW TABLES");
        $tables = array_column($rows, array_key_first($rows[0] ?? ['t' => '']));
        foreach ($required as $t) {
            $this->assertContains($t, $tables, "missing table {$t}");
        }
    }

    public function testNoCrossDatabaseQueries(): void
    {
        $files = glob(SLC_ROOT . '/database/*.php');
        foreach ($files as $f) {
            $content = file_get_contents($f);
            // crude guard: no USE of another DB name
            $this->assertStringNotContains('erp', strtolower($content));
        }
    }

    public function testSchemaIsIdempotent(): void
    {
        // re-running the installer must not error
        $installer = new \SLC\Database\Installer(Config::db());
        $installer->run();
        $this->assertTrue(Database::isReady());
    }
}
