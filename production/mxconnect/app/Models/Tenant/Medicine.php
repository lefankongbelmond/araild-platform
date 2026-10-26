<?php

namespace App\Models\Tenant;

use App\Models\Central\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Medicine extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $guarded = [];

    /** Catégories thérapeutiques (clé => [libellé, couleur du badge]). */
    public const CATEGORIES = [
        'antalgique'         => ['Antalgique / antipyrétique', 'blue'],
        'antibiotique'       => ['Antibiotique', 'violet'],
        'antipaludique'      => ['Antipaludique', 'orange'],
        'arv'                => ['Antirétroviral (ARV)', 'teal'],
        'anti_inflammatoire' => ['Anti-inflammatoire', 'pink'],
        'antiparasitaire'    => ['Antiparasitaire', 'indigo'],
        'antihypertenseur'   => ['Antihypertenseur', 'red'],
        'antidiabetique'     => ['Antidiabétique', 'amber'],
        'supplement'         => ['Vitamine / supplément', 'green'],
        'vaccin'             => ['Vaccin', 'cyan'],
        'materiel'           => ['Consommable médical', 'slate'],
        'autre'              => ['Autre', 'gray'],
    ];

    public function categoryLabel(): ?string
    {
        return $this->category ? (self::CATEGORIES[$this->category][0] ?? $this->category) : null;
    }

    public function categoryColor(): string
    {
        return self::CATEGORIES[$this->category][1] ?? 'gray';
    }

    /** Devise du prix : celle du médicament, sinon celle de la mutuelle. */
    public function currencyIn(iterable $currencies, Currency $default): Currency
    {
        foreach ($currencies as $c) {
            if ($c->code === $this->currency_code) {
                return $c;
            }
        }

        return $default;
    }
}
