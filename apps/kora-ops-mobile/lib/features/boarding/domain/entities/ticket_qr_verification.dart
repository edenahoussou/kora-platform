import 'package:equatable/equatable.dart';

/// Résultat de la vérification d'authenticité d'un QR de billet (Ed25519,
/// hors ligne — voir `TicketQrVerifier`). Miroir du couple
/// `VerifyTicketQrSignatureAction`/`ResolveTicketForBoardingAction` côté
/// backend : la signature est vérifiée EN PREMIER, le payload n'est
/// décodé qu'une fois la signature confirmée — jamais l'inverse (décoder
/// un payload non vérifié reviendrait à faire confiance à du contenu
/// potentiellement forgé).
sealed class TicketQrVerification extends Equatable {
  const TicketQrVerification();
}

/// Signature valide — [ticketUuid] est le champ `t` du payload signé,
/// [shortCode] le champ `s` (peut être absent selon la version du QR).
class TicketQrVerified extends TicketQrVerification {
  const TicketQrVerified({required this.ticketUuid, this.shortCode});

  final String ticketUuid;
  final String? shortCode;

  @override
  List<Object?> get props => <Object?>[ticketUuid, shortCode];
}

/// Signature cryptographiquement invalide — le contenu a été altéré, ou
/// ne provient pas de la clé privée KORA. Distingué de
/// [TicketQrMalformed] pour un futur usage (journalisation/alerte), mais
/// traité IDENTIQUEMENT côté appelant à ce stade (voir
/// `BoardingRepositoryImpl.scan`, statut `ticketUnknown` dans les deux
/// cas — même traitement que le backend, qui ne distingue pas non plus
/// ces deux cas dans sa réponse).
class TicketQrSignatureInvalid extends TicketQrVerification {
  const TicketQrSignatureInvalid();

  @override
  List<Object?> get props => const <Object?>[];
}

/// Ne suit pas le format `base64url(payload).base64url(signature)`, ou le
/// payload décodé n'est pas un JSON exploitable — jamais un vrai QR KORA
/// (ex. un QR d'un autre système scanné par erreur).
class TicketQrMalformed extends TicketQrVerification {
  const TicketQrMalformed();

  @override
  List<Object?> get props => const <Object?>[];
}
