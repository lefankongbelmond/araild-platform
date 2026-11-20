<?php

namespace App\Services\Social;

use App\Models\Central\PublicAccount;
use App\Models\Social\SocialNotification;
use Illuminate\Support\Str;

/**
 * Notifications in-app des membres. Les textes ne reprennent jamais le contenu
 * d'un dossier d'adhésion (seulement le statut), pour rester discrets.
 *
 * Multilingue (M2b, 2026-11-29) : le titre et le texte se passent de préférence sous la forme
 * [« Texte français :variable », ['variable' => valeur]] ; ils sont alors traduits dans la langue
 * du DESTINATAIRE (préférence de son compte, sinon français), pas dans celle de la personne qui
 * déclenche l'action. Une valeur ['__' => 'Libellé français'] est elle-même traduite. Une chaîne
 * simple reste acceptée (texte saisi par une personne, déjà dans sa langue).
 */
class NotificationService
{
    /** Types également envoyés par e-mail (si le membre l'accepte et si l'envoi est configuré). */
    public const EMAIL_TYPES = ['message', 'adhesion', 'group', 'mutuelle', 'demande'];

    public function __construct(private MailService $mail) {}

    public function notify(int|PublicAccount|null $account, string $type, string|array $title, string|array|null $body = null, ?string $url = null, ?string $fallbackLocale = null): void
    {
        $id = $account instanceof PublicAccount ? $account->id : $account;
        if (! $id) {
            return;
        }
        $acc = $account instanceof PublicAccount ? $account : PublicAccount::find($id);
        $locale = self::localeOf($acc, $fallbackLocale);
        $title = self::text($title, $locale);
        $body = $body === null ? null : self::text($body, $locale);
        $n = SocialNotification::create([
            'public_account_id' => $id, 'type' => $type,
            'title' => Str::limit($title, 185), 'body' => $body ? Str::limit($body, 270) : null,
            'url' => $url ? Str::limit($this->relative($url), 295, '') : null,
        ]);

        if (in_array($type, self::EMAIL_TYPES, true)) {
            if ($acc?->email && $acc->email_notifications) {
                $this->mail->send($acc->email, (new \App\Mail\SocialMail(
                    $n->title, $n->title, array_filter([$n->body]),
                    __('Voir sur MX-CONNECT', [], $locale), $this->mail->centralUrl($n->url ?: '/notifications'),
                ))->locale($locale));
            }
        }
    }

    /**
     * Langue du destinataire : celle enregistrée sur son compte si elle est proposée ; sinon la langue
     * de repli donnée par l'appelant (langue de la mutuelle pour ses adhérents, M3a) ; sinon le français.
     */
    public static function localeOf(?PublicAccount $account, ?string $fallback = null): string
    {
        $code = $account ? ($account->getAttributes()['locale'] ?? null) : null;
        if ($code && \App\Support\Locales::isActive($code)) {
            return $code;
        }

        return $fallback && \App\Support\Locales::isActive($fallback) ? $fallback : \App\Support\Locales::SOURCE;
    }

    /** Texte simple, ou [clé française, variables] traduit dans la langue donnée. */
    public static function text(string|array $text, string $locale): string
    {
        if (is_string($text)) {
            return $text;
        }
        $replace = [];
        foreach ($text[1] ?? [] as $k => $v) {
            $replace[$k] = is_array($v) && isset($v['__']) ? __((string) $v['__'], [], $locale) : (string) $v;
        }

        return __((string) $text[0], $replace, $locale);
    }

    public function unreadCount(PublicAccount $account): int
    {
        return SocialNotification::where('public_account_id', $account->id)->whereNull('read_at')->count();
    }

    /** Liens relatifs : valables quel que soit le domaine (www / sans www). */
    private function relative(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
}
