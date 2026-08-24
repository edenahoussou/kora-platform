import java.util.Properties
import java.io.FileInputStream

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// --- Signature release ---
// Lit android/key.properties (généré par le CI ou présent localement).
// Si le fichier n'existe pas, on retombe sur la signature debug pour
// que `flutter run --release` continue de fonctionner en dev.


val keystorePropertiesFile = rootProject.file("key.properties")
val keystoreProperties = Properties()
if (keystorePropertiesFile.exists()) {
    keystoreProperties.load(FileInputStream(keystorePropertiesFile))
}

// --- Identifiants Facebook ---
// Le SDK Facebook lit son App ID et son client token dans le manifest,
// au démarrage, via son propre FacebookInitProvider : `--dart-define`
// ne peut donc pas les porter, il n'atteint que le code Dart. Ils
// arrivent par android/facebook.properties, non versionné comme
// key.properties (voir docs/runbooks/mobile-release.md).
val facebookPropertiesFile = rootProject.file("facebook.properties")
val facebookProperties = Properties()
if (facebookPropertiesFile.exists()) {
    facebookProperties.load(FileInputStream(facebookPropertiesFile))
}

android {
    namespace = "com.kora_app_passager.app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    buildFeatures {
        // AGP 9 désactive `resValue` par défaut. Les identifiants
        // Facebook en dépendent (voir defaultConfig) : sans ce drapeau,
        // la configuration échoue sur « defaultConfig contains custom
        // resource values, but the feature is disabled ».
        resValues = true
    }

    defaultConfig {
        // Identifiant d'installation de l'app passager. Il est gravé
        // dans le client OAuth Android de Google et dans l'app Facebook :
        // le changer oblige à recréer les deux.
        applicationId = "com.kora_app_passager.app"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName

        // Le manifest les référence par `@string/...` et non par une
        // valeur brute : un App ID purement numérique placé dans
        // `android:value` serait lu comme un entier, et le SDK refuse
        // alors de démarrer. Le passer en ressource le garde en chaîne.
        //
        // Sans le fichier, les deux valeurs restent vides. Le bouton
        // Facebook est de toute façon désactivé côté Dart dans ce cas
        // (AppEnv.facebookAppId vient du même identifiant, par
        // --dart-define), donc le SDK n'est jamais sollicité.
        resValue(
            "string",
            "facebook_app_id",
            facebookProperties.getProperty("facebookAppId", ""),
        )
        resValue(
            "string",
            "facebook_client_token",
            facebookProperties.getProperty("facebookClientToken", ""),
        )
    }

    signingConfigs {
        create("release") {
            if (keystoreProperties.isNotEmpty()) {
                keyAlias = keystoreProperties["keyAlias"] as String
                keyPassword = keystoreProperties["keyPassword"] as String
                storeFile = file(keystoreProperties["storeFile"] as String)
                storePassword = keystoreProperties["storePassword"] as String
            }
        }
    }

    buildTypes {
        release {
            // Utilise la signature release si key.properties est présent,
            // sinon retombe sur debug (dev local).
            signingConfig = if (keystoreProperties.isNotEmpty()) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
