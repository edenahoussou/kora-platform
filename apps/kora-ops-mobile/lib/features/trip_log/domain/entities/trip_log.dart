import 'package:equatable/equatable.dart';

/// Carnet de bord d'un départ (chauffeur et convoyeur) : kilométrage au
/// départ et à l'arrivée, dépenses de voyage (carburant, péages, taxes
/// frontières). Les dépenses partent « en attente » de validation au
/// bureau (module Dépenses de KORA OPS Web) — voir
/// `GET/PUT/POST ops/departures/{uuid}/trip-log|odometer|trip-expenses`.

enum OdometerKind {
  start('start', 'Départ'),
  end('end', 'Arrivée');

  const OdometerKind(this.value, this.label);

  final String value;
  final String label;

  static OdometerKind fromValue(String? value) =>
      value == 'end' ? OdometerKind.end : OdometerKind.start;
}

enum TripExpenseKind {
  fuel('fuel', 'Carburant', 'Plein de carburant'),
  toll('toll', 'Péage', 'Péage'),
  borderTax('border_tax', 'Taxe frontière', 'Taxe frontière');

  const TripExpenseKind(this.value, this.label, this.formTitle);

  final String value;
  final String label;
  final String formTitle;

  static TripExpenseKind fromValue(String? value) => switch (value) {
        'toll' => TripExpenseKind.toll,
        'border_tax' => TripExpenseKind.borderTax,
        _ => TripExpenseKind.fuel,
      };
}

/// Moyen de paiement — mêmes codes que `ExpensePaymentChannel` côté
/// serveur.
enum TripPaymentChannel {
  cash('cash', 'Espèces'),
  mtnMomo('mtn_momo', 'MTN MoMo'),
  moovFlooz('moov_flooz', 'Moov Money'),
  celtiisCash('celtiis_cash', 'Celtiis Cash');

  const TripPaymentChannel(this.value, this.label);

  final String value;
  final String label;

  static TripPaymentChannel fromValue(String? value) =>
      TripPaymentChannel.values.firstWhere(
        (TripPaymentChannel c) => c.value == value,
        orElse: () => TripPaymentChannel.cash,
      );
}

class OdometerReading extends Equatable {
  const OdometerReading({
    required this.kind,
    required this.valueKm,
    required this.recordedAt,
    this.recordedBy,
    this.pendingSync = false,
  });

  final OdometerKind kind;
  final int valueKm;
  final DateTime recordedAt;
  final String? recordedBy;

  /// Saisi hors ligne, pas encore transmis au serveur.
  final bool pendingSync;

  @override
  List<Object?> get props =>
      <Object?>[kind, valueKm, recordedAt, recordedBy, pendingSync];
}

/// Dépense de voyage déclarée (ou en attente d'envoi).
class TripExpense extends Equatable {
  const TripExpense({
    required this.clientUuid,
    required this.kind,
    required this.amount,
    required this.occurredAt,
    required this.status,
    this.reference,
    this.liters,
    this.unitPrice,
    this.stationLabel,
    this.checkpointLabel,
    this.countryCode,
    this.odometerKm,
    this.paymentChannel = TripPaymentChannel.cash,
    this.rejectionReason,
    this.hasPhoto = false,
    this.submittedBy,
    this.pendingSync = false,
    this.note,
  });

  final String clientUuid;
  final TripExpenseKind kind;
  final int amount;
  final DateTime occurredAt;

  /// `pending`, `approved`, `rejected`, `reversed` (serveur) — ignoré tant
  /// que [pendingSync] est vrai.
  final String status;
  final String? reference;
  final double? liters;
  final int? unitPrice;
  final String? stationLabel;
  final String? checkpointLabel;
  final String? countryCode;
  final int? odometerKm;
  final TripPaymentChannel paymentChannel;
  final String? rejectionReason;
  final bool hasPhoto;
  final String? submittedBy;
  final bool pendingSync;
  final String? note;

  String get statusLabel {
    if (pendingSync) return 'À envoyer';
    return switch (status) {
      'approved' => 'Validée',
      'rejected' => 'Rejetée',
      'reversed' => 'Annulée',
      _ => 'En attente de validation',
    };
  }

  /// Libellé principal : station (carburant) ou poste (péage/taxe).
  String get placeLabel =>
      (kind == TripExpenseKind.fuel ? stationLabel : checkpointLabel) ??
      kind.label;

  @override
  List<Object?> get props => <Object?>[
        clientUuid,
        kind,
        amount,
        occurredAt,
        status,
        reference,
        liters,
        unitPrice,
        stationLabel,
        checkpointLabel,
        countryCode,
        odometerKm,
        paymentChannel,
        rejectionReason,
        hasPhoto,
        submittedBy,
        pendingSync,
        note,
      ];
}

