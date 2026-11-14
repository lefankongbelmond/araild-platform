<?php

/*
 * Mission « Mutuelle paramétrable », L1–L2 (2026-11-18) : moteur de prise en charge.
 * Scénarios 2, 3, 4, 6, 7, 8, 10, 11 (urgence), 12 de la Partie 10, dans une VRAIE base de
 * mutuelle (ou en tables préfixées avec MX_TEST_STORAGE=prefix).
 * Les taux et montants ci-dessous sont des PARAMÈTRES DE TEST, pas des valeurs du logiciel.
 */

use App\Models\Tenant\ActType;
use App\Models\Tenant\CareClaim;
use App\Models\Tenant\CoverageException;
use App\Models\Tenant\CoverageLimit;
use App\Models\Tenant\CoverageRule;
use App\Models\Tenant\Dependent;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use App\Models\Tenant\Member;
use App\Models\Tenant\Prestation;
use App\Models\Tenant\ServiceCategory;
use App\Models\Tenant\Subscription;
use App\Models\Tenant\WaitingPeriod;
use App\Services\ClaimCaptureService;
use App\Services\GuaranteeVersioningService;
use App\Services\Tenant\CoverageEngine;

beforeEach(function () {
    provisionTestMutual();
    $this->agent = staffUser('saisie@equipe.test', ['benefits_manager']);
    $this->today = now()->toDateString();
    $this->ids = $this->mutual->run(function () {
        $cat = fn ($code) => ServiceCategory::where('code', $code)->value('id');
        $g = Guarantee::create(['code' => 'FAM', 'name' => 'Famille', 'status' => 'active']);
        $v = GuaranteeVersion::create(['guarantee_id' => $g->id, 'version_no' => 1, 'valid_from' => now()->subYears(2)->toDateString(),
            'base_contribution_minor' => 5000, 'periodicity' => 'monthly', 'coverage_rate' => 0, 'copay_rate' => 0, 'observation_days' => 0]);
        $cons = ActType::create(['code' => 'CONS', 'label' => 'Consultation', 'category' => 'consultation', 'tariff_minor' => 10000, 'service_category_id' => $cat('consultation')]);
        $pharma = ActType::create(['code' => 'PHAR', 'label' => 'Ordonnance', 'category' => 'pharmacie', 'service_category_id' => $cat('pharmacy')]);
        $hosp = ActType::create(['code' => 'HOSP', 'label' => 'Hospitalisation', 'category' => 'hospitalisation', 'service_category_id' => $cat('hospitalization')]);
        $dent = ActType::create(['code' => 'DENT', 'label' => 'Soins dentaires', 'category' => 'dentaire', 'service_category_id' => $cat('dental')]);

        $rCons = CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $cat('consultation'), 'label' => 'Consultations',
            'beneficiary_category' => 'all', 'coverage_rate' => 80, 'copay_rate' => 20, 'tariff_cap' => true]);
        CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $cat('consultation'), 'label' => 'Consultations enfants',
            'beneficiary_category' => 'child_6_17', 'coverage_rate' => 100]);
        $rPhar = CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $cat('pharmacy'), 'label' => 'Pharmacie',
            'beneficiary_category' => 'all', 'coverage_rate' => 70]);
        $rHosp = CoverageRule::create(['guarantee_version_id' => $v->id, 'service_category_id' => $cat('hospitalization'), 'label' => 'Hospitalisation',
            'beneficiary_category' => 'all', 'coverage_rate' => 80, 'emergency_exempt' => true, 'accident_waiting_days' => 0]);
        // Pharmacie : 30 000 par an pour toute la famille ; consultations : 3 par mois et par bénéficiaire.
        CoverageLimit::create(['guarantee_version_id' => $v->id, 'label' => 'Pharmacie familiale', 'rule_keys' => [$rPhar->rule_key], 'scope' => 'year', 'holder' => 'family', 'amount_minor' => 30000]);
        CoverageLimit::create(['guarantee_version_id' => $v->id, 'label' => 'Consultations', 'rule_keys' => [$rCons->rule_key], 'scope' => 'month', 'holder' => 'beneficiary', 'max_count' => 3]);
        WaitingPeriod::create(['guarantee_version_id' => $v->id, 'rule_key' => $rHosp->rule_key, 'beneficiary_category' => 'all', 'days' => 90]);

        $m = Member::create(['member_code' => 'MUT-000501', 'first_name' => 'Awa', 'last_name' => 'Ngono', 'birth_date' => '1990-04-02',
            'sex' => 'F', 'status' => 'active', 'joined_at' => now()->subDays(10)->toDateString()]);
        $sub = Subscription::create(['member_id' => $m->id, 'guarantee_version_id' => $v->id, 'contribution_minor' => 5000,
            'effective_date' => now()->subDays(10)->toDateString(), 'status' => 'validated', 'captured_by' => 1]);
        $child = Dependent::create(['member_id' => $m->id, 'first_name' => 'Junior', 'last_name' => 'Ngono', 'birth_date' => now()->subYears(9)->toDateString(),
            'sex' => 'M', 'relationship' => 'child', 'status' => 'active']);

        return compact('g', 'v', 'cons', 'pharma', 'hosp', 'dent', 'm', 'sub', 'child', 'rPhar', 'rHosp', 'rCons');
    });
    tenancy()->end();
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/** Saisie par le service commun (back-office et portail), dans la mutuelle de test. */
function mpcCapture(int $actId, int $total, array $opt = [], ?int $dependentId = null): array
{
    $t = test();

    return $t->mutual->run(function () use ($t, $actId, $total, $opt, $dependentId) {
        $claim = CareClaim::create(['member_id' => $t->ids['m']->id, 'dependent_id' => $dependentId, 'opened_on' => $opt['date'] ?? $t->today, 'status' => 'open']);

        return app(ClaimCaptureService::class)->capture($claim, ['act_type_id' => $actId, 'provider_id' => 1, 'care_date' => $opt['date'] ?? $t->today,
            'total_minor' => $total, 'emergency' => $opt['emergency'] ?? false, 'accident' => $opt['accident'] ?? false], \App\Models\Tenant\User::where('email', 'saisie@equipe.test')->first());
    });
}

