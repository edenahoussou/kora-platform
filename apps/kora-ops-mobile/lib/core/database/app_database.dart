import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// Base de données locale du terminal (voir MEZ-91 / document.md § Base
/// locale). Un seul fichier sqlite pour toutes les données OPS Mobile qui
/// doivent survivre à un redémarrage à froid ou un crash :
///
/// - `terminal_activity_log` (v1) : journal des scans, impressions et
///   erreurs du terminal, envoyé au serveur en best-effort (endpoint
///   `ops-mobile/terminal/activity`) — voir `TerminalRepository.
///   logActivity`.
/// - `manifest_cache` / `manifest_meta` (v2, colonnes `origin_stop_uuid`/
///   `destination_stop_uuid`/`boarding_event_uuid` ajoutées en v4) :
///   dernier manifeste connu par départ — permet la recherche manuelle
///   par code court, l'affichage passager après un scan accepté, et le
///   calcul des arrêts compatibles pour une descente anticipée (MEZ-87),
///   même hors ligne ou après un redémarrage à froid — voir
///   `ManifesteRepository`.
/// - `boarding_operations` (v2) : anti-double-scan local et file
///   d'embarquements en attente de synchronisation (MEZ-86/MEZ-88) — voir
///   `BoardingRepository`.
/// - `seat_release_operations` (v3) : anti-doublon local et file des
///   libérations de siège (descente anticipée, no-show — MEZ-87/MEZ-88)
///   — voir `SeatReleaseRepository`. Table séparée de `boarding_
///   operations` plutôt qu'une table générique : schémas différents
///   (`actual_stop_uuid`, `operation_type` scan vs no-show) et le
///   `operation_uuid` reste la seule clé partagée entre les deux.
///
/// Singleton : une seule connexion ouverte pour toute l'app (voir
/// `injection.dart`), comme `SharedPreferences`/`FlutterSecureStorage`.
class AppDatabase {
  AppDatabase();

  static const String _fileName = 'kora_ops.db';
  static const int _schemaVersion = 4;

  Database? _database;

  Future<Database> get database async {
    final Database? existing = _database;
    if (existing != null) return existing;

    final Database opened = await _open();
    _database = opened;
    return opened;
  }

  Future<Database> _open() async {
    final String directory = await getDatabasesPath();
    final String path = p.join(directory, _fileName);

    return openDatabase(
      path,
      version: _schemaVersion,
      onCreate: (Database db, int version) async {
        await _createTerminalActivityLog(db);
        await _createManifestTables(db);
        await _createBoardingOperations(db);
        await _createSeatReleaseOperations(db);
      },
      onUpgrade: (Database db, int oldVersion, int newVersion) async {
        if (oldVersion < 2) {
          await _createManifestTables(db);
          await _createBoardingOperations(db);
        }
        if (oldVersion < 3) {
          await _createSeatReleaseOperations(db);
        }
        if (oldVersion < 4) {
          await _addManifestCacheStopColumns(db);
        }
      },
    );
  }

  Future<void> _createTerminalActivityLog(Database db) async {
    await db.execute('''
      CREATE TABLE terminal_activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        context TEXT,
        local_recorded_at TEXT NOT NULL,
        synced INTEGER NOT NULL DEFAULT 0
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_activity_log_synced ON terminal_activity_log(synced)',
    );
  }

  /// Dernier manifeste connu par départ. Rafraîchi en bloc à chaque fetch
  /// réussi (`ManifesteRepository.load` supprime puis réinsère les lignes
  /// du départ — un manifeste tient sur un bus, quelques dizaines à
  /// ~200 lignes, un remplacement complet reste largement assez rapide) ;
  /// `manifest_meta` retient la version renvoyée par le serveur, réutilisée
  /// à l'étape Synchro pour détecter un manifeste périmé.
  Future<void> _createManifestTables(Database db) async {
    await db.execute('''
      CREATE TABLE manifest_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        departure_uuid TEXT NOT NULL,
        ticket_uuid TEXT,
        short_code TEXT,
        passenger_name TEXT,
        seat_code TEXT,
        origin_stop TEXT,
        destination_stop TEXT,
        origin_stop_uuid TEXT,
        destination_stop_uuid TEXT,
        boarding_event_uuid TEXT,
        segment_status TEXT,
        ticket_status TEXT,
        fetched_at TEXT NOT NULL
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_manifest_departure ON manifest_cache(departure_uuid)',
    );
    await db.execute(
      'CREATE INDEX idx_manifest_short_code '
      'ON manifest_cache(departure_uuid, short_code)',
    );
    await db.execute('''
      CREATE TABLE manifest_meta (
        departure_uuid TEXT PRIMARY KEY,
        manifest_version TEXT NOT NULL,
        fetched_at TEXT NOT NULL
      )
    ''');
  }

