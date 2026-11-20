<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\AccountingRefusal;
use App\Services\TestDataResetService;
use App\Support\TestEnvironment;
use Illuminate\Http\Request;

/**
 * MUTUELLE TEST — réinitialisation par l'administrateur de la mutuelle (2026-11-22).
 * N'existe que dans une mutuelle marquée « test » par le super-administrateur (sinon 404).
 */
class TestEnvironmentController extends Controller
{
    public function index(TestDataResetService $resets)
    {
        abort_unless(TestEnvironment::active(), 404);

        return view('tenant.test-env.index', ['inventory' => $resets->inventory(tenant()), 'mutual' => tenant()]);
    }

    public function reset(Request $request, TestDataResetService $resets)
    {
        abort_unless(TestEnvironment::active(), 404);
        $data = $request->validate([
            'confirm'  => ['required', 'string', 'max:80'],
            'note'     => ['required', 'string', 'min:5', 'max:300'],
            'formulas' => ['nullable', 'boolean'],
            'examples' => ['nullable', 'boolean'],
        ], ['confirm.required' => __('Saisissez l’identifiant de la mutuelle pour confirmer.'), 'note.required' => __('Indiquez le motif.')]);
        $user = $request->user();
        try {
            $r = $resets->reset(tenant(), $data['confirm'], $data['note'], ['formulas' => (bool) ($data['formulas'] ?? false), 'examples' => (bool) ($data['examples'] ?? false)],
                null, trim($user->first_name . ' ' . $user->last_name) . ' (administrateur de la mutuelle)');
        } catch (AccountingRefusal $e) {
            return back()->withErrors(['confirm' => $e->getMessage()])->withInput();
        }

        return redirect()->route('test-env.index')->with('success', __('Données de test réinitialisées : :count ligne(s) effacée(s)', ['count' => $r['deleted']])
            . ($r['examples'] ? __(', :count formule(s) d’exemple rechargée(s)', ['count' => $r['examples']]) : '') . '. ' . __('Sauvegarde préalable : :file.', ['file' => $r['backup']]));
    }
}
