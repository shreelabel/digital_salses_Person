<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Database;
use SLC\Controllers\SidebarController;

class SidebarCountersTest extends TestCase
{
    public function testSidebarCountersEndpointReturnsAllRealCounts(): void
    {
        // Compute expected counts directly from DB
        $expCompanies = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_companies WHERE deleted_at IS NULL");
        $expContacts  = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_contacts WHERE deleted_at IS NULL");
        $expLeads     = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_leads WHERE deleted_at IS NULL");
        $expCampaigns = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_campaigns WHERE deleted_at IS NULL");
        $expFollowups = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_followups WHERE status = 'Pending'");
        $expOpps      = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_opportunities WHERE deleted_at IS NULL");
        $expDrafts    = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_email_messages WHERE status = 'draft'");
        $expReports   = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_research_reports");
        $expImports   = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_imports");

        // Execute controller logic
        ob_start();
        $controller = new SidebarController();
        $controller->counts();
        $output = ob_get_clean();

        $res = json_decode($output, true);
        $this->assertNotNull($res, 'Sidebar counts endpoint must return valid JSON');
        $this->assertTrue($res['ok'], 'Sidebar counts endpoint must return ok = true');
        $this->assertTrue(isset($res['counts']), 'Sidebar counts endpoint must return counts array');

        $counts = $res['counts'];
        $this->assertEquals($expCompanies, $counts['companies'], 'Companies count must match DB');
        $this->assertEquals($expContacts, $counts['contacts'], 'Contacts count must match DB');
        $this->assertEquals($expLeads, $counts['leads'], 'Leads count must match DB');
        $this->assertEquals($expCampaigns, $counts['campaigns'], 'Campaigns count must match DB');
        $this->assertEquals($expFollowups, $counts['followups'], 'Followups count must match DB');
        $this->assertEquals($expOpps, $counts['opportunities'], 'Opportunities count must match DB');
        $this->assertEquals($expDrafts, $counts['email-composer'], 'Email Drafts count must match DB');
        $this->assertEquals($expReports, $counts['research-reports'], 'Research Reports count must match DB');
        $this->assertEquals($expImports, $counts['imports'], 'Imports count must match DB');
    }

    public function testCountersIncreaseOnNewLeadAndDecreaseOnDelete(): void
    {
        $initialLeads = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_leads WHERE deleted_at IS NULL");

        // Insert a new temporary company and lead
        $compId = Database::insert('slc_companies', [
            'name' => 'Counter Test Company ' . uniqid(),
            'industry' => 'Packaging',
        ]);
        $leadId = Database::insert('slc_leads', [
            'company_id' => $compId,
            'title' => 'Counter Test Lead',
            'status' => 'New',
        ]);

        $afterInsert = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_leads WHERE deleted_at IS NULL");
        $this->assertEquals($initialLeads + 1, $afterInsert);

        // Delete the lead
        Database::query("UPDATE slc_leads SET deleted_at = NOW() WHERE id = :id", ['id' => $leadId]);
        $afterDelete = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_leads WHERE deleted_at IS NULL");
        $this->assertEquals($initialLeads, $afterDelete);

        // Clean up company
        Database::query("DELETE FROM slc_companies WHERE id = :id", ['id' => $compId]);
        Database::query("DELETE FROM slc_leads WHERE id = :id", ['id' => $leadId]);
    }
}
