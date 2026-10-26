<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** Demande de démonstration envoyée depuis la page d'accueil de la plateforme. */
class DemoRequest extends Model
{
    protected $connection = 'central';
    protected $guarded = [];

    public const RANGES = ['<1000' => 'Moins de 1 000', '1000-5000' => '1 000 à 5 000', '5000-20000' => '5 000 à 20 000', '>20000' => 'Plus de 20 000'];
    public const STATUSES = ['new' => 'Nouvelle', 'contacted' => 'Contactée', 'closed' => 'Clôturée'];
}
