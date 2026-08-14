<?php
declare(strict_types=1);

namespace SLC\Tests;

use SLC\Core\Auth;
use SLC\Core\CSRF;

/**
 * Validates the auth/CSRF guards that protect routes — without an HTTP server.
 * These assert the decision logic the API router relies on.
 */
class ProtectedRouteTest extends TestCase
{
    public function testNoSessionMeansNotAuthenticated(): void
    {
        $this->bootSession();
        Auth::logout();
        $this->assertFalse(Auth::check());
        // protected API endpoints would respond 401 in this state
    }

    public function testSessionEstablishesAuth(): void
    {
        $this->bootSession();
        Auth::logout();
        $res = Auth::attempt('admin@shreelabel.com', 'admin123');
        $this->assertTrue($res['ok']);
        $this->assertTrue(Auth::check());
        $this->assertNotNull(Auth::id());
    }

    public function testStateChangingRequestsNeedCsrf(): void
    {
        $this->bootSession();
        CSRF::token();
        // a valid token passes
        $this->assertTrue(CSRF::check(CSRF::token()));
        // a missing token would be rejected (returns false)
        $this->assertFalse(CSRF::check(''));
    }

    public function testInactiveUserCannotAuthenticate(): void
    {
        $this->bootSession();
        Auth::logout();
        \SLC\Core\Database::update('slc_users', 1, ['is_active' => 0]);
        $res = Auth::attempt('admin@shreelabel.com', 'admin123');
        $this->assertFalse($res['ok']);
        \SLC\Core\Database::update('slc_users', 1, ['is_active' => 1]);
    }
}
