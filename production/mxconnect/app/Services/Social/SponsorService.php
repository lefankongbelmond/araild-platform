<?php

namespace App\Services\Social;

use App\Models\Central\Currency;
use App\Models\Central\PublicAccount;
use App\Models\Social\Post;
use App\Models\Social\SocialSetting;
use App\Models\Social\Sponsorship;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Publications sponsorisées : tarifs, diffusion dans les fils, comptage.
 *
 * Diffusion : au plus UNE publication sponsorisée par page de fil (après la 3e
 * publication), choisie parmi les campagnes actives qui ciblent ce visiteur,
 * et vue au plus 3 fois par jour par la même personne. Chaque affichage est
 * compté ; la campagne s'arrête d'elle-même à la fin de son budget ou de sa période.
 */
class SponsorService
{
    public const DAILY_CAP = 3;

    public static function enabled(): bool
    {
        return SocialSetting::get('sponsor_enabled', '1') === '1';
    }

    public static function currency(): ?Currency
    {
        return Currency::find((int) SocialSetting::get('sponsor_currency_id', 0)) ?? Currency::where('code', 'XAF')->first() ?? Currency::first();
    }

    /** Prix pour 1 000 affichages, en unités mineures de la devise. */
    public static function cpmMinor(): int
    {
        $cur = self::currency();

        return (int) SocialSetting::get('sponsor_cpm_minor', $cur ? $cur->toMinor(1000) : 1000);
    }

    public static function minBudgetMinor(): int
    {
        $cur = self::currency();

        return (int) SocialSetting::get('sponsor_min_budget_minor', $cur ? $cur->toMinor(5000) : 5000);
    }

    public static function instructions(): string
    {
        return (string) SocialSetting::get('sponsor_instructions',
            'Les instructions de paiement n’ont pas encore été publiées par l’administration du réseau. Votre demande est enregistrée : vous serez contacté(e).');
    }

    /** Une publication sponsorisée à afficher à ce visiteur, ou null. */
    public function pick(?PublicAccount $viewer, array $excludePostIds = []): ?Sponsorship
    {
        if (! self::enabled() || ! \Illuminate\Support\Facades\Schema::connection('central')->hasTable('social_sponsorships')) {
            return null;
        }
        $today = now()->toDateString();
        $who = $viewer ? 'u' . $viewer->id : 'ip' . sha1((string) request()->ip());

        $candidates = Cache::remember('social:sponsor:active', 60, fn () => Sponsorship::where('status', 'active')
            ->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)
            ->whereColumn('impressions', '<', 'max_impressions')
            ->get(['id', 'post_id', 'target_city', 'target_interest']));

        $eligible = $candidates->filter(function ($s) use ($viewer, $excludePostIds, $who, $today) {
            if (in_array($s->post_id, $excludePostIds, true)) {
                return false;
            }
            if ($s->target_city && (! $viewer || mb_strtolower(trim((string) $viewer->city)) !== mb_strtolower(trim($s->target_city)))) {
                return false;
            }
            if ($s->target_interest && (! $viewer || ! in_array($s->target_interest, (array) $viewer->interests, true))) {
                return false;
            }

            return (int) Cache::get("social:sponsor:seen:{$s->id}:{$who}:{$today}", 0) < self::DAILY_CAP;
        });
        if ($eligible->isEmpty()) {
            return null;
        }

        foreach ($eligible->shuffle() as $choice) {
            $s = Sponsorship::with(['currency', 'post' => fn ($q) => $q->with(FeedService::WITH)])->find($choice->id);
            $post = $s?->post;
            // La publication doit toujours être publique et visible (pas masquée, pas supprimée, auteur non suspendu).
            if (! $post || $post->trashed() || ! Post::query()->publicFeed()->whereKey($post->id)->exists()) {
                continue;
            }
            $key = "social:sponsor:seen:{$s->id}:{$who}:{$today}";
            Cache::put($key, (int) Cache::get($key, 0) + 1, now()->endOfDay());
            $this->countImpression($s);

            return $s;
        }

        return null;
    }

    public function countImpression(Sponsorship $s): void
    {
        DB::connection('central')->table('social_sponsorships')->where('id', $s->id)->increment('impressions');
        $s->impressions++;
        if ($s->impressions >= $s->max_impressions) {
            Sponsorship::whereKey($s->id)->where('status', 'active')->update(['status' => 'completed']);
            Cache::forget('social:sponsor:active');
        }
    }

    /** Termine les campagnes dont la période est passée. */
    public static function closeExpired(): void
    {
        if (Sponsorship::where('status', 'active')->where('ends_on', '<', now()->toDateString())->update(['status' => 'completed'])) {
            Cache::forget('social:sponsor:active');
        }
    }
}
