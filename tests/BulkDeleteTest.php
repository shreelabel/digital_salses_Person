<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Repositories\CompanyRepository;
use SLC\Repositories\ContactRepository;
use SLC\Repositories\LeadRepository;
use SLC\Repositories\CampaignRepository;
use SLC\Repositories\OpportunityRepository;
use SLC\Repositories\FollowupRepository;
use SLC\Repositories\UserRepository;
use SLC\Controllers\CompanyController;
use SLC\Controllers\LeadController;
use SLC\Controllers\UserController;
use SLC\Core\Auth;
use SLC\Core\Database;

class BulkDeleteTest extends TestCase
{
    private CompanyRepository $companies;
    private ContactRepository $contacts;
    private LeadRepository $leads;
    private CampaignRepository $campaigns;
    private OpportunityRepository $opps;
    private FollowupRepository $followups;
    private UserRepository $users;

    protected function setUp(): void
    {
        $this->bootSession();
        $this->companies = new CompanyRepository();
        $this->contacts = new ContactRepository();
        $this->leads = new LeadRepository();
        $this->campaigns = new CampaignRepository();
        $this->opps = new OpportunityRepository();
        $this->followups = new FollowupRepository();
        $this->users = new UserRepository();
    }

    public function testCompanyBulkDelete(): void
    {
        $id1 = $this->companies->create(['name' => 'Bulk Co 1', 'industry' => 'Packaging']);
        $id2 = $this->companies->create(['name' => 'Bulk Co 2', 'industry' => 'Packaging']);
        $id3 = $this->companies->create(['name' => 'Bulk Co 3', 'industry' => 'Packaging']);

        $this->assertNotNull($this->companies->find($id1));
        $this->assertNotNull($this->companies->find($id2));
        $this->assertNotNull($this->companies->find($id3));

        $deletedCount = $this->companies->deleteMany([$id1, $id2]);
        $this->assertEquals(2, $deletedCount);

        // Soft-deleted: find() returns null
        $this->assertNull($this->companies->find($id1));
        $this->assertNull($this->companies->find($id2));
        // Remaining not deleted
        $this->assertNotNull($this->companies->find($id3));

        // Cleanup
        $this->companies->delete($id3);
    }

    public function testContactAndLeadBulkDelete(): void
    {
        $coId = $this->companies->create(['name' => 'Parent Co for Bulk']);

        $c1 = $this->contacts->create(['company_id' => $coId, 'name' => 'Contact 1', 'email' => 'c1@bulk.test']);
        $c2 = $this->contacts->create(['company_id' => $coId, 'name' => 'Contact 2', 'email' => 'c2@bulk.test']);

        $l1 = $this->leads->create(['company_id' => $coId, 'title' => 'Lead 1', 'status' => 'New']);
        $l2 = $this->leads->create(['company_id' => $coId, 'title' => 'Lead 2', 'status' => 'New']);

        $deletedContacts = $this->contacts->deleteMany([$c1, $c2]);
        $this->assertEquals(2, $deletedContacts);
        $this->assertNull($this->contacts->find($c1));
        $this->assertNull($this->contacts->find($c2));

        $deletedLeads = $this->leads->deleteMany([$l1, $l2]);
        $this->assertEquals(2, $deletedLeads);
        $this->assertNull($this->leads->find($l1));
        $this->assertNull($this->leads->find($l2));

        $this->companies->delete($coId);
    }

    public function testCampaignOpportunityAndFollowupBulkDelete(): void
    {
        $camp1 = $this->campaigns->create(['name' => 'Bulk Camp 1']);
        $camp2 = $this->campaigns->create(['name' => 'Bulk Camp 2']);
        $this->assertEquals(2, $this->campaigns->deleteMany([$camp1, $camp2]));
        $this->assertNull($this->campaigns->find($camp1));
        $this->assertNull($this->campaigns->find($camp2));

        $coId = $this->companies->create(['name' => 'Opp Co']);
        $o1 = $this->opps->create(['company_id' => $coId, 'title' => 'Opp 1', 'amount' => 50000]);
        $o2 = $this->opps->create(['company_id' => $coId, 'title' => 'Opp 2', 'amount' => 75000]);
        $this->assertEquals(2, $this->opps->deleteMany([$o1, $o2]));
        $this->assertNull($this->opps->find($o1));
        $this->assertNull($this->opps->find($o2));

        $fu1 = $this->followups->create(['company_id' => $coId, 'scheduled_at' => date('Y-m-d H:i:s'), 'type' => 'Call']);
        $fu2 = $this->followups->create(['company_id' => $coId, 'scheduled_at' => date('Y-m-d H:i:s'), 'type' => 'Email']);
        $this->assertEquals(2, $this->followups->deleteMany([$fu1, $fu2]));
        $this->assertNull($this->followups->find($fu1));
        $this->assertNull($this->followups->find($fu2));

        $this->companies->delete($coId);
    }

    public function testDeleteManyWithEmptyArrayReturnsZero(): void
    {
        $count = $this->companies->deleteMany([]);
        $this->assertEquals(0, $count);
        $count2 = $this->companies->deleteMany(['invalid', 0, -5]);
        $this->assertEquals(0, $count2);
    }
}
