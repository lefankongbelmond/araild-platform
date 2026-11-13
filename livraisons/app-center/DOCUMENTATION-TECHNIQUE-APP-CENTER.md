# MX-CONNECT — App Center : documentation technique

Version 2026.11.13. Ce document s’adresse à la personne qui maintient la plateforme.

## 1. Audit de départ (résumé)

- **Technologie conservée.** Laravel 13.25, PHP 8.4, stancl/tenancy, MariaDB ; hébergement mutualisé LWS (pas de SSH, cache en fichiers, tâches planifiées appelées par URL).
- **Adresses.**
  - `https://m.araild.com` : site principal et réseau social.
  - `https://<mutuelle>.m.araild.com` : l’espace de gestion de chaque mutuelle, une adresse et des données propres à chacune.
  - `/reseau` : l’espace super‑administrateur.
- **Avant l’App Center.**
  - Le réseau social était déjà installable (`/manifest.webmanifest`, `/sw-social.js`) ; l’application Android (TWA) l’ouvre.
  - L’espace de gestion des mutuelles n’était pas installable.
- **Connexion : trois systèmes distincts, conservés tels quels.**
  - Super‑admin : garde `network`.
  - Personnel d’une mutuelle : garde `web`, comptes dans la base de la mutuelle, double authentification obligatoire, matrice des droits.
  - Membres du réseau social : garde `member`, comptes centraux.

## 2. Architecture retenue

| Interface | Adresse | Rôle |
|---|---|---|
| App Center | `https://m.araild.com/applications` | Présentation et installation des deux applications (la page d’accueil `/` reste la présentation de la plateforme, avec des liens vers l’App Center) |
| MX-CONNECT SOCIAL | `https://m.araild.com/` (démarre sur `/app`) | Réseau social, installable directement depuis l’App Center |
| MX-CONNECT MUTUELLE | `https://<mutuelle>.m.araild.com/` | Gestion de la mutuelle ; installable depuis `https://<mutuelle>.m.araild.com/installer` |

**Pourquoi une application par mutuelle ?**
- Une application web (PWA) ne couvre qu’une seule adresse (son « origine »).
- La session du personnel est propre au sous‑domaine de sa mutuelle.
- Chaque mutuelle a donc sa propre application installée : nom « <Mutuelle> · MX-CONNECT Mutuelle », démarrage sur `/?source=pwa`, qui renvoie à sa page de connexion ou à son tableau de bord.
- Depuis l’App Center, l’utilisateur indique sa mutuelle. Il est alors dirigé vers `/installer` sur le sous‑domaine, qui seul peut déclencher l’installation.

| Élément | Social | Mutuelle |
|---|---|---|
| Manifeste | `/manifest.webmanifest` (statique) — id `/fil` | `/app-mutuelle.webmanifest` (dynamique, par mutuelle) — id `/?app=mx-mutuelle` |
| Service worker | `/sw-social.js` | `/sw-mutuelle.js` |
| Page hors ligne | `/hors-ligne.html` | `/hors-ligne-mutuelle.html` |
| Cache | styles, scripts, logo, polices uniquement | styles, logo, polices uniquement — **jamais de page de gestion ni de donnée** |
| Mises à jour | pages toujours chargées depuis le réseau ; fichiers statiques versionnés (`?v=`) ; nom du cache changé à chaque livraison | idem |
| Écran de démarrage | généré par le système à partir du nom, de l’icône et des couleurs du manifeste ; plus `/app`, l’écran animé | généré par le système à partir du manifeste |

**Mode hors ligne.** Seule une page « Pas de connexion » est servie hors ligne. Les données de gestion ne sont jamais conservées sur l’appareil, volontairement, pour la sécurité des données des adhérents.

## 3. Fichiers ajoutés ou modifiés

**Serveur (Laravel)**
- `database/migrations/2026_11_13_000100_create_app_center_tables.php` : base CENTRALE, migration additive.
- `app/Models/AppCenter/{AppCenterApp,AppCenterStat,AppCenterInstall}.php`
- `app/Support/AppCenter.php` : applications, version automatique, statistiques.
- `app/Support/ApkInspector.php` : contrôle de la signature d’un APK.
- `app/Http/Controllers/AppCenter/AppCenterController.php` : page publique, choix de la mutuelle, événements, APK, icône.
- `app/Http/Controllers/AppCenter/MutualInstallController.php` : `/installer`, manifeste, événements, icône (sous‑domaine).
- `app/Http/Controllers/Network/AppCenterAdminController.php` : APP CENTER MANAGEMENT.
- Vues : `appcenter/index`, `appcenter/install`, `network/appcenter/index`, `network/appcenter/edit`.
- Gabarits modifiés :
  - `layouts/social` : comptage des ouvertures en mode application ;
  - `layouts/tenant` : manifeste, service worker et comptage ;
  - `layouts/network` : menu « App Center » ;
  - `home/partials/header` et `home/platform` : liens vers l’App Center.