it('scénario 3 : plafond disponible — taux de la garantie, tarif conventionnel, catégorie de bénéficiaire', function () {
    $r = mpcCapture($this->ids['cons']->id, 12000);
    expect($r['ok'])->toBeTrue();
    $p = $r['prestation'];
    // Éligible plafonné au tarif conventionnel (10 000) ; 80 % = 8 000 ; le patient paie 4 000 (dont 2 000 hors tarif).
    expect([$p->eligible_minor, $p->mutual_part_minor, $p->beneficiary_part_minor, $p->copay_minor, $p->non_covered_minor])
        ->toBe([10000, 8000, 4000, 2000, 2000]);

    // L'enfant de 9 ans : la garantie « enfants 6–17 ans » (100 %) l'emporte sur « tous ».
    $c = mpcCapture($this->ids['cons']->id, 5000, [], $this->ids['child']->id);
    expect($c['prestation']->mutual_part_minor)->toBe(5000);

    // Acte d'une catégorie sans garantie : GARANTIE NON COUVERTE, rien n'est enregistré.
    $d = mpcCapture($this->ids['dent']->id, 5000);
    expect($d['ok'])->toBeFalse()->and($d['status'])->toBe('not_covered')->and($d['reasons'])->toBe(['no_rule']);
    expect($this->mutual->run(fn () => Prestation::count()))->toBe(2);
});

