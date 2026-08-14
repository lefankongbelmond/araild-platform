<?php

/** Module 17 — request correlation id propagation. */

it('sets an X-Request-Id header on the response', function () {
    $res = $this->get('/health');

    $res->assertHeader('X-Request-Id');
    expect($res->headers->get('X-Request-Id'))->not->toBeEmpty();
})->group('central');

it('honours an inbound X-Request-Id from upstream', function () {
    $incoming = 'trace-abc-123';

    $res = $this->withHeaders(['X-Request-Id' => $incoming])->get('/health');

    expect($res->headers->get('X-Request-Id'))->toBe($incoming);
})->group('central');
