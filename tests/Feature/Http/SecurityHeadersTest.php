<?php

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('security headers are present on responses', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

test('csp is not set in non-production environment', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    expect($response->headers->get('Content-Security-Policy'))->toBeNull();
    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
});

test('csp and hsts are set in production environment', function () {
    app()->detectEnvironment(fn () => 'production');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'");
});
