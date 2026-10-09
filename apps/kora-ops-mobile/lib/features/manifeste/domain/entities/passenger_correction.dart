import 'package:equatable/equatable.dart';

import '../../../ops/domain/entities/passenger.dart';

/// Clés du corps `PATCH .../tickets/{ticket}/passenger` — miroir de
/// `CorrectManifestPassengerRequest` côté backend.
abstract final class PassengerCorrectionKeys {
  static const String firstName = 'first_name';
  static const String lastName = 'last_name';
  static const String phone = 'phone';
  static const String email = 'email';
  static const String documentType = 'document_type';
  static const String documentNumber = 'document_number';
  static const String nationality = 'nationality';
  static const String birthDate = 'birth_date';
  static const String yellowFever = 'yellow_fever_vaccinated';
}

/// Correction d'informations passager saisie par le convoyeur : **seuls
/// les champs réellement modifiés** y figurent (PATCH partiel). Une valeur
/// `null` efface un champ facultatif. Envoyer uniquement le delta évite
/// d'écraser côté serveur une donnée que le cache local ne connaissait pas
/// (cache antérieur à cette fonction).
class PassengerCorrection extends Equatable {
  const PassengerCorrection(this.changes);

  final Map<String, Object?> changes;

  bool get isEmpty => changes.isEmpty;

  @override
  List<Object?> get props => <Object?>[changes];
}

extension PassengerCorrectionApply on Passenger {
  /// Passager tel qu'il serait après [correction] — utilisé pour l'affichage
  /// immédiat et le cache local (le serveur reste la référence).
  Passenger withCorrection(PassengerCorrection correction) {
    final Map<String, Object?> c = correction.changes;
    bool has(String key) => c.containsKey(key);

    final String newPrenom = has(PassengerCorrectionKeys.firstName)
        ? '${c[PassengerCorrectionKeys.firstName]}'
        : (prenom ?? _splitName().$1);
    final String newNom = has(PassengerCorrectionKeys.lastName)
        ? '${c[PassengerCorrectionKeys.lastName]}'
        : (nom ?? _splitName().$2);

    return Passenger(
      id: id,
      nomComplet: (has(PassengerCorrectionKeys.firstName) ||
              has(PassengerCorrectionKeys.lastName))
          ? '$newPrenom $newNom'.trim()
          : nomComplet,
      siege: siege,
      statut: statut,
      segmentOrigine: segmentOrigine,
      segmentDestination: segmentDestination,
      ticketId: ticketId,
      telephone: has(PassengerCorrectionKeys.phone)
          ? c[PassengerCorrectionKeys.phone] as String?
          : telephone,
      originStopUuid: originStopUuid,
      destinationStopUuid: destinationStopUuid,
      boardingEventUuid: boardingEventUuid,
      nationalite: has(PassengerCorrectionKeys.nationality)
          ? c[PassengerCorrectionKeys.nationality] as String?
          : nationalite,
      documentNumero: has(PassengerCorrectionKeys.documentNumber)
          ? c[PassengerCorrectionKeys.documentNumber] as String?
          : documentNumero,
      prenom: newPrenom,
      nom: newNom,
      email: has(PassengerCorrectionKeys.email)
          ? c[PassengerCorrectionKeys.email] as String?
          : email,
      documentType: has(PassengerCorrectionKeys.documentType)
          ? c[PassengerCorrectionKeys.documentType] as String?
          : documentType,
      dateNaissance: has(PassengerCorrectionKeys.birthDate)
          ? c[PassengerCorrectionKeys.birthDate] as String?
          : dateNaissance,
      fievreJauneVaccine: has(PassengerCorrectionKeys.yellowFever)
          ? c[PassengerCorrectionKeys.yellowFever] as bool?
          : fievreJauneVaccine,
    );
  }

  /// Découpage de [nomComplet] quand prénom/nom séparés sont inconnus
  /// (cache ancien) : premier mot = prénom, le reste = nom.
  (String, String) _splitName() {
    final String trimmed = nomComplet.trim();
    final int space = trimmed.indexOf(' ');
    if (space < 0) return (trimmed, '');
    return (trimmed.substring(0, space), trimmed.substring(space + 1).trim());
  }

  /// Prénom/nom à pré-remplir dans le formulaire de correction.
  (String, String) get editableName => (prenom != null || nom != null)
      ? (prenom ?? '', nom ?? '')
      : _splitName();
}

/// Issue d'une demande de correction.
enum PassengerCorrectionOutcome {
  /// Enregistrée et confirmée par le serveur.
  saved,

  /// Réseau indisponible : enregistrée localement, envoyée dès le retour
  /// de la connexion.
  pendingSync,
}
