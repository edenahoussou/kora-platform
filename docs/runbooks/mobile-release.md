# Release Mobile (Android)

Le workflow `release-mobile.yml` build un APK release signé et le publie sur la page GitHub Releases du repo. Il est déclenché par un tag git `v*`.

## Vue d'ensemble

```
tag v*  ──► release-mobile.yml  ──► flutter build apk --release
                                      ──► APK signé avec keystore production
                                      ──► upload-artifact (90 jours)
                                      ──► GitHub Release (téléchargeable)
```

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
| `KEY_ALIAS` | `kora-passenger` (ou l'alias choisi) |
| `KEY_PASSWORD` | Mot de passe de la clé (keyPassword) |

### 4. Vérifier

Créer un tag de test et vérifier que le workflow build bien un APK signé :

```bash
git tag v0.0.1-test
git push origin v0.0.1-test
```

L'APK doit apparaître dans GitHub > Releases. Supprimer le tag de test après vérification.

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
