import 'push_gateway.dart';

/// Affiche une notification dans la barre d'état du téléphone.
///
/// Cette pièce n'existe que pour un cas : **l'application à l'écran**.
/// Partout ailleurs — en arrière-plan, application fermée — c'est Android
/// qui dessine la notification à partir du bloc `notification` du message,
/// sans réveiller la moindre ligne de Dart. Mais au premier plan, Firebase
/// se tait volontairement et remet le message à l'application : à elle de
/// décider quoi en faire.
///
/// Ce que l'application en fait, ici, c'est exactement ce qu'Android
/// aurait fait : une vraie notification, sur le même canal, qui reste dans
/// le volet tant que le voyageur ne l'a pas lue. Un message passager —
/// bandeau, `SnackBar` — ne conviendrait pas : un billet confirmé doit
/// pouvoir se retrouver trois minutes plus tard.
///
/// Comme [PushGateway], l'interface sert surtout à couper le greffon natif
/// du reste : les notifications locales ne tournent pas dans `flutter
/// test`.
abstract class NotificationPresenter {
  /// Dépose [message] dans la barre d'état.
  ///
  /// Sans titre ni texte, il n'y a rien à montrer : l'appel ne fait rien.
  Future<void> show(PushMessage message);

  /// Les notifications affichées ici et touchées par le voyageur.
  ///
  /// Firebase ne connaît pas ces notifications — c'est l'application qui
  /// les a posées —, donc son propre flux d'ouvertures les ignore. Sans ce
  /// relais, toucher la notification d'un billet confirmé ne mènerait
  /// nulle part.
  Stream<PushMessage> get openings;
}
