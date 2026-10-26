<?php

namespace App\Services\Social;

use App\Models\Central\PublicAccount;
use App\Models\Social\SocialNotification;
use Illuminate\Support\Str;

/**
 * Notifications in-app des membres. Les textes ne reprennent jamais le contenu
 * d'un dossier d'adhésion (seulement le statut), pour rester discrets.
 */
class NotificationService
{
    /** Types également envoyés par e-mail (si le membre l'accepte et si l'envoi est configuré). */
    public const EMAIL_TYPES = ['message', 'adhesion', 'group', 'mutuelle', 'demande'];

    public function __construct(private MailService $mail) {}

    public function notify(int|PublicAccount|null $account, string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        $id = $account instanceof PublicAccount ? $account->id : $account;
        if (! $id) {
            return;
        }
        $n = SocialNotification::create([
            'public_account_id' => $id, 'type' => $type,
            'title' => Str::limit($title, 185), 'body' => $body ? Str::limit($body, 270) : null,
            'url' => $url ? Str::limit($this->relative($url), 295, '') : null,
        ]);

        if (in_array($type, self::EMAIL_TYPES, true)) {
            $acc = $account instanceof PublicAccount ? $account : PublicAccount::find($id);
            if ($acc?->email && $acc->email_notifications) {
                $this->mail->send($acc->email, new \App\Mail\SocialMail(
                    $n->title, $n->title, array_filter([$n->body]),
                    'Voir sur MX-CONNECT', $this->mail->centralUrl($n->url ?: '/notifications'),
                ));
            }
        }
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
