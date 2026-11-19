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

Le statut `authorization` est produit depuis la livraison L3 (§ 10) : garantie soumise à accord préalable, sans accord utilisable, hors urgence.

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


---

# Livraison L3 (VERSION 2026.11.19) : accords préalables, pharmacie, médecin-conseil

## 10. Accords préalables

### 10.1 Paramètres (garantie d'une formule)

`coverage_rules` reçoit trois colonnes :

- `requires_authorization` : un accord préalable est exigé ;
- `authorization_review` : `administrative` ou `medical` ;
- `authorization_above_minor` : l'accord n'est exigé qu'au-delà de ce montant facturé (vide = toujours).

Ces colonnes se règlent dans le catalogue, avec la garantie, et sont versionnées comme elle.

### 10.2 Table `preauthorizations` (tenant)

| Colonne | Rôle |
|---|---|
| `number` | AUT-AAAA-NNNNN, attribué à l'accord (séquentiel par année, sous verrou) |
| `member_id`, `dependent_id`, `provider_id`, `act_type_id`, `coverage_rule_id`, `guarantee_version_id` | Objet de la demande |
| `planned_on`, `estimated_minor`, `estimated_mutual_minor` | Soins prévus et estimation du moteur |
| `approved_minor` | Part mutuelle accordée au plus (vide = calcul de la garantie) |
| `review_type`, `status` | requested, approved, rejected, used, expired, cancelled |
| `emergency` | Régularisation d'une urgence soignée sans accord |
| `clinical_info` | DONNÉE DE SANTÉ : affichée seulement avec `capacite.donnees-sante`, exclue du journal d'audit |
| `auto_checks` | Résultat des contrôles automatiques, sans donnée médicale |
| `source`, `requested_by`, `decided_by`, `decided_at`, `decision_note`, `valid_until`, `prestation_id` | Traçabilité |

### 10.3 Déroulement (`App\Services\Tenant\PreauthorizationService`)

1. **Demande** : par le portail (`portal.preauth.create/store`, après une vérification des droits de moins de 12 h) ou par la mutuelle (`preauth.store`).
2. **Contrôles automatiques** : `CoverageEngine::evaluate(..., skip_authorization)` à la date prévue. Le résultat est conservé (`auto_checks`).
3. **Décision** (`decide`) :
   - jamais par la personne qui a saisi la demande ;
   - examen `medical` = rôle `medical_advisor` seulement ;
   - un rejet exige un motif ;
   - un accord reçoit son numéro, un montant accordé facultatif, et une validité : date choisie, sinon réglage `preauth_validity_days`, sinon sans limite.
4. **Notification** : le prestataire voit la décision dans « Accords préalables » de son portail (numéro, montant, validité, motif). Aucun e-mail n'est envoyé.
5. **Utilisation** : le moteur cherche un accord accordé, non utilisé et valide (`authorizationFor`) :
   - pour ce bénéficiaire ;
   - pour cet acte ou cette garantie (même `rule_key`) ;
   - pour cet établissement.

   La saisie le passe à `used` et le lie à la prestation : un accord ne sert qu'une fois. Une prestation rejetée le rend de nouveau utilisable (`releaseException`).

### 10.4 Urgence (scénario 11)

Si la garantie exige un accord, qu'aucun accord n'existe et que la case **Urgence** est cochée :

- la prise en charge est calculée et enregistrée : les soins ne sont **jamais bloqués** ;
- la prestation porte `authorization_missing = true` ;
- une demande `emergency` est ouverte et liée à la prestation ;
- un signal `emergency_without_authorization` est émis (à examiner, jamais un refus).

La **validation** de la prestation attend que la régularisation soit examinée. Une régularisation accordée lève l'indicateur.

## 11. Pharmacie

### 11.1 Schéma (tenant)

