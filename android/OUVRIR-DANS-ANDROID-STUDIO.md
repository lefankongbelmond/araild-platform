# Finaliser MX-CONNECT dans Android Studio

Projet prêt à ouvrir : application `com.araild.mxconnect`. C'est une fenêtre plein écran (TWA) sur https://m.araild.com.
Il a déjà été compilé avec succès par GitHub Actions (APK de test + lint sans erreur).

## 1. Ouvrir le projet (10 min)
1. Installer **Android Studio** (version récente, gratuite) : https://developer.android.com/studio
2. Décompresser le ZIP, puis **File → Open** et choisir le dossier `android` (celui qui contient `settings.gradle.kts`).
3. Accepter « Trust project ». Android Studio télécharge Gradle et le SDK Android 36, puis synchronise (connexion Internet requise, quelques minutes la 1re fois).
   - S'il demande un JDK : **Settings → Build → Gradle → Gradle JDK = « jbr-21 »** (fourni avec Android Studio) ou JDK 17.
   - Le fichier `local.properties` (chemin du SDK) est créé automatiquement. Ne le partagez pas.

## 2. Essayer sur un téléphone
1. Sur le téléphone, ouvrir **Paramètres → À propos → appuyer 7 fois sur « Numéro de build »**, puis activer **Options pour les développeurs → Débogage USB**.
2. Brancher le téléphone, choisir l'appareil en haut d'Android Studio, puis ▶ **Run 'app'**.
3. L'application s'ouvre sur l'écran de démarrage, puis sur la Bienvenue (ou l'accueil si vous êtes déjà connecté).
   - Une **barre d'adresse en haut** est normale en mode test : elle disparaît une fois l'empreinte de la clé saisie sur le site (étape 4).
   - Le téléphone doit avoir **Chrome à jour**.

## 3. Créer la version à publier (fichier .aab)
1. **Build → Generate Signed App Bundle / APK… → Android App Bundle → Next**.
2. **Key store path → Create new…** : choisissez un emplacement HORS du projet, un mot de passe fort, l'alias `mxconnect` et une validité de 25 ans ou plus.
   ⚠ **Gardez ce fichier `.jks` et ses mots de passe en lieu sûr (2 copies).** Sans eux, plus aucune mise à jour possible.
3. Variante **release** → **Create**. Fichier produit : `android/app/release/app-release.aab`.
4. À chaque nouvelle version : augmenter `mxVersionCode` dans `gradle.properties` (1 → 2 → 3…).

## 4. Relier l'application au site (plein écran)
1. Récupérer l'empreinte SHA-256 du certificat :
   - **si vous publiez sur Google Play** (recommandé) : Play Console → votre application → **Intégrité de l'application → Signature de l'application → Empreinte du certificat SHA-256** ;
   - **pour l'APK installé à la main** : Android Studio → panneau **Gradle** → `app › Tasks › android › signingReport` (ligne `SHA-256`), ou `keytool -list -v -keystore votre.jks -alias mxconnect`.
2. Sur le site : **Réseau (super-admin) → Application Android → Réglages → Empreintes SHA-256** → coller (une par ligne) → Enregistrer.
3. Vérifier : https://m.araild.com/.well-known/assetlinks.json doit afficher `com.araild.mxconnect` et l'empreinte.
4. Désinstaller puis réinstaller l'application : la barre d'adresse disparaît.

## 5. Publier en test interne (Google Play)
Play Console (inscription unique de 25 $) → **Créer une application** → *Tests → Test interne* → **Créer une version** → déposer `app-release.aab` → ajouter les e-mails des testeurs → partager le lien.

À remplir avant l'envoi :
- fiche de l'application ;
- **Sécurité des données** ;
- classification du contenu, public cible ;
- politique de confidentialité : `https://m.araild.com/legal/privacy` ;
- suppression de compte : `https://m.araild.com/compte/suppression`.

## Ce qu'il ne faut PAS faire
- Ne jamais mettre le fichier `.jks`, les mots de passe ou un `.env` dans le dépôt ou dans le ZIP.
- Ne pas changer `applicationId` (`com.araild.mxconnect`) après la 1re publication.

Plus de détails (GitHub Actions, retour arrière, limites) : `LISEZMOI.md`.
