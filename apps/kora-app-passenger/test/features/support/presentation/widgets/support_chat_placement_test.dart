// Où le bouton d'assistance se montre, et à quelle hauteur.
//
// La règle a changé deux fois. Le bouton couvrait d'abord toutes les
// pages, puis a quitté le tunnel de réservation parce qu'il recouvrait
// le bord droit de « Payer », puis y est revenu — remonté au-dessus de
// l'action. C'est précisément le moment du parcours où une question se
// pose, et une question sans réponse coûte la réservation.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/features/support/presentation/widgets/support_chat_overlay.dart';

void main() {
  group('le bouton se montre', () {
    test('sur tout le tunnel de réservation', () {
      for (final screen in const [
        AppRouter.busSearchPath,
        AppRouter.departuresPath,
        AppRouter.departureDetailPath,
        AppRouter.passengerInfoPath,
        AppRouter.summaryPath,
        AppRouter.paymentMethodPath,
        AppRouter.paymentMobileMoneyPath,
        AppRouter.paymentCardPath,
        AppRouter.paymentGatewayMomoPath,
      ]) {
        expect(
          SupportChatOverlay.showsOn(screen),
          isTrue,
          reason: '$screen devrait porter le bouton',
        );
      }
    });

    test('sur les écrans du compte', () {
      expect(SupportChatOverlay.showsOn(AppRouter.securityPath), isTrue);
    });
  });

  group('le bouton s’efface', () {
    test('avant la session', () {
      // Sans session, il n'y a pas de configuration d'assistance à lire.
      for (final screen in const [
        AppRouter.welcomePath,
        AppRouter.loginPath,
        AppRouter.otpPath,
        AppRouter.createPinPath,
      ]) {
        expect(SupportChatOverlay.showsOn(screen), isFalse, reason: screen);
      }
    });

    test('sur le plan des sièges, dont le pied est une carte', () {
      // Le siège retenu, le total et « Continuer » tiennent dans une
      // carte haute : remonter le bouton de 84 px le posait encore sur
      // le total, et le remonter davantage l'aurait mis au milieu du
      // plan.
      expect(SupportChatOverlay.showsOn(AppRouter.seatsPath), isFalse);
    });

    test('là où il mènerait à la page qu’on regarde', () {
      expect(SupportChatOverlay.showsOn(AppRouter.supportChatPath), isFalse);
      expect(SupportChatOverlay.showsOn(AppRouter.contactPath), isFalse);
    });

    test('sur les cinq onglets, que la coquille place elle-même', () {
      // Elle seule sait le poser au-dessus de la barre de navigation.
      for (final tab in const [
        '/home',
        '/voyages',
        '/colis',
        '/wallet',
        '/profil',
      ]) {
        expect(SupportChatOverlay.showsOn(tab), isFalse, reason: tab);
      }
    });
  });

  group('la hauteur du bouton', () {
    test('le remonte au-dessus de l’action du tunnel', () {
      // 84 px : la hauteur d'un `KoraButton` et sa marge. En dessous, il
      // recouvrait le bord droit de « Payer ».
      expect(SupportChatOverlay.bottomOn(AppRouter.paymentCardPath), 84);
      expect(SupportChatOverlay.bottomOn(AppRouter.paymentGatewayMomoPath), 84);
    });

    test('le laisse dans le coin ailleurs', () {
      expect(SupportChatOverlay.bottomOn(AppRouter.securityPath), 16);
    });
  });
}
