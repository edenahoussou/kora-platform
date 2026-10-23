// Ce que la feuille KKiaPay a le droit d'encaisser.
//
// Le serveur autorise `momo`, `card` et `wallet` sur un même paiement :
// sa feuille sait tout prendre. Mais le voyageur a déjà choisi, un écran
// plus tôt, par où il paie — et lui rouvrir les autres portes annulerait
// ce choix sans le dire.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/kkiapay_widget.dart';

void main() {
  const widget = KkiapayWidget(
    publicKey: 'pk_test',
    amount: 31600,
    partnerId: 'pay-1',
    methods: ['momo', 'card', 'wallet'],
    countries: ['BJ', 'CI', 'TG'],
    phone: '+2290166000001',
    reason: 'KORA BUS',
  );

  group('la feuille restreinte', () {
    test('à la carte ne propose que la carte', () {
      expect(widget.restrictedToCard().methods, ['card']);
    });

    test('au Mobile Money ne propose que le Mobile Money', () {
      // Venu par « KKiaPay · Mobile Money », le voyageur ne doit pas
      // retrouver « Cartes Bancaires » dans la feuille : l'écran
      // d'attente annoncerait ensuite un règlement Mobile Money qui n'en
      // serait pas un.
      expect(widget.restrictedToMomo().methods, ['momo']);
    });

    test('ne touche à rien d’autre', () {
      // Le montant, la clé et le `partnerId` sont arrêtés par le
      // serveur : les recopier de travers ferait débiter autre chose que
      // ce que la réservation attend.
      final momo = widget.restrictedToMomo();

      expect(momo.publicKey, 'pk_test');
      expect(momo.amount, 31600);
      expect(momo.partnerId, 'pay-1');
      expect(momo.sandbox, isTrue);
      expect(momo.countries, ['BJ', 'CI', 'TG']);
      expect(momo.phone, '+2290166000001');
      expect(momo.reason, 'KORA BUS');
    });
  });

  group('une configuration utilisable', () {
    test('exige une clé et un montant', () {
      expect(widget.isUsable, isTrue);
      expect(
        const KkiapayWidget(
          publicKey: '',
          amount: 31600,
          partnerId: 'pay-1',
        ).isUsable,
        isFalse,
      );
      expect(
        const KkiapayWidget(
          publicKey: 'pk_test',
          amount: 0,
          partnerId: 'pay-1',
        ).isUsable,
        isFalse,
      );
    });
  });
}
