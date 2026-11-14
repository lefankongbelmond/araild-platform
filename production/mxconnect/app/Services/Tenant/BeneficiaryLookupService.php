<?php

namespace App\Services\Tenant;

use App\Models\Tenant\BeneficiarySearch;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Member;
use App\Models\Tenant\User;
use App\Services\DigitalCardService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Identification d'un bénéficiaire depuis le portail prestataire (Portail V2, phase 3, 2026-11-17).
 *
 *  - Par la CARTE (code adhérent imprimé ou QR signé) : identification directe.
 *  - Par RECHERCHE ENCADRÉE, pour une personne sans sa carte :
 *      • nom + date de naissance complète (prénom facultatif) — adhérents ET ayants droit ;
 *      • téléphone (8 derniers chiffres au moins) + année de naissance — adhérents.
 *    Deux critères toujours exigés, 5 résultats au plus, code adhérent masqué, chaque recherche
 *    journalisée (beneficiary_searches) et limitée en fréquence (route). Le choix d'un résultat
 *    passe par un jeton chiffré, propre à la personne connectée et valable 15 minutes : impossible
 *    de parcourir le fichier des adhérents en devinant des numéros.
 */
class BeneficiaryLookupService
{
    public const MAX_RESULTS = 5;
    private const PICK_MINUTES = 15;

    public function __construct(private DigitalCardService $cards) {}

    /** @return array{member:?Member, method:string, invalid_card:bool} */
    public function identify(string $input): array
    {
        $input = trim($input);
        if (preg_match('~[?&]t=([^&\s]+)~', $input, $m) || substr_count($input, '.') === 2) {
            $token = isset($m[1]) ? urldecode($m[1]) : $input;
            $res = $this->cards->verifyToken($token);

            return ['member' => $res['valid'] ? Member::find($res['member_id']) : null, 'method' => 'qr', 'invalid_card' => ! $res['valid']];
        }

        return ['member' => Member::where('member_code', $input)->first(), 'method' => 'code', 'invalid_card' => false];
    }

    /**
     * @param array{last_name?:string, first_name?:string, birth_date?:string, phone?:string, birth_year?:int} $c
     * @return array<int,array{type:string, name:string, birth_year:int, quality:string, code:string, status:string, pick:string}>
     */
    public function search(string $mode, array $c, User $user, int $providerId): array
    {
        $results = collect();
        if ($mode === 'name') {
            $last = trim($c['last_name']);
            $first = trim((string) ($c['first_name'] ?? ''));
            $byName = fn ($q) => $q->where('last_name', $last)->whereDate('birth_date', $c['birth_date'])
                ->when($first !== '', fn ($w) => $w->where('first_name', 'like', addcslashes($first, '%_\\') . '%'));
            $results = $results
                ->merge(Member::where($byName)->limit(self::MAX_RESULTS + 1)->get()->map(fn ($m) => $this->row($m, null, $user, $providerId)))
                ->merge(Dependent::with('member')->where($byName)->limit(self::MAX_RESULTS + 1)->get()
                    ->filter(fn ($d) => $d->member)->map(fn ($d) => $this->row($d->member, $d, $user, $providerId)));
        } else {
            $digits = substr(preg_replace('/\D+/', '', (string) $c['phone']), -8);
            // Colonne seule dans le SQL brut (compatible avec les tables préfixées).
            $results = Member::whereRaw("replace(replace(replace(replace(phone, ' ', ''), '-', ''), '.', ''), '+', '') like ?", ['%' . $digits])
                ->whereYear('birth_date', (int) $c['birth_year'])->limit(self::MAX_RESULTS + 1)->get()
                ->map(fn ($m) => $this->row($m, null, $user, $providerId));
        }

        $tooMany = $results->count() > self::MAX_RESULTS;
        BeneficiarySearch::create([
            'user_id' => $user->id, 'care_provider_id' => $providerId, 'mode' => $mode,
            'criteria' => array_filter(['last_name' => $c['last_name'] ?? null, 'first_name' => $c['first_name'] ?? null,
                'birth_date' => $c['birth_date'] ?? null, 'phone' => $c['phone'] ?? null, 'birth_year' => $c['birth_year'] ?? null]),
            'results' => $results->count(), 'created_at' => now(),
        ]);

        // Trop de résultats : on n'en montre aucun (préciser la recherche), pour ne pas exposer une liste.
        return $tooMany ? ['too_many' => true] : $results->values()->all();
    }

    /** @return array{member:Member, dependent:?Dependent}|null */
    public function pick(string $token, User $user, int $providerId): ?array
    {
        try {
            $p = json_decode(Crypt::decryptString($token), true, 4, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }
        if (($p['u'] ?? null) !== $user->id || ($p['p'] ?? null) !== $providerId || ($p['x'] ?? 0) < now()->timestamp) {
            return null;
        }
        $member = Member::find($p['m'] ?? 0);
        $dependent = ! empty($p['d']) ? Dependent::where('member_id', $member?->id)->find($p['d']) : null;
        if (! $member || (! empty($p['d']) && ! $dependent)) {
            return null;
        }

        return ['member' => $member, 'dependent' => $dependent];
    }

    public static function hasSearchTable(): bool
    {
        return Schema::hasTable('beneficiary_searches');
    }

    private function row(Member $m, ?Dependent $d, User $user, int $providerId): array
    {
        $person = $d ?? $m;
        $rel = ['spouse' => 'conjoint(e)', 'child' => 'enfant', 'ascendant' => 'ascendant', 'other' => 'autre'];
        $code = (string) $m->member_code;

        return [
            'type' => $d ? 'dependent' : 'member',
            'name' => mb_strtoupper($person->last_name) . ' ' . $person->first_name,
            'birth_year' => (int) $person->birth_date?->format('Y'),
            'quality' => $d ? 'Ayant droit (' . ($rel[$d->relationship] ?? $d->relationship) . ')' : 'Adhérent principal',
            'code' => mb_strlen($code) > 3 ? str_repeat('•', max(3, mb_strlen($code) - 3)) . mb_substr($code, -3) : '•••',
            'status' => $d ? ($d->status === 'active' ? 'actif' : 'retiré') : ($m->status->value ?? (string) $m->status),
            'pick' => Crypt::encryptString(json_encode(['m' => $m->id, 'd' => $d?->id, 'u' => $user->id, 'p' => $providerId, 'x' => now()->addMinutes(self::PICK_MINUTES)->timestamp])),
        ];
    }
}
