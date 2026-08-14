<?php

use App\Models\Tenant\Complaint;

/** Module 13 — complaint SLA due date and overdue detection. */

it('flags a complaint as overdue past its due date when unresolved', function () {
    $overdue = Complaint::create([
        'ref' => 'RCL-26-AAAAA', 'channel' => 'phone', 'subject' => 'x',
        'status' => 'received', 'received_on' => now()->subDays(30)->toDateString(),
        'due_on' => now()->subDays(15)->toDateString(),
    ]);
    $onTime = Complaint::create([
        'ref' => 'RCL-26-BBBBB', 'channel' => 'phone', 'subject' => 'y',
        'status' => 'received', 'received_on' => now()->toDateString(),
        'due_on' => now()->addDays(15)->toDateString(),
    ]);
    $resolved = Complaint::create([
        'ref' => 'RCL-26-CCCCC', 'channel' => 'phone', 'subject' => 'z',
        'status' => 'resolved', 'received_on' => now()->subDays(30)->toDateString(),
        'due_on' => now()->subDays(15)->toDateString(), 'resolved_on' => now()->toDateString(),
    ]);

    expect($overdue->isOverdue())->toBeTrue()
        ->and($onTime->isOverdue())->toBeFalse()
        ->and($resolved->isOverdue())->toBeFalse();  // resolved is never overdue
})->group('tenant');
