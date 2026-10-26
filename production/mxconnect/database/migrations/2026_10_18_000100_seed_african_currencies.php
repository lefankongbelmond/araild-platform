<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Devises utilisées en Afrique + dollar américain et euro (ISO 4217).
 * Ajout seulement : une devise déjà présente n'est jamais modifiée.
 */
return new class extends Migration
{
    public const CURRENCIES = [
        // code, nom, symbole, décimales
        ['XAF', 'Franc CFA (CEMAC)', 'FCFA', 0],
        ['XOF', 'Franc CFA (UEMOA)', 'F CFA', 0],
        ['USD', 'Dollar américain', '$', 2],
        ['EUR', 'Euro', '€', 2],
        ['NGN', 'Naira nigérian', '₦', 2],
        ['GHS', 'Cedi ghanéen', 'GH₵', 2],
        ['GNF', 'Franc guinéen', 'FG', 0],
        ['CDF', 'Franc congolais', 'FC', 2],
        ['RWF', 'Franc rwandais', 'FRw', 0],
        ['BIF', 'Franc burundais', 'FBu', 0],
        ['KMF', 'Franc comorien', 'FC', 0],
        ['DJF', 'Franc djiboutien', 'Fdj', 0],
        ['MGA', 'Ariary malgache', 'Ar', 0],
        ['KES', 'Shilling kényan', 'KSh', 2],
        ['UGX', 'Shilling ougandais', 'USh', 0],
        ['TZS', 'Shilling tanzanien', 'TSh', 2],
        ['ETB', 'Birr éthiopien', 'Br', 2],
        ['MAD', 'Dirham marocain', 'DH', 2],
        ['DZD', 'Dinar algérien', 'DA', 2],
        ['TND', 'Dinar tunisien', 'DT', 3],
        ['EGP', 'Livre égyptienne', 'E£', 2],
        ['MRU', 'Ouguiya mauritanien', 'UM', 2],
        ['GMD', 'Dalasi gambien', 'D', 2],
        ['SLE', 'Leone sierra-léonais', 'Le', 2],
        ['LRD', 'Dollar libérien', 'L$', 2],
        ['CVE', 'Escudo cap-verdien', 'Esc', 2],
        ['AOA', 'Kwanza angolais', 'Kz', 2],
        ['MZN', 'Metical mozambicain', 'MT', 2],
        ['ZAR', 'Rand sud-africain', 'R', 2],
        ['ZMW', 'Kwacha zambien', 'K', 2],
        ['MWK', 'Kwacha malawien', 'MK', 2],
        ['BWP', 'Pula botswanais', 'P', 2],
        ['NAD', 'Dollar namibien', 'N$', 2],
        ['SDG', 'Livre soudanaise', 'LS', 2],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::CURRENCIES as [$code, $name, $symbol, $minor]) {
            DB::connection('central')->table('currencies')->insertOrIgnore([
                'code' => $code, 'name' => $name, 'symbol' => $symbol, 'minor_unit' => $minor,
                'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Jamais de suppression : des montants peuvent déjà y faire référence.
    }
};
