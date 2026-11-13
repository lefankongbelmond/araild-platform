<?php

namespace App\Models\AppCenter;

use Illuminate\Database\Eloquent\Model;

/** Compteur journalier anonyme (aucun compte, aucune adresse IP). */
class AppCenterStat extends Model
{
    protected $connection = 'central';
    protected $table = 'app_center_stats';
    protected $guarded = ['id'];
    public $timestamps = false;
    protected $casts = ['day' => 'date'];
}