- `medicines` + `eligible` (liste positive), `excluded`, `generic`, `max_quantity` (par délivrance).
- `prescriptions` : n°, prescripteur, date, renouvellements autorisés. L'empreinte `reference_key` est UNIQUE : sha256 du n° normalisé (majuscules, sans espaces ni ponctuation), du bénéficiaire et de la date.
- `dispensations`, liées à une prestation.
- `dispensation_lines` : quantités prescrite et délivrée, prix unitaire, ligne prise en charge ou non (`excluded`, `not_listed`), prix au-dessus de la référence.
- Les lignes sont aussi copiées dans `prescribed_medicines` (statistiques existantes).
- `mutual_settings` (clé → valeur ; clé absente = règle non appliquée) :
  - `prescription_validity_days` ;
  - `dispensation_watch_days` ;
  - `preauth_validity_days`.

### 11.2 Délivrance (`App\Services\Tenant\PharmacyService::dispense`)

Tout se fait dans une transaction : l'ordonnance est créée ou relue SOUS VERROU (une collision d'unicité est relue). Puis :

- **Refus** (`AccountingRefusal`) :
  - ordonnance future ou périmée (selon le réglage) ;
  - quantité délivrée supérieure à la quantité prescrite ;
  - quantité cumulée (prestations non rejetées, toutes pharmacies) supérieure à prescrite × (1 + renouvellements). C'est le cas d'une **ordonnance déjà utilisée** (scénario 5).
- **Hors base** (part du patient) :
  - médicament exclu ou hors liste ;
  - quantité au-delà de `max_quantity`.

  Ce montant est transmis au moteur (`excluded_minor`). Le calcul classique le respecte aussi.
- **Prise en charge** : par `ClaimCaptureService`, donc carence, plafonds de pharmacie, accord préalable et urgence s'appliquent.
- **Signaux** (jamais des refus) :
  - prix au-dessus du prix de référence (`above_reference_price`) ;
  - même médicament délivré au même bénéficiaire dans les N jours (`close_redispensing`, si le réglage existe).

Une prestation rejetée libère ses quantités.

## 12. Médecin-conseil

- Rôle `medical_advisor` (`TenantAccess::D`), ajouté aux rôles de chaque mutuelle par la migration.
- MFA obligatoire. Rôle incompatible avec le trésorier-comptable.
- Droits :
  - recours et détail médical (`capacite.donnees-sante`) ;
  - accords préalables (consulter, décider) ;
  - dérogations (consulter, décider) ;
  - registre des délivrances.
- Il est seul à décider un examen médical.

## 13. Routes et droits ajoutés

| Route | Droit |
|---|---|
| `preauth.index`, `preauth.show` | A, C, B, D |
| `preauth.store` | A, B |
| `preauth.decide` | A, C, D |
| `pharmacy.index` | A, C, B, D |
| `pharmacy.policy`, `pharmacy.policy.update`, `pharmacy.medicines` | A |
| `portal.preauth` | rôle provider ; droit d'établissement `claims.view` |
| `portal.preauth.create/store/cancel`, `portal.pharmacy`, `portal.pharmacy.store` | rôle provider ; droit d'établissement `care.declare` |

Migration TENANT `2026_11_19_000100_preauth_pharmacy` (additive).

Tests : `tests/Feature/AccordsPrealablesPharmacieTest.php` (6 tests), dont les scénarios 5 et 11.


---

# Livraison L4 (VERSION 2026.11.20) : portail adhérent

## 14. Architecture

- **Où.** Le portail est dans le compte CENTRAL MX-CONNECT (garde `member`, `PublicAccount`), sous `/compte/mon-adhesion`, et donc aussi dans l'application Android (TWA). C'est la décision du fondateur.
- **Lien compte ↔ adhérent.** Table centrale `member_links`, existante, unique par (compte, mutuelle). Un adhérent n'est relié qu'à UN compte : c'est contrôlé à l'activation.
- **Accès** (`App\Services\Social\MemberPortalService::resolve`) : la mutuelle est désignée par son slug, et le lien doit exister pour ce compte. Sinon la réponse est **404**. C'est le scénario 9 : aucune lecture des données d'une autre mutuelle, ni d'un autre adhérent.
- **Lecture** : `$mutual->run()` puis `App\Services\Tenant\MemberSelfService`. Toutes les requêtes sont filtrées sur `member_id`, ou sur les recours de l'adhérent. La page est rendue DANS la mutuelle.
- **Activation** (table CENTRALE `member_activation_codes`, migration `2026_11_20_000100`) :
  - le personnel (`members.portal.code`, A/E) génère un code de 12 caractères, sans caractères ambigus, valable 30 jours ;
  - seule l'empreinte sha256 est stockée, et un seul code est actif par adhérent ;
  - l'adhérent saisit le code et la date de naissance de l'adhérent principal (limiteur `member-activation` : 5 essais / 10 min) ;
  - un refus donne un message unique, sans indice.

  Le personnel peut aussi retirer l'accès (`members.portal.revoke`).
