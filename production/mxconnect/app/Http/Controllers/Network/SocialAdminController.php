<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Models\Social\SocialSetting;
use App\Models\Social\Sponsorship;
use App\Services\Social\SponsorService;
use App\Support\SocialLoad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Back-office du réseau : publications sponsorisées (validation des paiements)
 * et réglages du réseau social (mode allégé, tarifs). Rôles super_admin et moderator.
 */
class SocialAdminController extends Controller
{
    private const ROLES = ['super_admin', 'moderator'];

    public function sponsorships(Request $request)
    {
        $this->authorizeRole($request);
        SponsorService::closeExpired();
        $status = array_key_exists((string) $request->get('statut'), Sponsorship::STATUSES) ? $request->get('statut') : 'pending_payment';

        return view('network.social.sponsorships', [
            'status' => $status,
            'items'  => Sponsorship::where('status', $status)->with(['advertiser.account', 'post.mutual', 'post.account', 'currency'])->latest()->paginate(30)->withQueryString(),
            'counts' => Sponsorship::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function decide(Request $request, Sponsorship $sponsorship)
    {
        $this->authorizeRole($request);
        $data = $request->validate([
            'action' => ['required', Rule::in(['activer', 'refuser', 'pause', 'reprendre'])],
            'note'   => ['nullable', 'string', 'max:255'],
        ]);
        $s = $sponsorship;
        $new = match (true) {
            $data['action'] === 'activer' && in_array($s->status, ['pending_payment', 'rejected'], true) => 'active',
            $data['action'] === 'refuser' && in_array($s->status, ['pending_payment', 'active', 'paused'], true) => 'rejected',
            $data['action'] === 'pause' && $s->status === 'active' => 'paused',
            $data['action'] === 'reprendre' && $s->status === 'paused' => 'active',
            default => null,
        };
        abort_unless($new, 422, 'Action impossible pour cette campagne.');
        if ($new === 'rejected' && blank($data['note'] ?? null)) {
            return back()->withErrors(['note' => 'Indiquez le motif du refus : il est affiché à l’annonceur.']);
        }
        $update = ['status' => $new, 'admin_note' => ($data['note'] ?? null) ?: $s->admin_note];
        if ($data['action'] === 'activer') {
            $update += ['validated_by' => $request->user()->id, 'validated_at' => now()];
        }
        $s->update($update);
        Cache::forget('social:sponsor:active');

        $owner = $s->advertiser?->public_account_id;
        if ($owner && in_array($new, ['active', 'rejected'], true)) {
            app(\App\Services\Social\NotificationService::class)->notify($owner, 'sponsor',
                $new === 'active' ? "Votre campagne SP-{$s->id} est active" : "Votre campagne SP-{$s->id} a été refusée",
                $data['note'] ?? null, '/annonceur');
        }

        return back()->with('success', "Campagne SP-{$s->id} : " . $s->statusLabel() . '.');
    }

    public function settings(Request $request)
    {
        $this->authorizeRole($request);
        $cur = SponsorService::currency();

        return view('network.social.settings', [
            'light' => SocialSetting::get('light_mode') === '1', 'lightAuto' => SocialSetting::get('light_auto', '1') === '1',
            'autoUntil' => SocialLoad::autoUntil(), 'sponsorEnabled' => SponsorService::enabled(),
            'currency' => $cur, 'currencies' => Currency::where('active', true)->orderBy('code')->get(),
            'cpm' => SponsorService::cpmMinor() / (10 ** ($cur?->minor_unit ?? 0)),
            'minBudget' => SponsorService::minBudgetMinor() / (10 ** ($cur?->minor_unit ?? 0)),
            'instructions' => SocialSetting::get('sponsor_instructions'),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $this->authorizeRole($request);
        $data = $request->validate([
            'light_mode'      => ['nullable', 'boolean'],
            'light_auto'      => ['nullable', 'boolean'],
            'sponsor_enabled' => ['nullable', 'boolean'],
            'currency_id'     => ['required', 'integer', Rule::exists('central.currencies', 'id')],
            'cpm'             => ['required', 'numeric', 'min:0.01'],
            'min_budget'      => ['required', 'numeric', 'min:0'],
            'instructions'    => ['nullable', 'string', 'max:1000'],
        ]);
        $cur = Currency::findOrFail($data['currency_id']);
        SocialSetting::put('light_mode', $request->boolean('light_mode') ? '1' : '0');
        SocialSetting::put('light_auto', $request->boolean('light_auto') ? '1' : '0');
        SocialSetting::put('sponsor_enabled', $request->boolean('sponsor_enabled') ? '1' : '0');
        SocialSetting::put('sponsor_currency_id', $cur->id);
        SocialSetting::put('sponsor_cpm_minor', $cur->toMinor((float) $data['cpm']));
        SocialSetting::put('sponsor_min_budget_minor', $cur->toMinor((float) $data['min_budget']));
        SocialSetting::put('sponsor_instructions', $data['instructions'] ?: null);
        if (! $request->boolean('light_auto') || $request->boolean('reset_auto')) {
            Cache::forget('social:light_auto');
        }

        return back()->with('success', 'Réglages du réseau social enregistrés.');
    }

    private function authorizeRole(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(self::ROLES), 403);
    }
}
