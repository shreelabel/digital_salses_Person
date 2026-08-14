<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Database;
use SLC\Controllers\SidebarController;
use SLC\Controllers\PageController;

class VerifySidebarHtmlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Establish admin user session for testing SSR sidebar
        $_SESSION['_auth_user_id'] = 1;
    }

    public function testSidebarRenderContainsCountBadgesWithCorrectValues(): void
    {
        $_SESSION['_auth_user_id'] = 1; // Admin
        $counts = SidebarController::getLiveCounts();
        $dbCompanies = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_companies WHERE deleted_at IS NULL");
        $dbContacts  = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_contacts WHERE deleted_at IS NULL");
        $dbLeads     = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_leads WHERE deleted_at IS NULL");
        $dbCampaigns = (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_campaigns WHERE deleted_at IS NULL");

        $this->assertEquals($dbCompanies, $counts['companies']);
        $this->assertEquals($dbContacts, $counts['contacts']);
        $this->assertEquals($dbLeads, $counts['leads']);
        $this->assertEquals($dbCampaigns, $counts['campaigns']);

        // Capture page output for companies page under Admin
        ob_start();
        $controller = new PageController('/digital_sales_person2');
        $controller->render('companies');
        $html = ob_get_clean();

        // Verify HTML contains the sidebar counter spans with exact values
        $this->assertTrue(str_contains($html, 'data-count-key="companies"'), 'HTML must contain companies data-count-key');
        $this->assertTrue(str_contains($html, '>' . $dbCompanies . '</span>'), 'HTML must contain count for companies');
        $this->assertTrue(str_contains($html, 'data-count-key="contacts"'), 'HTML must contain contacts data-count-key');
        $this->assertTrue(str_contains($html, '>' . $dbContacts . '</span>'), 'HTML must contain count for contacts');
        $this->assertTrue(str_contains($html, 'data-count-key="leads"'), 'HTML must contain leads data-count-key');
        $this->assertTrue(str_contains($html, '>' . $dbLeads . '</span>'), 'HTML must contain count for leads');
        $this->assertTrue(str_contains($html, 'data-count-key="campaigns"'), 'HTML must contain campaigns data-count-key');
        $this->assertTrue(str_contains($html, '>' . $dbCampaigns . '</span>'), 'HTML must contain count for campaigns');

        // Admin sidebar must contain AI Lead Finder & Configuration
        $this->assertTrue(str_contains($html, 'AI Lead Finder'), 'Admin sidebar must contain AI Lead Finder');
        $this->assertTrue(str_contains($html, 'Configuration'), 'Admin sidebar must contain Configuration');
    }

    public function testNormalUserSidebarHidesAiLeadFinderAndConfiguration(): void
    {
        // Normal user account
        $normalUserId = (int) Database::fetchColumn("SELECT id FROM slc_users WHERE role = 'user' AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
        if (!$normalUserId) {
            $normalUserId = 2;
        }
        $_SESSION['_auth_user_id'] = $normalUserId;

        ob_start();
        $controller = new PageController('/digital_sales_person2');
        $controller->render('companies');
        $html = ob_get_clean();

        // Normal User sidebar must hide AI Lead Finder and Configuration
        $this->assertFalse(str_contains($html, 'AI Lead Finder'), 'Normal user sidebar must NOT contain AI Lead Finder');
        $this->assertFalse(str_contains($html, 'Configuration</div>'), 'Normal user sidebar must NOT contain Configuration section');
        $this->assertFalse(str_contains($html, 'AI Settings'), 'Normal user sidebar must NOT contain AI Settings');
        $this->assertFalse(str_contains($html, 'Integrations'), 'Normal user sidebar must NOT contain Integrations');

        // Normal User sidebar MUST show allowed modules
        $this->assertTrue(str_contains($html, 'Companies'), 'Normal user sidebar must contain Companies');
        $this->assertTrue(str_contains($html, 'Contacts'), 'Normal user sidebar must contain Contacts');
        $this->assertTrue(str_contains($html, 'Leads'), 'Normal user sidebar must contain Leads');
        $this->assertTrue(str_contains($html, 'My Profile'), 'Normal user sidebar must contain My Profile');
    }
}
