<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Repositories\CompanyRepository;
use SLC\Repositories\OpportunityRepository;

class OpportunityTest extends TestCase
{
    private int $companyId;

    protected function setUp(): void
    {
        $this->companyId = (new CompanyRepository())->create(['name' => 'Opp Owner Co']);
    }

    public function testOpportunityCrudAndValueRollup(): void
    {
        $repo = new OpportunityRepository();
        $id = $repo->create([
            'company_id' => $this->companyId, 'title' => 'Label Reprint Deal',
            'amount' => 500000, 'stage' => 'Proposal', 'probability' => 50,
        ]);
        $this->assertGreaterThan(0, $id);
        $this->assertEquals('Proposal', $repo->find($id)['stage']);

        // open value includes this
        $this->assertTrue($repo->openValue() >= 500000);

        $repo->update($id, ['stage' => 'Won']);
        $repo->delete($id);
    }

    public function testProbabilityIsClamped(): void
    {
        $repo = new OpportunityRepository();
        $id = $repo->create(['company_id' => $this->companyId, 'title' => 'Prob', 'probability' => 150]);
        $this->assertEquals(100, (int) $repo->find($id)['probability']);
        $repo->delete($id);
    }
}
