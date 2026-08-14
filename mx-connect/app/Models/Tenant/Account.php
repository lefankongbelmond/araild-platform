<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

/** SYSCOHADA chart-of-accounts line. */
class Account extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['class' => 'integer'];

    public static function byNumber(string $number): ?self
    {
        return static::where('number', $number)->first();
    }
}
