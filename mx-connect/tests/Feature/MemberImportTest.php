<?php

use App\Models\Tenant\Member;
use App\Services\MemberImportService;

/** Module 11 — CSV member import: create, validate, dedupe. */

function writeCsv(string $body): string
{
    $path = tempnam(sys_get_temp_dir(), 'imp') . '.csv';
    file_put_contents($path, $body);
    return $path;
}

it('imports valid rows and reports them', function () {
    $csv = "first_name,last_name,birth_date,sex,phone,id_document,address\n"
         . "Awa,Njoya,1990-05-12,F,690000001,,Yaoundé\n"
         . "Paul,Biya,1985-02-13,M,690000002,,Mvomeka\n";

    $report = app(MemberImportService::class)->import(writeCsv($csv));

    expect($report['created'])->toBe(2)
        ->and($report['skipped'])->toBe(0)
        ->and(Member::count())->toBe(2);
})->group('tenant');

it('skips invalid rows without aborting the whole import', function () {
    $csv = "first_name,last_name,birth_date,sex,phone,id_document,address\n"
         . "Awa,Njoya,1990-05-12,F,690000001,,Yaoundé\n"
         . ",Missing,2000-01-01,F,,,\n"            // missing first_name
         . "Bad,Date,not-a-date,M,,,\n";           // invalid date

    $report = app(MemberImportService::class)->import(writeCsv($csv));

    expect($report['created'])->toBe(1)
        ->and($report['skipped'])->toBe(2)
        ->and(collect($report['rows'])->where('display', 'invalid')->count())->toBe(2);
})->group('tenant');

it('skips a strong duplicate (same phone) already present', function () {
    Member::create([
        'member_code' => 'ADH-26-AAAAA', 'first_name' => 'Awa', 'last_name' => 'Njoya',
        'birth_date' => '1990-05-12', 'sex' => 'F', 'phone' => '690000001',
        'status' => 'active', 'joined_at' => now()->toDateString(),
    ]);

    $csv = "first_name,last_name,birth_date,sex,phone,id_document,address\n"
         . "Awa,Njoya,1990-05-12,F,690000001,,Yaoundé\n";

    $report = app(MemberImportService::class)->import(writeCsv($csv));

    expect($report['created'])->toBe(0)
        ->and($report['skipped'])->toBe(1)
        ->and($report['rows'][0]['display'])->toBe('duplicate')
        ->and(Member::count())->toBe(1);
})->group('tenant');
