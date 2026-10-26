<?php

namespace App\Services\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\Comment;
use App\Models\Social\Mention;
use App\Models\Social\MutualProfile;
use App\Models\Social\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Mentions (@). Le formulaire envoie les cibles choisies dans la liste de
 * suggestions (« u:12 » pour un membre, « p:<id> » pour une page) ; le serveur
 * ne garde que celles dont le libellé figure réellement dans le texte, et qui
 * peuvent être mentionnées (profil public, organisation non suspendue).
 */
class MentionService
{
    public const MAX = 20;

    public function __construct(private NotificationService $notifications) {}

    /** Suggestions pour la saisie « @… ». */
    public function search(string $q, ?PublicAccount $viewer, int $limit = 8): Collection
    {
        $q = trim($q);
        if (mb_strlen($q) < 1) {
            return collect();
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $people = PublicAccount::query()
            ->where('profile_visibility', 'public')->whereNull('suspended_at')
            ->when($viewer, fn ($w) => $w->where('id', '!=', $viewer->id))
            ->where(fn ($w) => $w->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", [$like]))
            ->orderBy('first_name')->limit($limit)->get()
            ->map(fn ($a) => ['id' => 'u:' . $a->id, 'label' => $a->name, 'kind' => 'Membre', 'avatar' => $a->avatarUrl(),
                'initials' => $a->initials(), 'sub' => $a->city]);

        $pages = Mutual::query()->where('status', '!=', 'suspended')->where('name', 'like', $like)
            ->with('profile')->orderBy('name')->limit($limit)->get()
            ->map(fn ($m) => ['id' => 'p:' . $m->id, 'label' => $m->name, 'kind' => MutualProfile::CATEGORIES[$m->profile?->category ?? 'mutuelle'] ?? 'Organisation',
                'avatar' => $m->profile?->logoUrl(), 'initials' => mb_strtoupper(mb_substr($m->name, 0, 2)), 'sub' => $m->city]);

        return $people->concat($pages)->take($limit)->values();
    }

    /**
     * Enregistre les mentions d'une publication ou d'un commentaire et prévient les personnes mentionnées.
     *
     * @param string[] $targets
     */
    public function attach(Post|Comment $item, ?string $body, array $targets, string $who, ?int $authorId = null): void
    {
        $body = (string) $body;
        if ($body === '' || ! $targets) {
            return;
        }
        $isPost = $item instanceof Post;
        $postId = $isPost ? $item->id : $item->post_id;
        $url = '/publications/' . $postId . ($isPost ? '' : '#comment-' . $item->id);
        $seen = [];

        foreach (array_slice(array_unique(array_filter($targets, 'is_string')), 0, self::MAX) as $target) {
            [$kind, $id] = array_pad(explode(':', $target, 2), 2, null);
            if ($kind === 'u' && ctype_digit((string) $id)) {
                $acc = PublicAccount::where('profile_visibility', 'public')->find((int) $id);
                if (! $acc || ! str_contains($body, '@' . $acc->name) || isset($seen['u' . $acc->id])) {
                    continue;
                }
                $seen['u' . $acc->id] = true;
                Mention::create(['post_id' => $isPost ? $item->id : null, 'comment_id' => $isPost ? null : $item->id,
                    'public_account_id' => $acc->id, 'label' => Str::limit($acc->name, 180, '')]);
                if ($acc->id !== $authorId) {
                    $this->notifications->notify($acc->id, 'mention',
                        $isPost ? "{$who} vous a mentionné dans une publication" : "{$who} vous a mentionné dans un commentaire",
                        Str::limit($body, 120), $url);
                }
            } elseif ($kind === 'p' && $id) {
                $m = Mutual::where('status', '!=', 'suspended')->with('profile')->find($id);
                if (! $m || ! str_contains($body, '@' . $m->name) || isset($seen['p' . $m->id])) {
                    continue;
                }
                $seen['p' . $m->id] = true;
                Mention::create(['post_id' => $isPost ? $item->id : null, 'comment_id' => $isPost ? null : $item->id,
                    'mutual_id' => $m->id, 'label' => Str::limit($m->name, 180, '')]);
                foreach (PageService::managerIds($m->id) as $manager) {
                    if ($manager !== $authorId) {
                        $this->notifications->notify($manager, 'mention', "{$who} a mentionné votre page {$m->name}", Str::limit($body, 120), $url);
                    }
                }
            }
        }
    }
}
