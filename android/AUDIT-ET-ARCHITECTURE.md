# MX-CONNECT Android (bêta) — Audit et architecture recommandée

Date : 27/10/2026 — Phase 1 (audit) et Phase 2 (architecture), **avant tout développement**.
Base examinée : `production/` (code réellement en ligne sur https://m.araild.com), branche `claude/mx-connect-vps-setup-jfby6i`.

---

## 1. En bref

| Question | Réponse |
|---|---|
| Technologie du site | Laravel 13.25 / PHP 8.4, rendu serveur (Blade), CSS compilé, JavaScript léger (aucun framework). Multi-mutuelles (stancl/tenancy). |
| Hébergement | LWS mutualisé : pas de SSH, pas de Node côté serveur, pas de Redis/Reverb, cron par URL. HTTPS actif sur `m.araild.com`. |
| API réutilisable pour le réseau social ? | **Non.** Le réseau social (fil, découvrir, profils, projets, demandes, messagerie, notifications) n'existe qu'en pages web avec session. Il existe une petite API Sanctum, mais elle est **par mutuelle** (`<slug>.m.araild.com/api/member/*` : carte, échéances, recours, ancien fil) et ne couvre pas le réseau social central. |
| PWA déjà en place ? | **Oui** : `manifest.webmanifest` (nom, icônes, `display: standalone`, démarrage `/fil`), service worker `sw-social.js` qui ne met en cache **que** les fichiers statiques publics, page hors ligne `hors-ligne.html`, interface mobile (< 900 px) déjà travaillée et testée à 320/360 px. |
| Réutilisation du frontend | **Totale** possible : toutes les pages du réseau sont responsives et testées sur mobile. |
| Solution recommandée | **Application Android « Trusted Web Activity » (TWA)** qui ouvre le vrai site en plein écran (moteur Chrome), **+ quelques évolutions serveur ciblées** : démarrage applicatif, politiques versionnées, aide et retours bêta, page publique de suppression de compte, liaison de domaine Android. |
| Réécriture ? | **Aucune.** Une application native (Kotlin ou Flutter) imposerait d'écrire une API complète du réseau social puis de refaire ~66 écrans : plusieurs mois, double maintenance, gros risque de régression. Non justifié pour une bêta. |

---

## 2. Ce que j'ai examiné

- Routes : `routes/web.php` (réseau social central, 312 lignes), `routes/tenant.php` (back-office des mutuelles et API adhérent), `bootstrap/app.php`.
- Contrôleurs du réseau social (21) et vues (66 gabarits Blade), services `App\Services\Social\*`, `App\Support\SocialText`.
- Authentification : garde `member` (comptes publics `public_accounts`), connexion par téléphone ou e-mail, « se souvenir de moi », limitation des tentatives, mot de passe oublié, suppression de compte ; garde `web` + MFA pour le personnel des mutuelles ; garde réseau + MFA pour le super-admin.
- Inscription : case « J'accepte les règles de la communauté et la politique de confidentialité » obligatoire, **mais aucune trace n'est enregistrée** (ni date, ni version, ni texte accepté).
- Pages légales : `/confidentialite` (résumé indiquant que « le texte juridique complet est fourni par l'exploitant ») et `/a-propos`. **Pas de page « Conditions d'utilisation » distincte, pas de « Règles communautaires » distinctes.**
- PWA : `manifest.webmanifest`, `sw-social.js`, `pwa/sw.js` (ancien espace adhérent par mutuelle), `hors-ligne.html`.
- Sécurité : en-têtes (`SecurityHeaders` : CSP, `X-Frame-Options: DENY`, `Permissions-Policy`), sessions (durée 120 min, cookie `SameSite=Lax`), séparation des données (réseau social central sans aucune donnée médicale ; dossiers, cotisations et prestations dans l'espace de chaque mutuelle).
- Notifications : in-app et e-mail ; **pas de notification « push »** (pas de Firebase, pas de Web Push) ; nouveautés relevées toutes les 20 s quand la page est ouverte.
- Environnement de construction de cette session : Java 21 et Gradle 8.14 disponibles ; **le SDK Android ne peut pas être téléchargé** (le domaine `dl.google.com` est refusé par la politique réseau de l'environnement). Le dépôt n'a pas encore d'intégration continue (GitHub Actions).

---

## 3. Les écrans demandés face à l'existant

| Écran demandé | Existant (adresse) | Statut pour la bêta |
|---|---|---|
| Écran de démarrage | — | **À créer** : écran natif Android (logo) puis page `/app` qui vérifie le service et la version. |
| Onboarding (1re ouverture, facultatif) | — | **À créer** (3 écrans, « Passer », affiché une seule fois). |
| Connexion / inscription | `/compte/connexion`, `/compte/inscription` | **Prêt** (à compléter par l'enregistrement des acceptations). |
| Récupération de compte | `/compte/mot-de-passe-oublie` (e-mail) | **Prêt** pour les comptes avec e-mail. Comptes « téléphone seul » : pas de récupération possible sans SMS (fournisseur non choisi) → limite connue, affichée honnêtement. |
| Validation des conditions | Case non tracée | **À créer** : documents versionnés + historique des acceptations + réacceptation. |
| Politique de confidentialité | `/confidentialite` (résumé) | **Prête à l'affichage** ; texte complet à **faire valider** (modèle fourni, informations manquantes listées). |
| Accueil | `/communaute`, `/fil` | **Prêt.** |
| Fil d'actualité | `/fil`, `/fil/abonnements`, `/publications`, `/explorer` | **Prêt** (défilement continu, publication, photos, vidéos, réactions, commentaires, partage, sondages). |
| Découvrir | `/decouvrir` | **Prêt.** |
| Profils personnels et organisationnels | `/u/{id}`, `/mutuelles/{slug}`, `/pages` | **Prêts.** |
| Engagement communautaire | « Je m'engage » : `/demandes/nouvelle?type=engagement` | **Prêt.** |
| Projets et résultats | `/projets`, `/projets/{id}` (résultats déclarés/vérifiés) | **Prêts.** |
| Soutien et partenariats | `/demandes/nouvelle?type=support`, `/opportunites` | **Prêts.** |
| Notifications | `/notifications` (in-app) | **Prêt** dans l'application. **Push Android : non** (évolution serveur à prévoir, voir §7). |
| Messagerie | `/messages` (membre ↔ mutuelle) | **Prête.** |
| Paramètres, confidentialité, compte | `/compte/parametres` (profil privé, mot de passe, suppression) | **Prêts** ; à compléter : consentements et historique d'acceptation, page publique de suppression de compte. |
| Centre d'aide et retours bêta | — | **À créer** (`/aide` + formulaire de retour, lu par le super-admin). |

**Hors périmètre de l'application bêta** : le back-office des mutuelles (données de santé et financières, MFA obligatoire) et l'espace super-admin restent sur le web. L'application ne les embarque pas : un lien vers `*.m.araild.com` s'ouvre dans un onglet Chrome séparé, jamais dans l'application.

---

## 4. Solutions Android comparées

| Critère | **A. TWA (recommandé)** | B. Capacitor (WebView + site distant) | C. Natif Kotlin / Flutter + API | D. PWA seule (« Installer l'application ») |
|---|---|---|---|---|
| Réutilise le site | 100 % (moteur Chrome) | ~100 % (WebView système) | 0 % de l'interface | 100 % |
| Travail serveur | Petit (liaison de domaine, démarrage, politiques) | Petit + adaptations WebView | **Très gros** : API complète du réseau (≈ 80 points d'accès), jetons, pagination JSON | Petit |
| Travail mobile | Petit (projet Android généré, ~10 fichiers) | Moyen (Node, plugins, sélecteur de fichiers, cookies) | **Très gros** (≈ 66 écrans à refaire) | Nul |
| Délai bêta | **Jours** | 1–2 semaines | **2 à 4 mois** | Immédiat |
| Mises à jour | **Instantanées** (le site) ; l'APK change rarement | Instantanées (site) ; plugins à suivre | Chaque écran = nouvelle version Play | Instantanées |
| Sessions et sécurité | Cookies du site dans Chrome, aucun jeton dans l'application | Cookies WebView (moins isolés), à configurer | Jetons à stocker (Keystore), API à sécuriser de bout en bout | Comme le site |
| Envoi de photos/vidéos | Natif Chrome | Plugin ou code de sélection de fichier | À recoder | Natif Chrome |
| Notifications push | Web Push (via Chrome, délégation à l'app) — évolution serveur | FCM via plugin — évolution serveur | FCM — évolution serveur | Web Push |
| Play Store | Accepté (PWA de qualité + liaison de domaine) | Risque « simple habillage de site » | Accepté | Pas de fiche Play |
| Téléphones modestes | Bon (Chrome à jour, cache partagé) | Moyen (WebView parfois ancienne) | Très bon si bien fait | Bon |
| Maintenance | **Une seule base de code** | Deux (site + coque) | Deux complètes (site + app) | Une |
| Limites | Chrome requis (sinon onglet Chrome personnalisé) ; pas d'accès natif avancé | Comportements WebView variables | Coût, risque de divergence fonctionnelle | Pas de présence Play, installation moins connue |

**Recommandation : A (TWA), construit avec la bibliothèque officielle Google `androidbrowserhelper`**, avec les évolutions serveur décrites au §6. C'est la seule option qui donne une vraie application Play rapidement, sans réécrire ni dupliquer le réseau social, et sans affaiblir la sécurité (aucun secret ni jeton dans l'application). Si un jour des besoins natifs forts apparaissent (hors ligne avancé, Bluetooth…), on pourra migrer écran par écran vers une API, sans jeter la bêta.

---

## 5. Architecture recommandée

```
 Téléphone Android                                   Serveur LWS (m.araild.com, inchangé sauf ajouts ⊕)
 ┌───────────────────────────────┐                   ┌────────────────────────────────────────────────┐
 │ App « MX-CONNECT » (TWA)      │  HTTPS            │ Laravel (pages web existantes du réseau social) │
 │  • écran de démarrage (logo)  │ ───────────────►  │ ⊕ /.well-known/assetlinks.json (liaison app)    │
 │  • ouvre /app?source=android… │                   │ ⊕ /app  : démarrage (service, version, onboarding│
 │  • moteur Chrome (cookies,    │ ◄───────────────  │          une fois, politiques, redirection)      │
 │    cache, sélecteur fichiers) │                   │ ⊕ documents légaux versionnés + acceptations    │
 │  • aucun secret, aucun jeton  │                   │ ⊕ /aide + retours bêta ; ⊕ /compte/suppression   │
 └───────────────────────────────┘                   │ Base centrale : comptes publics, réseau social  │
   liens *.m.araild.com (mutuelles) → onglet Chrome  │ (aucune donnée médicale) — bases des mutuelles  │
                                                     │ inchangées et hors de l'application             │
                                                     └────────────────────────────────────────────────┘
```

### 5.1 Démarrage et première utilisation (Phase 3)

1. **Logo** : écran de démarrage Android (couleur navy `#021F5A`, logo officiel) pendant que Chrome charge.
2. **Service et version** : l'application ouvre `/app?source=android&v=<versionCode>`. Le serveur compare la version à une **version minimale** réglable. Trop ancienne : page « Mise à jour requise », avec le lien Play. Service en maintenance : page d'information, avec bouton « Réessayer ». Pas de réseau : page hors ligne existante, avec « Réessayer ».
3. **Onboarding une seule fois** : trois écrans « Passer / Suivant », mémorisés sur l'appareil. Il n'est jamais montré à un utilisateur déjà connecté.
4. **Connexion ou inscription** : les pages existantes. Un utilisateur déjà inscrit se **connecte**, il n'a jamais à recréer de compte.
5. **Documents et acceptations** : à l'inscription, cases **non cochées**, séparées, avec lien vers chaque texte. Le serveur enregistre l'identifiant de la version, la date, le canal (Android/web) et l'adresse IP.
6. **Authentification** : session du site, « Se souvenir de moi » **proposé et coché par défaut seulement dans l'application** (une préférence de confort, pas un consentement).
7. **Profil et droits** : chargés par le serveur à chaque page (les droits restent vérifiés côté serveur).
8. **Accueil** : `/fil`.

Ouvertures suivantes : si la session est valide et qu'aucune acceptation n'est due, `/app` redirige **immédiatement** vers `/fil`. Session expirée : connexion, puis retour à la page demandée. Nouvelle version d'un document « à réaccepter » : écran d'acceptation **après** la connexion, avant toute action d'écriture ; la lecture reste possible pour ne pas bloquer inutilement.

### 5.2 Politiques et consentements versionnés (Phase 4)

Nouvelles tables **centrales** (aucune base parallèle) :

- `legal_documents` : type (`terms`, `privacy`, `community`, `consent_*`), version (ex. `2026-11-01`), titre, texte, `requires_acceptance` (oui pour les conditions et les règles communautaires ; **non** pour la confidentialité, qui est **portée à la connaissance**), `requires_reacceptance`, date de publication, statut (brouillon / publié). Un document publié n'est **jamais modifié** : on publie une nouvelle version.
- `legal_acceptances` : compte, document, version, type d'action (`accepted` / `acknowledged` / `consent_given` / `consent_withdrawn`), date, canal, IP, navigateur. L'historique est conservé, rien n'est écrasé.

Écrans : consultation de chaque document et de ses versions (`/legal/{type}`, `/legal/{type}/{version}`), « Mes acceptations » dans les paramètres, retrait d'un consentement spécifique (sans effet sur les conditions), publication d'une version par le super-admin.

**Textes** : des **modèles** seront préparés, marqués « MODÈLE — À FAIRE VALIDER ». Je n'invente ni l'identité juridique de l'exploitant, ni les traitements. Informations à fournir :
- raison sociale et forme juridique de l'exploitant (ARAILD ?) ;
- adresse, contact et éventuel délégué à la protection des données ;
- autorité de contrôle compétente (Cameroun, loi n° 2024-017 sur la protection des données personnelles, à confirmer par un juriste) ;
- durées de conservation ;
- sous-traitants (hébergeur LWS, envoi d'e-mails) ;
- transferts hors du pays ;
- âge minimum ;
- droit applicable.

### 5.3 Autorisations Android (Phase 5)

- Déclarées par l'application : **INTERNET** seulement.
- Photos et vidéos à publier : sélecteur de fichiers de Chrome, **aucune autorisation** de stockage demandée.
- Caméra : uniquement si l'utilisateur choisit « Prendre une photo » dans le sélecteur ; la demande vient alors de Chrome, au moment de l'action. Un refus est géré (choix d'un fichier existant).
- **Notifications (Android 13+)** : demandées seulement le jour où le push sera activé (§7), au moment où l'utilisateur l'active dans les paramètres. Pas pendant la bêta 1.
- **Jamais** : contacts, localisation (ni en premier plan ni en arrière-plan), SMS, appels, stockage complet.

### 5.4 Sécurité (Phase 6)

- Aucun identifiant de base de données, aucune clé serveur, aucun jeton d'API dans l'application. L'APK ne contient que l'adresse du site, le nom du paquet et l'empreinte publique du certificat.
- Les sessions restent des cookies `HttpOnly` / `Secure` gérés par Chrome. Les droits sont toujours vérifiés côté serveur (matrice existante).
- **Portée** de l'application limitée à `https://m.araild.com`. Les espaces des mutuelles (données de santé et financières) et l'espace super-admin s'ouvrent hors de l'application. Le service worker ne met en cache **aucune** page authentifiée ni donnée personnelle (vérifié dans `sw-social.js`).
- Suppression de compte :
  - **dans l'application** : existe déjà (mot de passe + mot « SUPPRIMER ») ;
  - **page publique** `/compte/suppression` (exigée par Google Play) : procédure et données supprimées ou conservées.
- Liaison de domaine : `assetlinks.json` lié à l'empreinte SHA-256 du certificat de signature, réglée dans `.env` (jamais dans le code).

### 5.5 Performance (Phase 7)

- Démarrage :
  - écran natif immédiat ;
  - `/app` répond sans requête lourde : contrôle de version et redirection ;
  - le fil se charge ensuite, avec la pagination continue existante.
- Fichiers statiques (CSS, JS, polices, logo) en cache ; les images sont déjà converties en WebP côté serveur ; la vidéo n'est pas lue automatiquement.
- Relève des nouveautés (toutes les 20 s) **suspendue quand l'application est en arrière-plan**, pour économiser données et batterie. À vérifier puis corriger si besoin.
- Mode allégé automatique déjà présent quand le serveur ralentit.
- Langue : français. L'architecture Laravel (`lang/`) permet d'ajouter l'anglais plus tard.

---

## 6. Évolutions serveur nécessaires (toutes additives)

| # | Évolution | Pourquoi | Risque |
|---|---|---|---|
| S1 | Route `/.well-known/assetlinks.json` (empreinte dans `.env`) | Plein écran sans barre d'adresse (TWA) | Nul |
| S2 | Page `/app` : démarrage (version minimale, maintenance, onboarding une fois, redirection) + réglages super-admin | Phases 3 et 7 | Faible |
| S3 | Documents légaux versionnés + acceptations + réacceptation + historique + publication par le super-admin + modèles à valider | Phase 4 (aujourd'hui l'acceptation n'est pas tracée) | Moyen : touche à l'inscription, à couvrir par des tests |
| S4 | `/aide` (FAQ) + formulaire de retour bêta (table `beta_feedback`) + écran super-admin | Phase 2 | Faible |
| S5 | Page publique `/compte/suppression` | Exigence Google Play | Nul |
| S6 | Manifeste PWA : `id`, `start_url` `/app`, raccourcis, captures | Qualité PWA et TWA | Nul |
| S7 (plus tard) | Notifications push (Web Push via Chrome, ou FCM) | Phase 2 « Notifications » | Moyen : nouvelle bibliothèque à livrer dans `vendor/` sur LWS, clés VAPID |

Toutes ces évolutions suivent le processus habituel : ZIP cumulatif, LISEZMOI, migrations automatiques, tests sur la copie fidèle LWS.

## 7. Risques et parades

- **Construction de l'APK impossible dans cet environnement** (`dl.google.com` bloqué). Deux options :
  - **(a) GitHub Actions** (recommandé) : les machines GitHub ont le SDK Android ; l'APK est construit et testé à chaque modification, et téléchargeable depuis GitHub ;
  - **(b)** autoriser `dl.google.com` dans les réglages réseau de l'environnement.

  Tant qu'aucune des deux n'est faite, je n'annoncerai aucun APK construit.
- **Tests sur appareil réel** (plein écran TWA, sélecteur de fichiers, retour arrière, Android 8 à 15) : impossibles ici ; ils feront partie de la liste de tests manuels.
- **Récupération de compte sans e-mail** : impossible sans SMS. Limite affichée, et invitation à ajouter un e-mail dans les paramètres.
- **Notifications push** absentes en bêta 1 : les notifications in-app restent fonctionnelles.
- **Chrome absent ou désactivé** sur l'appareil : l'application s'ouvre dans un autre navigateur compatible, ou dans un onglet personnalisé (barre d'adresse visible). Limite connue.
- **Régression web** : toutes les évolutions sont additives et testées (suite Pest et navigateur aux 4 largeurs).

## 8. Plan par étapes (après votre accord)

1. **Serveur — fondations** : S1, S5, S6, puis S2 (`/app`, version minimale, onboarding). Tests Pest et navigateur.
2. **Serveur — politiques versionnées** : S3 (tables, inscription, réacceptation, historique, publication super-admin, modèles à valider). Tests.
3. **Serveur — aide et retours bêta** : S4. Tests.
4. **Projet Android** : `android/`, Gradle, `androidbrowserhelper`, nom de paquet, icônes, écran de démarrage, `versionCode`, configuration de signature par variables d'environnement, **sans aucun secret dans le dépôt**.
5. **Construction** : GitHub Actions (APK de test signé avec une clé de bêta ; AAB pour le Play Store avec la clé d'envoi fournie en « secret » GitHub). Vérification statique de l'APK (manifeste, autorisations, empreinte).
6. **Livraison** :
   - ZIP serveur cumulatif + LISEZMOI ;
   - code Android + guide de construction, signature, installation et publication en test fermé Play ;
   - rapport de tests (exécutés, automatisés, manuels à faire) ;
   - limites connues et plan de retour en arrière.

## 9. Décisions dont j'ai besoin

1. **Architecture** : TWA (recommandé), Capacitor, ou natif ?
2. **Nom du paquet Android**, définitif une fois publié sur Play. Proposition : `com.araild.mxconnect`.
3. **Construction de l'APK** : GitHub Actions (recommandé), ou autorisation de `dl.google.com` dans l'environnement ?
4. **Informations juridiques** pour les modèles de documents : voir §5.2. Elles peuvent arriver plus tard ; les modèles resteront marqués « À FAIRE VALIDER ».
