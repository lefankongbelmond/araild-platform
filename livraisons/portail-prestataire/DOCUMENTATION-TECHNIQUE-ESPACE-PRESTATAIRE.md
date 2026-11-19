# MX-CONNECT — Espace prestataire central : documentation technique

Portail prestataire V2, phase 2. Version **2026.11.16**.
Décision du fondateur : **espace central** (option B de l'audit). Chaque personne a un seul compte et un rôle dans son établissement ; un établissement travaille avec plusieurs mutuelles.

## 1. Architecture

```
m.araild.com/espace-prestataire  (garde « provider », base CENTRALE)
  ├─ comptes, établissements, rattachements, documents, index des affiliations
  ├─ tableau de bord : lecture DANS chaque mutuelle, une à la fois ($mutual->run())
  └─ « Ouvrir » → jeton à usage unique (60 s)
        ↓
<mutuelle>.m.araild.com/prestataire/entree/{jeton}  (contexte tenant)
  └─ ouvre la session « web » du compte Prestataire de soins de la personne dans cette mutuelle
     → portail existant (vérifier, déclarer, factures) inchangé, filtré par les droits de l'établissement
```

**Principes respectés**
- **Isolation des mutuelles** : aucune donnée d'adhérent ni de soins en base centrale. Les lectures se font mutuelle par mutuelle, dans `run()`. Jamais de requête d'une mutuelle sur les tables d'une autre.
- **Pas de second système d'authentification incompatible** :
  - la garde `provider` suit le même modèle que les gardes `network` et `member` (session Laravel, mots de passe hachés, TOTP Google2FA) ;
  - dans la mutuelle, la session reste celle de la garde `web` existante.
- **Un acteur réel par saisie** : à la 1re entrée, la mutuelle reçoit un compte `users` au nom de la personne, avec `provider_account_id`, le rôle `provider` et `care_provider_id`. Ainsi :
  - `rights_checks.user_id`, `prestations.captured_by` et le contrôle « 4 yeux » fonctionnent sans changement ;
  - la mutuelle peut désactiver ce compte dans « Équipe et rôles ».
- **Ce compte de mutuelle ne s'ouvre jamais par mot de passe** :
  - mot de passe aléatoire de 48 caractères ;
  - `AuthController::login` le refuse et renvoie vers l'espace prestataire ;
  - adresse technique `espace-prestataire-{compte}-{établissement}@prestataires.mx-connect.invalid`.
- **Double authentification** : obligatoire dans l'espace central. Elle est considérée comme faite à l'entrée dans la mutuelle (`mfa_passed`). La clé de session centrale `provider_mfa_passed` est propre à l'espace prestataire et n'est jamais partagée avec le super-admin.

## 2. Schéma (migrations additives, aucune donnée supprimée)

### Centrale `2026_11_16_000100_create_provider_space_tables`

| Table / colonnes | Rôle |
|---|---|
| `care_providers` + `registration_status` (approved/pending), `approval_number`, `approval_expires_on`, `specialties` (json), `address`, `latitude`, `longitude`, `phone2`, `email`, `opening_hours` (json), `bank_details` (**chiffré**, `encrypted:array`, exclu de l'audit), `manager_name` | Fiche de l'établissement |
| `provider_accounts` (email unique, `password`, `mfa_secret` chiffré, `mfa_enabled`, `must_change_password`, `active`, `last_login_at`) | Personnes (garde `provider`) |
| `provider_memberships` (compte, établissement, `role`, `job_title`, `permissions` json `{grant,deny}`, `active`) — unique (compte, établissement) | Rôle dans l'établissement |
| `provider_documents` (type, titre, référence, dates, `mutual_id`, `path` disque privé, taille ; SoftDeletes) | Agréments, conventions, pièces |
| `provider_affiliations` (établissement, mutuelle, `status` requested/approved/suspended/rejected/withdrawn, message, `decision_note`, `history`) — unique (établissement, mutuelle) | Demandes d'affiliation et index des conventions |
| `provider_handoffs` (`token_hash` sha256 unique, cible, `expires_at`, `used_at`, IP) | Jetons d'entrée à usage unique |
| `provider_password_reset_tokens` | « Mot de passe oublié » (broker `provider_accounts`) |

### Chaque mutuelle `tenant/2026_11_16_000100_provider_space_links`
- `users.provider_account_id`
- `provider_conventions` : `signed_on`, `transmission_days`, `payment_days`, `billing_terms`, `history` (json)
- Remplissage de l'index central `provider_affiliations` pour les conventions existantes : `approved` si la convention est active, `suspended` sinon.

**La convention dans la base de la mutuelle fait foi.** L'index central est tenu à jour par `ProviderSpaceService::syncFromConvention` à chaque création, modification ou suspension, et à chaque acceptation d'une demande.

## 3. Droits

### 3.1 Établissement — `App\Support\ProviderAccess` (source unique)
- `ROLES` : admin, doctor, nurse, midwife, pharmacist, lab_tech, cashier, billing, manager.
- `PERMISSIONS` : rights.verify, care.declare, claims.view, invoices.view, invoices.bill, finance.view, conventions.view, affiliation.request, profile.edit, bank.edit, documents.manage, staff.manage.
- Droits effectifs = droits du rôle + `grant` − `deny`. `staff.manage` reste lié au rôle Administrateur (`ROLE_BOUND`).
- `ROUTES` : droit exigé par chaque route `pro.*`. Une route non classée est refusée. Un test vérifie que chaque route de l'espace a une règle.
- Middleware `ProviderSpaceGuard`, dans cet ordre :
  1. compte actif ;
  2. mot de passe provisoire ;
  3. double authentification (activation, puis code) ;
  4. établissement en cours (session `provider_membership`) ;
  5. droit de la route.
- Vues : `@droit('perm')` ou `ProviderAccess::allowed()`.

### 3.2 Dans le portail de chaque mutuelle
`TenantAccess::can()` exige en plus, pour un compte rattaché, le droit de l'établissement (`ProviderSpaceService::PORTAL_ROUTE_PERMS`) :

| Routes du portail | Droit exigé |
|---|---|
| `portal.index`, `portal.verify` | rights.verify |
| `portal.declare*` | care.declare |
| `portal.claims` | claims.view |
| `portal.invoices`, `portal.invoices.document` | invoices.view |
| `portal.invoices.store`, `portal.invoices.document.store` | invoices.bill |

`TenantAccountGuard` ferme la session si la personne ou son rattachement est désactivé dans l'établissement. Les droits sont relus une fois par requête.

## 4. Routes

### Centrales (`central.only`, préfixe `espace-prestataire`, noms `pro.*`)
- **Invité** : `login`, `login.store`, `register`, `register.store`, `forgot`, `forgot.send`, `reset`, `reset.update`.
- **Connecté** : `logout`, `password`, `password.update`, `mfa.enroll`, `mfa.confirm`, `mfa.challenge`, `mfa.verify`.
- **Espace** (`ProviderSpaceGuard`) :
  - `dashboard`, `switch` ;
  - `go/{verify|claims|invoices}`, `open/{target}/{mutual}` ;
  - `mutuals`, `mutuals.show`, `affiliations.store`, `affiliations.withdraw` ;
  - `profile`, `profile.update`, `bank.update` ;
  - `staff`, `staff.store`, `staff.update`, `staff.reset` ;
  - `documents`, `documents.store`, `documents.download`, `documents.destroy` ;
  - `settings`, `settings.update`, `settings.password`.

### Mutuelle
- `GET prestataire/entree/{jeton}` → `portal.entry` : hors authentification, 20 essais par minute.
- `providers.access` : ouvrir l'accès du responsable.
- `providers.affiliations.approve` et `providers.affiliations.reject` : couverts par la règle `providers.*`, réservée à l'administrateur.

## 5. Jeton d'entrée
- 64 caractères aléatoires. Seule l'empreinte sha256 est stockée.
- Valable 60 s, lié à une mutuelle et à un établissement.
- Consommé de façon atomique (`UPDATE … WHERE used_at IS NULL AND expires_at > NOW()`).
- Contrôles à l'entrée :
  - compte et rattachement actifs, droit de la destination ;
  - établissement actif ;
  - convention présente dans la mutuelle ;
  - compte de la mutuelle actif.
- Toute session déjà ouverte sur le sous-domaine est fermée d'abord. Puis `session()->regenerate()`, et redirection immédiate pour que le jeton ne reste pas dans l'historique.
- Refus : page 403 avec le motif et un lien « Retour à l'espace prestataire ».

## 6. Tableau de bord — `ProviderDashboardService`
Pour chaque mutuelle conventionnée et non suspendue, lecture dans `run()`, mise en cache 120 s par établissement et par mutuelle.

**Indicateurs**
- **Patients** : adhérents distincts dont les droits ont été vérifiés avec succès (`rights_checks`).
- **Prises en charge** : par statut (`captured` et `controlled` = en attente).
- **Consultations** : actes dont la catégorie contient « consult », ou dont le libellé commence par « consultation ».
- **Factures** : acceptées = `processed` + `prepared` + `paid` ; payées = `paid`, avec `decided_at` posé par le trésorier.
- **Délai moyen** : de la demande au paiement enregistré.
- **Taux de rejet** : rejetées / (acceptées + rejetées).

**Montants**
- Groupés **par code devise**, sans aucune conversion.
- « À facturer » suit la règle du portail : une prise en charge déjà facturée, même rejetée, ne se refacture pas avant la phase 5.

**Alertes** : chaque alerte part d'une donnée réelle, sans seuil ni délai inventé.
- Côté mutuelle : convention suspendue, échue ou à échéance dans 60 jours ; factures en retard (seulement si `payment_days` est fixé) ; rejets sur 30 jours ; paiements sur 14 jours.
- Côté central : établissement en attente ; agrément ou documents qui expirent dans 60 jours ; convention acceptée ou demande refusée sur 30 jours.

Une mutuelle injoignable est signalée sans bloquer les autres.

## 7. Sécurité
- **Documents** :
  - vrai PDF (`%PDF-`), 5 Mo au plus, disque privé `local`, dossier `provider-documents/{établissement}` ;
  - téléchargement forcé avec `CSP: sandbox`, `no-store` et `nosniff` ;
  - servis seulement à l'établissement propriétaire.
- **Coordonnées bancaires** : chiffrées avec APP_KEY, exclues du journal d'audit (seul le fait est journalisé), visibles seulement avec le droit `bank.edit`.
- **Notes internes de la convention** : jamais montrées à l'établissement. Seuls ces champs sont transmis : n°, signature, dates, délais, contact, conditions de facturation.
- **Journal d'audit** : les modèles de l'espace sont auditables, et la garde `provider` est ajoutée au résolveur d'utilisateur de l'audit.
- **Limites d'essais** : connexion 5 par minute, inscription 5 par 10 minutes, ouverture du portail 30 par minute, entrée 20 par minute.

## 8. Tests
`tests/Feature/PortailV2Phase2Test.php` contient 9 tests, joués dans une vraie base de mutuelle et en mode tables préfixées (`MX_TEST_STORAGE=prefix`) :
1. inscription d'un établissement, refus motivé puis acceptation, création de la convention ;
2. mot de passe provisoire, double authentification, cloisonnement des routes par droit, compte désactivé ;
3. entrée par jeton, saisie attribuée à la personne, connexion par mot de passe refusée, droits du caissier ;
4. jeton expiré ou réutilisé, droit retiré, rattachement désactivé, accès coupé par la mutuelle ;
5. personnel : mot de passe provisoire, droits ajoutés et retirés, dernier administrateur, réinitialisation, personne rattachée à deux établissements ;
6. tableau de bord sur données réelles (montants, facture en retard, paiement réel, médecin sans montants) ;
7. accès du responsable ouvert par la mutuelle, notes internes privées, coordonnées bancaires chiffrées ;
8. documents (faux PDF refusé, en-têtes, autre établissement : 404) ;
9. couverture des droits des routes.

## 9. Limites connues de la phase 2
- Les soins se saisissent toujours dans le portail de chaque mutuelle. Viendront plus tard :
  - recherche et vérification multi-mutuelles depuis l'espace central (phase 3) ;
  - tarifs propres à l'établissement (phase 4) ;
  - factures à lignes, bordereaux et renvoi après rejet (phase 5) ;
  - messagerie et notifications (phase 6) ;
  - rendez-vous, mode hors ligne et rapports exportables (phase 7).
- Les inscriptions en ligne ne sont pas encore modérées par le super-admin. Seule l'acceptation par une mutuelle reconnaît un établissement.
- Pas de géolocalisation automatique : la politique de sécurité bloque `geolocation`, les coordonnées GPS se saisissent à la main.
- Les anciens comptes « Prestataire de soins » par mutuelle restent en place. Aucune fusion automatique avec l'espace central.

---

# Phase 3 — Identification et vérification des droits (version 2026.11.17)

## 10. Côté mutuelle (portail `/prestataire`)

### 10.1 Identification
`App\Services\Tenant\BeneficiaryLookupService` :
- **`identify()`** : code adhérent, ou QR signé (`DigitalCardService::verifyToken`, qui exige la bonne mutuelle et une signature valide).
- **`search()`** : recherche encadrée.
  - Mode **nom** : nom + date de naissance **complète**, prénom en début de mot facultatif. Couvre `members` et `dependents`.
  - Mode **téléphone** : 8 derniers chiffres + année de naissance. Couvre `members` uniquement.
  - Au plus **5 résultats** ; au-delà, rien n'est affiché et la personne doit préciser.
  - Code de carte masqué (`•••••777`).
  - Journal `beneficiary_searches` : utilisateur, établissement, critères, nombre de résultats.
  - Limiteur nommé `portal-search` : 10 par minute, compteur propre.
- **`pick()`** : choix d'un résultat par jeton chiffré (`Crypt`), lié à l'utilisateur, à l'établissement et à 15 minutes. Impossible de vérifier un adhérent en devinant son identifiant.

### 10.2 Statut des droits — `App\Support\RightsStatus`
Le statut est dérivé des motifs de `RightsVerificationService`, qui reste la source unique des contrôles :

| Statut | Conditions |
|---|---|
| `unknown` | code ou carte inconnus, ou carte falsifiée |
| `expired` | adhérent sorti ou radié, ayant droit retiré, aucune garantie en cours |
| `suspended` | adhérent suspendu, cotisations en retard |
| `pending` | adhésion saisie non validée, période d'observation |
| `network` | établissement hors réseau de la garantie |
| `active` | aucun motif bloquant |

Un ayant droit retiré ajoute le motif `dependent_removed`.

### 10.3 Situation et estimation — `App\Services\Tenant\CoverageService`
- **`situation()`** :
  - garantie et fin de validité de la version, taux de couverture, ticket modérateur ;
  - cotisations : retard (échéances `overdue`, ou `to_pay` échues) et dernière échéance réglée (hors droit d'adhésion) ;
  - plafonds avec reste annuel (même calcul que la déclaration : parts validées et en attente réservées) ;
  - exclusions, réseau.
- **`lastCheck()`** : vérification précédente du même bénéficiaire par le même établissement.
- **`history()`** : prises en charge de **cet établissement uniquement** sur 12 mois (10 au plus). Affiché seulement si la route `portal.claims` est autorisée.
- **`estimate()`** : rien n'est enregistré.
  - S'appuie sur `ClaimAssessmentService::detail()`, ajouté : même calcul que `assess()`, avec la part théorique et le reste du plafond avant les soins. `assess()` est inchangé.
  - Acte exclu : tout le montant est non couvert.
  - Comparaison au tarif conventionnel (`act_types.tariff_minor`), affichée seulement si la mutuelle l'a fixé.

### 10.4 Signal anti-fraude
`FraudDetectionService`, règle 5 : montant déclaré supérieur au tarif conventionnel → alerte `financial / watch / above_tariff`. C'est un **signal à examiner**, jamais une correction ni un refus.

### 10.5 Routes
| Route | Méthode et chemin | Limite | Droit d'établissement |
|---|---|---|---|
| `portal.search` | `POST prestataire/rechercher` | `portal-search` | rights.verify |
| `portal.pick` | `POST prestataire/rechercher/choix` | 30 par minute | rights.verify |
| `portal.estimate` | `POST prestataire/estimer/{check}` | 30 par minute | rights.verify |

Toutes sont couvertes par la règle `portal.*` de `TenantAccess`. La caméra est autorisée sur ces pages pour la lecture du QR.

### 10.6 Migration TENANT `2026_11_17_000100_portal_identification`
- `rights_checks.dependent_id` et `rights_checks.method` (code, qr, search) ;
- table `beneficiary_searches` ;
- **resynchronisation** de l'index central `provider_affiliations` depuis `provider_conventions`. Elle corrige un défaut de la 2026.11.16 : une convention créée par la mutuelle était indexée « suspendue », car la valeur par défaut `active` n'était pas relue. `syncFromConvention` relit désormais la valeur en base, et `ProviderController::store` passe `active => true`.

## 11. Côté espace central
- `POST espace-prestataire/rechercher` (`pro.lookup`, droit `rights.verify`, limiteur `provider-lookup` à 20 par minute) → `ProviderSpaceService::lookup()` :
  - un **QR** désigne sa mutuelle (jeton `mutuelle.adhérent.signature`) ;
  - un **code** est cherché dans chaque mutuelle **conventionnée en place**, une à la fois avec `run()`, en testant seulement son existence.
- **Résultat** :
  - une seule mutuelle → jeton d'entrée avec `payload` (le code), puis le portail s'ouvre avec le code pré-rempli (session `portal_prefill`). La vérification est faite et journalisée **dans la mutuelle** ;
  - plusieurs mutuelles → l'utilisateur choisit ;
  - aucune → « Bénéficiaire non identifié ».
- **Journal central** `provider_lookups` : empreinte sha256 du code (jamais le code en clair), nombre et liste des mutuelles trouvées, IP.
- **Migration centrale** `2026_11_17_000100_add_provider_lookups` : `provider_handoffs.payload`, table `provider_lookups`.
- La recherche par nom ou téléphone reste **dans chaque mutuelle**. Elle n'est jamais faite à travers toutes les mutuelles.

## 12. Tests de la phase 3
`tests/Feature/PortailV2Phase3Test.php` contient 5 tests. Les aides de l'espace prestataire ont été déplacées dans `tests/Pest.php`.
1. Les 6 statuts, l'identité, la situation et la vérification précédente.
2. Recherche :
   - deux critères exigés ;
   - ayant droit trouvé, code masqué ;
   - choix protégé (autre utilisateur ou jeton falsifié : 410) ;
   - téléphone + année, ayant droit retiré, aucun résultat ;
   - journal.
3. Estimation : taux, plafond restant, part non couverte, acte exclu, tarif dépassé, rien d'enregistré, historique limité à l'établissement.
4. Signal « montant supérieur au tarif ».
5. Recherche centrale :
   - code → portail pré-rempli ;
   - QR → mutuelle lue dans le jeton ;
   - carte inconnue → non identifié, journal par empreinte ;
   - agent de facturation refusé.

## 13. Limites de la phase 3
- Aucune photo d'adhérent n'est enregistrée dans MX-CONNECT : rien ne s'affiche (pas de photo fictive).
- Pas de recherche par pièce d'identité : `members.id_document` est chiffré, pour la détection des doublons seulement.
- Les ayants droit n'ont pas de numéro propre : on les trouve par la carte de l'adhérent ou par nom + date de naissance.
- La part patient reste « total − part mutuelle ». Le ticket modérateur est affiché, mais n'est pas appliqué séparément, comme dans le calcul des déclarations.

---

# Phase 6 (partie 1), VERSION 2026.11.25 : avis des mutuelles aux établissements

## 14. Fonctionnement

- **Émission** : `App\Support\ProviderNotifier::notify($careProviderId, $kind, $title, $body, $target, $permission)`.
  - Elle est appelée DANS la mutuelle (contexte tenant) au moment de la décision.
  - Elle écrit dans la table CENTRALE `provider_notifications`, préfixée du nom de la mutuelle.
  - Un échec d'écriture est consigné et n'empêche jamais la décision.

| Décision de la mutuelle | Où | `kind` | Droit d'établissement | Destination |
|---|---|---|---|---|
| Prise en charge validée | `PrestationController::validatePrestation` | `claim_validated` | `claims.view` | Prises en charge |
| Prise en charge rejetée (avec le motif) | `PrestationController::reject` | `claim_rejected` | `claims.view` | Prises en charge |
| Facture payée | `ClaimSettlementController::payInvoice` | `invoice_paid` | `invoices.view` | Factures |
| Facture rejetée (avec le motif) | `InvoiceRegisterController::reject` | `invoice_rejected` | `invoices.view` | Factures |
| Accord préalable accordé ou refusé | `PreauthorizationService::decide` | `preauth_approved` / `preauth_rejected` | `claims.view` | Accords préalables |
| Affiliation acceptée | `ProviderController::approveAffiliation` | `affiliation_approved` | `conventions.view` | Mes mutuelles |
| Affiliation refusée (avec le motif) | `ProviderController::rejectAffiliation` | `affiliation_rejected` | `affiliation.request` | Mes mutuelles |

- **Contenu** : numéro, code adhérent, date des soins, montants dans la devise de la mutuelle, motif de la mutuelle. **Jamais de donnée médicale** : ni l'acte, ni le diagnostic, ni l'information clinique d'un accord préalable. Un test le vérifie.
- **Lecture** : `pro.notifications` (`/espace-prestataire/avis`), entrée « Avis des mutuelles » du menu, et cloche avec le nombre de non-lus dans l'en-tête.
  - `ProviderNotifier::visibleFor($membership)` ne garde que les avis de l'établissement en cours dont le droit est accordé à la personne. Par exemple, un médecin ne voit pas les paiements.
  - Un autre établissement ne voit jamais ces avis.
  - La visite de la page marque les avis comme lus **pour cette personne** (`provider_memberships.notifications_seen_id`).
- **Bouton d'un avis** : il ouvre la destination dans le portail de la mutuelle par le jeton d'entrée habituel (`pro.open`). La nouvelle cible `preauth` mène à `portal.preauth`, avec le droit `claims.view`.

**Migration CENTRALE** `2026_11_25_000100_create_provider_notifications` :
- table `provider_notifications` ;
- colonne `provider_memberships.notifications_seen_id`.

**Tests** : `tests/Feature/AvisPrestataireTest.php` (2). Ils couvrent les 7 décisions, le contenu sans donnée médicale, le filtrage par droit et par établissement, et la lecture propre à chaque personne.

**Reste de la phase 6** :
- messagerie par dossier entre le prestataire et la mutuelle ;
- tableau des signaux ;
- envoi par e-mail ou SMS des avis.

Aujourd'hui, les avis sont visibles dans l'espace seulement.
