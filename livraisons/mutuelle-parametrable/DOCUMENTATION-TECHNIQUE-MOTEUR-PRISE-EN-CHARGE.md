# MX-CONNECT — Catalogue des formules et moteur de prise en charge

Documentation technique de la livraison L1–L2 de la mission « Mutuelle paramétrable » (VERSION 2026.11.18).

## 1. Principes

- **Rien n'est codé en dur.** Formules, garanties, taux, plafonds, carences et dérogations sont saisis par l'administrateur de chaque mutuelle. Un paramètre absent ne reçoit jamais de valeur par défaut : par exemple, un taux est obligatoire pour créer une garantie.
- **Aucun effet rétroactif.** Une version de formule déjà utilisée par une prestation est verrouillée. Toute modification ouvre une nouvelle version, à une date d'effet choisie. Une prestation est calculée avec la version en vigueur à la date des soins.
- **Compatibilité.** Une version **sans** garantie détaillée garde le calcul existant, inchangé :
  - taux unique ;
  - `ceilings` ;
  - période d'observation ;
  - `ClaimAssessmentService::assess()`.

  Le moteur ne s'applique qu'aux versions qui ont au moins une garantie détaillée (`GuaranteeVersion::hasCatalog()`).
- **Calcul côté serveur uniquement.** L'interface n'affiche que le résultat du moteur.
- **Isolation.** Toutes les nouvelles tables sont des tables de la mutuelle : base dédiée ou tables préfixées. Aucune requête ne lit les données d'une autre mutuelle.

## 2. Schéma (migration TENANT `2026_11_18_000100_coverage_catalog`, additive)

| Table / colonnes | Rôle |
|---|---|
| `service_categories` | Bibliothèque des catégories de soins. 24 entrées sont créées (libellés seulement, aucun taux). La mutuelle peut en ajouter. |
| `act_types.service_category_id` | Rattachement d'un acte à une catégorie. |
| `guarantees` + `catalog_status`, `category`, `target_population`, `min_age`, `max_age`, `max_beneficiaries`, `sale_from`, `sale_to`, `is_example` | Fiche de la FORMULE. |
| `guarantee_versions` + périodicités `semiannual` et `custom` (`period_months`), `senior_age`, `renewal_waiver`, `created_by`, `change_note` | Paramètres versionnés. |
| `coverage_rules` | GARANTIES d'une version : catégorie de soins (vide = toutes), catégorie de bénéficiaire, taux, ticket modérateur, carence « accident », urgence exemptée, base limitée au tarif conventionnel, conditions. Le `rule_key` (ULID) reste le même d'une version à la suivante. |
| `coverage_limits` | PLAFONDS : `scope` (act / day / month / year), `holder` (beneficiary / family), `amount_minor` et/ou `max_count`. `rule_keys` vide = toutes les garanties ; plusieurs clés = plafond partagé. |
| `waiting_periods` | DÉLAIS DE CARENCE par `rule_key` (vide = toutes les garanties) et catégorie de bénéficiaire. |
| `coverage_exceptions` | DÉROGATIONS : `waiting_waiver`, `limit_extra`, `exclusion_override`. Statuts : requested, approved, rejected, revoked. |
| `dependents` + `is_student`, `joined_on`, `left_on` | Catégorie « élève », départ de la carence d'un ayant droit, sortie. |
| `prestations` + `coverage_rule_id`, `eligible_minor`, `copay_minor`, `non_covered_minor`, `emergency`, `accident`, `coverage_exception_id`, `exception_used_minor` | Trace complète du calcul. |

Le `down()` est tolérant : il retire seulement les colonnes présentes. L'énumération `periodicity` élargie est conservée.

Attribut CENTRAL `mutuals.is_test` (colonne virtuelle `data`) : MUTUELLE TEST, voir le § 7.

## 3. Moteur — `App\Services\Tenant\CoverageEngine`

### 3.1 Statuts (8)

`active` DROITS ACTIFS · `not_activated` DROITS NON ACTIVÉS · `waiting` DÉLAI DE CARENCE EN COURS · `limit_reached` PLAFOND ÉPUISÉ · `expired` ADHÉSION EXPIRÉE · `suspended` ADHÉSION SUSPENDUE · `not_covered` GARANTIE NON COUVERTE · `authorization` AUTORISATION ADMINISTRATIVE REQUISE.

Le statut `authorization` est réservé à la livraison L3 (préautorisations). Il n'est encore jamais produit.

### 3.2 `membership(member, dependent, date, providerId)`

Les contrôles s'appliquent dans cet ordre :

