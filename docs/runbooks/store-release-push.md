# Campagnes push store Passager (FCM)

Ce runbook décrit le contrat backend/OPS des campagnes FCM pour les releases store Passager Android et iOS. Les routes d'administration sont protégées par session OPS ; l'API publique mobile ne sert que la configuration Firebase et la vérification de version.

## Contrat topics

Les topics sont figés et déterminés par `platform` + `environment`.

| Platform | `production` | `staging` |
| --- | --- | --- |
| `android` | `passenger-android-prod` | `passenger-android-staging` |
| `ios` | `passenger-ios-prod` | `passenger-ios-staging` |

Règle de résolution : `passenger-{platform}-{prod|staging}`.

- `environment=production` → suffixe `prod`
- `environment=staging` → suffixe `staging`
- `platform=android|ios` reste inchangé

## Contrat payload FCM

Le `data` minimal obligatoire est :

```json
{
  "type": "app_update"
}
```

Champs additionnels recommandés par la campagne :

```json
{
  "type": "app_update",
  "release_uuid": "6f0c1d3e-7c1b-4a8a-9d6e-2b1a0c9f8e71",
  "platform": "android",
  "environment": "production",
  "version": "1.4.0",
  "build": 42,
  "store_url": "https://play.google.com/store/apps/details?id=app.kora.passenger"
}
```

`title` et `body` sont libres et servent uniquement à l'affichage système. Aucun secret serveur, compte de service ou binaire n'est transmis dans la payload FCM.

> **Breaking change (#344) :** les anciens topics `app_passenger_mobile_android_production` et l'ancien `data.type: app_release_update` ne sont plus utilisés. L'app mobile doit être déployée et abonnée aux nouveaux topics avant de lancer une campagne.

## Ordre de livraison OPS

1. Déployer d'abord l'application mobile qui récupère la configuration Firebase publique et s'abonne au topic correspondant.
2. Vérifier que l'endpoint public de configuration répond sans token.
3. Enregistrer la release store dans l'interface OPS.
4. Confirmer la release publiquement une fois la version présente sur le store.
5. Lancer la campagne push FCM depuis la fiche release.
6. En cas d'échec incertain ou `failed`, utiliser la relance de campagne après vérification du statut FCM.

## Vérifications publiques testables

Configuration Firebase Android, sans session :

```bash
curl 'https://staging.koraexpress.app/api/v1/passenger/firebase-configuration?platform=android' \
  -H 'Accept: application/json'
```

Vérification de version Android :

```bash
curl 'https://staging.koraexpress.app/api/v1/apps/passenger-mobile/update?platform=android&environment=production&version=1.3.2&build=37' \
  -H 'Accept: application/json'
```

Réponse attendue quand une release store confirmée est disponible :

```json
{
  "data": {
    "app": "passenger_mobile",
    "channel": "store",
    "platform": "android",
    "environment": "production",
    "status": "update_available",
    "installed_version": "1.3.2",
    "installed_build": 37,
    "latest_version": "1.4.0",
    "latest_build": 42,
    "latest_full_version": "1.4.0+42",
    "update_available": true,
    "mandatory": false,
    "store_url": "https://play.google.com/store/apps/details?id=app.kora.passenger",
    "release_uuid": "6f0c1d3e-7c1b-4a8a-9d6e-2b1a0c9f8e71"
  },
  "meta": {
    "server_time": "2026-01-01T00:00:00+00:00"
  }
}
```

## Actions d'administration interne

| Étape | Route nommée | Contrôleur |
| --- | --- | --- |
| Enregistrer une release store | `administration.releases.store-store` | `AppReleaseController@storeStore` |
| Confirmer la release store | `administration.releases.confirm-store` | `AppReleaseController@confirmStore` |
| Lancer la campagne push | `administration.releases.push-campaign` | `AppReleaseController@dispatchCampaign` |
| Relancer une campagne | `administration.releases.push-campaign-retry` | `AppReleaseController@retryCampaign` |

Ces routes sont des routes web Inertia protégées par l'authentification et les permissions OPS ; elles ne sont pas exposées dans l'API publique mobile.

## Diagnostic rapide

- **Aucun message reçu sur une app installée** : vérifier que l'app est sur l'environnement correspondant, abonnée au topic exact, puis relire la campagne dans l'interface OPS.
- **Campagne `failed`** : contrôler la configuration Firebase serveur et le message d'erreur stocké sur la campagne avant toute relance.
- **Campagne `uncertain`** : le message FCM n'a pas été confirmé ; vérifier le côté console FCM avant de relancer pour éviter un doublon.
