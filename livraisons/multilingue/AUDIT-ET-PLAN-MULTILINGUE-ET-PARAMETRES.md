# MX-CONNECT — Refonte des paramètres et plateforme multilingue : état des lieux et workflow proposé

20/11/2026. **Validé par le fondateur le 20/11/2026** : 7 langues (français, anglais, portugais, espagnol, arabe, plus swahili et haoussa), réseau social en premier après les fondations, fiche « Identité et langue » retenue. Relecture des traductions : à désigner.

## 1. État des lieux

### 1.1 Les langues aujourd'hui
- **Langue unique de fait : le français.** `config/app.php` fixe `fr` ; aucun code ne change la langue selon la personne ou la mutuelle.
- **Les bases existent, mais servent peu :**
  - une table centrale `locales` (fr, en ; activables dans Super-admin → Configuration → Langues) ;
  - chaque mutuelle a déjà une langue (`mutuals.locale_id`), qui n'est appliquée nulle part ;
  - des fichiers `lang/fr/mxconnect.php` et `lang/en/mxconnect.php` (840 lignes chacun), utilisés surtout par l'ancien back-office.
- **Textes écrits en dur en français** (estimation par comptage automatique) :

| Espace | Écrans | Segments de texte en dur | Déjà traduisibles |
|---|---|---|---|
| Réseau social, portail adhérent, application Android | 96 | ≈ 1 230 | presque aucun |
| Back-office des mutuelles | 118 | ≈ 1 580 | ≈ 510 appels |
| Espace prestataire | 16 | ≈ 240 | aucun |
| Super-admin | 29 | ≈ 320 | ≈ 170 appels |
| Accueil, App Center, erreurs, e-mails | 14 | ≈ 260 | quelques-uns |
| Messages des contrôleurs et services (succès, refus, libellés) | — | ≈ 2 100 chaînes | quelques-unes |
| Scripts JavaScript (fil, App Center, lecteur QR) | 4 | quelques dizaines | aucun |

  Au total, environ **5 000 à 6 000 textes** à rendre traduisibles, puis à traduire dans chaque langue.
- **Arabe** : il s'écrit de droite à gauche. Toutes les mises en page sont faites pour la gauche (barre latérale à gauche, marges, flèches). Il faudra une **feuille de style « droite à gauche »** dédiée : les CSS compilés n'ont pas de configuration dans le dépôt.
- **Dates, nombres, montants** : formatés « à la française » à environ 100 endroits. Il faudra les faire suivre la langue, sans jamais convertir les devises.

### 1.2 Les paramètres aujourd'hui
- **Réseau social**
  - Membre : une seule page « Paramètres du compte » (`/compte/parametres`) qui mélange profil, visibilité, notifications, mot de passe et suppression.
  - Super-admin : « Réseau social → Réglages » (mode allégé, sponsorisation).
- **Gestion des mutuelles**
  - Menu « Paramètres » de 9 rubriques : équipe, prestataires, formules, changements de formule, dérogations, garanties, antennes, actes, médicaments.
  - D'autres réglages sont dispersés ailleurs : règle de suspension (dans Cotisations), politique pharmaceutique (dans Pharmacie), accès du portail adhérent (dans Adhérents).
  - Il n'existe pas de fiche « Identité de la mutuelle » (nom, logo, langue, devise affichée) côté mutuelle : seul le super-admin la modifie.

## 2. Principes de la refonte (« sans casser ce qui marche »)
1. **Aucune adresse ne change.** Les écrans actuels restent ; la refonte les regroupe et les réorganise.
2. **Le français reste la langue source.** Chaque texte français devient sa propre clé de traduction (fichiers `lang/en.json`, `lang/pt.json`…). Si une traduction manque, le français s'affiche : jamais une clé technique ni un écran vide.
3. **Livraison par espace**, chacune testée comme d'habitude : Pest, tables préfixées, navigateur 320/360/768/1366 px. On y ajoute un contrôle par langue et le sens droite à gauche pour l'arabe.
4. **Test de complétude automatique** : toute clé française doit exister dans chaque langue activée, avec les mêmes variables (`:montant`, `:date`…). Une nouvelle phrase non traduite fait échouer les tests.
5. **Rien n'est inventé** : les contenus saisis par les utilisateurs ne sont pas traduits automatiquement (publications, noms de formules, motifs, documents).
6. **Honnêteté** : je fournis les traductions, mais elles sont marquées « à relire » tant qu'un locuteur natif ne les a pas validées. C'est indispensable pour l'arabe et pour le vocabulaire médical et juridique. Les documents légaux doivent être relus par un juriste.

## 3. Qui voit quelle langue
Ordre de priorité proposé :
1. **Le choix de la personne**, enregistré sur son compte : membre, personnel de la mutuelle, prestataire, super-admin. Un sélecteur de langue est présent partout, y compris avant connexion.
2. **La langue de la mutuelle**, pour son personnel et ses pages publiques (`mutuals.locale_id`, déjà en base).
3. **La langue du navigateur**, si elle est activée.
4. **Le français**.

