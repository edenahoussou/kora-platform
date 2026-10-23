// Ce que l'app comprend des réponses du SDK KKiaPay.
//
// Le SDK ne documente pas la liste close de ses statuts, les nomme
// tantôt `name` tantôt `status`, et émet des états intermédiaires entre
// l'ouverture de la feuille et le paiement. Mal lire l'un d'eux
// refermerait la feuille sous les doigts du payeur, ou prendrait une
// annulation pour un encaissement.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/payment/presentation/widgets/kkiapay_checkout.dart';

void main() {
  group('la réponse du widget', () {
    test('un statut non terminal ne referme rien', () {
      // Ouverture, chargement, attente du code : la feuille doit rester.
      for (final status in ['INIT', 'PENDING_PAYMENT', 'LOADING', 'WHATEVER']) {
        expect(
          kkiapayResultOf({'status': status}),
          isNull,
          reason: '$status n’est pas une issue',
        );
      }
    });

    test('une réponse sans statut ne referme rien non plus', () {
      expect(kkiapayResultOf(const {}), isNull);
      expect(kkiapayResultOf(const {'status': ''}), isNull);
    });

    test('un succès rend l’identifiant de transaction', () {
      final result = kkiapayResultOf({
        'status': 'PAYMENT_SUCCESS',
        'transactionId': 'txn-123',
      });

      expect(result?.outcome, KkiapayOutcome.succeeded);
      expect(result?.transactionId, 'txn-123');
    });

    test('l’identifiant se lit aussi sous `data`', () {
      // Le SDK le range à deux endroits selon le chemin emprunté.
      final result = kkiapayResultOf({
        'status': 'SUCCESS',
        'data': {'transactionId': 'txn-456'},
      });

      expect(result?.transactionId, 'txn-456');
    });

    test('`name` vaut `status`', () {
      expect(
        kkiapayResultOf({'name': 'PAYMENT_CANCELLED'})?.outcome,
        KkiapayOutcome.cancelled,
      );
    });

    test('une annulation n’est pas un échec', () {
      // Le voyageur a fermé la feuille : rien à lui dire, et surtout pas
      // un message d'erreur.
      expect(
        kkiapayResultOf({'status': 'PAYMENT_CANCELLED'})?.outcome,
        KkiapayOutcome.cancelled,
      );
    });

    test('une annulation l’emporte sur le mot « payment »', () {
      // `PAYMENT_CANCELLED` contient `PAYMENT`. Chercher le succès en
      // premier l'aurait pris pour un encaissement — et l'app aurait
      // envoyé au serveur un identifiant qui n'existe pas.
      final result = kkiapayResultOf({
        'status': 'PAYMENT_CANCELLED',
        'transactionId': 'txn-789',
      });

      expect(result?.outcome, KkiapayOutcome.cancelled);
      expect(result?.transactionId, isNull);
    });

    test('un refus se lit sous plusieurs mots', () {
      for (final status in ['FAILED', 'PAYMENT_ERROR', 'REJECTED']) {
        expect(
          kkiapayResultOf({'status': status})?.outcome,
          KkiapayOutcome.failed,
          reason: '$status est un refus',
        );
      }
    });

    test('un succès sans identifiant reste un succès', () {
      // Le serveur a d'autres moyens de conclure — le webhook porte le
      // `partnerId`. Mieux vaut un succès sans identifiant qu'un
      // paiement traité comme un échec alors qu'il a été encaissé.
      final result = kkiapayResultOf({'status': 'SUCCESS'});

      expect(result?.outcome, KkiapayOutcome.succeeded);
      expect(result?.transactionId, isNull);
    });
  });
}
