<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Laravel 11+ ne fournit plus ces traits par défaut : sans eux, chaque
 * $this->authorize() du back-office réseau provoquait une erreur 500.
 */
abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;
}