- **Aucun paiement en ligne** : l'agrégateur n'est pas choisi. Les reçus sont ceux enregistrés par la mutuelle.

## 15. Fonctions

| Mission | Implémentation |
|---|---|
| 2.1 Tableau de bord | `dashboard()` : statut (moteur `membership`), formule, dates, cotisations (retard, prochaine échéance), bénéficiaires, plafonds annuels et mensuels (consommé, reste, barres), soins de l'année |
| 2.2 Profil | Lecture ; coordonnées par demande (`member_change_requests`, kind `contact`) ; photo = avatar du compte ; attestation imprimable ; carte ; historique des demandes. La pièce d'identité, chiffrée, n'est jamais affichée |
| 2.3 Bénéficiaires | Liste (couvert / retiré, intégration, sortie, consommation de l'année) ; ajout, modification, retrait par demande. Rien n'est appliqué avant validation (`member-requests.*`, A/E). L'ajout crée l'ayant droit avec `joined_on` = jour de la validation. « Enfant adopté » est enregistré « enfant » |
| 2.4 Carte | Carte existante (`tenant.card.partials.card`) ; QR = jeton signé `DigitalCardService`, URL de vérification sur le SOUS-DOMAINE de la mutuelle (`$verifyUrl`). PDF = impression du navigateur |
| 2.5 Garanties | `guarantees()` : formule paramétrée (taux, ticket modérateur, délai d'attente et sa fin, accord préalable, tarif, conditions, plafonds, exclusions, réseau) ou formule classique |
| 2.6 Prestataires | Conventions actives de la mutuelle, limitées au réseau de la formule ; filtres type, ville, nom |
| 2.7 Prestations | Liste et détail (montants, ticket modérateur, non couvert, accord, médicaments délivrés, motif de rejet) |
| 2.8 Cotisations | Échéances, payé, impayés, paiements (annulés signalés), reçu par encaissement (`collection_ref`) |
| 2.9 Réclamations | `complaints.channel = online`, `category`, `public_account_id` ; échanges `complaint_messages` ; réponse au back-office (`complaints.reply`) |
| 2.10 Notifications | `App\Support\MemberNotifier` (dans la mutuelle → `NotificationService`, application + e-mail). Cas couverts : activation, paiement enregistré, prestation validée ou rejetée, demande décidée, réponse ou changement d'état d'une réclamation. JAMAIS de donnée médicale. Point d'entrée unique pour brancher plus tard SMS et WhatsApp |

## 16. Routes

**Compte central** (`social.member.*`) :

| Méthode | URL |
|---|---|
| GET | `/compte/mon-adhesion` |
| POST | `/compte/mon-adhesion/activer` |
| GET | `/compte/mon-adhesion/{mutuelle}`, puis `/profil`, `/attestation`, `/carte`, `/beneficiaires`, `/garanties`, `/prestataires`, `/prestations`, `/prestations/{id}`, `/cotisations`, `/recus/{id}`, `/reclamations`, `/reclamations/{id}` |
| POST | `/coordonnees`, `/beneficiaires`, `/reclamations`, `/reclamations/{id}/message` |

**Mutuelle** :

| Route | URL | Droit |
|---|---|---|
| `members.portal.code`, `members.portal.revoke` | — | A, E |
| `member-requests.index` | `/demandes-adherents` | A, E |
| `member-requests.decide` | — | A, E |
| `complaints.reply` | — | A, E, C, M |

Migration TENANT `2026_11_20_000100_member_portal` : `member_change_requests`, `complaint_messages`, et `complaints` (canal `online`, `category`, `public_account_id`).

Tests : `tests/Feature/PortailAdherentTest.php` (4 tests, scénarios 1 et 9).

---

# Livraison L5 (VERSION 2026.11.21) : tableau de bord de pilotage

## 17. Architecture

- **Service** `App\Services\Tenant\PilotageService::build($from, $to, $filtres)`. Il complète le rapport d'activité (`ActivityReportService`, inchangé) ; il ne le remplace pas.
- **Chiffres réels** uniquement, calculés à la demande dans la base de la mutuelle. Aucune table ni migration. Montants en unités mineures de la devise de la mutuelle, sans conversion.
- **Requêtes groupées** (par statut, par version, par garantie, par prestataire, par recours), jamais une requête par adhérent. Le SQL brut se limite à des noms de colonnes : compatible avec les tables préfixées.
- **Écran** `tenant/reports/dashboard` (URL `/pilotage`), en tête du menu (groupe « Pilotage »). La page d'arrivée après connexion ne change pas (`TenantAccess::home` saute `reports.dashboard`).
- **Export Excel** : `App\Support\SimpleXlsx`, un vrai classeur `.xlsx` (Office Open XML) écrit sans bibliothèque externe. Il exige l'extension PHP `zip` ; sinon l'export bascule en CSV. **PDF** : bouton « Imprimer ou PDF » (impression du navigateur, filtres masqués).

## 18. Définitions des indicateurs

| Indicateur | Calcul |
|---|---|
| Adhérents | Fiches du périmètre filtré. Bénéficiaires = adhérents actifs ou suspendus + ayants droit actifs |
| Adhésions actives | Souscription validée, en vigueur, sans échéance échue impayée |
| Adhésions suspendues | Fiche « suspendue », ou souscription validée avec une échéance échue impayée (même règle que la vérification des droits) |
| Adhésions expirées | Fiche « sortie » ou « radiée », ou aucune souscription en cours |
| En attente | Souscription saisie, non validée |
| Cotisations attendues | Échéances de cotisation (hors droit d'adhésion) dont la date tombe dans la période |
| Taux de recouvrement | Payé sur ces échéances ÷ attendu. « Encaissé sur la période » = paiements datés de la période, quelle que soit l'échéance. Les encaissements annulés sont exclus |
| Prestations facturées | Montant total de toutes les prestations de la période, quel que soit leur statut |
| Prise en charge | Part mutuelle des prestations **validées** |
| Tickets modérateurs | `copay_minor` des prestations validées (à défaut, la part de l'adhérent) |
| Taux de sinistralité | Prise en charge ÷ cotisations encaissées sur la période |
| Par formule / par garantie | Prestations validées groupées par version de formule, puis par garantie (`coverage_rule_id`). Les formules sans garanties détaillées sont sur une ligne « Formules à taux unique » |
| Pharmacie | Délivrances de la période : nombre, montant, non pris en charge, part mutuelle validée, médicaments les plus délivrés |
| Plafonds consommés | Plafonds ANNUELS en montant des versions en cours. Pour chaque titulaire (famille ou bénéficiaire) : parts mutuelle saisies, contrôlées et validées de l'année civile. On compte les titulaires à ≥ 80 % et à 100 % |

## 19. Filtres, droits, routes

- **Filtres** : période (mois en cours ou précédent, trimestre, année en cours ou précédente, dates au choix), formule, prestataire, antenne (zone), type de soins (catégorie de la bibliothèque), statut de traitement, et adhérent (n°).
- **Région** : non enregistrée sur les fiches. L'antenne tient lieu de zone géographique.
- **Données de santé** : le détail par garantie, la pharmacie, le filtre par adhérent et les feuilles Excel correspondantes exigent `capacite.donnees-sante` (A, C, B, D). Pour les autres, le filtre par adhérent est ignoré côté serveur.

| Route | URL | Droit |
|---|---|---|
| `reports.dashboard` | `/pilotage` | A, C, B, T, U (règle `reports.*`) |
| `reports.dashboard.export` | `/pilotage/export.xlsx` | idem |

Tests : `tests/Feature/TableauDeBordPilotageTest.php` (2 tests, 101 vérifications). Ils couvrent les indicateurs, les filtres, les droits par rôle et le contenu du classeur Excel.

---

# Livraison L6 (VERSION 2026.11.22) : MUTUELLE TEST, rôles, sécurité, scénarios

## 20. Réinitialisation de la MUTUELLE TEST

Service `App\Services\TestDataResetService` (`inventory()`, `reset()`), deux entrées :

| Qui | Écran | Route |
|---|---|---|
| Super-administrateur du réseau | Fiche de la mutuelle → « Réinitialiser les données de test » | `network.mutuals.test.reset` (droit `manageAccess`) |
| Administrateur de la mutuelle | Bandeau « Environnement de test » → `/mutuelle-test` | `test-env.index`, `test-env.reset` (A seul) |

**Garde-fous, tous vérifiés côté serveur :**
1. La mutuelle doit être marquée `is_test`. Sinon refus, et l'écran de la mutuelle répond 404.
2. L'identifiant (slug) de la mutuelle doit être saisi exactement, et le motif doit compter au moins 5 caractères.
3. **Chaque table de la mutuelle est classée** dans l'une de trois listes :
   - `KEEP` : personnes, rôles, plan comptable, listes de référence, conventions, réglages, journaux d'audit ;
   - `FORMULAS` : formules, versions, garanties, plafonds, carences, effacées seulement si l'option est cochée ;
   - `RESET` : toute l'activité.

   Une table non classée bloque l'opération. Un test lit toutes les migrations des mutuelles et vérifie qu'aucune table n'est oubliée : toute nouvelle table devra être classée.
4. **Sauvegarde SQL complète de la mutuelle avant tout effacement** (`BackupService::dumpMutual`, export PHP limité aux tables de la mutuelle, préfixe compris).
   - Elle est rangée dans `storage/app/test-resets/<slug>-AAAAMMJJ_HHMMSS.sql`, côté plateforme, jamais dans le stockage de la mutuelle. Les 5 dernières sont gardées.
   - Si la sauvegarde échoue, rien n'est effacé.
5. L'effacement se fait dans **une transaction**, avec les clés étrangères suspendues le temps de vider. Un verrou empêche deux réinitialisations simultanées. Tout se passe dans `$mutual->run()` : aucune autre mutuelle n'est touchée.
6. **Côté réseau** :
   - les liens compte ↔ adhérent (`member_links`) et les codes d'activation de cette mutuelle sont supprimés ;
   - les demandes d'adhésion en ligne sont gardées, mais déliées de la fiche (`member_id` vidé) ;
   - les pièces PDF des factures de prestataires sont supprimées seulement si le stockage est bien celui de la mutuelle (suffixe `tenant<id>` vérifié).
7. **Traces** :
   - historique de la mutuelle, action `test_reset`, avec motif, auteur, nombre de lignes et nom de la sauvegarde ;
   - `audit_logs` de la mutuelle, action `test_data_reset`, avec le détail par table.

   Les journaux `audits` et `audit_logs` ne sont jamais effacés.
8. **Option « recharger les formules d'exemple »** : `ExampleFormulaService::load()`, après effacement des formules.

## 21. Rôle « gestionnaire des cotisations » (profil 4 de la Partie 7)

`contribution_manager`, lettre `K` dans `TenantAccess`. Libellé et description dans `TenantNav`.

- **Droits** :
  - adhérents et souscriptions en lecture ;
  - cotisations : liste, relevé, export, encaissement au guichet, ancien bouton « payer », échéanciers, droit d'adhésion, demande d'annulation.
- **Pas de droit sur** : trésorerie, comptabilité, recours (santé), rapports, tableau de bord, équipe, formules.
- **Incompatible** avec le contrôleur-validateur : qui encaisse ne valide pas les annulations.
- **MFA obligatoire.**
- **Création du rôle** :
  - par le paramétrage initial des nouvelles mutuelles (`config('mxconnect.mutual_roles')`) ;
  - par la migration TENANT `2026_11_22_000100_contribution_manager_role` pour les mutuelles existantes.

Correspondance des 10 profils de la mission :

| Profil de la mission | MX-CONNECT |
|---|---|
| 1. Super administrateur | `super_admin` (espace réseau, MFA) |
| 2. Administrateur de mutuelle | `mutual_admin` |
| 3. Gestionnaire des adhésions | `enrollment_agent` |
| 4. Gestionnaire des cotisations | `contribution_manager` (nouveau) |
| 5. Gestionnaire des prestations | `benefits_manager` |
| 6. Médecin-conseil | `medical_advisor` |
| 7. Auditeur | `auditor` (lecture seule, sans santé, exclusif) |
| 8. Responsable financier | `treasurer_accountant` |
| 9. Prestataire de santé | espace prestataire central (`provider_accounts`, `ProviderAccess`) + rôle `provider` dans la mutuelle |
| 10. Adhérent | compte MX-CONNECT + portail « Mon adhésion » |

## 22. Sécurité (Partie 7)

| Exigence | Mise en œuvre |
|---|---|
| Authentification sécurisée | Mots de passe hachés, MFA TOTP obligatoire pour tout le personnel, les prestataires et le réseau ; mot de passe provisoire à changer |
| Sessions | Session fermée si le compte est désactivé (`TenantAccountGuard`) ou le rattachement prestataire retiré ; MFA propre à chaque espace |
| Réinitialisation du mot de passe | Par l'administrateur (personnel), par e-mail (membres, prestataires), MFA réinitialisable |
| Permissions côté serveur | `TenantRouteAccess` sur CHAQUE route (route non classée = refus) ; test de couverture ; `ProviderAccess` pour les prestataires |
| Accès inter-mutuelles | Base ou préfixe par mutuelle (stancl) ; portail adhérent : lien vérifié sinon 404 (scénario 9) ; réinitialisation limitée à la mutuelle |
| Journalisation | `audits` (modifications), `rights_checks`, `beneficiary_searches`, `provider_lookups`, `audit_logs` |
| **Traçabilité des accès aux données de santé** (nouveau) | `App\Support\HealthAccessLog` : ouverture d'un recours, d'un accord préalable, d'une pièce de prestataire ou du registre pharmacie par une personne habilitée → `audit_logs` (`health_data_view`, qui, quoi, quand, IP ; une fois par 10 min et par dossier ; aucune donnée médicale copiée). Visible dans **Journal d'audit** (A, C, U) |
| Données médicales limitées | Capacité `capacite.donnees-sante` (A, C, B, D) ; `clinical_info` exclue de l'audit ; notifications et portail sans donnée médicale |
| Sauvegardes | `BackupService` (toutes les bases, 7 conservées) + sauvegarde avant réinitialisation de la MUTUELLE TEST |
| Documents médicaux | Pièces des prestataires sur disque privé de la mutuelle, `%PDF-` contrôlé, téléchargement `sandbox`/`no-store`, droit `claims.invoice.document` (pas le trésorier) |

## 23. Les 12 scénarios de la Partie 10 et leurs tests

| Scénario | Test (fichier → cas) |
|---|---|
| 1. Adhérent actif consulte ses garanties | `PortailAdherentTest` → « scénario 1 » |
| 2. Carence non expirée | `MoteurPriseEnChargeTest` → « scénario 2 et 11 » |
| 3. Plafond disponible | `MoteurPriseEnChargeTest` → « scénario 3 » |
| 4. Dépassement du plafond annuel | `MoteurPriseEnChargeTest` → « scénario 4 » |
| 5. Double facturation d'une ordonnance | `AccordsPrealablesPharmacieTest` → « scénario 5 » |
| 6. Couverture expirée | `MoteurPriseEnChargeTest` → « scénario 6 » |
| 7. Modification de formule → nouvelle version | `MoteurPriseEnChargeTest` → « scénario 7 » ; `CatalogueFormulesTest` (par l'interface) |
| 8. Dérogation justifiée | `MoteurPriseEnChargeTest` → « scénario 8 » ; `CatalogueFormulesTest` (4 yeux, retrait) |
| 9. Accès à une autre mutuelle | `PortailAdherentTest` → « scénario 9 » |
| 10. Renouvellement continu | `MoteurPriseEnChargeTest` → « scénario 10 » |
| 11. Urgence sans autorisation préalable | `AccordsPrealablesPharmacieTest` → « scénario 11 » ; `MoteurPriseEnChargeTest` (carence) |
| 12. Deux demandes simultanées sur le même plafond | `MoteurPriseEnChargeTest` → « scénario 12 » (successives) **et « scénario 12 (simultané) »**. Une autre connexion tient le verrou de l'adhérent, comme une saisie concurrente en cours. La 2e demande attend puis échoue sans rien consommer ; une fois le verrou libéré, elle ne prend que le reste réel |

Commande pour rejouer les scénarios :

```
vendor/bin/pest --filter "scénario"
```

Ajouter `MX_TEST_STORAGE=prefix` pour les rejouer sur une mutuelle à tables préfixées.

**Autres vérifications de la Partie 10 :**
- **Affichage mobile** : navigateur réel à 320, 360, 768 et 1366 px à chaque livraison.
- **Sécurité des API et permissions** : matrice par route et tests de refus (`Lot7DroitsPortailTest`, `GestionnaireCotisationsTest`, etc.).
- **Migrations** : additives, rejouées en base dédiée et en tables préfixées.
- **Cohérence des calculs** : tests du moteur, de la comptabilité (balance et bilan équilibrés) et du tableau de bord.
- **Performances** : requêtes groupées, jamais une requête par adhérent dans les tableaux de bord.

**Non mesuré : la charge** (nombre d'utilisateurs simultanés sur l'hébergement LWS).

Tests ajoutés en L6 :
- `MutuelleTestReinitialisationTest` (4) ;
- `GestionnaireCotisationsTest` (2) ;
- `TraceAccesSanteTest` (1) ;
- 1 cas dans `MoteurPriseEnChargeTest`.

---

# Livraison du 23/11/2026 (VERSION 2026.11.23) : changement de formule contrôlé (Partie 5.2)

## 24. Changement de formule d'un groupe d'adhérents

**Rappel du modèle.**
- Une formule (`guarantees`) a une suite de versions datées (`guarantee_versions.valid_from/valid_to`).
- À une date de soins, la version appliquée est celle **en vigueur à cette date** (`GuaranteeVersion::applicableOn`).
- Modifier une formule crée une nouvelle version datée : tous les adhérents de la formule y passent à cette date, sans effet sur les soins antérieurs (scénario 7).
- Ce qui manquait : faire passer des adhérents **d'une formule à une autre**.

**Service** `App\Services\Tenant\FormulaMigrationService`, table tenant `formula_migrations` :

| Étape | Règle |
|---|---|
| Aperçu (`candidates`) | Adhérents dont la souscription validée en vigueur relève de la formule source. Pour chacun : ancienne et nouvelle cotisation, échéances retirées, ou motif d'exclusion |
| Demande (`request`, A) | Formule cible différente ; date d'effet aujourd'hui ou plus tard (**jamais rétroactive**) ; motif obligatoire ; adhérents cochés un par un ; numéro `MIG-AAAA-NNN` |
| Décision (`decide`, A ou C) | **Jamais par le demandeur** ; refus motivé. Validée et date atteinte → appliquée tout de suite ; sinon appliquée par la tâche de nuit (`contributions:sweep-overdue` → `applyDue()`), ou par le bouton « Appliquer maintenant » une fois la date atteinte |
| Application (`apply`) | Une seule fois (statut pris atomiquement). Contrôles refaits sous verrou, adhérent par adhérent, chacun dans sa transaction. Le résultat (changés, écartés avec motif) est enregistré dans `result` |

**Pour chaque adhérent changé :**
- **Ancienne souscription** : elle **reste « validée »**, avec `ends_on` = veille de la date d'effet. Les soins antérieurs restent pris en charge selon l'ancienne formule, et les prestations déjà enregistrées ne changent pas.
- **Nouvelle souscription** : validée, `effective_date` = date d'effet, `migrated_from_id` = l'ancienne. Saisie = demandeur, validation = décideur.
- **Cotisation** : cotisation de base de la formule cible × nombre de bénéficiaires, la même règle qu'à la souscription.
- **Ancienneté conservée** (option, cochée par défaut) : `waiting_from` = début de l'ancienne souscription. Le moteur fait partir les **délais de carence** de cette date (`CoverageEngine::coverageStart`), et la période d'observation des formules classiques est levée.
- **Échéances** :
  - celles de l'ancienne formule dues à partir de la date d'effet et sans aucun encaissement sont retirées (elles n'étaient qu'appelées) ;
  - la nouvelle souscription reçoit son échéancier (`ScheduleGenerationService::extend`, sans droit d'adhésion) ;
  - `extend()` ne crée plus jamais d'échéance après `ends_on`.

**Adhérent écarté (motif affiché), dans ces cas :**
- non actif ;
- souscription saisie en attente ;
- souscription plus récente déjà présente ;
- refus de la fiche de la formule cible (statut, commercialisation, âge, nombre de bénéficiaires) ;
- **échéance de l'ancienne formule due après la date d'effet déjà réglée, ou portant un encaissement même annulé**. Aucun paiement n'est déplacé : il faut régulariser au guichet (annulation 4 yeux).

**Formule cible refusée** si elle n'a pas de version en vigueur à la date d'effet, si sa **cotisation n'est pas fixée** (0, cas des formules d'exemple : rien n'est inventé) ou si sa périodicité n'a pas de nombre de mois.

**Choix de la souscription « en vigueur »**, rendu cohérent avec les dates :
- le moteur prenait déjà la souscription validée la plus récente dont la date d'effet est au plus tard la date des soins ;
- `ClaimCaptureService::versionFor` et `RightsVerificationService` (observation) font de même désormais, avec repli sur la plus récente ;
- le tableau de bord ne compte plus dans l'ancienne formule un adhérent qui l'a quittée.

**Routes** (`formula-migrations.*`, URL `/formules/changements`) :

| Action | Droit |
|---|---|
| Consulter | A, E, C, U |
| Demander, retirer | A |
| Valider, refuser, appliquer | A, C |

L'entrée de menu est **Paramètres → Changements de formule**. La table est classée `RESET` pour la réinitialisation de la MUTUELLE TEST.

**Notification à l'adhérent**, sans donnée médicale : « Votre mutuelle vous fait passer à la formule … à partir du … ».

**Migration TENANT** `2026_11_23_000100_formula_migrations` :
- `subscriptions.ends_on`, `migrated_from_id`, `waiting_from` ;
- table `formula_migrations`.

**Tests** : `tests/Feature/ChangementFormuleTest.php` (3). Ils couvrent :
- l'aperçu et les exclusions ;
- la date passée refusée ;
- la règle « 4 yeux » ;
- l'application à la date seulement, une seule fois ;
- la fin de l'ancienne souscription et la nouvelle cotisation ;
- les échéances retirées et le nouvel échéancier ;
- les soins avant la date (ancienne formule, 80 %) et après (nouvelle, 60 %) ;
- l'ancienneté conservée ou non ;
- le parcours complet par les écrans avec les droits ;
- la cotisation non fixée refusée.

**Limites** :
- **pas de prorata** : les échéances de l'ancienne formule dues avant la date d'effet restent dues entières. Choisissez de préférence une date d'effet qui coïncide avec une échéance ;
- **pas de retour arrière automatique** : il faut un nouveau changement, vers l'ancienne formule.
