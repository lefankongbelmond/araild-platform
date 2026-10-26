<?php

namespace App\Models\Social;

use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Demande adressée à une page : « Je m'engage », « Soutenir ou devenir partenaire »
 * ou candidature à une opportunité. Visible seulement par son auteur et par les
 * gestionnaires habilités de la page. Aucune donnée médicale, aucun paiement.
 */
class SocialRequest extends Model
{
    protected $connection = 'central';
    protected $table = 'social_requests';
    protected $guarded = [];
    protected $casts = ['consent_at' => 'datetime', 'decided_at' => 'datetime'];

    public const KINDS = [
        'engagement'  => 'Je m’engage',
        'support'     => 'Soutien / partenariat',
        'application' => 'Candidature',
    ];
    public const PREFIX = ['engagement' => 'ENG', 'support' => 'SOU', 'application' => 'CAN'];

    public const ENGAGEMENT_TYPES = [
        'benevolat'   => 'Bénévolat sur le terrain',
        'competences' => 'Mettre mes compétences à disposition',
        'relais'      => 'Relais communautaire / ambassadeur',
        'autre'       => 'Autre forme d’engagement',
    ];
    /** Le soutien financier se règle DIRECTEMENT avec l'organisation : la plateforme ne reçoit aucun fonds. */
    public const SUPPORT_TYPES = [
        'technique'   => 'Partenariat technique',
        'materiel'    => 'Don de matériel',
        'expertise'   => 'Expertise / formation',
        'financement' => 'Financement (réglé directement avec l’organisation)',
        'relation'    => 'Mise en relation',
        'autre'       => 'Autre soutien',
    ];

    public const STATUSES = [
        'submitted' => 'Envoyée',
        'in_review' => 'En cours d’étude',
        'accepted'  => 'Acceptée',
        'rejected'  => 'Non retenue',
        'withdrawn' => 'Retirée',
        'closed'    => 'Clôturée',
    ];
    public const OPEN = ['submitted', 'in_review'];

    public function mutual()      { return $this->belongsTo(Mutual::class, 'mutual_id'); }
    public function account()     { return $this->belongsTo(PublicAccount::class, 'public_account_id'); }
    public function project()     { return $this->belongsTo(Project::class, 'project_id')->withTrashed(); }
    public function opportunity() { return $this->belongsTo(Opportunity::class, 'opportunity_id')->withTrashed(); }
    public function events()      { return $this->hasMany(SocialRequestEvent::class, 'social_request_id')->orderBy('id'); }

    public function isOpen(): bool { return in_array($this->status, self::OPEN, true); }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }
    public function kindLabel(): string
    {
        if ($this->kind === 'application' && $this->opportunity && ! $this->opportunity->isJob()) {
            return 'Manifestation d’intérêt';
        }

        return self::KINDS[$this->kind] ?? $this->kind;
    }
    public function subtypeLabel(): ?string
    {
        return (self::ENGAGEMENT_TYPES + self::SUPPORT_TYPES)[$this->subtype] ?? null;
    }

    /** Ce sur quoi porte la demande (projet, opportunité ou l'organisation elle-même). */
    public function subject(): string
    {
        return $this->opportunity?->title ?? $this->project?->title ?? ($this->mutual?->name ?? '');
    }

    /** Classe CSS de statut (réutilise les couleurs des dossiers d'adhésion). */
    public function statusClass(): string
    {
        return match ($this->status) {
            'accepted' => 'accepted', 'rejected', 'withdrawn' => 'rejected', 'closed' => 'withdrawn', default => $this->status,
        };
    }

    public static function newReference(string $kind): string
    {
        do {
            $ref = (self::PREFIX[$kind] ?? 'DEM') . '-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }

    public function getRouteKeyName(): string { return 'reference'; }
}
