// Application MX-CONNECT : une fenêtre plein écran (Trusted Web Activity) sur le site.
// Aucun identifiant de base de données, jeton ou mot de passe n'est embarqué : la session
// est celle du site. La signature de la version publiée se règle par variables d'environnement.
plugins {
    id("com.android.application")
}

val mxHost = providers.gradleProperty("mxHost").get()
val mxVersionCode = providers.gradleProperty("mxVersionCode").get().toInt()
val mxVersionName = providers.gradleProperty("mxVersionName").get()

android {
    namespace = "com.araild.mxconnect"
    compileSdk = 36

    defaultConfig {
        applicationId = "com.araild.mxconnect"
        minSdk = 23
        targetSdk = 36
        versionCode = mxVersionCode
        versionName = mxVersionName

        // Le serveur lit « v » pour imposer une version minimale (Réseau → Application Android → Réglages).
        resValue("string", "host_name", mxHost)
        resValue("string", "launch_url", "https://$mxHost/app?source=android&v=$mxVersionCode")
        resValue("string", "provider_authority", "com.araild.mxconnect.fileprovider")
        resValue(
            "string", "asset_statements",
            "[{\"relation\":[\"delegate_permission/common.handle_all_urls\"],\"target\":{\"namespace\":\"web\",\"site\":\"https://$mxHost\"}}]"
        )
    }

    signingConfigs {
        // Clé d'envoi Play : jamais dans le dépôt. Fournie par l'environnement (poste local ou secrets GitHub).
        create("release") {
            val store = System.getenv("MX_KEYSTORE_PATH")
            if (!store.isNullOrBlank()) {
                storeFile = file(store)
                storePassword = System.getenv("MX_KEYSTORE_PASSWORD")
                keyAlias = System.getenv("MX_KEY_ALIAS")
                keyPassword = System.getenv("MX_KEY_PASSWORD")
            }
        }
    }

    buildTypes {
        getByName("release") {
            isMinifyEnabled = false
            if (!System.getenv("MX_KEYSTORE_PATH").isNullOrBlank()) {
                signingConfig = signingConfigs.getByName("release")
            }
        }
        getByName("debug") {
            // Même identifiant que la version publiée : la liaison du domaine (assetlinks.json) reste
            // valable si l'empreinte de la clé de débogage y est ajoutée. Désinstaller l'une avant l'autre.
            versionNameSuffix = "-debug"
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    buildFeatures {
        resValues = true
    }

    lint {
        abortOnError = true
        checkReleaseBuilds = true
    }
}

dependencies {
    implementation("com.google.androidbrowserhelper:androidbrowserhelper:2.5.0")
}
