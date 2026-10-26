<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMutualRequest;
use App\Models\Central\Country;
use App\Models\Central\Currency;
use App\Models\Central\Locale;
use App\Models\Central\Mutual;
use App\Services\MutualProvisioningService;
use Illuminate\Http\Request;

class MutualController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Mutual::class);

        $mutuals = Mutual::with(['country', 'currency'])
            // Les pages créées par les membres du réseau social se gèrent dans « Modération ».
            ->whereDoesntHave('profile', fn ($p) => $p->whereNotNull('owner_account_id'))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('network.mutuals.index', compact('mutuals'));
    }

    public function create()
    {
        $this->authorize('create', Mutual::class);

        return view('network.mutuals.create', [
            'countries'  => Country::where('active', true)->orderBy('name')->get(),
            'currencies' => Currency::where('active', true)->orderBy('code')->get(),
            'locales'    => Locale::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreMutualRequest $request, MutualProvisioningService $provisioner)
    {
        // Creating the Mutual fires the tenancy pipeline: create DB -> migrate -> seed.
        $data = $request->validated();
        $admin = array_filter([
            'email' => $data['admin_email'] ?? null,
            'first' => $data['admin_first_name'] ?? null,
            'last'  => $data['admin_last_name'] ?? null,
        ]);
        unset($data['admin_email'], $data['admin_first_name'], $data['admin_last_name']);

        try {
            $mutual = $provisioner->create($data);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput($request->except('db_password'))
                ->withErrors(['provisioning' => 'Création impossible : ' . $e->getMessage()]);
        }

        $redirect = redirect()
            ->route('network.mutuals.index')
            ->with('success', __('mxconnect.mutual.created', ['name' => $mutual->name]));

        if (! empty($admin['email'])) {
            try {
                $password = $provisioner->createUser($mutual, $admin['email'],
                    $admin['first'] ?? 'Admin', $admin['last'] ?? $mutual->name);
                $domain = optional($mutual->domains()->first())->domain;
                $redirect->with('warning', "Administrateur créé : {$admin['email']} · mot de passe (à noter, affiché une seule fois) : {$password}"
                    . " · connexion : https://{$domain}/connexion");
            } catch (\Throwable $e) {
                report($e);
                $redirect->with('error', "Mutuelle créée, mais l'administrateur n'a pas pu l'être : " . $e->getMessage());
            }
        }

        return $redirect;
    }

    public function approve(Request $request, Mutual $mutual, MutualProvisioningService $provisioner)
    {
        $this->authorize('approve', $mutual);

        $provisioner->approve($mutual, $request->user('network')->id);

        return back()->with('success', __('mxconnect.mutual.approved', ['name' => $mutual->name]));
    }

    public function suspend(Request $request, Mutual $mutual)
    {
        $this->authorize('suspend', $mutual);

        $mutual->update(['status' => 'suspended']);

        return back()->with('success', __('mxconnect.mutual.suspended', ['name' => $mutual->name]));
    }
}
