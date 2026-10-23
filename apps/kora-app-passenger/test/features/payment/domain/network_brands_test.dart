// Marque d'opérateur, pays, et le réseau que le serveur attend.
//
// Le serveur suffixe ses réseaux par pays (`moov_flooz_ci`) pour que le
// pays ne puisse pas diverger du réseau — voir ADR 0019. Le voyageur, lui,
// choisit une marque et compose un indicatif : c'est l'app qui recolle
// les deux.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_catalog.dart';

void main() {
  // Les neuf réseaux que le staging annonce pour chaque passerelle.
  const gateway = PaymentProvider(
    code: 'kkiapay',
    label: 'KKiaPay',
    enabled: true,
    networks: [
      'mtn_momo',
      'moov_flooz',
      'celtiis_cash',
      'mtn_momo_ci',
      'moov_flooz_ci',
      'wave_ci',
      'orange_money_ci',
      'moov_flooz_tg',
      'tmoney_tg',
    ],
    interaction: 'sdk_widget',
    verification: 'server_verify',
  );

  group('les marques servies', () {
    test('ne répètent pas un opérateur présent dans plusieurs pays', () {
      // Moov apparaît trois fois dans les réseaux — Bénin, Côte d'Ivoire,
      // Togo. La liste des opérateurs ne doit le montrer qu'une fois.
      expect(gateway.networkBrands, [
        'mtn_momo',
        'moov_flooz',
        'celtiis_cash',
        'wave',
        'orange_money',
        'tmoney',
      ]);
    });

    test('gardent l’ordre du serveur', () {
      // Le catalogue range ses réseaux par préférence : l'app ne
      // réordonne pas ce que le serveur a classé.
      expect(gateway.networkBrands.first, 'mtn_momo');
    });
  });

  group('le réseau à envoyer', () {
    test('n’a pas de suffixe au Bénin', () {
      expect(gateway.networkFor('mtn_momo', 'BJ'), 'mtn_momo');
      expect(gateway.networkFor('moov_flooz', 'BJ'), 'moov_flooz');
    });

    test('porte le suffixe du pays ailleurs', () {
      expect(gateway.networkFor('mtn_momo', 'CI'), 'mtn_momo_ci');
      expect(gateway.networkFor('moov_flooz', 'TG'), 'moov_flooz_tg');
      expect(gateway.networkFor('wave', 'CI'), 'wave_ci');
    });

    test('rend `null` quand la marque n’est pas servie dans ce pays', () {
      // Celtiis est béninois, Wave ivoirien, T-Money togolais : les
      // croiser avec le mauvais indicatif ferait débiter un compte dans
      // un pays où l'opérateur n'existe pas.
      expect(gateway.networkFor('celtiis_cash', 'CI'), isNull);
      expect(gateway.networkFor('wave', 'BJ'), isNull);
      expect(gateway.networkFor('tmoney', 'CI'), isNull);
    });

    test('rend `null` pour un pays hors catalogue', () {
      // Un indicatif sénégalais ou français ne correspond à aucun réseau.
      expect(gateway.networkFor('mtn_momo', 'SN'), isNull);
      expect(gateway.networkFor('mtn_momo', ''), isNull);
    });
  });

  group('les pays d’une marque', () {
    test('disent où l’opérateur est joignable', () {
      expect(gateway.countriesServing('moov_flooz'), ['BJ', 'CI', 'TG']);
      expect(gateway.countriesServing('mtn_momo'), ['BJ', 'CI']);
      expect(gateway.countriesServing('celtiis_cash'), ['BJ']);
      expect(gateway.countriesServing('tmoney'), ['TG']);
    });
  });
}
