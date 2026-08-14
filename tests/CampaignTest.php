<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Repositories\CampaignRepository;
use SLC\Repositories\CompanyRepository;
use SLC\Repositories\LeadRepository;

class CampaignTest extends TestCase
{
    public function testCampaignLifecycle(): void
    {
        $repo = new CampaignRepository();
        $id = $repo->create(['name' => 'Q1 Pharma Push', 'objective' => 'Intro', 'status' => 'Draft']);
        $this->assertGreaterThan(0, $id);

        $repo->activate($id);
        $this->assertEquals('Active', $repo->find($id)['status']);
        $repo->pause($id);
        $this->assertEquals('Paused', $repo->find($id)['status']);

        $repo->update($id, ['name' => 'Q1 Pharma Campaign']);
        $this->assertEquals('Q1 Pharma Campaign', $repo->find($id)['name']);

        $repo->delete($id);
        $this->assertNull($repo->find($id));
    }

    public function testCampaignLeadSequenceNoDuplicates(): void
    {
        $cid = (new CompanyRepository())->create(['name' => 'Camp Lead Co']);
        $lid = (new LeadRepository())->create(['company_id' => $cid, 'status' => 'New']);
        $campId = (new CampaignRepository())->create(['name' => 'Seq Camp']);

        $repo = new CampaignRepository();
        $added1 = $repo->addLeads($campId, [$lid]);
        $added2 = $repo->addLeads($campId, [$lid]); // duplicate — must not double add
        $this->assertEquals(1, $added1);
        $this->assertEquals(0, $added2);
        $this->assertEquals(1, $repo->leadCount($campId));
    }
}
