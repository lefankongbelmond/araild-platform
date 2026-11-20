<?php

namespace App\Services\Social;

use App\Models\Central\MemberActivationCode;
use App\Models\Central\MemberLink;
use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Tenant\Member;
use App\Services\AccountingRefusal;
use Illuminate\Support\Facades\DB;

/**
 * PORTAIL ADHÉRENT dans le compte MX-CONNECT (mission « Mutuelle paramétrable », L4, 2026-11-20).
 *
 * - Un compte voit une mutuelle SEULEMENT s'il y est relié (member_links) : par l'adhésion en ligne
 *   acceptée, ou par un CODE D'ACTIVATION remis au guichet. Toute autre mutuelle répond 404
 *   (scénario 9 : jamais de lecture des données d'une autre mutuelle).
 * - Activation : code (empreinte sha256, usage unique, 30 jours) + date de naissance de l'adhérent ;
 *   un adhérent n'est relié qu'à UN compte.
 */
class MemberPortalService
{
    public const CODE_DAYS = 30;

    public function __construct(private NotificationService $notifications) {}

    /** Mutuelles où ce compte est relié à une fiche adhérent. */
    public function links(PublicAccount $a)
    {
        return MemberLink::with('mutual')->where('public_account_id', $a->id)->get()->filter(fn ($l) => $l->mutual)->values();
    }

    /** @return array{0:Mutual,1:MemberLink} ou 404 */
    public function resolve(PublicAccount $a, string $slug): array
    {
        $mutual = Mutual::where('slug', $slug)->first();
        $link = $mutual ? MemberLink::where('public_account_id', $a->id)->where('mutual_id', $mutual->id)->first() : null;
        abort_unless($mutual && $link, 404);
        abort_if(($mutual->status ?? null) === 'suspended', 403, __('Cette mutuelle est momentanément fermée. Contactez-la directement.'));

        return [$mutual, $link];
    }

    /** Code d'activation pour un adhérent (appelé DANS la mutuelle) : affiché une seule fois. */
    public function issueCode(string $mutualId, int $memberId, ?int $staffId): array
    {
        $alphabet = MemberActivationCode::ALPHABET;
        $raw = '';
        for ($i = 0; $i < 12; $i++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $code = implode('-', str_split($raw, 4));
        MemberActivationCode::where('mutual_id', $mutualId)->where('member_id', $memberId)->whereNull('used_at')->delete();   // un seul code actif
        $row = MemberActivationCode::create(['mutual_id' => $mutualId, 'member_id' => $memberId, 'code_hash' => MemberActivationCode::hash($code),
            'expires_at' => now()->addDays(self::CODE_DAYS), 'created_by' => $staffId]);

        return ['code' => $code, 'expires_at' => $row->expires_at];
    }

    /** Activation par l'adhérent. Refus = AccountingRefusal (message affiché tel quel, sans indice exploitable). */
    public function activate(PublicAccount $a, string $code, string $birthDate): MemberLink
    {
        $fail = __('Code ou date de naissance incorrects, ou code expiré. Vérifiez, ou demandez un nouveau code à votre mutuelle.');

        return DB::connection('central')->transaction(function () use ($a, $code, $birthDate, $fail) {
            $row = MemberActivationCode::where('code_hash', MemberActivationCode::hash($code))->lockForUpdate()->first();
            if (! $row || $row->used_at || $row->expires_at->isPast()) {
                throw new AccountingRefusal($fail);
            }
            $mutual = Mutual::find($row->mutual_id);
            if (! $mutual) {
                throw new AccountingRefusal($fail);
            }
            $member = $mutual->run(fn () => Member::find($row->member_id));
            $born = $member ? $mutual->run(fn () => $member->birth_date?->toDateString()) : null;
            if (! $member || $born !== $birthDate) {
                throw new AccountingRefusal($fail);
            }
            $other = MemberLink::where('mutual_id', $mutual->id)->where('member_id', $member->id)->where('public_account_id', '!=', $a->id)->exists();
            if ($other) {
                throw new AccountingRefusal(__('Cette adhésion est déjà reliée à un autre compte MX-CONNECT. Contactez votre mutuelle.'));
            }
            $mine = MemberLink::where('public_account_id', $a->id)->where('mutual_id', $mutual->id)->first();
            if ($mine && (int) $mine->member_id !== (int) $member->id) {
                throw new AccountingRefusal(__('Votre compte est déjà relié à une autre fiche dans cette mutuelle. Contactez-la.'));
            }
            $link = MemberLink::updateOrCreate(['public_account_id' => $a->id, 'mutual_id' => $mutual->id],
                ['member_id' => $member->id, 'member_code' => $member->member_code]);
            $row->update(['used_at' => now(), 'used_by_account' => $a->id]);
            $this->notifications->notify($a, 'mutuelle', \App\Support\Msg::of('Votre adhésion à :name est accessible dans votre compte', ['name' => $mutual->name]),
                \App\Support\Msg::of('Numéro d’adhérent : :code', ['code' => $member->member_code]), '/compte/mon-adhesion/' . $mutual->slug);

            return $link;
        });
    }

    /** Retrait de l'accès par la mutuelle (appelé DANS la mutuelle). */
    public function revoke(string $mutualId, int $memberId): int
    {
        MemberActivationCode::where('mutual_id', $mutualId)->where('member_id', $memberId)->whereNull('used_at')->delete();

        return MemberLink::where('mutual_id', $mutualId)->where('member_id', $memberId)->delete();
    }
}