/// Saisie d'une dépense de voyage avant envoi.
class TripExpenseDraft extends Equatable {
  const TripExpenseDraft({
    required this.clientUuid,
    required this.departureUuid,
    required this.kind,
    required this.occurredAt,
    this.amount,
    this.liters,
    this.unitPrice,
    this.stationLabel,
    this.checkpointLabel,
    this.countryCode,
    this.odometerKm,
    this.paymentChannel = TripPaymentChannel.cash,
    this.note,
    this.photoPath,
  });

  final String clientUuid;
  final String departureUuid;
  final TripExpenseKind kind;
  final DateTime occurredAt;
  final int? amount;
  final double? liters;
  final int? unitPrice;
  final String? stationLabel;
  final String? checkpointLabel;
  final String? countryCode;
  final int? odometerKm;
  final TripPaymentChannel paymentChannel;
  final String? note;

  /// Chemin local de la photo du ticket (copiée dans le stockage de l'app
  /// pour survivre à un envoi différé hors ligne).
  final String? photoPath;

  /// Montant effectivement retenu : saisi, sinon litres × prix au litre.
  int get effectiveAmount {
    if (amount != null) return amount!;
    if (liters != null && unitPrice != null) {
      return (liters! * unitPrice!).round();
    }
    return 0;
  }

  TripExpense toPendingExpense() => TripExpense(
        clientUuid: clientUuid,
        kind: kind,
        amount: effectiveAmount,
        occurredAt: occurredAt,
        status: 'pending',
        liters: liters,
        unitPrice: unitPrice,
        stationLabel: stationLabel,
        checkpointLabel: checkpointLabel,
        countryCode: countryCode,
        odometerKm: odometerKm,
        paymentChannel: paymentChannel,
        hasPhoto: photoPath != null,
        pendingSync: true,
        note: note,
      );

  @override
  List<Object?> get props => <Object?>[
        clientUuid,
        departureUuid,
        kind,
        occurredAt,
        amount,
        liters,
        unitPrice,
        stationLabel,
        checkpointLabel,
        countryCode,
        odometerKm,
        paymentChannel,
        note,
        photoPath,
      ];
}

class TripLog extends Equatable {
  const TripLog({
    required this.departureUuid,
    this.busRegistration,
    this.lastKnownOdometerKm,
    this.start,
    this.end,
    this.expenses = const <TripExpense>[],
  });

  const TripLog.empty(this.departureUuid)
      : busRegistration = null,
        lastKnownOdometerKm = null,
        start = null,
        end = null,
        expenses = const <TripExpense>[];

  final String departureUuid;
  final String? busRegistration;

  /// Dernier kilométrage connu du bus sur un autre voyage — aide à la
  /// saisie (pré-remplissage) et contrôle de cohérence.
  final int? lastKnownOdometerKm;
  final OdometerReading? start;
  final OdometerReading? end;
  final List<TripExpense> expenses;

  int? get distanceKm =>
      start != null && end != null ? end!.valueKm - start!.valueKm : null;

  OdometerReading? reading(OdometerKind kind) =>
      kind == OdometerKind.start ? start : end;

  /// Dernier kilométrage connu (ce voyage d'abord) — pré-remplit les
  /// saisies suivantes.
  int? get latestKm => end?.valueKm ?? start?.valueKm ?? lastKnownOdometerKm;

  double get totalFuelLiters => expenses
      .where((TripExpense e) => e.kind == TripExpenseKind.fuel)
      .fold<double>(0, (double sum, TripExpense e) => sum + (e.liters ?? 0));

  int get pendingSyncCount =>
      expenses.where((TripExpense e) => e.pendingSync).length +
      (start?.pendingSync == true ? 1 : 0) +
      (end?.pendingSync == true ? 1 : 0);

  TripLog copyWith({
    String? busRegistration,
    int? lastKnownOdometerKm,
    OdometerReading? start,
    OdometerReading? end,
    List<TripExpense>? expenses,
  }) {
    return TripLog(
      departureUuid: departureUuid,
      busRegistration: busRegistration ?? this.busRegistration,
      lastKnownOdometerKm: lastKnownOdometerKm ?? this.lastKnownOdometerKm,
      start: start ?? this.start,
      end: end ?? this.end,
      expenses: expenses ?? this.expenses,
    );
  }

  @override
  List<Object?> get props => <Object?>[
        departureUuid,
        busRegistration,
        lastKnownOdometerKm,
        start,
        end,
        expenses,
      ];
}
