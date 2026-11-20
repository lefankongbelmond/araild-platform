<?php

namespace App\Support;

use App\Models\Central\MemberLink;
use App\Services\Social\NotificationService;

/**
 * Notification à un ADHÉRENT relié à un compte MX-CONNECT (portail adhérent, 2026-11-20), appelée
 * dans le contexte de sa mutuelle. Ne contient JAMAIS de donnée médicale (ni acte, ni diagnostic) :
 * seulement l'événement et un lien vers le portail. Canal : notification de l'application, et e-mail
 * si le membre l'accepte (NotificationService). SMS et WhatsApp : à brancher ici quand un fournisseur
 * sera choisi.
 * Multilingue (M2b) : titre et texte sous la forme [« Texte :variable », [...]] → traduits dans la
 * langue du compte de l'adhérent (NotificationService::text).
 */
final class MemberNotifier
{
    public static function notify(int $memberId, string|array $title, string|array|null $body = null, string $path = ''): void
    {
        try {
            $mutualId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;
            if (! $mutualId) {
                return;
            }
            $link = MemberLink::where('mutual_id', $mutualId)->where('member_id', $memberId)->first();
            if (! $link) {
                return;   // adhérent sans compte MX-CONNECT relié
            }
            $slug = tenant('slug') ?? $mutualId;
            app(NotificationService::class)->notify($link->public_account_id, 'mutuelle', $title, $body,
                '/compte/mon-adhesion/' . $slug . ($path !== '' ? '/' . ltrim($path, '/') : ''));
        } catch (\Throwable $e) {
            report($e);   // une notification ne doit jamais faire échouer l'opération de gestion
        }
    }
}
