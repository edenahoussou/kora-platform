import 'package:equatable/equatable.dart';

/// Ce que le serveur dit du chat support, à l'instant où on le lui
/// demande.
///
/// Rien n'est figé dans l'application : la configuration tawk.to se règle
/// depuis OPS, et couper le chat là-bas doit le faire disparaître ici sans
/// republier l'app. C'est pour ça que l'entrée « Discuter avec un agent »
/// n'existe que si cet objet dit qu'elle doit exister.
class SupportChatConfiguration extends Equatable {
  const SupportChatConfiguration({
    this.enabled = false,
    this.propertyId = '',
    this.widgetId = '',
    this.directChatUrl = '',
    this.secureHash,
  });

  /// L'intégration est allumée côté OPS. Fausse aussi quand elle n'a
  /// jamais été configurée : le serveur ne rend alors que ce drapeau.
  final bool enabled;

  /// Identifiants **publics** du widget. Ils se lisent dans n'importe
  /// quelle page web qui affiche le chat : rien à protéger ici.
  final String propertyId;
  final String widgetId;

  /// L'adresse de la conversation, telle que tawk.to la publie.
  final String directChatUrl;

  /// HMAC-SHA256 de l'e-mail du passager, calculé **par le serveur**.
  ///
  /// C'est le mode sécurisé de tawk.to : il prouve à tawk.to que le
  /// visiteur est bien celui qu'il prétend être. La clé qui le calcule ne
  /// quitte jamais le serveur — l'application reçoit le résultat, jamais
  /// le moyen de le produire.
  ///
  /// `null` quand le mode sécurisé n'est pas activé : le chat fonctionne
  /// alors sans identité vérifiée.
  final String? secureHash;

  /// Vrai quand il y a de quoi ouvrir une conversation.
  ///
  /// Le drapeau ne suffit pas : une intégration allumée mais sans adresse
  /// ouvrirait une page blanche, ce qui est pire qu'une entrée absente.
  bool get isUsable => enabled && directChatUrl.isNotEmpty;

  @override
  List<Object?> get props => [
    enabled,
    propertyId,
    widgetId,
    directChatUrl,
    secureHash,
  ];
}
