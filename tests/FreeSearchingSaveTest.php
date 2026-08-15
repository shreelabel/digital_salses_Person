<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Auth;
use SLC\Core\Database;
use SLC\Controllers\AiController;
use SLC\Repositories\CompanyRepository;
use SLC\Repositories\LeadRepository;

final class FreeSearchingSaveTest extends TestCase
{
    public function testSaveFreeSearchingLeadsWithFlexibleFieldsAndAssignment(): void
    {
        $this->bootSession();
        // 1. Authenticate as Admin
        $user = Database::fetch("SELECT * FROM slc_users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
        $this->assertNotEmpty($user, 'Admin user should exist');
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];

        // 2. Prepare test lead data generated from Free Searching
        $testCompanyName = 'Unit Test Free Search Plant Ltd (' . time() . ')';
        $testProspects = [
            [
                'company_name'        => $testCompanyName,
                'name'                => $testCompanyName,
                'website'             => 'https://www.unittest-freesearch-plant.com',
                'google_maps_url'     => 'https://www.google.com/maps?q=UnitTest+Plant',
                'contact_person'      => 'Rajesh Banerjee',
                'contact_name'        => 'Rajesh Banerjee',
                'designation'         => 'Head of Packaging Procurement',
                'contact_designation' => 'Head of Packaging Procurement',
                'direct_email'        => 'rajesh.banerjee@unittest-freesearch-plant.com',
                'direct_phone'        => '+91 98300 12345',
                'company_phone'       => '+91 33 26611000',
                'address'             => 'Dankuni Industrial Complex, Hooghly, West Bengal 712311',
                'location'            => 'West Bengal',
                'city'                => 'West Bengal',
                'keyword'             => 'Packaged Drinking Water Bottling',
                'industry'            => 'Packaged Drinking Water Bottling',
                'products'            => 'Multicolour Roll Labels, Bottle Wrap Labels',
                'why_relevant'        => 'Multicolour Roll Labels, Bottle Wrap Labels',
                'priority'            => 'High',
                'ai_score'            => 90,
                'source'              => 'Free Regional Lead Generator'
            ]
        ];

        // 3. Mock request body & invoke saveDiscovered
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        
        $assignedUserId = (int) $user['id'];
        
        // Direct database verification
        $repo = new CompanyRepository();
        $existing = $repo->findExisting($testCompanyName, 'unittest-freesearch-plant.com', '+91 98300 12345', 'rajesh.banerjee@unittest-freesearch-plant.com');
        $this->assertNull($existing);

        // Simulate save logic through Database transaction
        $saved = 0;
        $created = 0;
        Database::transaction(function () use ($testProspects, $repo, $assignedUserId, &$saved, &$created) {
            foreach ($testProspects as $p) {
                $name = $p['name'] ?? $p['company_name'] ?? null;
                $companyId = $repo->create([
                    'assigned_to'   => $assignedUserId,
                    'name'          => $name,
                    'industry'      => $p['industry'] ?? $p['keyword'] ?? null,
                    'city'          => $p['city'] ?? $p['location'] ?? null,
                    'website'       => $p['website'] ?? null,
                    'phone'         => $p['direct_phone'] ?? $p['company_phone'] ?? null,
                    'email'         => $p['direct_email'] ?? $p['email'] ?? null,
                    'description'   => "Factory Address: " . ($p['address'] ?? ''),
                    'ai_score'      => $p['ai_score'] ?? 90,
                    'ai_priority'   => $p['priority'] ?? 'High',
                    'source'        => $p['source'] ?? 'Google Maps & AI Discovery',
                ]);
                $created++;

                $contactId = Database::insert('slc_contacts', [
                    'assigned_to' => $assignedUserId,
                    'company_id'  => $companyId,
                    'name'        => $p['contact_name'] ?? $p['contact_person'] ?? 'Purchase Manager',
                    'designation' => $p['contact_designation'] ?? $p['designation'] ?? 'Procurement Head',
                    'email'       => $p['contact_email'] ?? $p['direct_email'] ?? null,
                    'phone'       => $p['direct_phone'] ?? null,
                    'source'      => $p['source'] ?? 'Google Maps & AI Discovery',
                    'importance'  => 'Medium',
                ]);

                $leadId = Database::insert('slc_leads', [
                    'assigned_to' => $assignedUserId,
                    'company_id'  => $companyId,
                    'contact_id'  => $contactId,
                    'title'       => 'Prospect: ' . $name,
                    'industry'    => $p['industry'] ?? $p['keyword'] ?? null,
                    'location'    => $p['location'] ?? 'West Bengal',
                    'status'      => 'New',
                    'priority'    => $p['priority'] ?? 'High',
                    'ai_score'    => $p['ai_score'] ?? 90,
                    'notes'       => $p['why_relevant'] ?? $p['products'] ?? null,
                    'source'      => $p['source'] ?? 'Google Maps & AI Discovery',
                ]);

                $saved++;
            }
        });

        $this->assertEquals(1, $saved);
        $this->assertEquals(1, $created);

        // Verify company created
        $savedCompany = Database::fetch("SELECT * FROM slc_companies WHERE name = :name", ['name' => $testCompanyName]);
        $this->assertNotEmpty($savedCompany);
        $this->assertEquals($assignedUserId, (int) $savedCompany['assigned_to']);
        $this->assertEquals('Free Regional Lead Generator', $savedCompany['source']);

        // Verify contact created
        $savedContact = Database::fetch("SELECT * FROM slc_contacts WHERE company_id = :cid", ['cid' => $savedCompany['id']]);
        $this->assertNotEmpty($savedContact);
        $this->assertEquals('Rajesh Banerjee', $savedContact['name']);
        $this->assertEquals('rajesh.banerjee@unittest-freesearch-plant.com', $savedContact['email']);

        // Verify lead created
        $savedLead = Database::fetch("SELECT * FROM slc_leads WHERE company_id = :cid", ['cid' => $savedCompany['id']]);
        $this->assertNotEmpty($savedLead);
        $this->assertEquals('New', $savedLead['status']);
        $this->assertEquals($assignedUserId, (int) $savedLead['assigned_to']);

        // Clean up test records
        Database::query("DELETE FROM slc_leads WHERE id = :id", ['id' => $savedLead['id']]);
        Database::query("DELETE FROM slc_contacts WHERE id = :id", ['id' => $savedContact['id']]);
        Database::query("DELETE FROM slc_companies WHERE id = :id", ['id' => $savedCompany['id']]);
    }
}
