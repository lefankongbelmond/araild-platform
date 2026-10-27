# MX-CONNECT — application Android (bêta)

Application **Trusted Web Activity (TWA)** : une fenêtre plein écran, sans barre d’adresse, sur
https://m.araild.com. Tout le fonctionnement (comptes, publications, demandes, documents légaux)
reste celui du site. L’application ne contient **aucun identifiant de base de données, jeton,
mot de passe ni clé d’API**.

| Élément | Valeur |
|---|---|
| Nom du paquet | `com.araild.mxconnect` |
| Adresse d’ouverture | `https://m.araild.com/app?source=android&v=<versionCode>` |
| Android minimum | 6.0 (API 23) — cible API 36 |
| Permissions | `INTERNET` seulement |
| Navigateur requis | Chrome (ou un navigateur compatible TWA) ; sinon ouverture en onglet personnalisé |

## 1. Réglages (sans secret)

`android/gradle.properties` :

- `mxHost` : domaine du site (`m.araild.com`) ;
- `mxVersionCode` : numéro de build, **à augmenter à chaque envoi sur le Play Store** ;
- `mxVersionName` : version affichée (`0.1.0-beta`).

On peut les surcharger : `./gradlew -PmxVersionCode=2 bundleRelease`.

Côté serveur, espace super-admin → **Application Android → Réglages** :
empreinte SHA-256 du certificat, version minimale (en dessous : « Mise à jour requise »),
lien de téléchargement, mode maintenance. Variables `.env` de repli (facultatives) :
`MX_ANDROID_PACKAGE`, `MX_ANDROID_SHA256`, `MX_ANDROID_STORE_URL`.

## 2. Compiler

### Par GitHub Actions (recommandé)

Le workflow `.github/workflows/android.yml` se lance à chaque modification de `android/`,
ou à la main (onglet **Actions → Android → Run workflow**, avec un `versionCode` facultatif).

- Toujours : **APK de test** (`mxconnect-android-debug`) + rapport lint.
- Si les secrets de signature existent : **AAB** pour le Play Store + **APK signé**
  (`mxconnect-android-release`). Le journal affiche l’empreinte SHA-256 du certificat.

Secrets à créer (dépôt GitHub → Settings → Secrets and variables → Actions) :

| Secret | Contenu |
|---|---|
| `MX_KEYSTORE_BASE64` | le fichier `.jks` encodé en base64 (`base64 -w0 upload.jks`) |
| `MX_KEYSTORE_PASSWORD` | mot de passe du fichier |
| `MX_KEY_ALIAS` | alias de la clé |
| `MX_KEY_PASSWORD` | mot de passe de la clé |

### Sur un poste (Android Studio ou JDK 17 + SDK Android)

```bash
cd android
./gradlew assembleDebug                 # APK de test : app/build/outputs/apk/debug/
export MX_KEYSTORE_PATH=/chemin/upload.jks MX_KEYSTORE_PASSWORD=… MX_KEY_ALIAS=… MX_KEY_PASSWORD=…
./gradlew bundleRelease assembleRelease # AAB : app/build/outputs/bundle/release/
```

## 3. Clé de signature (une seule fois)

```bash
keytool -genkeypair -v -keystore upload.jks -alias mxconnect -keyalg RSA -keysize 4096 -validity 10000
```

- Conservez `upload.jks` et ses mots de passe **hors du dépôt** (coffre-fort, deux copies).
- Avec **Play App Signing** (recommandé), Google signe l’application publiée : c’est l’empreinte
  SHA-256 du **certificat de signature de l’application** (Play Console → Intégrité de
  l’application) qu’il faut coller dans les Réglages du site. Ajoutez aussi celle de la clé
  d’envoi si vous distribuez l’APK signé hors du Play Store (une empreinte par ligne).

## 4. Liaison du domaine (plein écran)

1. Collez l’empreinte dans **Application Android → Réglages** et enregistrez.
2. Vérifiez https://m.araild.com/.well-known/assetlinks.json (doit lister le paquet et l’empreinte).
3. Contrôle Google : https://developers.google.com/digital-asset-links/tools/generator

Sans empreinte valide, l’application fonctionne mais affiche une barre d’adresse en haut.
L’APK de test (debug) est signé avec une clé de débogage différente à chaque compilation
GitHub : il s’ouvrira avec la barre d’adresse, sauf si son empreinte est ajoutée.

## 5. Installer et tester

- **APK** : téléchargez l’artefact, copiez l’APK sur le téléphone, autorisez « Installer des
  applications inconnues » pour le gestionnaire de fichiers, puis installez. Désinstallez la
  version de test avant d’installer la version signée (même paquet, signatures différentes).
- **Play Store, test interne** : Play Console → créer l’application `com.araild.mxconnect` →
  Tests → Test interne → nouvelle version → déposer l’AAB → ajouter les adresses e-mail des
  testeurs → partager le lien d’inscription. Avant la publication, remplir : fiche, catégorie,
  **sécurité des données**, classification du contenu, public cible, URL de la politique de
  confidentialité (`https://m.araild.com/legal/privacy`) et URL de suppression de compte
  (`https://m.araild.com/compte/suppression`).

## 6. Limites connues (bêta)

- Pas de notifications système (push) : les notifications restent dans l’application et par e-mail.
- Pas de mode hors ligne : page « hors ligne » si le réseau coupe.
- Connexion Google non proposée (n’existe pas sur le site).
- Les conditions d’utilisation sont un **modèle à faire valider** : non publiées tant que
  le super-admin ne les publie pas (Application Android → Documents légaux).
- Sur un téléphone sans Chrome à jour, l’application s’ouvre en onglet personnalisé.

## 7. Retour arrière

- **Application** : Play Console → Test interne → réactiver la version précédente (un
  `versionCode` supérieur est exigé : recompiler l’ancienne source avec un numéro plus grand).
- **Bloquer une version défectueuse** : Réglages → version minimale = numéro de la version
  corrigée ; ou mode maintenance (l’application affiche le message, le site reste ouvert).
- **Serveur** : réinstaller le ZIP précédent (`mxconnect-maj-20261108.zip`) ; les tables
  `legal_documents`, `legal_acceptances`, `beta_feedback` restent en base sans gêner l’ancienne version.
