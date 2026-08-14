<?php

use App\Models\Tenant\Member;
use App\Services\DigitalCardService;

/** Module 6 — the card token is signed; forged or altered tokens fail. */

it('issues a token that verifies back to the member', function () {
    $member = Member::factory()->create();
    $svc = app(DigitalCardService::class);

    $token = $svc->issueToken($member);
    $result = $svc->verifyToken($token);

    expect($result['valid'])->toBeTrue()
        ->and($result['member_id'])->toBe($member->id);
})->group('tenant');

it('rejects a tampered token', function () {
    $member = Member::factory()->create();
    $svc = app(DigitalCardService::class);

    $token = $svc->issueToken($member);
    // Flip the signature segment.
    [$m, $id, $sig] = explode('.', $token);
    $forged = "{$m}.{$id}.deadbeefdeadbeefdeadbeefdeadbeef";

    expect($svc->verifyToken($forged)['valid'])->toBeFalse();
})->group('tenant');

it('rejects a malformed token', function () {
    expect(app(DigitalCardService::class)->verifyToken('garbage')['valid'])->toBeFalse();
})->group('tenant');
