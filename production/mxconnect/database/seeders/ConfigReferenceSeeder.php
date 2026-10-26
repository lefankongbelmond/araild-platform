<?php

namespace Database\Seeders;

use App\Models\Central\Country;
use App\Models\Central\Currency;
use App\Models\Central\Locale;
use App\Models\Central\PaymentProvider;
use Illuminate\Database\Seeder;

/**
 * Seeds the configuration layer as DATA. Cameroon is only the first row —
 * adding another country/currency/provider is inserting data, not editing code.
 */
use App\Models\Central\CareProvider;

class ConfigReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $xaf = Currency::firstOrCreate(['code' => 'XAF'], ['name' => 'Franc CFA (CEMAC)', 'symbol' => 'FCFA', 'minor_unit' => 0]);
        $usd = Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2]);

        $fr = Locale::firstOrCreate(['code' => 'fr'], ['name' => 'Français']);
        $en = Locale::firstOrCreate(['code' => 'en'], ['name' => 'English']);

        $cm = Country::firstOrCreate(['iso2' => 'CM'], [
            'name' => 'Cameroun',
            'default_currency_id' => $xaf->id,
            'default_locale_id'   => $fr->id,
            'phone_prefix' => '+237',
        ]);
        // Second country as proof the model is not Cameroon-bound.
        Country::firstOrCreate(['iso2' => 'CI'], [
            'name' => "Côte d'Ivoire",
            'default_currency_id' => $xaf->id,
            'default_locale_id'   => $fr->id,
            'phone_prefix' => '+225',
        ]);

        $mtn    = PaymentProvider::firstOrCreate(['code' => 'mtn_momo'], ['name' => 'MTN Mobile Money', 'config_schema' => ['merchant_id', 'api_key']]);
        $orange = PaymentProvider::firstOrCreate(['code' => 'orange_money'], ['name' => 'Orange Money', 'config_schema' => ['merchant_id', 'api_key']]);

        // Availability per country (Cameroon: MTN + Orange).
        $cm->paymentProviders()->syncWithoutDetaching([$mtn->id, $orange->id]);
        // --- Care providers (central registry; whitelisted per guarantee) ---
        CareProvider::firstOrCreate(['name' => 'Hôpital de District'], ['type' => 'hospital', 'country_id' => $cm->id ?? null, 'locality' => 'Yaoundé']);
        CareProvider::firstOrCreate(['name' => 'Centre de Santé Intégré'], ['type' => 'health_center', 'country_id' => $cm->id ?? null, 'locality' => 'Akono']);
        CareProvider::firstOrCreate(['name' => 'Pharmacie de la Cité'], ['type' => 'pharmacy', 'country_id' => $cm->id ?? null, 'locality' => 'Yaoundé']);

    }
}