it('scénario 2 et 11 : délai de carence non expiré ; urgence et accident non bloqués', function () {
    $r = mpcCapture($this->ids['hosp']->id, 100000);
    expect($r['ok'])->toBeFalse()->and($r['status'])->toBe('waiting')->and($r['reasons'])->toBe(['waiting_period'])
        ->and($r['waiting_until'])->toBe(now()->subDays(10)->addDays(90)->toDateString());

    // Urgence : la garantie exempte de carence → prise en charge, tracée comme urgence.
    $u = mpcCapture($this->ids['hosp']->id, 100000, ['emergency' => true]);
    expect($u['ok'])->toBeTrue()->and($u['prestation']->mutual_part_minor)->toBe(80000)->and((bool) $u['prestation']->emergency)->toBeTrue();
    // Accident : délai « accident » de la garantie (0 jour).
    expect(mpcCapture($this->ids['hosp']->id, 50000, ['accident' => true])['ok'])->toBeTrue();
});

it('scénario 4 : dépassement du plafond annuel familial (partagé entre l’adhérent et l’ayant droit), puis plafond épuisé', function () {
    expect(mpcCapture($this->ids['pharma']->id, 30000)['prestation']->mutual_part_minor)->toBe(21000);          // 70 %
    $b = mpcCapture($this->ids['pharma']->id, 20000, [], $this->ids['child']->id);                               // famille : reste 9 000
    expect($b['prestation']->mutual_part_minor)->toBe(9000)->and($b['prestation']->beneficiary_part_minor)->toBe(11000)
        ->and($b['prestation']->non_covered_minor)->toBe(5000);                                              // 14 000 au taux − 9 000
    $c = mpcCapture($this->ids['pharma']->id, 5000);
    expect($c['ok'])->toBeFalse()->and($c['status'])->toBe('limit_reached')->and($c['reasons'])->toBe(['ceiling_reached']);

    // Une prestation rejetée libère sa part du plafond.
    $this->mutual->run(fn () => Prestation::whereKey($b['prestation']->id)->update(['status' => 'rejected']));
    expect(mpcCapture($this->ids['pharma']->id, 5000)['prestation']->mutual_part_minor)->toBe(3500);

    // Plafond en NOMBRE : 3 consultations par mois et par bénéficiaire.
    $this->mutual->run(fn () => Prestation::where('act_type_id', $this->ids['cons']->id)->delete());
    foreach (range(1, 3) as $i) {
        expect(mpcCapture($this->ids['cons']->id, 5000)['ok'])->toBeTrue();
    }
    $n = mpcCapture($this->ids['cons']->id, 5000);
    expect($n['ok'])->toBeFalse()->and($n['reasons'])->toBe(['count_reached']);
    expect(mpcCapture($this->ids['cons']->id, 5000, [], $this->ids['child']->id)['ok'])->toBeTrue();            // compteur par bénéficiaire
});

