<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/** Journal des recherches de bénéficiaires (nom ou téléphone) faites depuis le portail prestataire. */
class BeneficiarySearch extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];
    protected $casts = ['criteria' => 'array'];
}
