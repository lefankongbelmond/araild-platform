# Lot 2 — Réseau social : écrans et logique métier

Couche **publique et centrale**, ajoutée à côté de l'existant. Le fil interne de
chaque mutuelle (`app/Models/Tenant/FeedPost`, base tenant, membres uniquement)
n'est pas modifié.

## Fonctionnalités livrées (opérationnelles, testées)

| Module | Ce qui fonctionne réellement | Règles appliquées côté serveur |
|---|---|---|
| Comptes publics | Inscription, connexion par téléphone, déconnexion (garde `member`, table `public_accounts` déjà utilisée par la PWA) | Mot de passe haché, téléphone unique, limitation `throttle:login`, téléphone jamais affiché publiquement |
| 1 — Accueil `/` | Hero, recherche, domaines, projets à la une, dernières publications, organisations, événements | Tous les chiffres sont des comptages réels ; états vides quand il n'y a pas de données |
| 2 — Profil d'organisation `/organisations/{slug}` | Création, édition, logo/couverture, domaines, coordonnées publiques, chiffres déclarés, abonnement | Créée publiée **non vérifiée** ; `verified_at`, `status`, `mutual_id` ne sont jamais modifiables par l'organisation ; profil suspendu/brouillon visible des seuls gestionnaires |
| 3 — Découvrir `/decouvrir` | Recherche par mots-clés + filtres (type, domaine, pays, région) sur organisations, projets, événements, opportunités ; régions les plus actives | Filtres validés sur listes fermées ; pas de carte : les organisations n'ont pas de coordonnées GPS |
| 4 — Fil `/fil` | Publication (texte, photos/vidéo, événement, opportunité, résultat), au nom de soi ou d'une organisation gérée ; j'aime, commentaires et réponses, partage (lien), hashtags et tendances | Auteur = soi **ou** une organisation dont on est gestionnaire ; événements/opportunités/résultats réservés aux organisations ; visibilité « abonnés » appliquée partout ; contenu échappé (anti-XSS) |
| 5A — « Je m'engage » | Formulaire, boîte de réception de l'organisation, suivi dans « Mes demandes », retrait | Pas de doublon ouvert sur le même sujet ; impossible pour ses propres gestionnaires ; seuls propriétaires/admins répondent ; transitions contrôlées ; téléphone du demandeur révélé **après acceptation** seulement |
| 5B — « Voir le projet et les résultats » | Page projet : contexte, objectifs, zone, calendrier, avancement, bénéficiaires (agrégés), partenaires, mises à jour, publications liées | Indicateurs toujours enregistrés comme **déclarés** ; « vérifié » réservé au réseau (Lot 3) |
| 5C — « Soutenir / devenir partenaire » | Proposition de soutien (6 types), coordonnées facultatives, même workflow que 5A | **Aucun paiement** : intention uniquement, aucun champ ni bouton de paiement |
| Notifications | Cloche avec compteur, page des notifications (canal base de données) | Organisation notifiée à chaque demande ; demandeur notifié à chaque changement de statut |

## Modifications de base de données (toutes additives)

| Migration | Effet |
|---|---|
| `2026_02_01_000200_create_notifications_table` | Table `notifications` (standard Laravel) |
| `2026_02_01_000300_add_published_by_to_posts` | `posts.published_by_account_id` : quel gestionnaire a publié au nom de l'organisation |

(Les 13 tables sociales viennent du Lot 1, migration `2026_02_01_000100`.)

**Retour arrière** : `php artisan migrate:rollback --step=2` (Lot 2) puis `--step=1` (Lot 1). Aucune table existante n'est modifiée.

## Corrections de bugs préexistants trouvés pendant le lot

1. **Routes Fortify exposées par erreur** : le paquet enregistrait 17 routes (`/login`, `/register`, `/user/two-factor-*`…) non configurées (erreur 500), qui masquaient aussi les noms de routes de l'application. Désactivées (`Fortify::ignoreRoutes()`) ; seul le trait `TwoFactorAuthenticatable` était utilisé.
2. **Contrôleur de base sans `AuthorizesRequests`** (régression introduite lors d'un lot précédent) : `$this->authorize()` plantait sur le back-office réseau. Corrigé.
3. Redirection des visiteurs non connectés : `reseau/*` → connexion réseau, le reste → connexion publique.

## Tests

`vendor/bin/pest tests/Feature/Social` → **33 tests, 174 assertions, tous verts** (SQLite en mémoire).
Vérification visuelle réelle dans Chromium (ordinateur 1440 px et mobile 390 px) : aucune erreur JavaScript, pas de défilement horizontal sur mobile.

Suite complète : 66 tests verts, **54 en échec, tous préexistants et hors périmètre** (modules mutuelles) :

| Cause | Tests | Nature |
|---|---|---|
| Tables tenant absentes (`members`, `guarantees`…) | ~40 | Les tests n'initialisent pas de base tenant ; nécessitent MySQL + tenancy |
| `Country::factory()` / `Tenant\User::factory()` inexistants | ~11 | Factories manquantes dans le socle |
| `Stancl\Tenancy\Listeners\CreatePendingTenantAwareCommands` inexistant | 2 | **Bug bloquant réel** : la création d'une mutuelle plante (voir Lot 3) |

## À traiter au Lot 3 (non fait, volontairement)

1. **Prioritaire** — provisioning des mutuelles cassé (`TenancyServiceProvider` référence une classe d'une autre version de stancl/tenancy et branche les jobs directement au lieu d'un `JobPipeline`).
2. Aucune route de connexion web pour le back-office des mutuelles (garde `web`, domaine tenant).
3. Espace réseau : vérification des organisations et des indicateurs, suspension, modération des publications.
4. Messagerie, groupes/communautés, sondages (le schéma de sondage n'a pas de table de votes) : non développés, non affichés.
5. Lien profil public ↔ mutuelle (`organizations.mutual_id`) : réservé au réseau, sans écran pour l'instant.

## Déploiement

```bash
php artisan migrate --force
php artisan storage:link        # nécessaire pour les photos/vidéos et logos
php artisan optimize:clear && php artisan optimize
```