it('scénario 6 : couverture expirée, suspendue ou non activée — jamais couvert automatiquement', function () {
    $engine = fn () => app(CoverageEngine::class);
    $this->mutual->run(function () use ($engine) {
        $m = Member::find($this->ids['m']->id);
        // Souscription seulement saisie : droits NON ACTIVÉS.
        Subscription::whereKey($this->ids['sub']->id)->update(['status' => 'captured']);
        expect($engine()->membership($m, null, $this->today)['status'])->toBe('not_activated');
        // Validée mais avec une date d'effet future : NON ACTIVÉS.
        Subscription::whereKey($this->ids['sub']->id)->update(['status' => 'validated', 'effective_date' => now()->addDays(5)->toDateString()]);
        expect($engine()->membership($m, null, $this->today)['status'])->toBe('not_activated');
        // Résiliée : EXPIRÉE.
        Subscription::whereKey($this->ids['sub']->id)->update(['status' => 'terminated', 'effective_date' => now()->subDays(10)->toDateString()]);
        expect($engine()->membership($m, null, $this->today)['status'])->toBe('expired');
        Subscription::whereKey($this->ids['sub']->id)->update(['status' => 'validated']);
        // Version de formule échue sans successeur : EXPIRÉE.
        GuaranteeVersion::whereKey($this->ids['v']->id)->update(['valid_to' => now()->subDay()->toDateString()]);
        expect($engine()->membership($m, null, $this->today)['status'])->toBe('expired');
        GuaranteeVersion::whereKey($this->ids['v']->id)->update(['valid_to' => null]);
        // Échéance impayée et échue : SUSPENDUE.
        \App\Models\Tenant\ContributionSchedule::create(['subscription_id' => $this->ids['sub']->id, 'period' => now()->subMonth()->format('Y-m'), 'year' => now()->year,
            'due_minor' => 5000, 'due_date' => now()->subDays(3)->toDateString(), 'status' => 'to_pay']);
        expect($engine()->membership($m, null, $this->today)['status'])->toBe('suspended');
        \App\Models\Tenant\ContributionSchedule::query()->update(['status' => 'paid']);
        // Ayant droit retiré : EXPIRÉE pour lui seulement.
        Dependent::whereKey($this->ids['child']->id)->update(['status' => 'removed']);
        expect($engine()->membership($m, Dependent::find($this->ids['child']->id), $this->today)['status'])->toBe('expired');
        expect($engine()->membership($m, null, $this->today)['status'])->toBe('active');
    });
    tenancy()->end();
    $r = mpcCapture($this->ids['cons']->id, 5000, [], $this->ids['child']->id);
    expect($r['ok'])->toBeFalse()->and($r['reasons'])->toBe(['dependent_removed']);
});

it('scénario 7 : modifier une formule utilisée crée une nouvelle version, sans effet rétroactif', function () {
    $first = mpcCapture($this->ids['pharma']->id, 10000)['prestation'];
    expect($first->mutual_part_minor)->toBe(7000);

    $this->mutual->run(function () {
        $svc = app(GuaranteeVersioningService::class);
        $g = Guarantee::find($this->ids['g']->id);
        expect(GuaranteeVersion::find($this->ids['v']->id)->locked)->toBeTrue();   // utilisée par une prestation
        $new = $svc->editableForCatalog($g, now()->addDay()->toDateString(), 'Pharmacie portée à 60 %');
        expect($new->id)->not->toBe($this->ids['v']->id)->and($new->version_no)->toBe(2)->and($new->change_note)->toBe('Pharmacie portée à 60 %');
        // Garanties, plafonds et carences recopiés avec la MÊME identité (rule_key).
        expect($new->rules()->count())->toBe(4)->and($new->limits()->count())->toBe(2)->and($new->waitingPeriods()->count())->toBe(1);
        $new->rules()->where('rule_key', $this->ids['rPhar']->rule_key)->update(['coverage_rate' => 60]);
        // Une 2e modification avant tout usage ne recrée pas de version.
        expect($svc->editableForCatalog($g, now()->addDay()->toDateString())->id)->toBe($new->id);
    });
    tenancy()->end();

    // La prestation déjà réalisée garde son calcul ; les soins d'aujourd'hui restent sur la v1.
    expect($this->mutual->run(fn () => Prestation::find($first->id)->mutual_part_minor))->toBe(7000);
    expect(mpcCapture($this->ids['pharma']->id, 10000)['prestation']->mutual_part_minor)->toBe(7000);
    // Soins de demain : v2 à 60 %, et le plafond familial continue de se consommer (14 000 déjà pris).
    $this->travelTo(now()->addDay());
    $this->today = now()->toDateString();
    $r = mpcCapture($this->ids['pharma']->id, 30000);
    expect($r['prestation']->mutual_part_minor)->toBe(16000);   // 60 % = 18 000, mais reste 30 000 − 14 000 = 16 000
});

