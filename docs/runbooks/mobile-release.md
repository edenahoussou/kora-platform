# Release Mobile (Android)

Le workflow `release-mobile.yml` build un APK release signé et le publie sur la page GitHub Releases du repo. Il se déclenche de deux façons : un tag git `v*` (→ build **prod**, release publiée) ou un lancement manuel depuis l'onglet Actions (→ build **staging** par défaut, artefact seulement).

## Vue d'ensemble

```
tag v*        ──► release-mobile.yml ──► build -t lib/main_prod.dart
                                          ──► APK signé avec keystore production
                                          ──► upload-artifact (90 jours)
                                          ──► GitHub Release (téléchargeable)

workflow_dispatch ─► release-mobile.yml ──► build -t lib/main_<environnement>.dart
                                          ──► APK signé, artefact uniquement
```

Le point d'entrée est **toujours nommé explicitement**. `flutter build apk` sans `-t`
prendrait `lib/main.dart`, qui redirige vers `main_dev.dart` : un APK tagué `v1.0.0`
taperait alors sur staging (voir `lib/core/env/app_env.dart`).

## Prérequis : configurer le keystore (one-time)

### 1. Générer le keystore

Sur ta machine locale :

```bash
keytool -genkey -v \
  -keystore kora-passenger.jks \
  -keyalg RSA -keysize 2048 -validity 10000 \
  -alias kora-passenger
```

Note les mots de passe que tu choisis (storePassword et keyPassword). **Garde ce fichier en lieu sûr** — il est irréversible : si tu le perds, tu ne pourras plus publier de mise à jour sur le Play Store avec la même identité.

### 2. Encoder le keystore en base64

```bash
base64 -w 0 kora-passenger.jks > kora-passenger.jks.b64
```

### 3. Configurer les secrets GitHub

Dans GitHub > edenahoussou/kora-platform > Settings > Secrets and variables > Actions > New repository secret :

| Secret | Valeur |
|--------|--------|
| `KEYSTORE_BASE64` | Contenu de `kora-passenger.jks.b64` |
| `KEYSTORE_PASSWORD` | Mot de passe du keystore (storePassword) |
| `KEY_ALIAS` | L'alias choisi à la génération (celui du keystore local est `korapassager`) |
| `KEY_PASSWORD` | Mot de passe de la clé (keyPassword) |

### 3 bis. Secrets des connexions sociales

