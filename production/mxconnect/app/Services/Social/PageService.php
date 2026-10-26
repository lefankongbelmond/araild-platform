<?php

namespace App\Services\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\PageAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Qui peut publier au nom d'une page :
 *  - le propriétaire d'une page créée par un membre ;
 *  - les gestionnaires désignés (par le propriétaire, ou par la mutuelle depuis son back-office).
 */
class PageService
{
    /** Pages (mutuelles comprises) que ce compte peut animer. */
    public static function managedBy(PublicAccount $account): Builder
    {
        $hasAdmins = self::adminsTable();

        return Mutual::query()->where('status', '!=', 'suspended')
            ->where(function ($w) use ($account, $hasAdmins) {
                $w->whereHas('profile', fn ($p) => $p->where('owner_account_id', $account->id));
                if ($hasAdmins) {
                    $w->orWhereIn('id', PageAdmin::where('public_account_id', $account->id)->select('mutual_id'));
                }
            });
    }

    public static function canPublish(?PublicAccount $account, ?Mutual $mutual): bool
    {
        if (! $account || ! $mutual || $mutual->status === 'suspended') {
            return false;
        }
        if ((int) $mutual->profile?->owner_account_id === $account->id) {
            return true;
        }

        return self::adminsTable() && PageAdmin::where('mutual_id', $mutual->id)->where('public_account_id', $account->id)->exists();
    }

    /** Peut gérer la liste des gestionnaires (propriétaire ou administrateur). */
    public static function canAdminister(?PublicAccount $account, Mutual $mutual): bool
    {
        if (! $account) {
            return false;
        }

        return (int) $mutual->profile?->owner_account_id === $account->id
            || (self::adminsTable() && PageAdmin::where('mutual_id', $mutual->id)->where('public_account_id', $account->id)->where('role', 'admin')->exists());
    }

    /** Comptes à prévenir pour une page (propriétaire + gestionnaires). @return int[] */
    public static function managerIds(string $mutualId): array
    {
        $ids = \App\Models\Social\MutualProfile::whereKey($mutualId)->whereNotNull('owner_account_id')->pluck('owner_account_id')->all();
        if (self::adminsTable()) {
            $ids = array_merge($ids, PageAdmin::where('mutual_id', $mutualId)->pluck('public_account_id')->all());
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Ajoute un gestionnaire à partir de son téléphone ou de son e-mail de compte MX-CONNECT.
     */
    public static function addManager(Mutual $mutual, string $login, string $role, ?PublicAccount $by = null): PageAdmin
    {
        $login = trim($login);
        $account = str_contains($login, '@')
            ? PublicAccount::where('email', mb_strtolower($login))->first()
            : PublicAccount::whereIn('phone', array_unique(array_filter([\App\Http\Controllers\Social\AuthController::normalizePhone($login), $login])))->first();
        if (! $account) {
            throw \Illuminate\Validation\ValidationException::withMessages(['manager' => 'Aucun compte MX-CONNECT avec ce téléphone ou cet e-mail. La personne doit d’abord créer son compte sur le réseau.']);
        }
        if ($account->isSuspended()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['manager' => 'Ce compte est suspendu.']);
        }
        if (PageAdmin::where('mutual_id', $mutual->id)->count() >= 20) {
            throw \Illuminate\Validation\ValidationException::withMessages(['manager' => '20 gestionnaires maximum par page.']);
        }
        $admin = PageAdmin::updateOrCreate(['mutual_id' => $mutual->id, 'public_account_id' => $account->id],
            ['role' => array_key_exists($role, PageAdmin::ROLES) ? $role : 'editor', 'added_by_account_id' => $by?->id]);
        app(NotificationService::class)->notify($account->id, 'page', "Vous pouvez maintenant publier au nom de {$mutual->name}",
            'Choisissez « Publier en tant que » dans la zone de publication.', route('social.mutual', $mutual->slug));

        return $admin;
    }

    private static function adminsTable(): bool
    {
        static $has;

        return $has ??= Schema::connection('central')->hasTable('social_page_admins');
    }
}