it('scénario 8 : dérogation justifiée (4 yeux) — dispense de carence et plafond supplémentaire, rendue au rejet', function () {
    $this->mutual->run(function () {
        CoverageException::create(['member_id' => $this->ids['m']->id, 'kind' => 'waiting_waiver', 'service_category_id' => $this->ids['hosp']->service_category_id,
            'valid_from' => $this->today, 'reason' => 'Transfert d’une autre mutuelle', 'justification' => 'Attestation de couverture continue fournie.',
            'status' => 'requested', 'requested_by' => 1]);
        CoverageException::create(['member_id' => $this->ids['m']->id, 'kind' => 'limit_extra', 'service_category_id' => $this->ids['pharma']->service_category_id,
            'amount_minor' => 5000, 'valid_from' => $this->today, 'reason' => 'Traitement chronique', 'justification' => 'Avis du médecin-conseil.',
            'status' => 'approved', 'requested_by' => 1, 'decided_by' => 2, 'decided_at' => now()]);
    });
    tenancy()->end();
    // Dérogation seulement DEMANDÉE : aucun effet.
    expect(mpcCapture($this->ids['hosp']->id, 10000)['status'])->toBe('waiting');
    $this->mutual->run(fn () => CoverageException::where('kind', 'waiting_waiver')->update(['status' => 'approved', 'decided_by' => 2, 'decided_at' => now()]));
    $h = mpcCapture($this->ids['hosp']->id, 10000);
    expect($h['ok'])->toBeTrue()->and($h['prestation']->coverage_exception_id)->not->toBeNull();

    // Plafond familial épuisé à 2 000 près, puis 5 000 supplémentaires par dérogation.
    mpcCapture($this->ids['pharma']->id, 40000);                                         // 28 000 ; reste 2 000
    $p = mpcCapture($this->ids['pharma']->id, 10000);                                     // 7 000 au taux : 2 000 + 5 000 de dérogation
    expect($p['prestation']->mutual_part_minor)->toBe(7000)->and($p['prestation']->exception_used_minor)->toBe(5000);
    expect($this->mutual->run(fn () => CoverageException::where('kind', 'limit_extra')->value('used_minor')))->toBe(5000);
    // La règle de la formule n'est pas modifiée.
    expect($this->mutual->run(fn () => CoverageLimit::where('label', 'Pharmacie familiale')->value('amount_minor')))->toBe(30000);

    // Rejet de la prestation : la part de dérogation est rendue.
    $this->mutual->run(function () use ($p) {
        $pr = Prestation::find($p['prestation']->id);
        $pr->update(['status' => 'rejected']);
        app(ClaimCaptureService::class)->releaseException($pr);
        expect(CoverageException::where('kind', 'limit_extra')->value('used_minor'))->toBe(0);
    });
});

it('scénario 10 : renouvellement continu sans nouvelle carence', function () {
    $this->mutual->run(function () {
        // Adhérent depuis 2 ans ; nouvelle souscription il y a 10 jours.
        Member::whereKey($this->ids['m']->id)->update(['joined_at' => now()->subYears(2)->toDateString()]);
    });
    tenancy()->end();
    expect(mpcCapture($this->ids['hosp']->id, 10000)['status'])->toBe('waiting');            // sans renouvellement continu
    $this->mutual->run(fn () => GuaranteeVersion::whereKey($this->ids['v']->id)->update(['renewal_waiver' => true]));
    expect(mpcCapture($this->ids['hosp']->id, 10000)['ok'])->toBeTrue();
});

it('scénario 12 : deux demandes successives sur le même plafond ne consomment jamais deux fois le reste', function () {
    mpcCapture($this->ids['pharma']->id, 40000);                                           // 28 000 ; reste 2 000
    $a = mpcCapture($this->ids['pharma']->id, 10000);
    $b = mpcCapture($this->ids['pharma']->id, 10000, [], $this->ids['child']->id);
    expect($a['prestation']->mutual_part_minor)->toBe(2000)->and($b['ok'])->toBeFalse()->and($b['status'])->toBe('limit_reached');
    expect($this->mutual->run(fn () => (int) Prestation::sum('mutual_part_minor')))->toBe(30000);
});

