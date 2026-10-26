<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class MemberNotification extends Model
{
    protected $guarded = [];

    public const AUDIENCES = [
        'member'    => 'Un adhérent',
        'all'       => 'Tous les adhérents actifs',
        'antenna'   => 'Les adhérents d’une antenne',
        'guarantee' => 'Les adhérents d’une garantie',
        'overdue'   => 'Les adhérents en retard de cotisation',
    ];

    public function recipientsRows() { return $this->hasMany(MemberNotificationRecipient::class); }
}