Sans eux, le build réussit mais `OAuthSignInImpl.isConfigured()` renvoie `false` et les
boutons Google/Facebook restent désactivés dans l'APK publié. Ces identifiants ne sont
pas des secrets au sens strict (ils finissent dans l'APK), mais ils ne sont pas versionnés :
chaque équipe a sa propre console.

| Secret | Valeur |
|--------|--------|
| `GOOGLE_SERVER_CLIENT_ID` | Le client OAuth de type **Web** du projet Google Cloud — surtout pas le client Android, qui ne sert qu'à valider la signature de l'APK |
| `FACEBOOK_APP_ID` | L'App ID Facebook (Paramètres > Général). Sert deux fois : le `--dart-define` qui active le bouton, et `android/facebook.properties` que le workflow écrit pour le SDK natif |
| `FACEBOOK_CLIENT_TOKEN` | Le jeton client Facebook (Paramètres > Avancé > Sécurité). Exigé par le SDK Android depuis la v13 : sans lui la connexion échoue, même avec un App ID correct |

Les deux doivent être renseignés ensemble. Un `FACEBOOK_APP_ID` seul activerait le bouton
côté Dart alors que le SDK natif n'a pas de jeton : le bouton deviendrait cliquable et
échouerait.

### 3 ter. Enregistrer les empreintes de signature

Google lie le client OAuth Android au couple *(package name, empreinte SHA-1)*. Une
empreinte inconnue fait échouer la connexion Google avec un code opaque — c'est le
symptôme classique du « ça marche en debug, pas en release ». Tout se fait en ligne de
commande, sans passer par la console :

```bash
firebase apps:list ANDROID --project <projet>
firebase apps:android:sha:create <appId> <SHA-1 release> --project <projet>
firebase apps:android:sha:create <appId> <SHA-1 debug>   --project <projet>
```

Pour lire les empreintes d'un keystore :

```bash
keytool -list -v -keystore <keystore.jks> -alias <alias>
```

Une seule étape reste manuelle : activer le fournisseur **Google** dans Firebase Console >
Authentication > Sign-in method. C'est elle qui provisionne les clients OAuth à partir des
empreintes ; Google n'expose aucune API publique de création de clients OAuth 2.0, donc ni
`firebase`, ni `gcloud`, ni `flutterfire` ne savent le faire.

Une fois activé, le client Web se relit en ligne de commande :

```bash
firebase apps:sdkconfig ANDROID <appId> --project <projet>
```

### 4. Vérifier

Créer un tag de test et vérifier que le workflow build bien un APK signé :

```bash
git tag v0.0.1-test
git push origin v0.0.1-test
```

L'APK doit apparaître dans GitHub > Releases. Supprimer le tag de test après vérification.

### 3 quater. Connexion Facebook (natif)

Le SDK Facebook lit ses identifiants dans le manifest Android, au démarrage, via son
propre `FacebookInitProvider`. Un `--dart-define` ne peut donc pas les lui porter : il
n'atteint que le code Dart, où il ne sert qu'à décider si le bouton est actif.

Ils passent par `android/facebook.properties`, non versionné comme `key.properties`.
Copier le modèle et le remplir :

```bash
cp android/facebook.properties.example android/facebook.properties
```

`android/app/build.gradle.kts` le lit et injecte les deux valeurs en ressources string
(`resValue`), que le manifest référence par `@string/facebook_app_id` et
`@string/facebook_client_token`. Le passage par une ressource n'est pas cosmétique : un
App ID purement numérique placé directement dans `android:value` serait lu comme un
entier, et le SDK refuserait de démarrer.

Rien d'autre n'est à déclarer. `FacebookActivity`, `CustomTabActivity` (avec son
intent-filter `fbconnect://cct.<applicationId>`, généré automatiquement) et la visibilité
de paquets Android 11+ (`<package android:name="com.facebook.katana" />`) sont fournis par
le manifest du SDK et arrivent par fusion.

Côté console Facebook, l'app Android doit connaître le **key hash** de chaque clé de
signature (format base64, distinct de la SHA-1 hexadécimale que veut Google) :

```bash
keytool -exportcert -alias <alias> -keystore <keystore.jks> | openssl sha1 -binary | openssl base64
```

## Produire un APK de test interne

Sans poser de tag : GitHub > Actions > **Release Mobile** > *Run workflow*, choisir
l'environnement (`staging` par défaut). L'APK sort en artefact du job, nommé
`kora-app-passenger-staging-<sha>.apk`. Aucune GitHub Release n'est créée.

En local, l'équivalent :

```bash
flutter build apk --release   -t lib/main_staging.dart   --dart-define=GOOGLE_SERVER_CLIENT_ID=<client Web>.apps.googleusercontent.com
```

## Produire une release

Quand les devs donnent le feu vert :

```bash
# 1. S'assurer d'être sur main et à jour
git checkout main
git pull

# 2. Tagger avec un versionnage sémantique
git tag v0.1.0
git push origin v0.1.0

# 3. Le workflow se déclenche automatiquement
#    → APK buildé, signé, uploadé en artefact + GitHub Release
```

### Numérotation des versions

| Tag | Type | Quand |
|-----|------|-------|
| `v0.x.y` | Pre-release | Tests internes, validation équipe |
| `v1.0.0` | Release | Première version production-ready |
| `v1.x.y` | Patch/feature | Mises à jour ultérieures |

## Télécharger l'APK

1. Aller sur https://github.com/edenahoussou/kora-platform/releases
2. Télécharger `kora-app-passenger-vX.Y.Z.apk`
3. Installer sur un appareil Android (sideloading) :
   ```bash
   adb install kora-app-passenger-v0.1.0.apk
   ```
   Ou transférer le fichier sur le téléphone et l'installer depuis le gestionnaire de fichiers.

## Plus tard : Play Store via Fastlane

Quand un compte Google Play Console sera disponible, on ajoutera Fastlane au workflow pour pousser automatiquement l'APK vers la track internal/beta du Play Store. Les secrets à ajouter alors :

| Secret | Description |
|--------|-------------|
| `PLAY_SERVICE_ACCOUNT_JSON` | Clé JSON du service account Google Play API |

Le workflow `release-mobile.yml` sera étendu avec un step `fastlane supply` après le build.
