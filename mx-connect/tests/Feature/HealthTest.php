<?php

/** Module 15 — health probe endpoint. */

it('returns an ok status with per-check detail', function () {
    $res = $this->getJson('/health');

    $res->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure(['status', 'checks' => ['app', 'db', 'cache'], 'time']);
})->group('central');
