<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/** The foundation's controllers call $this->authorize(), so the trait is required here. */
abstract class Controller
{
    use AuthorizesRequests;
}
