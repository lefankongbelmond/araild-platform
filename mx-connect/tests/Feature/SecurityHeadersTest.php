<?php

/** Module 16 — baseline security headers on responses. */

it('adds baseline security headers to responses', function () {
    $res = $this->get('/health');

    $res->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($res->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
})->group('central');
