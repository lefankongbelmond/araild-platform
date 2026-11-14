<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/** Catégorie de soins de la bibliothèque (consultation, pharmacie, hospitalisation…). Aucun taux ici. */
class ServiceCategory extends Model
{
    protected $guarded = [];
    protected $casts = ['active' => 'boolean', 'sort' => 'integer'];

    public const FAMILIES = [
        'consultation' => 'Consultations', 'pharmacy' => 'Pharmacie', 'lab' => 'Laboratoire', 'imaging' => 'Imagerie',
        'hospital' => 'Hospitalisation', 'surgery' => 'Chirurgie', 'maternity' => 'Maternité', 'dental' => 'Dentaire',
        'optical' => 'Ophtalmologie', 'emergency' => 'Urgences', 'transport' => 'Transport sanitaire', 'other' => 'Autres',
    ];

    public function acts() { return $this->hasMany(ActType::class); }
}