**Langues** : le super-admin active ou désactive chaque langue (l'écran existe déjà, il sera complété d'un taux de traduction). Chaque mutuelle choisit sa langue par défaut et les langues proposées à ses adhérents.

## 4. Workflow proposé (6 livraisons)

| # | Livraison | Contenu | Visible pour l'utilisateur |
|---|---|---|---|
| **M1** — **faite le 27/11/2026** (VERSION 2026.11.27) | Fondations | Choix de la langue (comptes, mutuelle, navigateur), sélecteur, `lang` et `dir` corrects, feuille droite à gauche, dates, nombres et montants selon la langue, fichiers de langue, test de complétude, écran « Langues » du super-admin avec le taux de traduction. Migrations additives : colonne `locale` sur les 4 types de comptes, langues proposées par mutuelle. | Le sélecteur ; aucune page ne change en français. |
| **M2** | Refonte des paramètres du réseau social, traduite | Membre : `/compte/parametres` en sections (Profil, Compte et sécurité, Confidentialité, Notifications, Langue et région, Applications, Mes données et suppression), avec navigation latérale sur ordinateur et liste repliable sur mobile. Super-admin : réglages du réseau en sections. Puis tout le réseau social, le portail adhérent, l'application Android, les notifications et les e-mails dans les 5 langues. | Paramètres réorganisés ; réseau social multilingue. |
| **M3** | Refonte des paramètres de la mutuelle, traduite | Page d'accueil « Paramètres » en cartes, qui regroupe toutes les rubriques existantes et les réglages dispersés (règle de suspension, politique pharmaceutique, portail adhérent), avec une recherche. Nouvelle fiche « Identité et langue de la mutuelle » (A seul) : langue par défaut, langues des adhérents, coordonnées affichées. Puis tout le back-office dans les 5 langues. | Paramètres regroupés ; back-office multilingue. |
| **M4** | Espace prestataire et portail | Espace central, portail dans la mutuelle, avis, bordereaux. | Multilingue. |
| **M5** | Super-admin, accueil, App Center, pages d'erreur | Dernier espace. | Toute la plateforme est multilingue. |
| **M6** | Documents imprimables et documents légaux | Carte, reçus, attestation, bordereau dans la langue de la personne ; documents légaux versionnés **par langue** (une langue n'est imposée à l'acceptation que si sa version existe). | Documents multilingues. |

Chaque livraison suit le circuit habituel : ZIP cumulatif, LISEZMOI, VERSION, tests, navigateur, CLAUDE.md.

## 5. Ce qui n'est pas prévu (sauf demande)
- Traduction automatique des contenus des utilisateurs (publications, commentaires, messages).
- Traduction des référentiels saisis par chaque mutuelle (noms de formules, d'actes, de médicaments). Option possible plus tard : un champ « traductions » sur ces fiches.
- Langues africaines (swahili, haoussa, wolof…) : la structure les accepte, il suffira d'ajouter un fichier de langue.

## 6. Décisions demandées au fondateur
1. **Langues de la première vague** : français, anglais, portugais, arabe, espagnol. En ajouter d'autres ?
2. **Ordre** : commencer par le réseau social (M2) ou par la mutuelle (M3) après les fondations ?
3. **Refonte des paramètres** : réorganisation des fonctions existantes, plus la fiche « Identité et langue », comme décrit. Faut-il ajouter d'autres réglages ?
4. **Relecture des traductions** : qui relira l'arabe, le portugais et l'espagnol ?

## 7. Suivi des livraisons

| Livraison | État | LISEZMOI |
|---|---|---|
| M1 Fondations | Faite (VERSION 2026.11.27) | `LISEZMOI-20261127.md` |
| M2a Réseau social : paramètres du membre + écrans | Faite (VERSION 2026.11.28) | `LISEZMOI-20261128.md` |
| M2b Réseau social : phrases avec variables, messages du serveur, notifications, e-mails, JavaScript | Faite (VERSION 2026.11.29) | `LISEZMOI-20261129.md` |
| M3a Mutuelle : page Paramètres en cartes, fiche « Identité et langue », cadre du back-office et écrans de paramétrage | Faite (VERSION 2026.11.30) | `LISEZMOI-20261130.md` |
| M3b Mutuelle : reste du back-office (adhérents, prestations, cotisations, comptabilité, trésorerie, rapports, pharmacie, portail prestataire de la mutuelle) + vocabulaire `mxconnect.php` complet dans les 6 langues | Faite (VERSION 2026.12.01) | `LISEZMOI-20261201.md` |
| M3c Mutuelle : messages renvoyés par le serveur (contrôleurs et services du back-office, `AccountingRefusal`, `TeamService`…) — **étape M3 terminée** | Faite (VERSION 2026.12.02) | `LISEZMOI-20261202.md` |
| M4 Prestataires | À faire | — |
| M5 Super-admin, accueil | À faire | — |
| M6 Documents | À faire | — |

### Règles techniques posées par M1 (à suivre dans M2 à M6)
- **Texte traduisible** : `__('Texte français exact')`. La clé est le texte français ; elle doit être ajoutée à `lang/fr.json` et à chaque `lang/<code>.json`. Le test `MultilingueFondationsTest` échoue sinon.
- **Variables** : `:nom` identiques dans toutes les langues (contrôlé).
- **Montants** : `Currency::format()` suit la langue (séparateurs seulement).
- **Sens d'écriture** : `<html {!! Locales::htmlAttributes() !!}>` ; corrections physiques dans `css/mx-rtl.css` (structure, puis écran par écran).

### Règles ajoutées par M3a
- **Langue du personnel** : choix personnel (colonne `users.locale`) → langue par défaut de la mutuelle (`mutuals.locale_id`, fiche « Identité et langue ») → navigateur → français.
- **Adhérents** : une notification à un adhérent qui n'a jamais choisi de langue est écrite dans la langue par défaut de sa mutuelle (`MutualIdentity::locale()` passée en repli à `NotificationService::notify`).
- **Nouvelle rubrique de paramétrage** : l'ajouter à `App\Support\TenantSettings::SECTIONS` (carte affichée seulement si `TenantAccess::can()` l'autorise).
- **Libellés de menu, rôles, sections** : traduits à l'affichage avec `__()` ; déclarés via `TranslatableLabels::extra()`.
- **Vocabulaire `lang/<code>/mxconnect.php`** : complet en anglais ; en pt, es, ar, sw, ha, complet pour toutes les clés employées par le back-office (M3b, 351 clés ; test de couverture dans `MultilingueMutuelleTest`). Les clés propres au super-admin restent à faire en M5 (repli : français).

