import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/manifeste/data/models/manifeste_dtos.dart';
import 'package:kora_ops_mobile/features/manifeste/domain/entities/passenger_correction.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';

Passenger _passenger({String? prenom, String? nom}) => Passenger(
      id: 'ticket-1',
      nomComplet: 'Awa Traoré Diallo',
      siege: '1A',
      statut: BoardingStatus.enAttente,
      segmentOrigine: 'Cotonou',
      segmentDestination: 'Lomé',
      ticketId: 'AB12CD',
      telephone: '+22900000000',
      nationalite: 'BJ',
      documentNumero: 'PP123456',
      documentType: 'passport',
      fievreJauneVaccine: true,
      prenom: prenom,
      nom: nom,
    );

void main() {
  group('Passenger.withCorrection', () {
    test('ne modifie que les champs corrigés', () {
      final Passenger updated = _passenger(prenom: 'Awa', nom: 'Traoré Diallo')
          .withCorrection(const PassengerCorrection(<String, Object?>{
        PassengerCorrectionKeys.documentNumber: 'PP999888',
        PassengerCorrectionKeys.yellowFever: false,
      }));

      expect(updated.documentNumero, 'PP999888');
      expect(updated.fievreJauneVaccine, isFalse);
      expect(updated.telephone, '+22900000000');
      expect(updated.nationalite, 'BJ');
      expect(updated.nomComplet, 'Awa Traoré Diallo');
    });

    test('recompose le nom complet et efface un champ facultatif (null)', () {
      final Passenger updated = _passenger(prenom: 'Awa', nom: 'Traoré Diallo')
          .withCorrection(const PassengerCorrection(<String, Object?>{
        PassengerCorrectionKeys.firstName: 'Awa-Marie',
        PassengerCorrectionKeys.phone: null,
      }));

      expect(updated.nomComplet, 'Awa-Marie Traoré Diallo');
      expect(updated.telephone, isNull);
    });

    test('découpe le nom complet quand prénom/nom sont inconnus (ancien cache)',
        () {
      final Passenger passenger = _passenger();
      expect(passenger.editableName, ('Awa', 'Traoré Diallo'));

      final Passenger updated =
          passenger.withCorrection(const PassengerCorrection(<String, Object?>{
        PassengerCorrectionKeys.lastName: 'Diallo',
      }));
      expect(updated.nomComplet, 'Awa Diallo');
    });
  });

  group('ManifesteDtos.passenger', () {
    test('lit l\'identité détaillée, la pièce et le vaccin', () {
      final Passenger? passenger = ManifesteDtos.passenger(<String, dynamic>{
        'ticketUuid': 't1',
        'shortCode': 'AB12CD',
        'name': 'Awa Traoré',
        'firstName': 'Awa',
        'lastName': 'Traoré',
        'phone': '+22900000000',
        'email': 'awa@example.com',
        'document': <String, dynamic>{'type': 'passport', 'number': 'PP1'},
        'nationality': 'BJ',
        'birthDate': '1990-01-01',
        'yellowFeverVaccinated': false,
      });

      expect(passenger?.prenom, 'Awa');
      expect(passenger?.nom, 'Traoré');
      expect(passenger?.telephone, '+22900000000');
      expect(passenger?.email, 'awa@example.com');
      expect(passenger?.documentType, 'passport');
      expect(passenger?.documentNumero, 'PP1');
      expect(passenger?.dateNaissance, '1990-01-01');
      expect(passenger?.fievreJauneVaccine, isFalse);
    });

    test('ligne nationale : champs frontière absents', () {
      final Passenger? passenger = ManifesteDtos.passenger(<String, dynamic>{
        'ticketUuid': 't1',
        'name': 'Awa Traoré',
        'firstName': 'Awa',
        'lastName': 'Traoré',
      });

      expect(passenger?.documentType, isNull);
      expect(passenger?.fievreJauneVaccine, isNull);
    });
  });
}
