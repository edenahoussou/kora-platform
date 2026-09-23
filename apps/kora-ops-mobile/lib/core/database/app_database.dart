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
/// - `border_manifest_print_jobs` (v5, MEZ-94) : une ligne par demande
///   d'impression du manifeste frontière (3 exemplaires) — nombre
///   d'exemplaires **confirmés** et exemplaire **en cours** persistés
///   avant/après chaque envoi à l'imprimante, pour reprendre après une
///   erreur matérielle ou un crash sans jamais imprimer un exemplaire de
///   trop en silence — voir `BorderManifestPrintRepository`. Le v5 ajoute
///   aussi `nationality`/`document_number` à `manifest_cache` et
///   `international` à `manifest_meta` (champs de contrôle frontière).
/// - `ops_notifications` (v6, MEZ-97) : cache des alertes opérationnelles
///   (départ affecté/modifié, manifeste périmé, terminal révoqué, conflit
///   de scan...) — restaure les alertes non acquittées après redémarrage,
///   y compris hors ligne — voir `NotificationLocalDataSource`.
/// - `incident_operations` (v7, MEZ-98) : file de signalements terrain
///   (panne, retard, accident, sécurité, autre) en attente de
///   synchronisation — voir `IncidentLocalDataSource`.
/// - `tracking_positions_queue` / `tracking_session_state` (v8, MEZ-101) :
///   file des positions GPS du convoyeur en attente d'envoi par lots, et
///   état de la session de suivi en cours (départ, terminal, prochaine
///   séquence) — restaure le suivi après un crash ou un redémarrage à
///   froid sans perdre de point ni recommencer la séquence à zéro, voir
///   `TrackingLocalDataSource`.
///
/// Singleton : une seule connexion ouverte pour toute l'app (voir
/// `injection.dart`), comme `SharedPreferences`/`FlutterSecureStorage` —
/// SAUF `TrackingTaskHandler` (MEZ-101, isolate séparé du service premier
/// plan), qui ouvre sa propre connexion vers le même fichier : sqflite
/// gère nativement plusieurs connexions concurrentes sur un même fichier
/// sqlite, chaque isolate Dart ayant la sienne.
class AppDatabase {
  AppDatabase();

