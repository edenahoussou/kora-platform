# flutter_networking

Couche réseau partagée entre toutes les apps Flutter du monorepo KORA :
client HTTP [Dio](https://pub.dev/packages/dio) préconfiguré (interceptors
auth/logs/retry/erreurs), modèles de réponse standardisés et gestion basique
du mode hors ligne.

Volontairement agnostique de l'app qui l'utilise : pas d'URL, de clé de
stockage ni de nom d'endpoint en dur ici — toute la configuration
d'environnement est injectée par l'app consommatrice (voir [DioClient] et
`core/di/injector.dart` de kora-app-passenger).

## Installation

```yaml
dependencies:
  flutter_networking:
    path: ../../packages/flutter-networking
```

## Client HTTP

```dart
final dioClient = DioClient(
  config: NetworkConfig(
    baseUrl: AppEnv.current.apiBaseUrl,
    connectTimeout: const Duration(seconds: 15),
    receiveTimeout: const Duration(seconds: 15),
    enableLogging: kDebugMode && AppEnv.current.enableNetworkLogs,
  ),
  getAccessToken: () => secureStorage.read(key: StorageKeys.accessToken),
  // Optionnel : active le fallback hors ligne sur les GET (voir plus bas).
  cache: InMemoryNetworkCache(),
);

final dio = dioClient.dio; // à injecter dans les data sources
```

Chaque requête passe automatiquement par (dans cet ordre, à l'erreur) :

1. **Auth** — ajoute `Authorization: Bearer <token>` via `getAccessToken`, s'il existe.
2. **Cache** *(opt-in)* — ressert la dernière réponse connue si la requête `GET` échoue faute de réseau.
3. **Retry** — rejoue jusqu'à `NetworkConfig.maxRetries` fois une erreur transitoire (timeout, connexion perdue), uniquement sur les méthodes idempotentes (`GET`/`HEAD`/`PUT`/`DELETE`/`OPTIONS` par défaut). Jamais sur un `POST`, pour ne pas risquer de dupliquer une réservation ou un paiement.
4. **Logging** *(si `enableLogging`)* — journalise requêtes/réponses/erreurs.
5. **Erreurs** — traduit toute erreur restante en [ApiException] typée.

## Erreurs typées

```dart
try {
  final response = await dio.get(ApiConstants.profile);
} on DioException catch (e) {
  switch (e.apiException) {
    case UnauthorizedApiException():
      // rediriger vers le login
    case ValidationApiException(:final fieldErrors):
      // afficher fieldErrors dans le formulaire
    case NetworkApiException() || TimeoutApiException():
      // "vérifie ta connexion"
    default:
      // message générique
  }
}
```

`ApiException` est un vocabulaire indépendant de Dio : `NetworkApiException`,
`TimeoutApiException`, `UnauthorizedApiException` (401), `ForbiddenApiException`
(403), `NotFoundApiException` (404), `ValidationApiException` (422, avec
`fieldErrors`), `ServerApiException` (5xx), `CancelledApiException`,
`UnknownApiException`.

## Modèles de réponse standardisés

```dart
// success / error
final result = ApiResult<User>.success(user);
result.fold(
  onSuccess: (user) => print(user.name),
  onFailure: (exception) => print(exception.message),
);

// paginated (format Laravel : data + meta.current_page/last_page/total)
final page = PaginatedResponse.fromJson(
  response.data as Map<String, dynamic>,
  (json) => BookingModel.fromJson(json as Map<String, dynamic>),
);
```

## Mode hors ligne

- **Cache (lecture)** : fournir un `NetworkCache` (ex: `InMemoryNetworkCache`)
  à `DioClient` met en cache chaque réponse `GET` réussie et la ressert
  automatiquement si la même requête échoue plus tard faute de réseau.
- **Queue (écriture)** : `OfflineRequestQueue` n'est **pas** branchée
  automatiquement — une requête mutante (réservation, paiement...) ne doit
  jamais être mise en file sans que l'app le décide explicitement. À utiliser
  dans le `catch` d'un repository, pour les mutations où différer l'action
  est acceptable :
  ```dart
  try {
    await dio.post(ApiConstants.someMutation, data: payload);
  } on DioException catch (e) {
    if (e.apiException is NetworkApiException) {
      offlineQueue.enqueue(e.requestOptions);
      return const Right(QueuedForSync());
    }
    rethrow;
  }

  // Plus tard, à la reconnexion :
  await offlineQueue.flush(dio);
  ```

## Connectivité

```dart
final networkInfo = NetworkInfoImpl(Connectivity());
if (!await networkInfo.isConnected) {
  return const Left(NetworkFailure());
}
```

## Tests

```bash
flutter test
```
