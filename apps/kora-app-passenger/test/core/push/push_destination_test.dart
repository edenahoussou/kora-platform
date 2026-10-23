// Où mène une notification quand on la touche.
//
// Le serveur ne dit pas toujours la même chose : parfois un chemin tout
// fait, parfois seulement le sujet du message, parfois rien. Les trois
// cas doivent mener quelque part — un appui sans effet sur une
// notification est pire que pas de notification du tout.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/push/push_gateway.dart';
import 'package:kora_app_passenger/core/push/push_listener.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';

void main() {
  group('la destination d’une notification', () {
    test('suit le chemin donné par le serveur', () {
      // Il sait des choses que l'app ignore : son chemin l'emporte.
      expect(
        pushDestination(const PushMessage(data: {'route': '/voyages'})),
        '/voyages',
      );
    });

    test('accepte aussi `link`, la clé déjà employée côté web', () {
      expect(
        pushDestination(const PushMessage(data: {'link': '/aide'})),
        '/aide',
      );
    });

    test('ouvre le billet que la notification désigne', () {
      // La même lecture que la boîte de réception : `resource_uuid` est
      // l'identifiant de la réservation, et l'écran du billet sait le
      // résoudre. Pas besoin de réclamer un second champ au serveur.
      expect(
        pushDestination(
          const PushMessage(
            data: {
              'type': 'ticket.issued',
              'resource_uuid': 'reservation-1234',
            },
          ),
        ),
        AppRouter.ticketByReservation('reservation-1234'),
      );
    });

    test('un chemin relatif ne passe pas pour un chemin', () {
      // `go` attend un chemin absolu ; une valeur douteuse retombe donc
      // sur le sujet du message plutôt que de lancer le routeur sur une
      // route qui n'existe pas.
      expect(
        pushDestination(
          const PushMessage(
            data: {'route': 'voyages', 'resource_uuid': 'reservation-1234'},
          ),
        ),
        AppRouter.ticketByReservation('reservation-1234'),
      );
    });

    test('à défaut, la liste des voyages', () {
      // C'est le sujet de la quasi-totalité des messages : un billet
      // émis, un départ retardé, un paiement confirmé s'y retrouvent.
      expect(pushDestination(const PushMessage()), AppRouter.tripsPath);
      expect(
        pushDestination(const PushMessage(data: {'type': 'ticket.issued'})),
        AppRouter.tripsPath,
      );
    });

    test('une valeur vide vaut une valeur absente', () {
      expect(
        pushDestination(
          const PushMessage(data: {'route': '', 'resource_uuid': ''}),
        ),
        AppRouter.tripsPath,
      );
    });
  });
}
