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

// Certains plugins (flutter_facebook_auth) déclarent encore Java 11 dans
// leur propre bloc `android`, alors que Kotlin suit le JDK de la machine
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

    // `in_app_update` fixe son propre Java 8 pendant sa propre
    // évaluation, donc après le bloc ci-dessus : il faut repasser
    // derrière lui. On ne le fait que pour ce module — la plupart des
    // autres figent leur valeur tôt (`connectivity_plus` lève alors
    // « sourceCompatibility has been finalized »), et un `afterEvaluate`
    // général casserait le build pour réparer un seul greffon.
    if (name == "in_app_update") {
        afterEvaluate {
            extensions.findByType<com.android.build.gradle.LibraryExtension>()
                ?.compileOptions {
                    sourceCompatibility = JavaVersion.VERSION_17
                    targetCompatibility = JavaVersion.VERSION_17
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
