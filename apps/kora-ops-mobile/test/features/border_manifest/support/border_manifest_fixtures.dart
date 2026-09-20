import 'package:kora_ops_mobile/features/border_manifest/data/datasources/border_manifest_print_local_datasource.dart';
import 'package:kora_ops_mobile/features/border_manifest/domain/entities/border_manifest_print_job.dart';
import 'package:kora_ops_mobile/features/border_manifest/domain/services/border_manifest_builder.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/depart.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';

const String kDepartureUuid = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e';

const Depart kInternationalDepart = Depart(
  id: kDepartureUuid,
  route: 'Cotonou → Lomé',
  date: '14/08/2026',
  heure: '07:30',
  vehiculeImmat: 'AB-1234-RB',
  totalPlaces: 60,
  placesOccupees: 2,
  lineCode: 'COT-LFW',
  lineName: 'Cotonou - Lomé',
);

Passenger borderPassenger({
  String id = 'ticket-1',
  String name = 'Awa Traoré',
  String seat = '1A',
  String destination = 'Lomé',
  String? nationality = 'BJ',
  String? document = 'PP123456',
  BoardingStatus status = BoardingStatus.enAttente,
}) =>
    Passenger(
      id: id,
      nomComplet: name,
      siege: seat,
      statut: status,
      segmentOrigine: 'Cotonou',
      segmentDestination: destination,
      ticketId: 'AB12CD',
      nationalite: nationality,
      documentNumero: document,
    );

BorderManifestInput borderInput({
  List<Passenger>? passengers,
  bool? isInternational = true,
  String? version = 'abcdef1234567890',
}) =>
    BorderManifestInput(
      depart: kInternationalDepart,
      passengers: passengers ??
          <Passenger>[
            borderPassenger(),
            borderPassenger(id: 'ticket-2', name: 'Koffi Mensah', seat: '1B'),
          ],
      isInternational: isInternational,
      manifestVersion: version,
      fetchedAt: DateTime(2026, 8, 14, 6, 45),
    );

/// Stockage en mémoire des jobs d'impression — remplace sqlite dans les
/// tests. Partager la **même** instance entre deux repositories/cubits
/// simule un redémarrage de l'app : l'état persisté survit, pas les objets.
class InMemoryBorderManifestPrintDataSource
    implements BorderManifestPrintLocalDataSource {
  final Map<String, BorderManifestPrintJob> jobs =
      <String, BorderManifestPrintJob>{};

  @override
  Future<void> insert(BorderManifestPrintJob job) async {
    jobs[job.jobUuid] = job;
  }

  @override
  Future<void> update(BorderManifestPrintJob job) async {
    jobs[job.jobUuid] = job;
  }

  @override
  Future<BorderManifestPrintJob?> find(String jobUuid) async => jobs[jobUuid];

  List<BorderManifestPrintJob> _forDeparture(String departureUuid) =>
      jobs.values
          .where((BorderManifestPrintJob j) => j.departureUuid == departureUuid)
          .toList()
        ..sort((BorderManifestPrintJob a, BorderManifestPrintJob b) =>
            b.createdAt.compareTo(a.createdAt));

  @override
  Future<BorderManifestPrintJob?> findUnfinished(String departureUuid) async {
    final Iterable<BorderManifestPrintJob> unfinished = _forDeparture(
      departureUuid,
    ).where((BorderManifestPrintJob j) => !j.isCompleted);
    return unfinished.isEmpty ? null : unfinished.first;
  }

  @override
  Future<BorderManifestPrintJob?> findLast(String departureUuid) async {
    final List<BorderManifestPrintJob> all = _forDeparture(departureUuid);
    return all.isEmpty ? null : all.first;
  }
}
