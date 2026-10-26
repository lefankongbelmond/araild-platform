<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

/**
 * « Signaler un incident ou un risque » : ouvert à TOUT le personnel (matrice des droits).
 * Les registres eux-mêmes restent réservés à l'administrateur, au contrôleur et à l'auditeur ;
 * les formulaires envoient vers incidents.store / risks.store.
 */
class SignalController extends Controller
{
    public function create()
    {
        return view('tenant.signal.create');
    }
}
