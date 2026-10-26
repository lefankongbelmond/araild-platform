# CLAUDE.md — MX-CONNECT (contexte de reprise pour Claude Code)

Fichier de passation depuis claude.ai (projet « M CONNECT »). À placer à la racine du dépôt.
Langue de travail : **français** (code commenté, guides, LISEZMOI, messages UI).
Interlocuteur : Lefankong Belmond (Yaoundé) — fondateur ARAILD / KidAid. Réponses concises, chaleureuses, orientées action.

## 0. ⚠ État de CE dépôt (à lire en premier — ajouté le 26/10/2026)

Le dossier `mx-connect/` de ce dépôt **n'est pas** le code de production.

- Il part de l'archive « foundation » du 12/08/2026 (M-CONNECT, avant toutes les livraisons claude.ai).
- Il contient, sur la branche `claude/mx-connect-vps-setup-jfby6i` : la mise à jour Laravel 13 (faille CVE-2026-48019 d'illuminate/mail), puis un Lot 1 (tables sociales centrales) et un Lot 2 (réseau social + actions « Je m'engage », « Voir le projet et les résultats », « Soutenir ou devenir partenaire »), voir `mx-connect/docs/LOT2_RESEAU_SOCIAL.md`.
- La **production** (https://m.araild.com, livraisons jusqu'au 21/10/2026) est beaucoup plus avancée et a des conventions différentes (cache fichiers, polling, `/media/…`, CSS compilé, palette « Premium », routes `/compte/connexion`, `/se-connecter`, `/communaute`…). **Son code est dans `production/`** (copie exacte du ZIP `mxconnect-maj-20261021.zip`, = `htdocs/mconnect`), sans `composer.json`/`composer.lock`, `artisan`, `vendor/` ni `tests/`. C'est la base de référence pour toute nouvelle livraison.
- Conséquence : **ne rien livrer en production depuis `mx-connect/` tel quel.** Toute évolution repart de `production/`.
- **Lot 3 (26/10/2026, VERSION 2026.10.26) est fait dans `production/`** : projets à résultats déclarés/vérifiés, emplois et opportunités (recherche `/opportunites`), demandes « Je m'engage » / « Soutenir ou devenir partenaire » / candidatures (CV PDF privé), pour les mutuelles ET les pages des membres ; back-office « Réseau social → Projets et opportunités » ; réseau « Projets et offres ». Livrable courant : `livraisons/mxconnect-maj-20261027.zip` (VERSION 2026.10.27, cumulatif : Lot 3 + correctif du service worker qui resservait l’ancien CSS) + `LISEZMOI-20261027.md` / `LISEZMOI-20261026.md`. Le 27/10, l’utilisateur signalait « rien n’a changé » après déploiement du 26/10 : non encore confirmé installé. **Changer `VERSION` dans `sw-social.js` à chaque livraison.**
- **Lot 4 (28/10/2026, VERSION 2026.10.28)** : fonctionnalités et **design du Lot 2 portés en production, palette Lot 2 choisie par le fondateur** (navy `#0B2559`, vert d'action `#2E9E4F`, bleu info `#2F6FDB`, orange soutien `#E07B24` ; dans `css/mx-social.css`, `--mx-blue` = couleur d'action, désormais verte). Domaines d'intervention (`App\Support\SocialDomains`, colonne `domains` sur profils et projets), `/decouvrir`, accueil Lot 2 sur `/communaute` (la page `/` reste inchangée), en-tête de profil Suivre/Je m'engage/Soutenir, tuiles d'action dans le fil, page projet (avancement %, bénéficiaires, partenaires, publications liées), tendances de la semaine. Livrable courant : `livraisons/mxconnect-maj-20261028.zip` (cumulatif) + `LISEZMOI-20261028.md`. Non confirmé installé.
- **Lot 5 (29/10/2026, VERSION 2026.10.29)** : documents PDF publics (publications au nom d'une page, back-office, projets ; vrai PDF contrôlé, téléchargement forcé + CSP sandbox via `/documents/{id}`, jamais via `/media`, jamais sur un profil personnel) et sondages (2–4 choix, durée, vote unique modifiable, résultats anonymes). Contrôle « Envoi de fichiers » ajouté à `/_installation`. Livrable courant : `livraisons/mxconnect-maj-20261029.zip` (cumulatif) + `LISEZMOI-20261029.md`. Non confirmé installé.
- **Lot 6 (30/10/2026, VERSION 2026.10.30)** : back-office « Paramètres → Équipe et rôles » (`TeamController`, `App\Services\Tenant\TeamService`) : ajout du personnel avec mot de passe provisoire affiché une fois + changement obligatoire (`must_change_password`, middleware `TenantAccountGuard` qui déconnecte aussi les comptes désactivés), contrôle « 4 yeux » réellement appliqué, dernier admin protégé, réinitialisation mot de passe/MFA, page « Rôles et droits » calculée depuis `App\Support\TenantNav` (source unique du menu tenant), « Mon mot de passe ». Migration TENANT `2026_10_30_000100`. **Constat signalé, non corrigé** : les rubriques sans restriction sont visibles par tous les rôles (ex. « Prestataire de soins » voit Trésorerie/Journal/Audit) — à décider avec le fondateur. Livrable courant : `livraisons/mxconnect-maj-20261030.zip` + `LISEZMOI-20261030.md`. Non confirmé installé.
- `production/mxconnect/composer.json` + `composer.lock` (fournis le 26/10, = Laravel 13.25), `tests/` (Pest, 20 tests Lot 3) et `phpunit.xml` sont dans le dépôt mais **exclus du ZIP**. Copie fidèle LWS exécutable : `devtools/copie-lws/` (voir son LISEZMOI).
- Le site n'est pas joignable depuis les sessions cloud tant que `m.araild.com` n'est pas dans les domaines autorisés de l'environnement.

## 1. Le produit

**MX-CONNECT** : plateforme SaaS multi-tenant de digitalisation des mutuelles de santé communautaires (Cameroun / Afrique), avec un réseau social grand public.

- Historique du nom : MUTUALINK → M-CONNECT → MX-CONNECT (renommage vers une marque inventée, courte, « futuriste » envisagé).
- 6 modules de référence (guide M-ASSUR) : Inscriptions, Cotisation, Prestation, Trésorerie, Comptabilité, Utilitaires.

### Principes non négociables

- Une base de données isolée par mutuelle (stancl/tenancy).
- La plateforme ne détient jamais de fonds : aucun paiement encaissé ni simulé ; réconciliation idempotente le jour où des passerelles existent.
- Multi-devises sans conversion automatique (XAF/XOF jamais mélangés ; prix stockés dans leur devise).
- Comptabilité SYSCOHADA.
- Aucune donnée médicale dans le réseau social / adhésions en ligne / notifications.
- Honnêteté de l'UI : pas de bouton qui ne mène nulle part, pas de faux chiffres (chiffres réels ou badge « Exemple »), pas de fonction simulée (SMS, paiement, vidéo de démo…). Les données de démo sont marquées « Démo » et exclues des stats.

## 2. Stack

Laravel **13.25** (vérifié dans le `composer.lock` de production le 26/10/2026 — et non Laravel 11 ; ≥ 13.10 donc corrigé pour CVE-2026-48019) · stancl/tenancy 3.10.1 · PHP 8.4 (requis : symfony/console 8.1 exige PHP ≥ 8.4.1) · MariaDB 11.8 · Apache · Redis (VPS) · Reverb (VPS) · Sanctum · Google2FA (TOTP) · spatie/permission · Fortify (routes désactivées) · Pest.
Back-office mutuelles : CSS compilé `css/mx-tenant.css` (plus de Tailwind CDN).

## 3. Environnements

### A. Production active — hébergement web mutualisé LWS ⭐

- URL : https://m.araild.com (⚠ `www.m.araild.com` n'existe pas) ; mutuelles sur `https://<slug>.m.araild.com`.
- Dossier du sous-domaine (à côté du site de l'école ARAILD — ne pas toucher au htdocs principal d'araild.com) : `htdocs/mconnect` → contient `.htaccess`, `index.php`, `robots.txt`, `pwa/`, `mxconnect/` (app protégée, `.env` doit renvoyer 403).
- Base centrale : `arail2824952_3u2lww`, utilisateur `arail2824952`, hôte `localhost`.
- Pas de SSH, pas d'`exec`, pas de symlinks fiables, pas de `CREATE DATABASE`, pas de ffmpeg, pas de Redis/Reverb.
  - Cache/sessions : fichiers (isolés par mutuelle). File : table `jobs`.
  - Cron par URL : `/_cron?token=…` toutes les 5–15 min (cron LWS ou cron-job.org).
  - Fil social : polling 20 s.
  - Médias servis par l'app via `/media/…` (pas `/storage`), tout converti en WebP (repli JPEG).
  - Sauvegardes PHP : `mxconnect/storage/app/backups` (7 conservées).
- Créer une mutuelle : base vide créée à la main dans le panneau LWS + sous-domaine pointant vers le même dossier + SSL Let's Encrypt, puis formulaire Réseau → Mutuelles → Créer (vérifie base accessible/vide, crée 46 tables, 7 rôles, plan SYSCOHADA, 1er admin — mot de passe affiché une fois).
- Mutuelle de test : `mutueltest`. Mutuelle réelle : TOGETHER.
- Envoi e-mail : SMTP réglable depuis `/_installation` (enregistré seulement si l'e-mail de test part) ; boîte `no-reply@araild.com`.

### B. VPS LWS (historique) — mx-connect.com

- IP 185.98.139.224, ISPConfig, app `/var/www/clients/client1/web4`, propriétaire `web4:client1`, MySQL user `mconnect`.
- Base centrale `c1central`, tenant TOGETHER `mut_01` → `mxconnect_mut_mut_01`.
- Script `deploy/deploy-lws.sh` (`--dry-run`, `--rollback`, `KEEP_WEB_ROUTES=1`), sauvegardes `/root/mxconnect-backups/<date>/`.
- En attente : certificat wildcard `*.mx-connect.com` (DNS-01 manuel via zone LWS, cf. `deploy/DEPLOIEMENT_LWS.md` §10).

## 4. Processus de livraison (mutualisé)

- Chaque livraison = ZIP cumulatif `mxconnect-maj-AAAAMMJJ.zip`, sans `.env`, sans mot de passe, sans `vendor/` (sauf paquet d'installation initial), + un `LISEZMOI-…md` en français (problème, ce qui change, installer, vérifier, non inclus, tests réalisés).
- Installation : extraire dans le dossier du site en remplaçant (y compris `index.php` et `.htaccess`).
- `index.php` racine détecte `mxconnect/VERSION` : à la 1re visite après MAJ, il purge les caches (config/routes/vues/OPcache), applique les migrations centrales + tenants et lance les conversions par lots. → **Incrémenter `VERSION` à chaque livraison.**
- `/_installation?token=<INSTALL_TOKEN>` (≥ 24 car.) : contrôles, adresse du site, SMTP, appliquer la MAJ, voir les 6 dernières erreurs (secrets masqués), réinitialiser la MFA d'un admin réseau, données de démo, Terminer (vide le jeton → 404).
- Tester sur une copie fidèle du site LWS (Apache, `RewriteBase /`, PHP 8.4, fonctions système désactivées, symlinks refusés) + navigateur réel 320/360/768/1366 px, aucune erreur JS, aucun défilement horizontal.
- Suites de non-régression actuelles : réseau 59 + 41 + 47 + 38 (+40 navigateur), back-office mutuelles 24, e-mails 16, accueil 19, médicaments 20, gestion avancée 49, Pest 37. Lots 3 à 6 : Pest 43 (dont 7 dans une vraie base de mutuelle `mx_tests_tenant`) (dans `production/mxconnect/tests`, aides communes dans `tests/Pest.php`), navigateur 137 affichages + 1 parcours complet.

## 5. Leçons multi-tenant (à relire avant de toucher à la tenancy)

1. `TenancyServiceProvider` : `BootstrapTenancy` / `RevertToCentralContext` obligatoires ; pipeline synchrone create → migrate → seed.
2. Connexion `tenant` déclarée dans `config/database.php` ; `template_tenant_connection => 'mysql'`.
3. File `database` par défaut (redis sans worker = jobs perdus).
4. Grants MySQL : motif échappé `mxconnect\_mut\_%`.
5. Ordre des migrations selon les FK (`members` avant `feed_tables` → renumérotée 100250).
6. Jamais de `DB::transaction()` autour du DDL (COMMIT implicite) ; nettoyage auto (ligne + domaine + base) si provisioning échoue.
7. Table `domains` : stancl attend `tenant_id` (modèle `App\Models\Central\Domain` adapté).
8. Sous-domaine = `<slug>.` + `TENANT_BASE_DOMAIN` (pas `env('CENTRAL_DOMAIN')` après `config:cache`).
9. Seeder des rôles : garde `web` forcée. Cache spatie séparé par mutuelle.
10. `tenants:migrate` / `tenants:seed` avec `--force` + contrôle post-création (tables + rôles présents sinon annulation).
11. Contrôleur de base avec `AuthorizesRequests`. Sous-domaine inconnu → 404. Session sur la base centrale.
12. Après un `cp` en root : `chown -R web4:client1` + chmod. Toujours auditer une archive contre les correctifs précédents.

## 6. État fonctionnel (au 21/10/2026, dernière livraison connue)

**Comptes** : admin réseau `/reseau/connexion` (e-mail + MFA TOTP) ; personnel mutuelle `<slug>.../connexion` (+ MFA) ; membres `/compte/connexion` (téléphone ou e-mail). Choix d'espace : `/se-connecter`.

**Page d'accueil plateforme** (`/`) d'après maquette : chiffres réels uniquement, tarifs lus depuis Facturation → Plans (sinon « sur devis »), formulaire de démo → menu « Démos » du super-admin. Ancienne home sociale : `/communaute`.

**Réseau social** (livraisons 1→20/10→21/10) : fils (Pour vous, Abonnements, Toutes, Organisations, Mutuelles, Explorer), pages de mutuelles (badge vérifié = agrément seulement), publications texte/6 photos/vidéo (MP4/MOV/WebM ≤ 100 Mo/15 min, envoi par morceaux 1,5 Mo) et réels (≤ 90 s), réactions, commentaires, partage sans imbrication, mentions @, hashtags, aperçus de liens (anti-SSRF), emojis (utf8mb4), défilement continu, groupes publics/privés gérés par les mutuelles, adhésion en ligne avec pièces privées (`storage/app/private-social`), messagerie membre↔mutuelle, notifications in-app + e-mail (désactivables), mot de passe oublié, suppression de compte, pages créées par les membres (max 5, jamais vérifiées, gestionnaires admin/éditeur), suivre un membre, photo de couverture, sponsorisation (paiement hors plateforme + référence, activation manuelle, 1 sponso/page de fil, 3×/jour/personne), signalements (publication, commentaire, réel, profil, page) + suspension de comptes, mode allégé auto (40 % des requêtes > 2,5 s → 10 min), pages d'erreur FR 419/429/500/503.

Palette réseau : « Premium » jusqu'au 27/10 ; **depuis le 28/10, palette Lot 2** (voir §0). Mobile < 900 px : en-tête (+, recherche, ☰) + barre d'icônes, pas de barre du bas. Logo officiel : navy `#021F5A`, bleu `#0A5FB5`, vert `#4F992F`. Nom toujours écrit « MX-CONNECT ».

**Back-office mutuelle** : barre latérale navy groupée (Adhérents, Facturation & finances, Conformité, Réseau social, Paramètres), en-tête unifié sur 28 pages, Médicaments multi-devises (34 devises), Actes refaits (tarif conventionnel), listes de référence importables (48 actes, 55 médicaments essentiels, 5 garanties types — sans prix/tarifs), Facturation (tableau de bord), calcul de quote-part (simulation + bon imprimable), budget (classes 6/7, exécution depuis écritures validées, adoption = verrouillage), cartes de membre (format CB, QR signé, 10/A4), notifications aux membres (app + e-mail, lien WhatsApp, export CSV SMS).

## 7. Non fait / prochaines pistes

- SMS (choix fournisseur Orange/MTN/agrégateur), vérification téléphone par SMS.
- Paiement en ligne (Mobile Money) — jamais simulé.
- Registre des fournisseurs, pages Factures / Rapports (absentes volontairement du menu). Rôles & droits : fait au Lot 6.
- Taux de change / sélecteur de devise d'affichage.
- Transcodage vidéo (impossible sur mutualisé ; HEVC iPhone → réglage « Le plus compatible »).
- Wildcard SSL `*.mx-connect.com` (VPS) ; renommage de la marque.
- Non testé : envois réels SMS, passerelles de paiement, S3, Reverb derrière Apache.

## 8. Façon de travailler

- Livrables attendus : ZIP déployable + LISEZMOI français clair + liste des tests réalisés et de ce qui n'est pas vérifiable.
- Suivre les maquettes fournies de près, avec cycles de revue (marges, breakpoints, menu mobile), en signalant explicitement tout écart et sa raison.
- Ne jamais committer de secrets (jetons d'installation/cron, APP_KEY, mots de passe BDD/SMTP).
