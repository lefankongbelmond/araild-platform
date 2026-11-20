<?php

namespace App\Models\Provider;

use App\Support\ProviderNotifier;
use Illuminate\Database\Eloquent\Model;

/** Avis d'une mutuelle à un établissement de soins (phase 6, 2026-11-25). Aucune donnée médicale. */
class ProviderNotification extends Model
{
    protected $connection = 'central';
    protected $guarded = [];
    public const UPDATED_AT = null;
    protected $casts = ['created_at' => 'datetime', 'i18n' => 'array'];

    /**
     * Titre dans la langue affichée (multilingue M4, 2026-12-03) : rendu depuis la forme traduisible
     * si elle existe, sinon le texte enregistré (avis antérieurs). Préfixé du nom de la mutuelle.
     */
    public function displayTitle(): string
    {
        $i = $this->i18nData();
        if (! $i || ! isset($i['t'])) {
            return (string) $this->title;
        }

        return mb_substr((! empty($i['m']) ? $i['m'] . ' · ' : '') . ProviderNotifier::render($i['t']), 0, 200);
    }

    public function displayBody(): ?string
    {
        $i = $this->i18nData();
        if (! $i || ! array_key_exists('b', $i)) {
            return $this->body;
        }

        return $i['b'] === null ? null : ProviderNotifier::render($i['b']);
    }

    private function i18nData(): ?array
    {
        $v = $this->getAttributes()['i18n'] ?? null;   // colonne absente avant la migration : texte enregistré

        return $v === null ? null : (is_array($v) ? $v : json_decode((string) $v, true));
    }
}
