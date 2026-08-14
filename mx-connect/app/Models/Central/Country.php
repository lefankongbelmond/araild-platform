<?php

namespace App\Models\Central;

use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

use Illuminate\Database\Eloquent\Model;

class Country extends Model implements Auditable
{
    use AuditableTrait;

    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = ['active' => 'boolean'];

    public function defaultCurrency() { return $this->belongsTo(Currency::class, 'default_currency_id'); }
    public function defaultLocale()   { return $this->belongsTo(Locale::class, 'default_locale_id'); }
    public function paymentProviders()
    {
        return $this->belongsToMany(PaymentProvider::class, 'country_payment_provider')->withPivot('active');
    }
}
