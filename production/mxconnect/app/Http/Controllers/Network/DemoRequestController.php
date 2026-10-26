<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Central\DemoRequest;
use Illuminate\Http\Request;

/** Demandes de démonstration reçues depuis la page d'accueil (super-admin uniquement). */
class DemoRequestController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        return view('network.demos.index', ['items' => DemoRequest::orderByRaw("status = 'new' desc")->latest()->paginate(30)]);
    }

    public function status(Request $request, DemoRequest $demo)
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
        $demo->update($request->validate(['status' => ['required', 'in:new,contacted,closed']]));

        return back()->with('success', 'Demande mise à jour.');
    }
}
