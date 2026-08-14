<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Repositories\CompanyRepository;
use SLC\Repositories\FollowupRepository;

class FollowupTest extends TestCase
{
    private int $companyId;

    protected function setUp(): void
    {
        $this->companyId = (new CompanyRepository())->create(['name' => 'Fu Owner Co']);
    }

    public function testFollowupCrud(): void
    {
        $repo = new FollowupRepository();
        $id = $repo->create([
            'company_id' => $this->companyId, 'type' => 'Call',
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('+1 day')), 'status' => 'Pending',
        ]);
        $this->assertGreaterThan(0, $id);
        $row = $repo->find($id);
        $this->assertEquals('Pending', $row['status']);

        $repo->update($id, ['status' => 'Completed', 'completed_at' => date('Y-m-d H:i:s')]);
        $this->assertEquals('Completed', $repo->find($id)['status']);

        $repo->delete($id); // hard delete (no soft delete on follow-ups)
        $this->assertNull($repo->find($id));
    }
}
