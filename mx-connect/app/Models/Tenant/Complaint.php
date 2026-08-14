<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Complaint extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = [
        'received_on' => 'date',
        'due_on'      => 'date',
        'resolved_on' => 'date',
    ];

    public function member() { return $this->belongsTo(Member::class); }

    /** True when past the SLA due date and not yet resolved/closed. */
    public function isOverdue(): bool
    {
        return $this->due_on
            && ! in_array($this->status, ['resolved', 'closed'], true)
            && $this->due_on->isPast();
    }
}
