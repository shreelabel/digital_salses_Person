<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Repositories\CompanyRepository;
use SLC\Repositories\ContactRepository;

class ContactTest extends TestCase
{
    private int $companyId;

    protected function setUp(): void
    {
        $companies = new CompanyRepository();
        $this->companyId = $companies->create(['name' => 'Contact Owner Co']);
    }

    public function testContactCrudRequiresCompany(): void
    {
        $repo = new ContactRepository();
        $id = $repo->create([
            'company_id' => $this->companyId, 'name' => 'Priya Das',
            'designation' => 'Procurement Head', 'email' => 'priya@example.com',
            'is_decision_maker' => 1, 'is_primary' => 1, 'importance' => 'High',
        ]);
        $this->assertGreaterThan(0, $id);

        $row = $repo->find($id);
        $this->assertEquals('Priya Das', $row['name']);
        $this->assertEquals(1, (int) $row['is_decision_maker']);

        $repo->update($id, ['designation' => 'VP Procurement']);
        $this->assertEquals('VP Procurement', $repo->find($id)['designation']);

        $repo->delete($id);
        $this->assertNull($repo->find($id));
    }

    public function testBooleanFlagsAreNormalised(): void
    {
        $repo = new ContactRepository();
        $id = $repo->create(['company_id' => $this->companyId, 'name' => 'Flag Test', 'is_decision_maker' => 'true']);
        $this->assertEquals(1, (int) $repo->find($id)['is_decision_maker']);
        $repo->delete($id);
    }
}
