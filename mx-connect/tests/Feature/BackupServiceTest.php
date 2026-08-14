<?php

use App\Models\Central\DrTest;
use App\Services\BackupService;

/** Module 16 — backup runner records a DR-log entry with a derived outcome. */

it('records a success when every database dumps cleanly', function () {
    $service = new BackupService(fn (string $conn, string $path) => true);

    $drill = $service->run();

    expect($drill)->toBeInstanceOf(DrTest::class)
        ->and($drill->type)->toBe('backup_restore')
        ->and($drill->outcome)->toBe('success')
        ->and($drill->performed_by)->toContain('system')
        ->and(DrTest::count())->toBe(1);
})->group('central');

it('records a failure when the dumper fails for everything', function () {
    $service = new BackupService(fn (string $conn, string $path) => false);

    $drill = $service->run();

    expect($drill->outcome)->toBe('failed')
        ->and($drill->findings)->toContain('Failed');
})->group('central');
