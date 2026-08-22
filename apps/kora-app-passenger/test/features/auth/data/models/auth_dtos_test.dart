// L'API n'enveloppe pas ses réponses de la même façon partout : `auth/me`
// et la mise à jour du profil répondent `{ data: { user } }`, la connexion
// place l'utilisateur dans `data.user`. Une couche oubliée ne lève aucune
// erreur — elle rend un utilisateur vide, qui finit en stockage.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/auth/data/models/auth_dtos.dart';

void main() {
  const raw = {
    'uuid': 'user-1',
    'name': 'NASSARA KÉVINE',
    'email': 'passager@korabus.app',
    'phone': '0191003606',
    'country_code': '+229',
    'birth_date': '2000-04-06',
    'status': 'active',
    'permissions': ['bookings.create'],
  };

  void expectComplete(dynamic body) {
    final user = AuthDtos.user(body);

    expect(user.uuid, 'user-1');
    expect(user.name, 'NASSARA KÉVINE');
    expect(user.email, 'passager@korabus.app');
    expect(user.phone, '0191003606');
    expect(user.countryCode, '+229');
    expect(user.birthDate, DateTime(2000, 4, 6));
    expect(user.can('bookings.create'), isTrue);
  }

  test('lit l’objet nu', () => expectComplete(raw));

  test('lit `{ user }`', () => expectComplete({'user': raw}));

  test('lit la réponse complète `{ data: { user } }`', () {
    // La forme rendue par `PUT auth/me` : c'est celle qui manquait, et
    // le profil revenait vide après chaque enregistrement.
    expectComplete({
      'data': {'user': raw},
    });
  });

  test('lit `{ data: {...} }` sans niveau `user`', () {
    expectComplete({'data': raw});
  });

  test(
    'rend un utilisateur vide plutôt que de lever, sur un corps absurde',
    () {
      final user = AuthDtos.user('pas un objet');

      expect(user.name, isEmpty);
      expect(user.email, isEmpty);
      expect(user.permissions, isEmpty);
    },
  );
}