it('scénario 12 (simultané) : tant qu’une demande tient le plafond, une 2e demande ne peut pas le consommer', function () {
    mpcCapture($this->ids['pharma']->id, 40000);                                           // 28 000 ; reste 2 000
    // Demande A « en cours » : une AUTRE connexion à la base de la mutuelle tient le verrou de l'adhérent,
    // exactement comme la transaction de saisie (lockForUpdate) d'une requête concurrente.
    [$cfg, $table] = $this->mutual->run(fn () => [\DB::connection()->getConfig(), \DB::connection()->getTablePrefix() . 'members']);
    $other = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']}", $cfg['username'], $cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $other->beginTransaction();
    $other->prepare("SELECT id FROM `{$table}` WHERE id = ? FOR UPDATE")->execute([$this->ids['m']->id]);

    // Demande B : elle attend le verrou (1 s ici) et échoue sans rien consommer.
    $blocked = false;
    try {
        $this->mutual->run(function () {
            \DB::statement('SET SESSION innodb_lock_wait_timeout = 1');   // même connexion que la saisie
            $claim = CareClaim::create(['member_id' => $this->ids['m']->id, 'dependent_id' => $this->ids['child']->id, 'opened_on' => $this->today, 'status' => 'open']);
            app(ClaimCaptureService::class)->capture($claim, ['act_type_id' => $this->ids['pharma']->id, 'provider_id' => 1, 'care_date' => $this->today,
                'total_minor' => 10000], \App\Models\Tenant\User::where('email', 'saisie@equipe.test')->first());
        });
    } catch (\Illuminate\Database\QueryException $e) {
        $blocked = str_contains($e->getMessage(), 'Lock wait timeout');
    }
    expect($blocked)->toBeTrue();
    expect($this->mutual->run(fn () => (int) Prestation::sum('mutual_part_minor')))->toBe(28000);

    // A se termine : B passe ensuite et ne prend que le reste réel du plafond.
    $other->rollBack();
    $b = mpcCapture($this->ids['pharma']->id, 10000, [], $this->ids['child']->id);
    expect($b['prestation']->mutual_part_minor)->toBe(2000);
    expect($this->mutual->run(fn () => (int) Prestation::sum('mutual_part_minor')))->toBe(30000);
});

it('estime au portail selon la formule paramétrée et refuse avec le statut du moteur', function () {
    $conv = conventioned();
    tenancy()->end();
    staffUser('accueil@csi.test', ['provider'], ['care_provider_id' => $conv->care_provider_id]);
    $res = as_('accueil@csi.test')->post($this->host . '/prestataire/verifier', ['code' => 'MUT-000501'])
        ->assertOk()->assertSee('DROITS ACTIFS')->assertSee('Garanties pour ce bénéficiaire')->assertSee('Pharmacie familiale')
        ->assertSee('carence jusqu’au', false);
    $check = $this->mutual->run(fn () => \App\Models\Tenant\RightsCheck::latest('id')->value('id'));
    as_('accueil@csi.test')->post($this->host . "/prestataire/estimer/{$check}", ['act_type_id' => $this->ids['hosp']->id, 'total' => '100000'])
        ->assertOk()->assertSee('DÉLAI DE CARENCE EN COURS')->assertSee('Fin du délai de carence');
    as_('accueil@csi.test')->post($this->host . "/prestataire/estimer/{$check}", ['act_type_id' => $this->ids['hosp']->id, 'total' => '100000', 'emergency' => 1])
        ->assertOk()->assertSee('DROITS ACTIFS')->assertSee('Hospitalisation');
    // Rien n'est enregistré par une estimation.
    expect($this->mutual->run(fn () => Prestation::count()))->toBe(0);
});
