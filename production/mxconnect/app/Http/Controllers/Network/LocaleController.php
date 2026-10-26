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
        Locale::create($request->validated() + ['active' => $request->boolean('active', true)]);
        return back()->with('success', __('mxconnect.config.saved'));
    }

    public function update(LocaleRequest $request, Locale $locale)
    {
        $locale->update($request->validated());
        return back()->with('success', __('mxconnect.config.saved'));
    }

    public function toggle(Locale $locale)
    {
        $this->authorize('manage-config');
        $locale->update(['active' => ! $locale->active]);
        return back()->with('success', __('mxconnect.config.saved'));
    }
}
