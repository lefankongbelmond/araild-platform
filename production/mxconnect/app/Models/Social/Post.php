<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Publication du réseau (organisation ou compte grand public). */
class Post extends Model
{
    use SoftDeletes;

    protected $connection = 'central';
    protected $table = 'social_posts';
    protected $guarded = [];
    protected $casts = [
        'event_starts_at' => 'datetime',
        'published_at'    => 'datetime',
        'is_demo'         => 'boolean',
    ];

    public const TYPES = [
        'texte'     => 'Publication',
        'annonce'   => 'Annonce',
        'campagne'  => 'Campagne de santé',
        'activite'  => 'Activité',
        'evenement' => 'Événement',
        'offre'     => 'Offre de santé',
        'document'  => 'Document public',
        'lien'      => 'Lien',
        'reel'      => 'Réel',
    ];

    /** Types qu'un membre peut choisir en publiant (les événements restent réservés aux mutuelles). */
    public const MEMBER_TYPES = [
        'texte'    => 'Actualité',
        'annonce'  => 'Annonce communautaire',
        'campagne' => 'Campagne de sensibilisation',
        'activite' => 'Rapport d’activité',
    ];

    public const ACTIVITY_CATEGORIES = [
        'sensibilisation' => 'Campagne de sensibilisation',
        'depistage'       => 'Dépistage et prévention',
        'education'       => 'Éducation sanitaire',
        'communautaire'   => 'Journée communautaire',
        'proximite'       => 'Activité de proximité',
        'formation'       => 'Formation',
        'partenariat'     => 'Partenariat',
        'reunion'         => 'Réunion et événement',
        'solidarite'      => 'Action de solidarité',
        'temoignage'      => 'Témoignage autorisé',
        'rapport'         => 'Rapport public d’activités',
    ];

    public const REACTIONS = ['jaime' => 'J’aime', 'soutien' => 'Soutien', 'bravo' => 'Bravo', 'utile' => 'Utile'];

    public function mutual()  { return $this->belongsTo(Mutual::class, 'mutual_id'); }
    public function account() { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
    public function media()   { return $this->hasMany(PostMedia::class, 'post_id')->orderBy('position'); }
    public function comments(){ return $this->hasMany(Comment::class, 'post_id'); }
    public function reactions(){ return $this->hasMany(Reaction::class, 'post_id'); }
    public function shared()  { return $this->belongsTo(Post::class, 'shared_post_id')->withTrashed(); }
    public function group()   { return $this->belongsTo(Group::class, 'group_id'); }
    public function mentions(){ return $this->hasMany(Mention::class, 'post_id'); }

    /** Ce compte peut-il gérer (supprimer) cette publication ? Auteur, ou propriétaire de la page qui l'a publiée. */
    public function canManage(?PublicAccount $viewer): bool
    {
        if (! $viewer) {
            return false;
        }
        if ($this->mutual_id) {
            return \App\Services\Social\PageService::canPublish($viewer, $this->mutual);
        }

        return $this->public_account_id === $viewer->id;
    }

    public function isReel(): bool { return $this->type === 'reel'; }

    /** Exclut les publications des comptes suspendus par la modération. */
    public function scopeWithoutSuspended(Builder $q): Builder
    {
        static $has;
        $has ??= \Illuminate\Support\Facades\Schema::connection('central')->hasColumn('public_accounts', 'suspended_at');

        return $has ? $q->where(fn ($w) => $w->whereNull('social_posts.public_account_id')
            ->orWhereIn('social_posts.public_account_id', PublicAccount::whereNull('suspended_at')->select('id'))) : $q;
    }

    /** Publications visibles par tout le monde. */
    public function scopePublicFeed(Builder $q): Builder
    {
        $q->withoutSuspended();

        return $q->where('status', 'published')
            ->where('visibility', 'public')
            ->whereNull('group_id')                 // les groupes ont leur propre fil
            ->whereNotNull('published_at')
            ->where(fn ($w) => $w->whereNull('mutual_id')
                ->orWhereHas('mutual', fn ($m) => $m->where('status', '!=', 'suspended')));
    }

    /** Publications que ce compte a le droit de voir (publiques + réservées aux abonnés suivis). */
    public function scopeVisibleTo(Builder $q, ?PublicAccount $viewer): Builder
    {
        $q->withoutSuspended();

        return $q->where('status', 'published')
            ->whereNotNull('published_at')
            // Publication de groupe : groupe actif, et public — ou privé dont le visiteur est membre.
            ->where(function ($g) use ($viewer) {
                $g->whereNull('group_id')
                  ->orWhereIn('group_id', Group::where('status', 'active')->where('visibility', 'public')->select('id'));
                if ($viewer) {
                    $g->orWhereIn('group_id', GroupMember::where('public_account_id', $viewer->id)->where('status', 'active')->select('group_id'));
                }
            })
            ->where(function ($w) use ($viewer) {
                $w->where('visibility', 'public');
                if ($viewer) {
                    $w->orWhere(fn ($f) => $f->where('visibility', 'followers')
                        ->whereIn('mutual_id', Follow::where('public_account_id', $viewer->id)->select('mutual_id')))
                      ->orWhere('public_account_id', $viewer->id);
                }
            });
    }

    public function authorName(): string
    {
        if ($this->mutual_id) {
            return $this->mutual?->name ?? 'Mutuelle';
        }

        return $this->account ? trim($this->account->first_name . ' ' . $this->account->last_name) : 'Membre';
    }

    public function authorUrl(): ?string
    {
        if ($this->mutual_id && $this->mutual) {
            return route('social.mutual', $this->mutual->slug);
        }

        return $this->public_account_id ? route('social.user', $this->public_account_id) : null;
    }

    public function authorAvatar(): ?string
    {
        if ($this->mutual_id) {
            return $this->mutual?->profile?->logoUrl();
        }

        return $this->account?->avatarUrl();
    }

    public function typeLabel(): string
    {
        if (! $this->mutual_id && isset(self::MEMBER_TYPES[$this->type])) {
            return self::MEMBER_TYPES[$this->type];
        }

        return self::TYPES[$this->type] ?? 'Publication';
    }
}
