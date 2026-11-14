// La traduction d'une notification du serveur vers l'écran.
//
// Deux choses s'y jouent : le pictogramme, déduit du type d'événement, et
// la tolérance aux champs manquants — une notification mal formée doit
// s'afficher amputée plutôt que vider la liste.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/notifications/data/models/notification_model.dart';
import 'package:kora_app_passenger/features/notifications/domain/entities/app_notification.dart';

void main() {
  test('lit une notification complète', () {
    final notification = NotificationModel.fromJson({
      'uuid': '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb80',
      'type': 'reservation_confirmed',
      'title': 'Réservation confirmée',
      'message': 'Votre trajet Cotonou → Bohicon est confirmé.',
      'is_read': false,
      'created_at': '2026-10-13T08:26:31.000000Z',
    });

    expect(notification.id, '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb80');
    expect(notification.kind, NotificationKind.ticket);
    expect(notification.title, 'Réservation confirmée');
    expect(notification.isUnread, isTrue);
    expect(
      notification.receivedAt.toUtc(),
      DateTime.utc(2026, 10, 13, 8, 26, 31),
    );
  });

  test('`is_read` vrai éteint la pastille', () {
    final notification = NotificationModel.fromJson({
      'uuid': 'un',
      'is_read': true,
    });

    expect(notification.isUnread, isFalse);
  });

  group('le pictogramme suit le type d’événement', () {
    final expected = {
      'reservation_confirmed': NotificationKind.ticket,
      'ticket_issuance_failed': NotificationKind.ticket,
      'departure_cancelled': NotificationKind.cancelled,
      'departure_reminder': NotificationKind.reminder,
      'departure_delayed': NotificationKind.delay,
      'departure_modified': NotificationKind.delay,
      'refund_succeeded': NotificationKind.wallet,
      'refund_pending': NotificationKind.wallet,
      // Le serveur émet tout le Wallet sous ce préfixe. Ils tombaient
      // dans le repli : une recharge confirmée s'affichait avec le
      // pictogramme d'un billet.
      'wallet_topup_confirmed': NotificationKind.wallet,
      'wallet_points_expiring': NotificationKind.wallet,
      'wallet_restitution_paid': NotificationKind.wallet,
      'wallet_bonus_welcome': NotificationKind.wallet,
    };

    for (final entry in expected.entries) {
      test(entry.key, () {
        expect(NotificationModel.kindOf(entry.key), entry.value);
      });
    }
  });

  test('un type inconnu reste une mise à jour de voyage', () {
    // Le serveur peut en ajouter sans prévenir : mieux vaut un
    // pictogramme générique qu'un écran qui tombe.
    expect(NotificationModel.kindOf('colis_arrived'), NotificationKind.ticket);
    expect(NotificationModel.kindOf(''), NotificationKind.ticket);
  });

  test('une notification sans titre ni date reste affichable', () {
    final before = DateTime.now();
    final notification = NotificationModel.fromJson({'uuid': 'un'});

    expect(notification.title, 'Mise à jour de votre trajet');
    expect(notification.text, '');
    expect(
      notification.receivedAt.isBefore(
        before.subtract(const Duration(seconds: 1)),
      ),
      isFalse,
    );
  });
}
