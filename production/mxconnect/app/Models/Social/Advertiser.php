<?php

namespace App\Models\Social;

use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;

/** Compte annonceur : permet de sponsoriser des publications. */
class Advertiser extends Model
{
    protected $connection = 'central';
    protected $table = 'social_advertisers';
    protected $guarded = [];

    public function account()      { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
    public function sponsorships() { return $this->hasMany(Sponsorship::class, 'advertiser_id'); }
}
