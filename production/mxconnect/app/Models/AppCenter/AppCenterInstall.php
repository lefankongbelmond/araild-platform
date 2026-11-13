<?php

namespace App\Models\AppCenter;

use Illuminate\Database\Eloquent\Model;

/** Installation confirmée, identifiée par l'empreinte d'un identifiant aléatoire de l'appareil. */
class AppCenterInstall extends Model
{
    protected $connection = 'central';
    protected $table = 'app_center_installs';
    protected $guarded = ['id'];
    protected $casts = ['installed_at' => 'datetime', 'last_launch_at' => 'datetime'];
}
