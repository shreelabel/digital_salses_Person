<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Auth;
use SLC\Core\Database;
use SLC\Core\RateLimiter;
use SLC\Core\Security;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $this->bootSession();
        // ensure a clean auth state
        Auth::logout();
        // reset the seeded admin's password to a known hash
        $hash = Security::hashPassword('admin123');
        $u = Database::fetch("SELECT id FROM slc_users WHERE email='admin@shreelabel.com'");
        if ($u) {
            Database::update('slc_users', (int) $u['id'], ['password_hash' => $hash, 'is_active' => 1]);
        }
        RateLimiter::clear('admin@shreelabel.com');
    }

    public function testPasswordHashingUsesBcryptAndVerifies(): void
    {
        $hash = Security::hashPassword('secret');
        $this->assertTrue(str_starts_with($hash, '$2y$'));
        $this->assertTrue(Security::verifyPassword('secret', $hash));
        $this->assertFalse(Security::verifyPassword('wrong', $hash));
    }

    public function testPlaintextPasswordIsNeverStored(): void
    {
        $row = Database::fetch("SELECT password_hash FROM slc_users WHERE email='admin@shreelabel.com'");
        $this->assertNotNull($row);
        $this->assertTrue(str_starts_with($row['password_hash'], '$2y$'));
        $this->assertStringNotContains('admin123', $row['password_hash']);
    }

    public function testSuccessfulLoginSetsSession(): void
    {
        $res = Auth::attempt('admin@shreelabel.com', 'admin123');
        $this->assertTrue($res['ok']);
        $this->assertTrue(Auth::check());
        $this->assertEquals('admin@shreelabel.com', Auth::current()['email']);
    }

    public function testWrongPasswordFails(): void
    {
        $res = Auth::attempt('admin@shreelabel.com', 'nope');
        $this->assertFalse($res['ok']);
        $this->assertFalse(Auth::check());
    }

    public function testUnknownUserFails(): void
    {
        $res = Auth::attempt('ghost@nowhere.com', 'x');
        $this->assertFalse($res['ok']);
    }

    public function testRateLimitLocksOutAfterMaxAttempts(): void
    {
        $email = 'ratelimit@example.com';
        RateLimiter::clear($email);
        for ($i = 0; $i < 5; $i++) {
            Auth::attempt($email, 'wrong'); // recordFailure on each miss
        }
        $this->assertTrue(RateLimiter::isLockedOut($email));
    }

    public function testLogoutClearsSession(): void
    {
        Auth::attempt('admin@shreelabel.com', 'admin123');
        $this->assertTrue(Auth::check());
        Auth::logout();
        $this->assertFalse(Auth::check());
    }

    public function testChangePassword(): void
    {
        Auth::attempt('admin@shreelabel.com', 'admin123');
        $uid = Auth::id();
        $this->assertTrue(Auth::changePassword($uid, 'admin123', 'newpass123')['ok'] ?? false);
        // old password now fails
        Auth::logout();
        $this->assertFalse(Auth::attempt('admin@shreelabel.com', 'admin123')['ok']);
        $this->assertTrue(Auth::attempt('admin@shreelabel.com', 'newpass123')['ok']);
        // restore for other tests
        Auth::changePassword($uid, 'newpass123', 'admin123');
    }
}
