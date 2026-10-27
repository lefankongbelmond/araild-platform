<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

/**
 * Document légal VERSIONNÉ (2026-11-09) : conditions d'utilisation, politique de
 * confidentialité, règles communautaires, consentements spécifiques. Une version publiée
 * n'est jamais modifiée ; une nouvelle version est publiée à la place.
 */
class LegalDocument extends Model
{
    protected $connection = 'central';
    protected $table = 'legal_documents';
    protected $guarded = [];
    protected $casts = ['requires_reacceptance' => 'boolean', 'is_template' => 'boolean', 'published_at' => 'datetime'];

    /** Types connus : libellé, action attendue, phrase de la case à cocher. */
    public const TYPES = [
        'terms'     => ['Conditions d’utilisation', 'accept', 'J’ai lu et j’accepte les :doc'],
        'privacy'   => ['Politique de confidentialité', 'acknowledge', 'J’ai pris connaissance de la :doc'],
        'community' => ['Règles communautaires', 'accept', 'J’accepte les :doc'],
    ];

    public const ACTIONS = ['accept' => 'Acceptation', 'acknowledge' => 'Prise de connaissance', 'consent' => 'Consentement (facultatif, retirable)'];

    public function label(): string
    {
        return self::TYPES[$this->type][0] ?? $this->title;
    }

    /** Phrase de la case à cocher (jamais pré-cochée). */
    public function checkboxText(): string
    {
        $tpl = self::TYPES[$this->type][2] ?? 'J’accepte : :doc';

        return str_replace(':doc', $this->label(), $tpl);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    public function url(): string
    {
        return route('social.legal.show', ['type' => $this->type, 'version' => $this->version]);
    }
}
