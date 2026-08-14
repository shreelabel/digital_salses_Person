<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Auth;
use SLC\Core\Database;
use SLC\Core\Permissions;
use SLC\Core\Session;
use SLC\Repositories\UserRepository;
use SLC\Controllers\PageController;
use SLC\Controllers\UserController;
use SLC\Controllers\ImportController;
use SLC\Controllers\AiController;
use SLC\Controllers\IntegrationController;
use SLC\Controllers\CompanyController;

class UserRolesAndPermissionsTest extends TestCase
{
    private UserRepository $userRepo;
    private array $adminUser;
    private array $normalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepo = new UserRepository();

        $this->adminUser = [
            'id'          => 1,
            'name'        => 'Admin User',
            'email'       => 'admin@test.com',
            'role'        => 'admin',
            'permissions' => null,
            'is_active'   => 1,
        ];

        $this->normalUser = [
            'id'          => 2,
            'name'        => 'Normal Sales User',
            'email'       => 'user@test.com',
            'role'        => 'user',
            'permissions' => null,
            'is_active'   => 1,
        ];
    }

    public function testAdminRoleHasFullAccessToAllModules(): void
    {
        $allPerms = array_keys(Permissions::ALL);
        foreach ($allPerms as $perm) {
            $this->assertTrue(
                Permissions::check($this->adminUser, $perm),
                "Admin must have permission: {$perm}"
            );
        }
    }

    public function testNormalUserRoleHasRestrictedAccessByDefault(): void
    {
        // Normal User must NOT have AI Lead Finder or Configuration
        $this->assertFalse(Permissions::check($this->normalUser, 'ai_lead_finder.view'));
        $this->assertFalse(Permissions::check($this->normalUser, 'ai_lead_finder.use'));
        $this->assertFalse(Permissions::check($this->normalUser, 'configuration.view'));
        $this->assertFalse(Permissions::check($this->normalUser, 'ai_settings.view'));
        $this->assertFalse(Permissions::check($this->normalUser, 'ai_settings.manage'));
        $this->assertFalse(Permissions::check($this->normalUser, 'integrations.view'));
        $this->assertFalse(Permissions::check($this->normalUser, 'users.manage'));

        // Normal User MUST have standard CRM business modules
        $this->assertTrue(Permissions::check($this->normalUser, 'dashboard.view'));
        $this->assertTrue(Permissions::check($this->normalUser, 'companies.view'));
        $this->assertTrue(Permissions::check($this->normalUser, 'contacts.view'));
        $this->assertTrue(Permissions::check($this->normalUser, 'leads.view'));
        $this->assertTrue(Permissions::check($this->normalUser, 'campaigns.view'));
        $this->assertTrue(Permissions::check($this->normalUser, 'followups.view'));
        $this->assertTrue(Permissions::check($this->normalUser, 'opportunities.view'));
        $this->assertTrue(Permissions::check($this->normalUser, 'email_composer.view'));
        $this->assertTrue(Permissions::check($this->normalUser, 'research.view'));
        $this->assertTrue(Permissions::check($this->normalUser, 'profile.view'));
    }

    public function testGranularPermissionOverrideForSpecificUser(): void
    {
        $customUser = $this->normalUser;
        $customUser['permissions'] = json_encode([
            'ai_lead_finder.view' => true,
            'ai_lead_finder.use'  => true,
        ]);

        // Explicitly granted
        $this->assertTrue(Permissions::check($customUser, 'ai_lead_finder.view'));
        $this->assertTrue(Permissions::check($customUser, 'ai_lead_finder.use'));

        // Still restricted for configuration
        $this->assertFalse(Permissions::check($customUser, 'configuration.view'));
        $this->assertFalse(Permissions::check($customUser, 'ai_settings.view'));
        $this->assertFalse(Permissions::check($customUser, 'integrations.view'));
    }

    public function testInactiveUserIsDeniedAllPermissions(): void
    {
        $inactive = $this->adminUser;
        $inactive['is_active'] = 0;
        $this->assertFalse(Permissions::check($inactive, 'dashboard.view'));
        $this->assertFalse(Permissions::check($inactive, 'companies.view'));
        $this->assertFalse(Permissions::check($inactive, 'ai_lead_finder.view'));
    }

    public function testWebPageAuthorizationMapping(): void
    {
        $this->assertEquals('ai_lead_finder.view', Permissions::PAGE_PERMISSIONS['ai-lead-finder']);
        $this->assertEquals('ai_settings.view', Permissions::PAGE_PERMISSIONS['ai-settings']);
        $this->assertEquals('integrations.view', Permissions::PAGE_PERMISSIONS['integrations']);
        $this->assertEquals('companies.view', Permissions::PAGE_PERMISSIONS['companies']);
        $this->assertEquals('users.manage', Permissions::PAGE_PERMISSIONS['users']);
    }

    public function testUserRepositoryCrudAndSafety(): void
    {
        $testEmail = 'perm_test_' . time() . '@shreelabel.com';
        $id = $this->userRepo->createUser([
            'name'        => 'Test Role User',
            'email'       => $testEmail,
            'password'    => 'secret123',
            'role'        => 'user',
            'is_active'   => 1,
            'permissions' => ['ai_lead_finder.view' => true],
        ]);
        $this->assertGreaterThan(0, $id);

        $fetched = $this->userRepo->find($id);
        $this->assertNotNull($fetched);
        $this->assertEquals('user', $fetched['role']);
        $this->assertTrue($fetched['computed_permissions']['ai_lead_finder.view']);
        $this->assertFalse($fetched['computed_permissions']['configuration.view']);

        // Update to Admin
        $this->userRepo->updateUser($id, ['role' => 'admin', 'permissions' => null]);
        $updated = $this->userRepo->find($id);
        $this->assertEquals('admin', $updated['role']);
        $this->assertTrue($updated['computed_permissions']['configuration.view']);

        // Clean up test user
        $this->userRepo->delete($id);
        $this->assertNull($this->userRepo->find($id));
    }
}
