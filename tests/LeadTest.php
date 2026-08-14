<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Repositories\CompanyRepository;
use SLC\Repositories\LeadRepository;

class LeadTest extends TestCase
{
    private int $companyId;

    protected function setUp(): void
    {
        $this->companyId = (new CompanyRepository())->create(['name' => 'Lead Owner Co']);
    }

    public function testLeadPipelineCrud(): void
    {
        $repo = new LeadRepository();
        $id = $repo->create([
            'company_id' => $this->companyId, 'industry' => 'FMCG', 'location' => 'Kolkata',
            'status' => 'New', 'priority' => 'High', 'estimated_value' => 250000, 'source' => 'Test',
        ]);
        $this->assertGreaterThan(0, $id);

        $row = $repo->find($id);
        $this->assertEquals('New', $row['status']);

        $repo->update($id, ['status' => 'Won', 'priority' => 'High']);
        $this->assertEquals('Won', $repo->find($id)['status']);

        $joined = $repo->listWithCompany(['company_id' => $this->companyId]);
        $this->assertGreaterThan(0, $joined['total']);

        $repo->delete($id);
        $this->assertNull($repo->find($id));
    }

    public function testStatusesAreConstrained(): void
    {
        $this->assertContains('Won', LeadRepository::STATUSES);
        $this->assertContains('Lost', LeadRepository::STATUSES);
        $this->assertContains('Negotiation', LeadRepository::STATUSES);
    }
}
