<?php

namespace Tests\Feature;

use App\Models\Core\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Regression test for the "log out twice" bug.
 *
 * Every login in this app supports remember-me, and each guard keeps its own
 * recaller cookie. Logging out a single guard left the other guards'
 * remember cookies alive, so the next request silently re-authenticated via
 * recaller and the user appeared to still be logged in.
 *
 * DB-free on purpose: the repo migrations are MySQL-specific (ENUM/MODIFY),
 * so sqlite-based RefreshDatabase cannot run here. Logout only touches the
 * session and cookies for in-memory users, which is exactly what this
 * exercises.
 */
class LogoutEveryGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The Inertia share middleware queries employee permission tables on
        // every request — irrelevant to logout and unavailable without a
        // migrated database, so it is disabled for these tests.
        $this->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class);
    }
    public function test_main_logout_forgets_foreign_guard_remember_cookies(): void
    {
        $user = new User();

        // Live web-guard session + a lingering client-guard remember cookie
        // (e.g. the same browser previously used the client portal). Sent
        // encrypted like a real browser cookie so it survives the
        // EncryptCookies middleware and is visible to the guard.
        $clientRecaller = Auth::guard('client')->getRecallerName();

        $response = $this
            ->actingAs($user)
            ->withCookie($clientRecaller, 'stale-id|stale-token')
            ->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest('web');

        // The foreign recaller must be expired — otherwise the next request
        // resurrects the session and the user has to log out a second time.
        $response->assertCookieExpired($clientRecaller);
    }

    public function test_main_logout_clears_all_guards(): void
    {
        $user = new User();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest('web');
        $this->assertGuest('client');
        $this->assertGuest('supplier');
        $this->assertGuest('applicant');
        $this->assertGuest();
    }
}
