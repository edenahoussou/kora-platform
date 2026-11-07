// Ce que le Wallet sait dire de lui-même.
//
// Deux soldes qui ne se mélangent pas, une contre-valeur réglée par le
// serveur, et une échéance de points qui doit se voir **avant** de
// tomber. Les seuils d'alerte viennent du serveur aussi : l'app n'a pas
// à décider à partir de quand il est urgent de prévenir.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_account.dart';

void main() {
  WalletAccount accountWith({
    int points = 2480,
    DateTime? expiry,
    WalletRules rules = const WalletRules(
      cashEnabled: true,
      pointsEnabled: true,
      pointValue: 1,
      expiryReminderDays: [30, 7, 1],
    ),
  }) {
    return WalletAccount(
      id: 'w-1',
      cash: const Price(18750),
      points: points,
      pointsExpireAt: expiry,
      rules: rules,
    );
  }

  group('la contre-valeur des points', () {
    test('suit le taux du serveur, pas une règle écrite dans l’app', () {
      // Un point vaut un franc aujourd'hui (MEZ-49), mais c'est un
      // réglage : le jour où il en vaut deux, l'écran doit suivre sans
      // qu'on republie l'application.
      final account = accountWith(
        rules: const WalletRules(pointsEnabled: true, pointValue: 2),
      );

      expect(account.pointsValue, const Price(4960));
    });

    test('vaut zéro quand il n’y a pas de points', () {
      expect(accountWith(points: 0).pointsValue, const Price(0));
    });
  });

  group('l’échéance des points', () {
    final now = DateTime(2026, 11, 4);

    test('se compte en jours, pas en heures', () {
      // À quelques heures près, « dans 7 jours » ne doit pas devenir
      // « dans 6 » selon l'heure à laquelle l'écran s'ouvre.
      final account = accountWith(expiry: DateTime(2026, 11, 11, 2));

      expect(account.daysBeforeExpiry(DateTime(2026, 11, 4, 23)), 7);
    });

    test('n’existe pas sans points', () {
      // Un solde vide n'a rien à faire expirer : annoncer une échéance
      // inquiéterait pour rien.
      final account = accountWith(points: 0, expiry: DateTime(2026, 11, 6));

      expect(account.daysBeforeExpiry(now), isNull);
      expect(account.needsExpiryWarning(now), isFalse);
    });

    test('n’existe pas sans date', () {
      expect(accountWith().daysBeforeExpiry(now), isNull);
    });
  });

  group('l’alerte d’expiration', () {
    final now = DateTime(2026, 11, 4);

    test('se déclenche au seuil le plus large annoncé par le serveur', () {
      // Les seuils du staging sont 30, 7 et 1 jour : la bannière doit
      // apparaître dès le premier, pas au dernier.
      expect(
        accountWith(expiry: DateTime(2026, 12, 3)).needsExpiryWarning(now),
        isTrue,
      );
      expect(
        accountWith(expiry: DateTime(2026, 12, 5)).needsExpiryWarning(now),
        isFalse,
      );
    });

    test('tient le jour même et après', () {
      expect(accountWith(expiry: now).needsExpiryWarning(now), isTrue);
      expect(
        accountWith(expiry: DateTime(2026, 11, 1)).needsExpiryWarning(now),
        isTrue,
      );
    });

    test('ne se déclenche pas sans seuil serveur', () {
      // Le serveur n'expose pas encore `points_expiry_reminder_days` :
      // sans lui, l'app se tait plutôt que d'inventer un seuil.
      final account = accountWith(
        expiry: DateTime(2026, 11, 5),
        rules: const WalletRules(pointsEnabled: true),
      );

      expect(account.needsExpiryWarning(now), isFalse);
    });
  });

  group('un Wallet fermé', () {
    test('se reconnaît à ses deux soldes désactivés', () {
      expect(accountWith(rules: const WalletRules()).isDormant, isTrue);
    });

    test('ne l’est plus dès qu’un des deux ouvre', () {
      expect(
        accountWith(rules: const WalletRules(pointsEnabled: true)).isDormant,
        isFalse,
      );
      expect(
        accountWith(rules: const WalletRules(cashEnabled: true)).isDormant,
        isFalse,
      );
    });
  });
}
