<?php

namespace App\Http\Controllers\MemberApi;

use App\Http\Controllers\Controller;

/** Serves the member PWA shell + tenant-aware manifest (public, no auth). */
class PwaController extends Controller
{
    public function shell()
    {
        return view('tenant.pwa.app');
    }

    public function manifest()
    {
        return response()->view('tenant.pwa.manifest')
            ->header('Content-Type', 'application/manifest+json');
    }

    public function feed()
    {
        return view('tenant.pwa.feed', [
            'tenantId' => (string) tenant('id'),
            'reverb' => [
                // Sans Reverb (hébergement mutualisé) : clé nulle -> le fil
                // s'actualise périodiquement au lieu du temps réel.
                'key'    => config('broadcasting.default') === 'reverb' ? config('broadcasting.connections.reverb.key') : null,
                'host'   => config('broadcasting.connections.reverb.options.host'),
                'port'   => (int) config('broadcasting.connections.reverb.options.port', 443),
                'scheme' => config('broadcasting.connections.reverb.options.scheme', 'https'),
            ],
        ]);
    }
}
