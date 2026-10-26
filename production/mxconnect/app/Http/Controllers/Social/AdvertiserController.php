<?php

namespace App\Http\Controllers\Social;

use App\Models\Social\Advertiser;
use App\Models\Social\Post;
use App\Models\Social\Sponsorship;
use App\Services\Social\SponsorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Compte annonceur et campagnes de sponsorisation.
 *
 * La plateforme n'encaisse rien : l'annonceur paie selon les instructions
 * publiées par l'administration du réseau (Mobile Money, virement…), indique la
 * référence de son paiement, et l'administration ACTIVE la campagne après
 * vérification. Aucun paiement n'est simulé.
 */
class AdvertiserController extends SocialController
{
    public function index()
    {
        $adv = $this->advertiser();
        SponsorService::closeExpired();

        return view('social.ads.index', [
            'advertiser' => $adv,
            'campaigns'  => $adv ? $adv->sponsorships()->with(['post.mutual', 'currency'])->latest()->get() : collect(),
            'instructions' => SponsorService::instructions(),
        ]);
    }

    /** Ouvre (ou met à jour) le compte annonceur. */
    public function save(Request $request)
    {
        $viewer = $this->viewer();
        $data = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:160'],
            'organisation' => ['nullable', 'string', 'max:160'],
            'phone'        => ['required', 'string', 'max:40'],
            'email'        => ['nullable', 'email:rfc', 'max:180'],
            'city'         => ['nullable', 'string', 'max:120'],
            'accept'       => [$this->advertiser() ? 'nullable' : 'accepted'],
        ], ['accept.accepted' => 'Merci d’accepter les règles de la publicité sur MX-CONNECT.']);
        unset($data['accept']);
        Advertiser::updateOrCreate(['public_account_id' => $viewer->id], $data);

        $back = $request->input('retour');

        return ($back && str_starts_with($back, '/') && ! str_starts_with($back, '//'))
            ? redirect($back)->with('ok', 'Compte annonceur enregistré.')
            : redirect()->route('social.ads')->with('ok', 'Compte annonceur enregistré. Vous pouvez sponsoriser vos publications.');
    }

    public function create(int $post)
    {
        $post = $this->sponsorablePost($post);
        if (! $this->advertiser()) {
            return redirect()->route('social.ads', ['retour' => route('social.sponsor.create', $post->id, false)])
                ->with('info', 'Ouvrez d’abord votre compte annonceur (une seule fois).');
        }

        return view('social.ads.create', [
            'post' => $post, 'currency' => SponsorService::currency(), 'cpm' => SponsorService::cpmMinor(),
            'minBudget' => SponsorService::minBudgetMinor(), 'interests' => AccountController::INTERESTS,
            'instructions' => SponsorService::instructions(),
        ]);
    }

    public function store(Request $request, int $post)
    {
        $post = $this->sponsorablePost($post);
        $adv = $this->advertiser();
        abort_unless($adv && $adv->status === 'active', 403, 'Compte annonceur inactif.');
        $cur = SponsorService::currency();
        $min = SponsorService::minBudgetMinor();
        // « 10 000 » ou « 10000,50 » : espaces et virgule décimale acceptés.
        $request->merge(['budget' => str_replace([' ', "\u{202F}", "\u{00A0}", ','], ['', '', '', '.'], (string) $request->input('budget'))]);
        $data = $request->validate([
            'budget'          => ['required', 'numeric', 'min:0'],
            'days'            => ['required', 'integer', 'min:1', 'max:60'],
            'starts_on'       => ['required', 'date', 'after_or_equal:today', 'before:+90 days'],
            'target_city'     => ['nullable', 'string', 'max:120'],
            'target_interest' => ['nullable', Rule::in(array_keys(AccountController::INTERESTS))],
            'cta'             => ['required', Rule::in(array_keys(Sponsorship::CTA))],
        ]);
        $budget = $cur->toMinor((float) $data['budget']);
        if ($budget < $min) {
            return back()->withInput()->withErrors(['budget' => 'Budget minimum : ' . $cur->format($min) . '.']);
        }
        if ($adv->sponsorships()->whereIn('status', ['pending_payment', 'active', 'paused'])->count() >= 10) {
            return back()->withInput()->withErrors(['budget' => '10 campagnes en cours maximum.']);
        }
        $cpm = SponsorService::cpmMinor();
        $start = \Illuminate\Support\Carbon::parse($data['starts_on'])->startOfDay();

        $s = Sponsorship::create([
            'advertiser_id' => $adv->id, 'post_id' => $post->id, 'currency_id' => $cur->id,
            'budget_minor' => $budget, 'cpm_minor' => $cpm, 'max_impressions' => max(1, intdiv($budget * 1000, max(1, $cpm))),
            'target_city' => $data['target_city'] ?? null, 'target_interest' => $data['target_interest'] ?? null,
            'cta_label' => $data['cta'], 'starts_on' => $start, 'ends_on' => $start->copy()->addDays((int) $data['days'] - 1),
            'status' => 'pending_payment',
        ]);

        return redirect()->route('social.ads')->with('ok', "Demande de sponsorisation n° SP-{$s->id} enregistrée. Payez selon les instructions puis indiquez la référence du paiement : la campagne sera activée après vérification.");
    }

    /** L'annonceur indique la référence de son paiement. */
    public function payment(Request $request, int $id)
    {
        $s = $this->own($id);
        abort_unless($s->status === 'pending_payment', 422, 'Cette campagne n’attend plus de paiement.');
        $data = $request->validate(['payment_reference' => ['required', 'string', 'min:4', 'max:120']],
            ['payment_reference.required' => 'Indiquez la référence de la transaction (ex. identifiant Mobile Money).']);
        $s->update(['payment_reference' => $data['payment_reference']]);

        return back()->with('ok', 'Référence enregistrée. L’administration du réseau va vérifier le paiement et activer la campagne.');
    }

    /** Pause, reprise ou annulation par l'annonceur. */
    public function action(Request $request, int $id)
    {
        $s = $this->own($id);
        $action = $request->validate(['action' => ['required', Rule::in(['pause', 'reprendre', 'annuler'])]])['action'];
        $new = match (true) {
            $action === 'pause' && $s->status === 'active'                  => 'paused',
            $action === 'reprendre' && $s->status === 'paused'              => 'active',
            $action === 'annuler' && $s->status === 'pending_payment'       => 'cancelled',
            default                                                         => null,
        };
        abort_unless($new, 422, 'Action impossible pour cette campagne.');
        $s->update(['status' => $new]);
        Cache::forget('social:sponsor:active');

        return back()->with('ok', 'Campagne mise à jour : ' . $s->statusLabel() . '.');
    }

    /** Clic sur le bouton d'une publication sponsorisée (compté, puis redirection). */
    public function click(int $id)
    {
        $s = Sponsorship::with('post.mutual')->findOrFail($id);
        Sponsorship::whereKey($s->id)->increment('clicks');
        $post = $s->post;
        abort_unless($post && ! $post->trashed(), 404);
        $target = match ($s->cta_label) {
            'suivre', 'contacter', 'adherer' => $post->mutual ? route('social.mutual', [$post->mutual->slug] + ($s->cta_label === 'contacter' ? ['onglet' => 'contact'] : [])) : route('social.post', $post),
            default => $post->link_url ?: route('social.post', $post),
        };

        return redirect()->away($target);
    }

    // ---

    private function advertiser(): ?Advertiser
    {
        return Advertiser::where('public_account_id', $this->viewer()->id)->first();
    }

    private function own(int $id): Sponsorship
    {
        $adv = $this->advertiser();
        abort_unless($adv, 404);

        return $adv->sponsorships()->findOrFail($id);
    }

    /** Publication publique de l'annonceur, ou d'une page qu'il gère. */
    private function sponsorablePost(int $id): Post
    {
        $post = Post::query()->publicFeed()->with(\App\Services\Social\FeedService::WITH)->findOrFail($id);
        abort_unless($post->canManage($this->viewer()), 403, 'Vous ne pouvez sponsoriser que vos publications ou celles de vos pages.');

        return $post;
    }
}
