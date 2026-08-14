<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Repositories\CompanyRepository;

class DeduplicationTest extends TestCase
{
    public function testFindsExistingByName(): void
    {
        $repo = new CompanyRepository();
        $id = $repo->create(['name' => 'Dedup Pharma Ltd', 'website' => 'https://deduppharma.com']);
        $match = $repo->findExisting('Dedup Pharma Ltd', null, null, null);
        $this->assertNotNull($match);
        $this->assertEquals($id, (int) $match['id']);
        $repo->delete($id);
    }

    public function testFindsExistingByDomain(): void
    {
        $repo = new CompanyRepository();
        $id = $repo->create(['name' => 'Domain Match Co', 'website' => 'https://domainmatch.example']);
        $match = $repo->findExisting('A Totally Different Name', 'domainmatch.example', null, null);
        $this->assertNotNull($match);
        $this->assertEquals($id, (int) $match['id']);
        $repo->delete($id);
    }

    public function testFindsExistingByPhone(): void
    {
        $repo = new CompanyRepository();
        $id = $repo->create(['name' => 'Phone Match Co', 'phone' => '+91-98300-12345']);
        $match = $repo->findExisting('Unrelated Name', null, '919830012345', null);
        $this->assertNotNull($match);
        $repo->delete($id);
    }

    public function testReturnsNullForNoMatch(): void
    {
        $repo = new CompanyRepository();
        $match = $repo->findExisting('Nonexistent-' . uniqid(), null, null, null);
        $this->assertNull($match);
    }
}