- Routes : `routes/web.php` (public et `/reseau/app-center`) et `routes/tenant.php` (public `/installer…`).

**Fichiers publics**
- `js/mx-appcenter.js` : détection, fenêtre native, guides, statistiques.
- `css/mx-apps.css`
- `sw-mutuelle.js`, `hors-ligne-mutuelle.html`
- `brand/appcenter/*.jpg` : captures réelles, données fictives.
- `manifest.webmanifest` : nom « MX-CONNECT Social », captures, raccourcis, `related_applications`.

### Routes

| Méthode | Adresse | Nom | Accès |
|---|---|---|---|
| GET | `/applications` | `appcenter.index` | public (domaine principal) |
| POST | `/applications/mutuelle` | `appcenter.mutual` | public, limité |
| POST | `/applications/evenement` | `appcenter.event` | public, 60/min |
| GET | `/applications/{mutuelle|social}/android.apk` | `appcenter.apk` | public, 20/min, seulement si l’APK est vérifié |
| GET | `/applications/{key}/icone.png` | `appcenter.icon` | public |
| GET | `<mutuelle>/installer` | `mutual-app.install` | public (mutuelle active) |
| GET | `<mutuelle>/app-mutuelle.webmanifest` | `mutual-app.manifest` | public |
| POST | `<mutuelle>/installer/evenement` | `mutual-app.event` | public, 60/min |
| GET | `/reseau/app-center` | `network.appcenter.index` | super_admin, moderator |
| GET/POST | `/reseau/app-center/{key}` (+ `/icone`, `/apk`, `/apk/retirer`) | `network.appcenter.*` | super_admin |

Les routes `/installer…` sont publiques et hors du back‑office. La matrice des droits (`TenantAccess`) ne les concerne pas. Une mutuelle suspendue garde son espace fermé, `/installer` compris (`EnsureMutualActive`).

## 4. Détection et installation (`js/mx-appcenter.js`)

**Détection**
- Système : Windows, macOS, Linux, Android, iOS/iPadOS (y compris l’iPad qui se présente comme un Mac), ChromeOS.
- Navigateur : Chrome, Edge, Firefox, Safari, Samsung Internet, Opera.
- Mode application (`display-mode: standalone`).

**Installation**
1. Sur les pages d’installation (attribut `data-app-install`), l’événement `beforeinstallprompt` est conservé. Le bouton ouvre alors la **fenêtre native** du navigateur. Sur les autres pages, le comportement habituel du navigateur n’est pas modifié.
2. Après acceptation, une barre de progression s’affiche jusqu’à l’événement `appinstalled`, puis « Installation terminée » avec un bouton « Ouvrir ».
3. Sans fenêtre native, un guide adapté s’affiche :
   - Chrome et Edge sur PC : icône d’installation dans la barre d’adresse ou menu ;
   - Safari sur macOS : « Ajouter au Dock » ;
   - Android : Chrome, Samsung Internet ou Firefox ;
   - iPhone : « Sur l’écran d’accueil » ;
   - **Firefox sur ordinateur : on indique honnêtement qu’il n’installe pas d’application web.**
4. Mauvais appareil :
   - bouton Android depuis un PC : QR code vers la page, à scanner avec le téléphone ;
   - bouton PC depuis un téléphone : explication.
5. Application déjà installée : repérée par le mode application, par un indicateur local posé à l’installation ou à la première ouverture, ou par `getInstalledRelatedApps` quand le navigateur le permet. On affiche alors « Application déjà installée », avec « Ouvrir » et la possibilité de revoir les instructions.

## 5. Statistiques (anonymes)

- **Table `app_center_stats`** : compteurs journaliers par application, événement, système, navigateur et version.
  - Événements : `click_pc`, `click_android`, `qr`, `guide`, `prompt`, `accepted`, `dismissed`, `installed`, `launched`, `open`, `apk_download`.
- **Table `app_center_installs`** : une ligne par installation **confirmée**.
  - Une installation est confirmée par l’événement `appinstalled` du navigateur, ou par la première ouverture en mode application.
  - Elle est identifiée par `sha256(APP_KEY | application | identifiant aléatoire tiré par l’appareil)`.
  - On y trouve : système, navigateur, PC ou mobile, version, date d’installation, dernière ouverture, et la mutuelle pour l’application Mutuelle.
- **Ce qui n’est jamais enregistré** : compte, adresse IP, nom, donnée de santé.
- **Clics et installations sont séparés.** Un clic n’est jamais compté comme une installation.
- **Installation active** : ouverte dans les 30 derniers jours. Une ouverture est comptée au plus une fois par jour et par appareil.
- **Limite connue.** Une installation faite depuis le menu du navigateur, ou depuis l’écran d’accueil d’un iPhone, n’est comptée qu’à sa première ouverture. Une installation jamais ouverte n’apparaît donc pas.

## 6. APK Android officiel