  static const String _fileName = 'kora_ops.db';
  static const int _schemaVersion = 8;

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
        await _createBorderManifestPrintJobs(db);
        await _createOpsNotifications(db);
        await _createIncidentOperations(db);
        await _createTrackingTables(db);
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
        if (oldVersion < 5) {
          await _addBorderControlColumns(db);
          await _createBorderManifestPrintJobs(db);
        }
        if (oldVersion < 6) {
          await _createOpsNotifications(db);
        }
        if (oldVersion < 7) {
          await _createIncidentOperations(db);
        }
        if (oldVersion < 8) {
          await _createTrackingTables(db);
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
        nationality TEXT,
        document_number TEXT,
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
        international INTEGER,
        fetched_at TEXT NOT NULL
      )
    ''');
  }

  /// Terminal déjà en service avant MEZ-87 : `manifest_cache` existe déjà
  /// sans ces 3 colonnes — `ALTER TABLE ADD COLUMN` plutôt que de
  /// recréer, le cache existant reste valide (juste sans ces champs
  /// jusqu'au prochain téléchargement complet, qui les remplit).
  Future<void> _addManifestCacheStopColumns(Database db) async {
    // Idempotent (voir `_addColumnIfMissing`) : un schéma < 2 vient de
    // recréer `manifest_cache` avec ces colonnes déjà présentes.
    await _addColumnIfMissing(db, 'manifest_cache', 'origin_stop_uuid', 'TEXT');
    await _addColumnIfMissing(
        db, 'manifest_cache', 'destination_stop_uuid', 'TEXT');
    await _addColumnIfMissing(
        db, 'manifest_cache', 'boarding_event_uuid', 'TEXT');
  }

  /// Terminal déjà en service avant MEZ-94 : `manifest_cache`/`manifest_meta`
  /// existent sans les champs de contrôle frontière — `ALTER TABLE ADD
  /// COLUMN`, le cache existant reste valide (sans ces champs jusqu'au
  /// prochain téléchargement complet, qui les remplit ; `international`
  /// reste `NULL` = « inconnu » d'ici là, jamais assimilé à `false`).
  /// Idempotent : un terminal parti d'un schéma < 2 a déjà les colonnes
  /// après `_createManifestTables` (définition à jour).
  Future<void> _addBorderControlColumns(Database db) async {
    await _addColumnIfMissing(db, 'manifest_cache', 'nationality', 'TEXT');
    await _addColumnIfMissing(db, 'manifest_cache', 'document_number', 'TEXT');
    await _addColumnIfMissing(db, 'manifest_meta', 'international', 'INTEGER');
  }

  Future<void> _addColumnIfMissing(
    Database db,
    String table,
    String column,
    String type,
  ) async {
    final List<Map<String, Object?>> columns =
        await db.rawQuery('PRAGMA table_info($table)');
    final bool exists =
        columns.any((Map<String, Object?> c) => c['name'] == column);
    if (exists) return;
    await db.execute('ALTER TABLE $table ADD COLUMN $column $type');
  }

  /// Une ligne par demande d'impression du manifeste frontière (MEZ-94).
  ///
  /// - `requested_copies` : nombre demandé (3 en pratique).
  /// - `confirmed_copies` : exemplaires dont l'imprimante a confirmé la
  ///   sortie — jamais incrémenté avant cette confirmation.
  /// - `in_flight_copy` : exemplaire envoyé à l'imprimante dont le résultat
  ///   n'est pas encore connu. Non `NULL` après un redémarrage = résultat
  ///   **ambigu** (l'exemplaire est peut-être sorti) : l'app ne réimprime
  ///   jamais seule, elle demande au convoyeur.
  /// - `status` : `inProgress`, `interrupted` ou `completed`.
  ///
  /// `user_uuid`/`terminal_serial` : qui a demandé l'impression et sur quel
  /// terminal (journalisation). Aucune donnée passager (nom, document)
  /// n'est stockée ici.
  Future<void> _createBorderManifestPrintJobs(Database db) async {
    await db.execute('''
      CREATE TABLE border_manifest_print_jobs (
        job_uuid TEXT PRIMARY KEY,
        departure_uuid TEXT NOT NULL,
        requested_copies INTEGER NOT NULL,
        confirmed_copies INTEGER NOT NULL DEFAULT 0,
        in_flight_copy INTEGER,
        status TEXT NOT NULL,
        last_error TEXT,
        manifest_version TEXT,
        user_uuid TEXT,
        terminal_serial TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
      )
    ''');
    await db.execute(
      'CREATE INDEX idx_border_print_departure '
      'ON border_manifest_print_jobs(departure_uuid, status)',
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

  /// Cache local des alertes opérationnelles (v6, MEZ-97) — restaure les
  /// alertes non acquittées après un redémarrage à froid ou hors ligne
  /// (voir `NotificationLocalDataSource`). `mark_read_pending` : un
  /// marquage "lu" fait hors ligne, pas encore confirmé par le serveur —
  /// jamais écrasé par un rafraîchissement tant qu'il n'est pas à 0 (voir
  /// `NotificationRepositoryImpl.retryPendingReads`).
  Future<void> _createOpsNotifications(Database db) async {
    await db.execute('''
      CREATE TABLE IF NOT EXISTS ops_notifications (
        uuid TEXT PRIMARY KEY,
        event_uuid TEXT,
        type TEXT NOT NULL,
        severity TEXT NOT NULL,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        action TEXT NOT NULL,
        is_read INTEGER NOT NULL DEFAULT 0,
        read_at TEXT,
        created_at TEXT NOT NULL,
        occurred_at TEXT,
        departure_uuid TEXT,
        departure_line_code TEXT,
        departure_date TEXT,
        departure_time TEXT,
        data TEXT,
        mark_read_pending INTEGER NOT NULL DEFAULT 0
      )
    ''');
    await db.execute(
      'CREATE INDEX IF NOT EXISTS idx_ops_notifications_created '
      'ON ops_notifications(created_at)',
    );
    await db.execute(
      'CREATE INDEX IF NOT EXISTS idx_ops_notifications_unread '
      'ON ops_notifications(is_read)',
    );
  }

  /// File d'incidents en attente de synchronisation (v7, MEZ-98) — voir
  /// `IncidentLocalDataSource`. Pas d'anti-doublon métier ici (chaque
  /// signalement est un événement distinct) : `operation_uuid` sert
  /// uniquement à l'idempotence du rejeu réseau.
  Future<void> _createIncidentOperations(Database db) async {
    await db.execute('''
      CREATE TABLE IF NOT EXISTS incident_operations (
        operation_uuid TEXT PRIMARY KEY,
        departure_uuid TEXT NOT NULL,
        type TEXT NOT NULL,
        description TEXT NOT NULL,
        latitude REAL,
        longitude REAL,
        occurred_at TEXT NOT NULL,
        sync_status TEXT NOT NULL,
        created_at TEXT NOT NULL
      )
    ''');
    await db.execute(
      'CREATE INDEX IF NOT EXISTS idx_incident_operations_sync_status '
      'ON incident_operations(sync_status)',
    );
  }

  /// File des positions GPS en attente d'envoi par lots (v8, MEZ-101) —
  /// voir `TrackingLocalDataSource`. Pas d'anti-doublon métier (chaque
  /// mesure est distincte, même convention que `incident_operations`) :
  /// l'idempotence repose uniquement sur `position_uuid`, généré côté
  /// mobile et réutilisé tel quel à chaque tentative d'envoi.
  /// `departure_uuid` permet de retrouver la file d'une session précise
  /// après un redémarrage (voir `tracking_session_state`).
  Future<void> _createTrackingTables(Database db) async {
    await db.execute('''
      CREATE TABLE IF NOT EXISTS tracking_positions_queue (
        position_uuid TEXT PRIMARY KEY,
        departure_uuid TEXT NOT NULL,
        sequence INTEGER NOT NULL,
        status TEXT NOT NULL,
        latitude REAL,
        longitude REAL,
        accuracy_meters REAL,
        speed_kmh REAL,
        heading_degrees REAL,
        source TEXT NOT NULL,
        recorded_at TEXT NOT NULL,
        sync_status TEXT NOT NULL,
        created_at TEXT NOT NULL
      )
    ''');
    await db.execute(
      'CREATE INDEX IF NOT EXISTS idx_tracking_queue_departure_seq '
      'ON tracking_positions_queue(departure_uuid, sequence)',
    );
    await db.execute(
      'CREATE INDEX IF NOT EXISTS idx_tracking_queue_sync_status '
      'ON tracking_positions_queue(sync_status)',
    );

    // Une seule ligne logique (`id = 1`), remplacée à chaque changement
    // d'état plutôt qu'historisée : c'est l'état courant du suivi qui
    // compte pour la reprise après crash/redémarrage
    // (`TrackingRepositoryImpl.bootstrap`), pas son historique.
    // `last_sequence` est LA source de vérité du compteur de séquence —
    // jamais recalculé depuis la file, dont les lignes déjà synchronisées
    // peuvent avoir été purgées.
    await db.execute('''
      CREATE TABLE IF NOT EXISTS tracking_session_state (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        departure_uuid TEXT NOT NULL,
        terminal_serial TEXT NOT NULL,
        status TEXT NOT NULL,
        last_sequence INTEGER NOT NULL DEFAULT 0,
        started_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
      )
    ''');
  }

  /// Réservé aux tests : ferme et oublie la connexion pour repartir d'une
  /// base neuve au test suivant.
  Future<void> closeForTests() async {
    final Database? existing = _database;
    _database = null;
    await existing?.close();
  }
}