1. **Adhérent sorti ou radié** → `expired`.
2. **Ayant droit** :
   - retiré ou sorti → `expired` (`dependent_removed`) ;
   - pas encore intégré à la date → `not_activated`.
3. **Adhérent suspendu** → `suspended`.
4. **Pas de souscription validée en vigueur à la date** :
   - souscription saisie, ou validée avec une date d'effet future → `not_activated` ;
   - sinon → `expired`.

   Un bénéficiaire n'est jamais couvert automatiquement.
5. **Aucune version de formule à la date** → `expired`.
6. **Échéance de cotisation impayée et échue** → `suspended`.
7. **Prestataire hors du réseau** de la version → `not_covered`.

### 3.3 `evaluate()` puis `compute(version, ctx)`

1. **Garantie applicable** (`ruleFor`) :
   - d'abord la catégorie de soins de l'acte, puis « toutes prestations » ;
   - pour chacune, la catégorie de bénéficiaire la plus précise, dans l'ordre : nouveau conjoint, élève, enfant 0–5 / 6–17, senior, adulte, tous.

   Aucune garantie → `not_covered` (`no_rule`).
2. **Exclusions** de la version (`exclusions`) → `not_covered`, sauf dérogation `exclusion_override` accordée.
3. **Carence** (`waitingDays`) :
   - accident avec `accident_waiting_days` défini sur la garantie → ce délai ;
   - sinon le délai le plus précis (garantie + catégorie), puis « toutes garanties », puis `observation_days` de la version.

   Point de départ (`coverageStart`) :
   - date d'effet de la souscription, ou arrivée de l'ayant droit si elle est plus tardive ;
   - avec `renewal_waiver`, date de 1re adhésion (`members.joined_at`).

   **Urgence** (`emergency`) sur une garantie qui l'exempte → pas de carence.

   Carence non échue → `waiting` avec `waiting_until`, sauf dérogation `waiting_waiver` accordée.
4. **Montants** :
   - éligible = facturé, ou min(facturé, tarif conventionnel) si `tariff_cap` ;
   - base = éligible × taux ;
   - ticket modérateur = éligible − base.
5. **Plafonds** applicables à la garantie (`appliesTo`) :
   - le reste se calcule par plafond en montant ;
   - nombre atteint → part mutuelle 0 ;
   - une dérogation `limit_extra` s'ajoute au reste, sans toucher à la règle.

   Résultat 0 alors que la base > 0 → `limit_reached` (`ceiling_reached` ou `count_reached`).
6. **Résultat** : `billed`, `eligible`, `mutual`, `copay`, `non_covered` (hors tarif + au-delà du plafond), `patient`, `limits` (reste avant et après), `exception`, `exception_extra_used`.

### 3.4 Consommation des plafonds (`consumed`)

- **Prestations comptées** : statuts `captured`, `controlled` et `validated`. Une prestation rejetée libère sa part.
- **Fenêtre** : jour, mois ou **année civile**.
- **Titulaire** : le bénéficiaire (adhérent seul ou ayant droit) ou toute la famille.
- **Garanties concernées** : les `rule_key` du plafond, **toutes versions de la formule confondues**. La consommation continue donc après un changement de version.

### 3.5 Saisie — `ClaimCaptureService::captureWithEngine`

Elle sert au back-office et au portail prestataire. Tout se fait dans une transaction :

1. verrouillage `lockForUpdate` de la ligne de l'adhérent ;
2. évaluation ;
3. création de la prestation avec la trace du calcul ;
4. `used_minor` de la dérogation incrémenté ;
5. version verrouillée ;
6. contrôle anti-fraude.

Deux saisies simultanées pour une même famille sont traitées l'une après l'autre (scénario 12).

Refus → aucun enregistrement ; les motifs sont affichés.

Rejet d'une prestation → `releaseException()` rend la part de dérogation consommée.

### 3.6 Autres branchements

- `RightsVerificationService` : pour une version à garanties détaillées, les contrôles 4 (observation) et 5 (exclusion) sont laissés au moteur, qui tient compte de l'acte, de l'urgence et des dérogations.
- `CoverageService` (portail prestataire) :
  - `situation()` ajoute `catalog` : garanties applicables à CE bénéficiaire, carences en cours, plafonds avec le reste ;
  - `estimate()` utilise le moteur, avec ayant droit, urgence et accident.
- Formulaires de déclaration (back-office et portail) : cases « Urgence » et « Accident ».

## 4. Catalogue — `FormulaCatalogController` (routes `formulas.*`, URL `/formules`)

| Route | Droit |
|---|---|
| `formulas.index`, `formulas.show`, `formulas.categories`, `formulas.simulate` | A, E, C, B, T |
| toutes les autres `formulas.*` : fiche, paramètres, garanties, plafonds, carences, catégories, rattachement des actes, formules d'exemple | A |

