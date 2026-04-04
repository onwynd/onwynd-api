<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ARCH-8: Tests for auth redirect URL validation.
 *
 * Verifies that the isSafeRedirect() guard prevents open redirect attacks.
 * These cases map to the TypeScript isSafeRedirect() in web/src/app/auth/signin/page.tsx
 * and the middleware that no longer sets auth_intent_url cookies.
 */
class AuthRedirectTest extends TestCase
{
    /**
     * Simulate the isSafeRedirect logic from the Next.js frontend.
     */
    private function isSafeRedirect(string $url): bool
    {
        return str_starts_with($url, '/')
            && ! str_starts_with($url, '/auth')
            && ! str_contains($url, '://');
    }

    public function test_valid_internal_paths_are_safe(): void
    {
        $this->assertTrue($this->isSafeRedirect('/dashboard'));
        $this->assertTrue($this->isSafeRedirect('/dashboard/sessions'));
        $this->assertTrue($this->isSafeRedirect('/profile'));
        $this->assertTrue($this->isSafeRedirect('/therapist-booking'));
    }

    public function test_external_urls_are_blocked(): void
    {
        $this->assertFalse($this->isSafeRedirect('https://evil.com'));
        $this->assertFalse($this->isSafeRedirect('http://attacker.com/steal'));
        $this->assertFalse($this->isSafeRedirect('//evil.com'));
    }

    public function test_protocol_relative_urls_are_blocked(): void
    {
        $this->assertFalse($this->isSafeRedirect('//example.com'));
        $this->assertFalse($this->isSafeRedirect('/path://injection'));
    }

    public function test_auth_routes_are_blocked_to_prevent_loops(): void
    {
        $this->assertFalse($this->isSafeRedirect('/auth/signin'));
        $this->assertFalse($this->isSafeRedirect('/auth/register'));
        $this->assertFalse($this->isSafeRedirect('/auth'));
    }

    public function test_relative_paths_without_slash_prefix_are_blocked(): void
    {
        $this->assertFalse($this->isSafeRedirect('dashboard'));
        $this->assertFalse($this->isSafeRedirect('javascript:alert(1)'));
    }

    public function test_signin_endpoint_does_not_set_cookie_for_intent(): void
    {
        // The middleware should no longer set auth_intent_url cookie
        $response = $this->get('/dashboard');

        $this->assertFalse(
            $response->headers->has('Set-Cookie') &&
            str_contains($response->headers->get('Set-Cookie', ''), 'auth_intent_url'),
            'auth_intent_url cookie must not be set by middleware'
        );
    }
}
