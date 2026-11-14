<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité des accès aux données de santé (mission « Mutuelle paramétrable », Partie 7 ; 2026-11-22).
 * Chaque ouverture d'un écran au DÉTAIL médical (recours, accord préalable, pièce d'un prestataire,
 * registre de la pharmacie) par une personne habilitée est inscrite dans audit_logs
 * (action « health_data_view » : qui, quoi, quand, adresse IP). Aucune donnée médicale n'y est copiée.
 * Une même personne sur un même dossier n'est inscrite qu'une fois par tranche de 10 minutes.
 */
final class HealthAccessLog
{
    public const ACTION = 'health_data_view';

    public const LABELS = [
        'claim' => 'Recours', 'preauthorization' => 'Accord préalable', 'invoice_document' => 'Pièce d’un prestataire', 'pharmacy_registry' => 'Registre de la pharmacie',
    ];

    public static function record(string $object, ?int $id = null): void
    {
        $user = auth('web')->user();
        if (! $user || ! TenantAccess::can('capacite.donnees-sante', $user)) {
            return;   // sans habilitation, le détail médical n'est pas affiché : rien à tracer
        }
        try {
            $key = 'health-view:' . (tenant()?->getTenantKey() ?? '-') . ":{$user->id}:{$object}:" . ($id ?? 0);
            if (! Cache::add($key, 1, now()->addMinutes(10)) || ! Schema::hasTable('audit_logs')) {
                return;
            }
            DB::table('audit_logs')->insert(['user_id' => $user->id, 'action' => self::ACTION, 'object_type' => $object, 'object_id' => $id,
                'ip' => request()?->ip(), 'occurred_at' => now()]);
        } catch (\Throwable $e) {
            report($e);   // la trace ne doit jamais empêcher l'accès légitime
        }
    }
}
