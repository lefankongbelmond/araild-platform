<?php

namespace App\Services\Social;

use App\Mail\SocialMail;
use App\Models\Central\Mutual;
use App\Support\MailStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envoi des e-mails du réseau social. Si l'envoi n'est pas configuré, rien n'est
 * envoyé (et rien n'est promis à l'utilisateur). Une erreur SMTP ne bloque jamais
 * l'action de l'utilisateur : elle est seulement journalisée.
 */
class MailService
{
    public function send(?string $to, SocialMail $mail): bool
    {
        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL) || ! MailStatus::configured()) {
            return false;
        }
        try {
            Mail::to($to)->send($mail);

            return true;
        } catch (\Throwable $e) {
            Log::warning('E-mail non envoyé', ['to_domain' => substr(strrchr($to, '@') ?: '', 1), 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Lien absolu vers le site central à partir d'un chemin relatif. */
    public function centralUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }

    /** Lien absolu vers l'espace de gestion (sous-domaine) d'une mutuelle. */
    public function tenantUrl(Mutual $mutual, string $path): ?string
    {
        $domain = $mutual->domains()->value('domain');
        if (! $domain) {
            return null;
        }
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return "{$scheme}://{$domain}/" . ltrim($path, '/');
    }

    /** Alerte au personnel de la mutuelle (adresse d'alerte privée), sans aucune donnée personnelle. */
    public function alertMutual(Mutual $mutual, string $subject, string $text, string $path): bool
    {
        $to = $mutual->profile?->alert_email;
        if (! $to) {
            return false;
        }

        return $this->send($to, new SocialMail(
            "[MX-CONNECT] {$subject}", $subject, [$text, 'Pour des raisons de confidentialité, le détail n’est visible qu’en vous connectant à votre espace de gestion.'],
            'Ouvrir mon espace de gestion', $this->tenantUrl($mutual, $path),
            'Alerte envoyée à l’adresse d’alerte de votre mutuelle (Réseau social → Page publique).',
        ));
    }
}
