import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../../../core/constants/storage_keys.dart';

/// Un paiement laissé en cours, retenu sur le téléphone.
///
/// Le parcours de paiement vit dans un Bloc attaché au tunnel de
/// réservation : l'application fermée, il disparaît avec tout ce qu'il
/// savait. Le voyageur qui ferme l'app pendant qu'il compose son code
/// revenait alors sans rien — ni le paiement, ni le moyen de savoir
/// s'il avait abouti, alors que le serveur, lui, l'a peut-être confirmé
/// entre-temps.
///
/// Rien de sensible ici : l'identifiant d'un paiement et celui d'une
/// réservation, que le serveur refuse de servir à quelqu'un d'autre.
class PendingPayment {
  const PendingPayment({
    required this.paymentId,
    required this.reservationId,
    this.transactionId,
  });

  factory PendingPayment.fromJson(Map<String, dynamic> json) {
    return PendingPayment(
      paymentId: json['payment_id']?.toString() ?? '',
      reservationId: json['reservation_id']?.toString() ?? '',
      transactionId: json['transaction_id']?.toString(),
    );
  }

  /// `uuid` du paiement, avec lequel on relit son état.
  final String paymentId;

  final String reservationId;

  /// L'identifiant rendu par le widget KKiaPay, quand il y en a eu un.
  ///
  /// Gardé avec le reste : c'est la seule chose que le widget ait
  /// produite, et le serveur en a besoin pour conclure si la première
  /// vérification n'a pas abouti avant la fermeture de l'app.
  final String? transactionId;

  bool get isUsable => paymentId.isNotEmpty;

  Map<String, dynamic> toJson() => {
    'payment_id': paymentId,
    'reservation_id': reservationId,
    if (transactionId != null) 'transaction_id': transactionId,
  };
}

/// Couche DATA : le paiement en cours, d'un lancement de l'app à l'autre.
class PendingPaymentStore {
  const PendingPaymentStore(this._prefs);

  final SharedPreferences _prefs;

  /// Le paiement laissé en attente, ou `null` s'il n'y en a pas.
  PendingPayment? read() {
    final raw = _prefs.getString(StorageKeys.pendingPayment);
    if (raw == null || raw.isEmpty) return null;

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;

      final pending = PendingPayment.fromJson(decoded.cast<String, dynamic>());
      return pending.isUsable ? pending : null;
    } on FormatException {
      // Une trace illisible ne doit pas empêcher de payer : on l'oublie.
      return null;
    }
  }

  Future<void> save(PendingPayment pending) => _prefs.setString(
    StorageKeys.pendingPayment,
    jsonEncode(pending.toJson()),
  );

  /// Efface la trace : le paiement a abouti, échoué, ou le voyageur a
  /// repris la main.
  ///
  /// **Un paiement terminé doit disparaître d'ici.** Le garder ferait
  /// proposer au prochain lancement de reprendre un paiement déjà réglé,
  /// et le voyageur croirait devoir payer deux fois.
  Future<void> clear() => _prefs.remove(StorageKeys.pendingPayment);
}
