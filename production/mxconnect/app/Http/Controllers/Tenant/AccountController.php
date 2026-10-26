<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** « Mon mot de passe » : chaque membre de l'équipe change son mot de passe (obligatoire après un mot de passe provisoire). */
class AccountController extends Controller
{
    public function edit(Request $request)
    {
        return view('tenant.account.password', ['forced' => (bool) ($request->user()->must_change_password ?? false)]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], [
            'password.confirmed' => 'Les deux nouveaux mots de passe ne correspondent pas.',
            'password.min'       => 'Le mot de passe doit contenir au moins 10 caractères.',
        ]);
        if (! Hash::check($request->input('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => 'Mot de passe actuel incorrect.']);
        }
        if (Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Choisissez un mot de passe différent de l’actuel.']);
        }
        $user->forceFill(['password' => $request->input('password')] + (TeamService::hasTeamColumns() ? ['must_change_password' => false] : []))->save();
        $request->session()->regenerate();

        return redirect()->route('members.index')->with('success', 'Votre mot de passe a été changé.');
    }
}
