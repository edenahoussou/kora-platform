allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}
subprojects {
    project.evaluationDependsOn(":app")
}

// Certains plugins déclarent encore Java 11 dans leur propre bloc
// `android`, alors que Kotlin suit le JDK de la machine
// — le build échoue sur « Inconsistent JVM-target compatibility ». On
// aligne les deux sur la version que l'app utilise déjà, en passant par
// l'extension Android : régler les tâches ne suffit pas, AGP les
// reconfigure ensuite à partir de ce bloc.
subprojects {
    plugins.withId("com.android.library") {
        extensions.configure<com.android.build.gradle.LibraryExtension> {
            compileOptions {
                sourceCompatibility = JavaVersion.VERSION_17
                targetCompatibility = JavaVersion.VERSION_17
            }
        }
    }

    // `in_app_update` et `kkiapay_flutter_sdk` fixent leur propre Java 8
    // pendant leur propre évaluation, donc après le bloc ci-dessus : il
    // faut repasser derrière eux. On ne le fait que pour ces modules —
    // la plupart des autres figent leur valeur tôt (`connectivity_plus`
    // lève alors « sourceCompatibility has been finalized »), et un
    // `afterEvaluate` général casserait le build pour réparer deux
    // greffons.
    if (name in setOf("in_app_update", "kkiapay_flutter_sdk")) {
        afterEvaluate {
            extensions.findByType<com.android.build.gradle.LibraryExtension>()
                ?.let { android ->
                    android.compileOptions {
                        sourceCompatibility = JavaVersion.VERSION_17
                        targetCompatibility = JavaVersion.VERSION_17
                    }

                    // `kkiapay_flutter_sdk` compile contre l'API 34, mais
                    // dépend de `webview_flutter_android`, qui en exige 36
                    // au moins. Son AAR est donc refusé tel quel. On le
                    // remonte au niveau de l'app, qui compile déjà contre
                    // cette API — c'est précisément ce que recommande le
                    // message d'erreur d'AGP.
                    if (name == "kkiapay_flutter_sdk") {
                        android.compileSdk = 36
                    }
                }
        }
    }

    tasks.withType<org.jetbrains.kotlin.gradle.tasks.KotlinCompile>().configureEach {
        compilerOptions {
            jvmTarget.set(org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17)
        }
    }
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