### Règles ajoutées par M3c
- **Message renvoyé par le serveur** (flash, `withErrors`, `abort`, `refuse(…)`, `AccountingRefusal`, messages de validation) : toujours `__('Texte :var', ['var' => …])`, jamais de concaténation ni de `"{$x}"`. Outil : `devtools/i18n/messages-php.py` (reconnaît aussi `refuse(`).
- **Libellé stocké** (écriture comptable, mouvement de trésorerie) : reste en français, non traduit, pour que le journal soit le même pour tous.
- **Phrases à deux variantes** : deux `__()` complets (`$x ? __('… bénéfice …') : __('… perte …')`), jamais `__($x ? '…' : '…')` (non repéré par les scanners).

### Règles ajoutées par M3b
- **Listes de libellés dans une vue** : les traduire à l'affectation (`$S = array_map('__', Modele::STATUSES)` ou `['clé' => __('Libellé')]`), pas à chaque écho ; déclarer la constante dans `TranslatableLabels::LISTS` (ou `extra()` pour une liste [libellé, teinte]).
- **Méthodes d'affichage** (`label()`, `categoryLabel()`, `RightsStatus::label()`…) : elles renvoient le texte traduit ; les valeurs stockées restent les clés françaises.
- **Nouvelle vue du back-office** : passer `devtools/i18n/extraire-blade.py --apply` puis `convertir-variables.py --apply`, relire le diff (classes CSS jamais traduites), puis `catalogue.py sync` + traductions.
- **Outils (M2a)** : `devtools/i18n/extraire-blade.py` (`--dry-run`, `--apply`, `--list`, `--mixed`) rend traduisibles les textes « purs » d'une vue ; `devtools/i18n/catalogue.py` (`sync`, `missing`, `merge`) tient le catalogue et refuse une traduction aux variables différentes. Relire le `git diff` après extraction (directives aux parenthèses imbriquées, attributs liés `:title`).
- **Listes de libellés PHP** (constantes `STATUSES`, `TYPES`…) : afficher avec `__($libellé)` et **déclarer la constante dans `App\Support\TranslatableLabels::LISTS`** (test `MultilingueReseauSocialTest`). Les valeurs enregistrées en base restent les clés, jamais le texte traduit.
- **Mot vérifié par le serveur** (« SUPPRIMER ») : jamais traduit.
- **Phrases avec variables (M2b)** : `__('Projets de :name', ['name' => …])` ; outil `devtools/i18n/convertir-variables.py` pour les vues, `devtools/i18n/messages-php.py` pour les messages du code PHP (relire le diff). Pluriels : `trans_choice(':count x|:count y', $n, ['count' => $n])`.
- **Notifications (M2b)** : titre et texte sous la forme `Msg::of('Texte :var', [...])`, traduits dans la langue du DESTINATAIRE par `NotificationService` (valeur `['__' => 'Libellé']` = libellé traduit). Un texte saisi par une personne reste une chaîne simple.
- **JavaScript (M2b)** : `T('Texte :var', {var: …}, n)` dans `js/mx-social*.js` ; chaque clé déclarée dans `App\Support\JsTranslations::SOCIAL` (test), traductions envoyées par le gabarit dans `<script type="application/json" id="mx-i18n">`.
- **Une langue est « complète »** seulement quand elle figure dans `Locales::COMPLETE` ; sinon le sélecteur affiche « traduction partielle ».
