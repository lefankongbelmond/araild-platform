<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Http\Requests\LocaleRequest;
use App\Models\Central\Locale;

class LocaleController extends Controller
{
    public function index()
    {
        $this->authorize('view-config');
        return view('network.config.locales', ['locales' => Locale::orderBy('name')->get()]);
    }

    public function store(LocaleRequest $request)
    {
        Locale::create($request->validated() + ['active' => $request->boolean('active', true),
            'direction' => \App\Support\Locales::direction($request->input('code'))]);
        \App\Support\Locales::forgetCache();
        return back()->with('success', __('mxconnect.config.saved'));
    }

    public function update(LocaleRequest $request, Locale $locale)
    {
        $locale->update($request->validated());
        \App\Support\Locales::forgetCache();
        return back()->with('success', __('mxconnect.config.saved'));
    }

    public function toggle(Locale $locale)
    {
        $this->authorize('manage-config');
        // Le français est la langue source : toujours disponible (2026-11-27).
        if ($locale->code === \App\Support\Locales::SOURCE && $locale->active) {
            return back()->with('error', __('Le français est la langue de référence : il reste toujours activé.'));
        }
        $locale->update(['active' => ! $locale->active]);
        \App\Support\Locales::forgetCache();
        return back()->with('success', __('mxconnect.config.saved'));
    }
}
