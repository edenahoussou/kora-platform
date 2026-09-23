import '../entities/ticket_qr_verification.dart';

/// Vérifie l'authenticité d'un token QR de billet SANS réseau (Ed25519,
/// clé publique embarquée — voir `AppConfig.ticketSigningPublicKeyBase64`).
/// Miroir mobile de `VerifyTicketQrSignatureAction` côté backend : même
/// format de token (`base64url(payload).base64url(signature)`, voir
/// `BuildTicketQrPayloadAction`), même algorithme, clé publique
/// correspondant à la clé privée qui signe côté serveur.
///
/// Utilisé par `BoardingRepositoryImpl.scan` avant toute tentative
/// réseau : un QR falsifié doit être rejeté immédiatement, aussi bien
/// hors ligne (où le serveur ne peut pas trancher) qu'en ligne (où il
/// n'y a pas lieu d'attendre une réponse serveur pour un résultat déjà
/// certain localement — le serveur revérifie quand même à son tour, en
/// défense en profondeur).
abstract class TicketQrVerifier {
  Future<TicketQrVerification> verify(String token);
}
