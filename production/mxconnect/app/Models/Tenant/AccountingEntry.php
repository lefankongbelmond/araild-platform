<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

/**
 * Double-entry line: one debit account, one credit account, one amount.
 * With a single amount the entry is balanced by construction (debit == credit).
 */
class AccountingEntry extends Model implements Auditable
{
    use AuditableTrait;

    protected $guarded = [];
    protected $casts = ['amount_minor' => 'integer'];

    public function accountingDay() { return $this->belongsTo(AccountingDay::class); }
    public function debitAccount()  { return $this->belongsTo(Account::class, 'debit_account_id'); }
    public function creditAccount() { return $this->belongsTo(Account::class, 'credit_account_id'); }
}
