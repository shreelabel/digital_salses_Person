<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Database;
use SLC\Services\Import\ApolloCsvParser;
use SLC\Services\Import\ApolloCsvImporter;

class ApolloCsvImportTest extends TestCase
{
    private string $csvFile;
    private ApolloCsvParser $parser;
    private ApolloCsvImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csvFile = SLC_ROOT . '/apollo-contacts-export.csv';
        $this->parser = new ApolloCsvParser();
        $this->importer = new ApolloCsvImporter($this->parser);

        // Reset any prior Apollo CSV imported records before test execution
        Database::query("DELETE FROM slc_contacts WHERE apollo_contact_id IS NOT NULL");
        Database::query("DELETE FROM slc_leads WHERE source = 'Apollo CSV'");
        Database::query("DELETE FROM slc_imports WHERE source = 'Apollo CSV'");
    }

    public function testCsvValidationAndHeaderParsing(): void
    {
        $this->assertTrue(file_exists($this->csvFile), 'Apollo test CSV file must exist');

        $val = $this->parser->validateFile($this->csvFile);
        $this->assertTrue($val['ok'], 'CSV file validation must pass');

        $headers = $this->parser->readHeaders($this->csvFile);
        $this->assertCount(71, $headers['headers'], 'Apollo CSV must have exactly 71 headers detected dynamically');
        $this->assertContains('First Name', $headers['headers']);
        $this->assertContains('Last Name', $headers['headers']);
        $this->assertContains('Email', $headers['headers']);
        $this->assertContains('Company Name', $headers['headers']);
        $this->assertContains('Apollo Contact Id', $headers['headers']);
        $this->assertContains('Technologies', $headers['headers']);
    }

    public function testCsvParsingRowsAndSanitization(): void
    {
        $parsed = $this->parser->parse($this->csvFile);
        $this->assertCount(25, $parsed['rows'], 'Apollo CSV must parse exactly 25 data rows');

        $firstRow = $parsed['rows'][0];
        $this->assertEquals('Sagar', $firstRow['First Name']);
        $this->assertEquals('Gupta', $firstRow['Last Name']);
        $this->assertEquals('Anything Skool Limited', $firstRow['Company Name']);
        $this->assertEquals('purchase.apparel@anythingskool.com', $firstRow['Email']);
        $this->assertEquals('Verified', $firstRow['Email Status']);
        $this->assertEquals('6a7d80fdd4427500010b0af0', $firstRow['Apollo Contact Id']);
        // Verify Excel quote prefix was sanitized from phone
        $this->assertStringNotContains("'", $firstRow['Corporate Phone']);
        $this->assertStringContains('+91 99969 97639', $firstRow['Corporate Phone']);
    }

    public function testPreviewAndMappingPreservesAll71Fields(): void
    {
        $preview = $this->importer->preview($this->csvFile, 'apollo-contacts-export.csv');
        $this->assertTrue($preview['ok'], 'Preview generation must succeed');

        $p = $preview['preview'];
        $this->assertEquals(25, $p['total_rows']);
        $this->assertEquals(71, $p['total_columns']);
        $this->assertEquals('Apollo Contacts Export CSV', $p['detected_format']);
        $this->assertEquals(25, $p['new_leads_count']);
        $this->assertEquals(0, $p['existing_leads_count']);
        $this->assertEquals(0, $p['invalid_rows_count']);
        $this->assertNotEmpty($p['batch_token']);
        $this->assertCount(25, $p['preview_rows']);

        // Check preservation of all 71 fields in first preview row
        $firstPreview = $p['preview_rows'][0];
        $this->assertTrue(isset($firstPreview['raw_apollo_data']));
        $this->assertCount(71, $firstPreview['raw_apollo_data'], 'Must preserve all 71 original Apollo fields');
        $this->assertEquals('Sagar Gupta', $firstPreview['contact_name']);
        $this->assertEquals('Purchaser', $firstPreview['job_title']);
        $this->assertEquals('Anything Skool Limited', $firstPreview['company_name']);
    }

    public function testEndToEndDatabaseImportAndDuplicateDetection(): void
    {
        // 1. Initial Preview
        $preview = $this->importer->preview($this->csvFile, 'apollo-contacts-export.csv');
        $batchToken = $preview['preview']['batch_token'];

        // 2. Execute Import
        $importResult = $this->importer->executeImport($batchToken, ['skip_duplicates' => true], 1);
        $this->assertTrue($importResult['ok'], 'Import execution must succeed: ' . ($importResult['error'] ?? ''));

        $res = $importResult['result'];
        $this->assertEquals(25, $res['total_rows']);
        $this->assertEquals(25, $res['imported']);
        $this->assertEquals(0, $res['duplicates']);
        $this->assertEquals(0, $res['errors']);

        // 3. Verify Database Records
        $leads = Database::fetchAll("SELECT * FROM slc_leads WHERE source = 'Apollo CSV' AND import_batch_id = :b", ['b' => $batchToken]);
        $this->assertCount(25, $leads, 'Database must contain 25 imported Apollo leads');

        // Check first lead details and raw data preservation
        $firstLead = $leads[0];
        $this->assertEquals('Apollo CSV', $firstLead['source']);
        $this->assertNotEmpty($firstLead['raw_data']);
        $leadRaw = json_decode((string)$firstLead['raw_data'], true);
        $this->assertEquals('apollo-contacts-export.csv', $leadRaw['source_file']);
        $this->assertCount(71, $leadRaw['original_apollo_data'], 'Lead record must retain all 71 raw Apollo attributes');

        // 4. Verify Contact and Company preservation
        $contact = Database::fetch("SELECT * FROM slc_contacts WHERE id = :id", ['id' => $firstLead['contact_id']]);
        $this->assertNotNull($contact);
        $this->assertEquals('6a7d80fdd4427500010b0af0', $contact['apollo_contact_id']);
        $contactRaw = json_decode((string)$contact['raw_data'], true);
        $this->assertCount(71, $contactRaw, 'Contact record must retain all 71 raw Apollo attributes');

        // 5. Verify slc_imports History Record
        $importHistory = Database::fetch("SELECT * FROM slc_imports WHERE batch_id = :b", ['b' => $batchToken]);
        $this->assertNotNull($importHistory);
        $this->assertEquals('Apollo CSV', $importHistory['source']);
        $this->assertEquals(25, (int)$importHistory['total_rows']);
        $this->assertEquals(25, (int)$importHistory['imported_count']);
        $this->assertEquals(0, (int)$importHistory['error_count']);

        // 6. Test Duplicate Detection (Re-importing the same CSV)
        $secondPreview = $this->importer->preview($this->csvFile, 'apollo-contacts-export.csv');
        $this->assertTrue($secondPreview['ok']);
        $p2 = $secondPreview['preview'];
        $this->assertEquals(25, $p2['total_rows']);
        $this->assertEquals(0, $p2['new_leads_count'], 'Second preview must detect 0 new leads');
        $this->assertEquals(25, $p2['existing_leads_count'], 'Second preview must flag all 25 rows as existing in CRM');

        // Execute second import
        $secondImport = $this->importer->executeImport($p2['batch_token'], ['skip_duplicates' => true], 1);
        $res2 = $secondImport['result'];
        $this->assertEquals(0, $res2['imported'], 'Must import 0 leads on duplicate re-run');
        $this->assertEquals(25, $res2['duplicates'], 'Must detect 25 duplicates on re-run');
        $this->assertEquals(25, $res2['skipped']);

        // 7. Test 4-Tier Duplicate Detection Priorities on the imported database
        // Priority 1: Match by Apollo Contact ID
        $dup1 = $this->importer->findExistingRecord([
            'apollo_contact_id' => '6a7d80fdd4427500010b0af0',
            'email' => 'completely_new_email@example.com',
            'linkedin_url' => 'http://linkedin.com/in/unrelated',
            'contact_name' => 'Random Name',
            'company_name' => 'Random Co',
        ]);
        $this->assertNotNull($dup1);
        $this->assertStringContains('Apollo Contact ID', $dup1['reason']);

        // Priority 2: Match by Email
        $dup2 = $this->importer->findExistingRecord([
            'apollo_contact_id' => 'non_existent_id_123',
            'email' => 'purchase.apparel@anythingskool.com',
            'linkedin_url' => 'http://linkedin.com/in/unrelated',
            'contact_name' => 'Different Person',
            'company_name' => 'Different Co',
        ]);
        $this->assertNotNull($dup2);
        $this->assertStringContains('Email', $dup2['reason']);

        // Priority 3: Match by LinkedIn URL
        $dup3 = $this->importer->findExistingRecord([
            'apollo_contact_id' => 'non_existent_id_456',
            'email' => 'new_unique_email@test.com',
            'linkedin_url' => 'http://www.linkedin.com/in/sagar-gupta-b38801239',
            'contact_name' => 'Different Person 2',
            'company_name' => 'Different Co 2',
        ]);
        $this->assertNotNull($dup3);
        $this->assertStringContains('LinkedIn URL', $dup3['reason']);

        // Priority 4: Match by Name + Company
        $dup4 = $this->importer->findExistingRecord([
            'apollo_contact_id' => 'non_existent_id_789',
            'email' => 'brand_new_email@test.com',
            'linkedin_url' => 'http://linkedin.com/in/another_unrelated',
            'contact_name' => 'Sagar Gupta',
            'company_name' => 'Anything Skool Limited',
        ]);
        $this->assertNotNull($dup4);
        $this->assertStringContains('Name (Sagar Gupta) + Company (Anything Skool Limited)', $dup4['reason']);
    }
}