- **Version utilisée.** Toute écriture exige `effective_from` (et accepte `change_note`). Elle passe par `GuaranteeVersioningService::editableForCatalog()` :
  - version non verrouillée → modifiée sur place ;
  - sinon → nouvelle version, avec `copyCatalog()` (garanties, plafonds, carences, même `rule_key`).

  La ligne modifiée est retrouvée dans la nouvelle version par `rule_key` (garanties) ou par ses attributs (plafonds, carences).
- **Garde-fous** :
  - une seule garantie par catégorie de soins × catégorie de bénéficiaire ;
  - un plafond exige un montant et/ou un nombre ;
  - un plafond « par acte » se fixe en montant ;
  - retirer une garantie retire ses carences et la retire des plafonds qui la visent ;
  - un plafond qui ne visait qu'elle est retiré. Il ne devient jamais un plafond « toutes garanties ».
- **Simulateur** : `CoverageEngine::compute()` avec un contexte saisi (âge, élève, nouveau conjoint, ancienneté, déjà consommé, urgence, accident). Rien n'est enregistré.
- **Rattachement des actes** : il est proposé d'après le code de la liste de référence (`CS-`, `PHA-`, `LAB-`…), sinon d'après la catégorie libre. Rien n'est enregistré avant la validation par l'administrateur.

## 5. Dérogations — `CoverageExceptionController` (routes `derogations.*`, URL `/derogations`)

| Route | Droit |
|---|---|
| `index` | A, C, B |
| `store` | A, B |
| `approve` / `reject` / `revoke` | A, C |

- Une demande comporte un motif et une justification (pas de diagnostic).
- **4 yeux** : le demandeur ne peut jamais décider sa propre demande. C'est contrôlé côté serveur.
- Un refus ou un retrait exige un motif.
- Rien n'est supprimé. Les modèles sont `Auditable`.

## 6. Souscription

`Guarantee::subscriptionRefusal()` est appliqué côté serveur (`SubscriptionController::store`) et filtre la liste de création. Une nouvelle souscription est refusée dans ces cas :

- statut autre qu'« Active », ou autre qu'« En test » dans la mutuelle de test ;
- hors période de vente ;
- âge hors limites ;
- trop de bénéficiaires.

Les adhésions en cours ne sont jamais touchées.

## 7. MUTUELLE TEST

- **Marquage** : super-admin → fiche mutuelle → « Mutuelle de test », motif obligatoire (`network.mutuals.test`, `MutualProvisioningService::setTestFlag`). Le changement est tracé dans l'historique (`test_on` / `test_off`).
- **Repère visuel** : `App\Support\TestEnvironment::active()` affiche le bandeau « Environnement de test » dans le back-office.
- **Formules d'exemple** (`App\Services\Tenant\ExampleFormulaService`) : seulement dans la mutuelle de test, marquées « Exemple ». Seules les valeurs de la mission sont reprises :
  - **FAMILLE** : taux 80 / 70 / 80 / 80 / 70 %, tableau des carences, plafond pharmacie de 30 000 ;
  - **ESSENTIEL** et **SCOLAIRE** : plafonds de 25 000 et 15 000, enregistrés INACTIFS, sans taux inventé ;
  - **CONFORT**, **SENIOR**, **PERSONNALISÉE** : brouillons ;
  - la cotisation est à 0 dans toutes les formules : elle reste à fixer.
- **Non inclus** : la réinitialisation de la mutuelle de test (effacement), prévue en L6 avec une confirmation explicite.

## 8. Tests

- `tests/Feature/MoteurPriseEnChargeTest.php` (9 tests) : scénarios 2, 3, 4, 6, 7, 8, 10, 11 (urgence et accident) et 12, plus l'estimation au portail.
- `tests/Feature/CatalogueFormulesTest.php` (5 tests) : paramétrage par l'interface, versionnement, matrice des droits, dérogations « 4 yeux », MUTUELLE TEST et refus de souscription.
- Les deux fichiers passent en base dédiée et en tables préfixées (`MX_TEST_STORAGE=prefix`).

## 9. Limites connues

- La simultanéité réelle (deux processus) n'est pas rejouée en Pest. La sérialisation repose sur le verrou de ligne InnoDB dans la transaction de saisie. Le test vérifie que deux saisies successives ne consomment pas deux fois le même reste.
- L'année des plafonds est l'année civile, pas l'année d'adhésion.
- Les plafonds par prestataire et les plafonds par ordonnance (pharmacie) sont prévus en L3.
- La catégorie « senior » n'existe que si l'âge senior est fixé dans la version.
