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
