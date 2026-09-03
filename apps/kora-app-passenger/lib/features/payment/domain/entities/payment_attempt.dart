import 'package:equatable/equatable.dart';

/// Où en est un paiement, du côté du serveur.
///
/// Les valeurs sont celles de `PaymentResource.status`. L'app ne conclut
/// jamais un paiement elle-même : elle relit cet état, et c'est tout.
enum PaymentAttemptStatus {
  pending,
  confirmed,
  failed,
  cancelled;

  /// L'état porté par le serveur, ou [pending] pour une valeur inconnue —
  /// un statut ajouté côté back ne doit pas faire croire à un échec.
  static PaymentAttemptStatus fromWire(String wire) => switch (wire) {
    'confirmed' => confirmed,
    'failed' => failed,
    'cancelled' => cancelled,
    _ => pending,
  };

  /// Plus rien à attendre : inutile de continuer à interroger le serveur.
  bool get isSettled => this != pending;
}

/// Couche DOMAIN : un paiement lancé, et ce qu'il reste à en faire.
///
/// **Aucune URL de paiement n'y figure, et c'est voulu.** Le seul moyen
/// exposé par le serveur est le Mobile Money, que `FedaPayAdapter` déclenche
/// par `sendNow()` : l'opérateur pousse sa demande de code directement sur
/// le téléphone du payeur. Il n'y a donc pas de page web à ouvrir — la
/// saisie se fait hors de l'app, dans la fenêtre de l'opérateur, et l'app
/// se contente d'attendre.
///
/// [checkoutUrl] reste prévu pour le jour où le serveur appellerait
/// `generateToken()` et rendrait un lien FedaPay. Il s'ouvrira alors dans
/// le **navigateur du système**, jamais dans un WebView embarqué : un
/// paiement encapsulé dans l'app est exactement ce que la revue Apple
/// examine de près, alors qu'un renvoi vers le navigateur ne pose aucune
/// question.
class PaymentAttempt extends Equatable {
  const PaymentAttempt({
    required this.id,
    required this.status,
    this.reference,
    this.nextAction = '',
    this.pollingInterval = defaultPollingInterval,
    this.expiresAt,
    this.failureMessage,
    this.checkoutUrl,
  });

  /// `uuid` du paiement, avec lequel on interroge son état.
  final String id;

  final PaymentAttemptStatus status;

  /// `provider_reference` : la référence à citer au support.
  final String? reference;

  /// Ce que le serveur attend de l'app : `verify`, `show_tickets`,
  /// `wait_for_tickets` ou `retry`.
  final String nextAction;

  /// Cadence conseillée entre deux relectures. Vient du serveur, qui seul
  /// sait ce que son provider tolère.
  final Duration pollingInterval;

  /// Échéance de la réservation retenue, telle que le serveur la connaît
  /// au moment du paiement.
  final DateTime? expiresAt;

  /// Renseigné quand le paiement a échoué.
  final String? failureMessage;

  /// Lien de paiement, si le serveur en produit un un jour. `null`
  /// aujourd'hui — voir la note de classe.
  final String? checkoutUrl;

  /// Cadence retenue quand le serveur n'en annonce pas.
  static const Duration defaultPollingInterval = Duration(seconds: 10);

  bool get isConfirmed => status == PaymentAttemptStatus.confirmed;
  bool get isPending => status == PaymentAttemptStatus.pending;

  /// Les billets sont émis et consultables.
  bool get hasTickets => nextAction == 'show_tickets';

  @override
  List<Object?> get props => [
    id,
    status,
    reference,
    nextAction,
    pollingInterval,
    expiresAt,
    failureMessage,
    checkoutUrl,
  ];
}
