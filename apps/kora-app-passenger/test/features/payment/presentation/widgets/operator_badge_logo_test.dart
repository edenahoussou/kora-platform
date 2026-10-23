// Quel logo pour quel réseau.
//
// Le serveur suffixe les réseaux par pays (`mtn_momo_ci`,
// `moov_flooz_tg`) pour éviter un champ `payer_country` séparé — voir
// ADR 0019. La marque, elle, ne change pas d'un pays à l'autre : sans ce
// rattachement, un passager ivoirien verrait des initiales là où son
// voisin béninois voit le logo MTN.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/mobile_money_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/widgets/operator_badge.dart';

void main() {
  group('le logo d’un réseau', () {
    test('les codes béninois restent inchangés', () {
      expect(OperatorBadge.logoFor('mtn_momo'), 'mtn_momo');
      expect(OperatorBadge.logoFor('moov_flooz'), 'moov_flooz');
      expect(OperatorBadge.logoFor('celtiis_cash'), 'celtiis_cash');
    });

    test('un réseau suffixé retrouve le logo de sa marque', () {
      expect(OperatorBadge.logoFor('mtn_momo_ci'), 'mtn_momo');
      expect(OperatorBadge.logoFor('moov_flooz_ci'), 'moov_flooz');
      expect(OperatorBadge.logoFor('moov_flooz_tg'), 'moov_flooz');
    });

    test('les marques ivoiriennes et togolaises ont leur logo', () {
      // Wave, Orange Money et T-Money ont rejoint le dépôt : leurs codes
      // suffixés doivent retrouver la même image que les autres.
      expect(OperatorBadge.logoFor('wave_ci'), 'wave');
      expect(OperatorBadge.logoFor('orange_money_ci'), 'orange_money');
      expect(OperatorBadge.logoFor('tmoney_tg'), 'tmoney');
    });

    test('un réseau sans logo au dépôt n’en invente pas', () {
      // La vignette retombe alors sur les initiales, ce qui vaut mieux
      // qu'une erreur de rendu sur un fichier absent.
      expect(OperatorBadge.logoFor('reseau_inconnu'), isNull);
    });

    test('un suffixe inconnu ne devient pas un logo', () {
      expect(OperatorBadge.logoFor('mtn_momo_zz'), isNull);
      expect(OperatorBadge.logoFor('inconnu'), isNull);
    });
  });

  group('le nom d’un réseau', () {
    test('porte la marque, pas le code du serveur', () {
      // Le suffixe de pays est une affaire de serveur : le voyageur
      // reconnaît « MTN MoMo », pas « Mtn Momo Ci ».
      expect(mobileMoneyLabel('mtn_momo_ci'), 'MTN MoMo');
      expect(mobileMoneyLabel('moov_flooz_tg'), 'Moov Flooz');
      expect(mobileMoneyLabel('wave_ci'), 'Wave');
      expect(mobileMoneyLabel('orange_money_ci'), 'Orange Money');
      expect(mobileMoneyLabel('tmoney_tg'), 'T-Money');
    });

    test('porte la marque aussi sans suffixe', () {
      // L'écran liste les **marques** : le suffixe de pays n'est recollé
      // qu'au moment de payer. Sans ces entrées-là, le voyageur lisait
      // « Tmoney » sous le logo T-Money.
      expect(mobileMoneyLabel('wave'), 'Wave');
      expect(mobileMoneyLabel('orange_money'), 'Orange Money');
      expect(mobileMoneyLabel('tmoney'), 'T-Money');
    });

    test('un réseau inconnu reste lisible', () {
      // Un réseau ajouté côté serveur après cette version s'affiche sous
      // son code remis en forme plutôt que brut.
      expect(mobileMoneyLabel('nouveau_reseau'), 'Nouveau Reseau');
    });
  });
}
