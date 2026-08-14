<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Repositories\CompanyRepository;

class CompanyTest extends TestCase
{
    private CompanyRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new CompanyRepository();
    }

    public function testCreateReadUpdateDelete(): void
    {
        $id = $this->repo->create([
            'name' => 'Test Pharma Ltd', 'industry' => 'Pharmaceutical',
            'city' => 'Kolkata', 'state' => 'West Bengal', 'country' => 'India',
            'website' => 'https://testpharma.example', 'ai_score' => 85, 'ai_priority' => 'High',
        ]);
        $this->assertGreaterThan(0, $id);

        $row = $this->repo->find($id);
        $this->assertEquals('Test Pharma Ltd', $row['name']);
        $this->assertEquals(85, (int) $row['ai_score']);

        $this->repo->update($id, ['name' => 'Test Pharma Pvt Ltd']);
        $this->assertEquals('Test Pharma Pvt Ltd', $this->repo->find($id)['name']);

        $deleted = $this->repo->delete($id);
        $this->assertTrue($deleted);
        // soft-delete: find() excludes it
        $this->assertNull($this->repo->find($id));
    }

    public function testAiScoreIsClampedToRange(): void
    {
        $id = $this->repo->create(['name' => 'Clamp Co', 'ai_score' => 999]);
        $this->assertEquals(100, (int) $this->repo->find($id)['ai_score']);
        $this->repo->delete($id);
    }

    public function testPaginationAndSearch(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->repo->create(['name' => 'Search Co ' . $i, 'industry' => 'Cosmetics']);
        }
        $res = $this->repo->paginate(['q' => 'Search Co'], 1, 50);
        $this->assertTrue($res['total'] >= 3);
        $res2 = $this->repo->paginate(['industry' => 'Cosmetics'], 1, 50);
        $this->assertTrue($res2['total'] >= 3);
    }

    public function testRelatedContactsLeadsActivities(): void
    {
        $id = $this->repo->create(['name' => 'Rel Co']);
        $this->assertIsArray($this->repo->contacts($id));
        $this->assertIsArray($this->repo->leads($id));
        $this->assertIsArray($this->repo->activities($id));
        $this->repo->delete($id);
    }

    protected function assertIsArray($v): void
    {
        $this->assertions++;
        if (!is_array($v)) {
            throw new \AssertionError('expected array');
        }
    }
}