  /// Terminal déjà en service avant MEZ-87 : `manifest_cache` existe déjà
  /// sans ces 3 colonnes — `ALTER TABLE ADD COLUMN` plutôt que de
  /// recréer, le cache existant reste valide (juste sans ces champs
  /// jusqu'au prochain téléchargement complet, qui les remplit).
  Future<void> _addManifestCacheStopColumns(Database db) async {
    await db.execute(
      'ALTER TABLE manifest_cache ADD COLUMN origin_stop_uuid TEXT',
    );
    await db.execute(
      'ALTER TABLE manifest_cache ADD COLUMN destination_stop_uuid TEXT',
    );
    await db.execute(
      'ALTER TABLE manifest_cache ADD COLUMN boarding_event_uuid TEXT',
    );
  }

  /// Anti-double-scan local + file d'embarquements en attente de
  /// synchronisation. `operation_uuid` est la clé d'idempotence (même
  /// convention que côté serveur) ; `qr_payload`/`short_code` sont ce qui
  /// a été effectivement scanné/saisi et servent à détecter un second scan
  /// du même billet **avant** tout appel réseau, y compris hors ligne —
  /// voir `BoardingRepository.scan`.
  Future<void> _createBoardingOperations(Database db) async {
    await db.execute('''
      CREATE TABLE boarding_operations (
        operation_uuid TEXT PRIMARY KEY,
        departure_uuid TEXT NOT NULL,
        qr_payload TEXT,
        short_code TEXT,
        ticket_uuid TEXT,
        local_recorded_at TEXT NOT NULL,
        server_recorded_at TEXT,
        sync_status TEXT NOT NULL,
        result_status TEXT NOT NULL,
        result_code TEXT,
        result_message TEXT,
        created_at TEXT NOT NULL
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_boarding_departure_code '
      'ON boarding_operations(departure_uuid, qr_payload, short_code)',
    );
    await db.execute(
      'CREATE INDEX idx_boarding_ticket ON boarding_operations(ticket_uuid)',
    );
    await db.execute(
      'CREATE INDEX idx_boarding_sync_status '
      'ON boarding_operations(sync_status)',
    );
  }

  /// Anti-doublon local + file de libérations de siège en attente de
  /// synchronisation — descente anticipée (`seat_release`) et no-show
  /// individuel (`boarding.no_show`), voir MEZ-87/MEZ-88.
  /// `actual_stop_uuid` n'a de sens que pour `seat_release` (l'arrêt réel
  /// choisi) — toujours `NULL` pour un no-show (voir
  /// `ProcessIndividualNoShowAction`, qui utilise l'arrêt d'origine du
  /// segment côté serveur, jamais transmis par le terminal).
  Future<void> _createSeatReleaseOperations(Database db) async {
    await db.execute('''
      CREATE TABLE seat_release_operations (
        operation_uuid TEXT PRIMARY KEY,
        departure_uuid TEXT NOT NULL,
        operation_type TEXT NOT NULL,
        ticket_uuid TEXT NOT NULL,
        actual_stop_uuid TEXT,
        local_recorded_at TEXT NOT NULL,
        server_recorded_at TEXT,
        sync_status TEXT NOT NULL,
        result_status TEXT NOT NULL,
        result_code TEXT,
        result_message TEXT,
        recommended_action TEXT,
        created_at TEXT NOT NULL
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_seat_release_ticket '
      'ON seat_release_operations(departure_uuid, ticket_uuid, operation_type)',
    );
    await db.execute(
      'CREATE INDEX idx_seat_release_sync_status '
      'ON seat_release_operations(sync_status)',
    );
  }

  /// Réservé aux tests : ferme et oublie la connexion pour repartir d'une
  /// base neuve au test suivant.
  Future<void> closeForTests() async {
    final Database? existing = _database;
    _database = null;
    await existing?.close();
  }
}