- **Dépôt.** Dans « App Center → Gérer l’application → APK Android officiel ». Taille maximale 50 Mo : vérifier que `upload_max_filesize` et `post_max_size` de PHP le permettent chez LWS.
- **Contrôles** (`ApkInspector`) :
  - archive ZIP valide, contenant `AndroidManifest.xml` ;
  - lecture des certificats de signature : schémas v2/v3 (« APK Sig Block 42 ») et, à défaut, schéma v1 (`META-INF/*.RSA|DSA|EC`) ;
  - l’empreinte SHA‑256 doit correspondre à une empreinte déclarée dans « Application Android → Réglages ». Sinon l’APK est **refusé** (débogage, autre clé, fichier non signé).
- **Limite.** Ce contrôle prouve qui a signé le fichier. Android refait la vérification cryptographique complète à l’installation.
- **Stockage et téléchargement.**
  - Le fichier est stocké sur le disque privé (`storage/app/private/appcenter/apk`), jamais dans un dossier public.
  - Il est servi en téléchargement avec `Content-Type: application/vnd.android.package-archive`, `nosniff` et `no-store`.
  - L’empreinte SHA‑256 du fichier est conservée et affichée.

### Générer l’APK signé (rappel)

Le projet Android est dans `android/` (TWA). Deux façons de le compiler :
- **GitHub Actions** (`.github/workflows/android.yml`) avec les secrets `MX_KEYSTORE_BASE64`, `MX_KEYSTORE_PASSWORD`, `MX_KEY_ALIAS` et `MX_KEY_PASSWORD` ;
- **Android Studio** : voir `android/OUVRIR-DANS-ANDROID-STUDIO.md`.

Ensuite :
1. Lire l’empreinte SHA‑256 de la clé avec `signingReport` ou `keytool -list -v`.
2. La déclarer dans `/reseau/android/reglages`.
3. Déposer l’APK release signé dans l’App Center.

## 7. Exécutables PC (plus tard, si nécessaire)

L’installation PWA ne télécharge aucun `.exe`. Deux voies existent pour un vrai logiciel Windows :

- **Paquet MSIX à partir du manifeste** (PWABuilder, outil de Microsoft) :
  - il enveloppe la PWA existante, sans nouveau code ;
  - il est publiable sur le Microsoft Store ou en installation directe ;
  - il exige un certificat de signature de code.
- **Tauri** (léger, recommandé) ou **Electron** :
  - une fenêtre native qui charge `https://<mutuelle>.m.araild.com` ou `https://m.araild.com` ;
  - il faut un projet à maintenir, un certificat de signature Windows (Authenticode) et une procédure de mise à jour.

Dans les deux cas, ne publier dans l’App Center qu’un fichier signé.

Le schéma est prêt à évoluer : une colonne `exe_*` ou une table de fichiers par plateforme, contrôlée comme l’APK (signature Authenticode vérifiée).

## 8. Connexion unique (SSO) : étude

**Constat.**
- Le personnel des mutuelles et les membres du réseau social ont des comptes séparés.
- Les comptes du personnel sont dans la base de chaque mutuelle (isolement des données), avec double authentification et matrice des droits.
- Les membres ont des comptes centraux.
- Un même humain peut avoir les deux, avec des rôles très différents.

**Recommandation : ne pas fusionner les comptes.** Si une connexion unique est souhaitée, la faire par un **fournisseur d’identité central**, par exemple OpenID Connect via Laravel Passport ou un service dédié. Les principes à respecter :
- chaque mutuelle reste maîtresse des autorisations (rôles, 4 yeux, désactivation) ;
- la double authentification reste exigée pour le personnel ;
- un compte du réseau social ne donne jamais, à lui seul, accès à l’espace d’une mutuelle (liaison explicite approuvée par la mutuelle) ;
- la déconnexion se propage (déconnexion du fournisseur et des sessions des sous‑domaines).

**Ce qui est livré aujourd’hui.**
- La page « Connexion » de l’App Center mène à `/se-connecter`, qui oriente chacun vers le bon espace.
- Aucun second système de connexion n’a été créé.

## 9. Déploiement sur LWS

1. Extraire `mxconnect-maj-20261113.zip` dans `htdocs/mconnect` en remplaçant tout.
2. Ouvrir https://m.araild.com. La première visite applique la mise à jour : purge des caches et migration centrale `2026_11_13_000100`. Sinon, utiliser `/_installation` → « Appliquer la mise à jour ».
3. Les deux applications sont créées automatiquement à la première ouverture de `/applications`.
4. HTTPS est obligatoire, déjà en place : certificat SSL du domaine principal et de chaque sous‑domaine de mutuelle (Let’s Encrypt dans le panneau LWS). Sans HTTPS sur le sous‑domaine, l’application de la mutuelle n’est pas installable.
5. Facultatif : déposer une icône ou un APK signé dans `/reseau/app-center`.

## 10. Rapport de tests

Voir `LISEZMOI-20261113.md`, section « Tests réalisés », pour le détail et pour ce qui n’a pas pu être vérifié.
