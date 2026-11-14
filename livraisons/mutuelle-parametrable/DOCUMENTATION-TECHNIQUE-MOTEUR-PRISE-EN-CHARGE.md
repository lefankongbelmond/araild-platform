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
