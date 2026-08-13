/// Stockage clé/valeur générique utilisé par [CacheInterceptor] pour
/// mettre en cache les réponses `GET` et les resservir en mode hors ligne.
///
/// Interface volontairement minimale pour rester substituable : l'app
/// consommatrice peut fournir sa propre implémentation persistante (ex:
/// `Hive`, `SharedPreferences`) si le cache en mémoire de
/// [InMemoryNetworkCache] ne survit pas assez longtemps pour son cas
/// d'usage (le cache en mémoire est vidé à chaque redémarrage de l'app).
abstract interface class NetworkCache {
  /// Range [data] sous [key]. Si [maxAge] est fourni, l'entrée est
  /// considérée périmée après ce délai (voir [read]).
  Future<void> write(String key, Object? data, {Duration? maxAge});

  /// Lit la valeur associée à [key], ou `null` si absente ou périmée.
  Future<Object?> read(String key);

  Future<void> remove(String key);

  Future<void> clear();
}

class _CacheEntry {
  _CacheEntry(this.data, this.expiresAt);
  final Object? data;
  final DateTime? expiresAt;

  bool get isExpired => expiresAt != null && DateTime.now().isAfter(expiresAt!);
}

/// Implémentation en mémoire de [NetworkCache] — suffisante pour servir de
/// filet de sécurité "hors ligne" pendant la durée de vie de l'app.
class InMemoryNetworkCache implements NetworkCache {
  final _store = <String, _CacheEntry>{};

  @override
  Future<void> write(String key, Object? data, {Duration? maxAge}) async {
    _store[key] = _CacheEntry(
      data,
      maxAge != null ? DateTime.now().add(maxAge) : null,
    );
  }

  @override
  Future<Object?> read(String key) async {
    final entry = _store[key];
    if (entry == null) return null;
    if (entry.isExpired) {
      _store.remove(key);
      return null;
    }
    return entry.data;
  }

  @override
  Future<void> remove(String key) async {
    _store.remove(key);
  }

  @override
  Future<void> clear() async {
    _store.clear();
  }
}
