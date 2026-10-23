import 'dart:async';

import 'package:flutter/material.dart';
import 'package:kkiapay_flutter_sdk/kkiapay_flutter_sdk.dart';

import '../../domain/entities/kkiapay_widget.dart';

/// Ce que la feuille KKiaPay a donné.
enum KkiapayOutcome {
  /// Le payeur est allé au bout. **Cela ne vaut pas paiement** : seul le
  /// serveur conclut, en interrogeant KKiaPay avec son identifiant.
  succeeded,

  /// Fermée sans payer. Ce n'est pas une erreur : il n'y a rien à dire
  /// au voyageur qu'il ne sache déjà.
  cancelled,

  /// Refusée par l'opérateur, ou cassée en route.
  failed,
}

/// Le verdict de la feuille, et l'identifiant qu'elle a rendu.
@immutable
class KkiapayResult {
  const KkiapayResult(this.outcome, {this.transactionId});

  final KkiapayOutcome outcome;

  /// `transactionId` du SDK, renseigné sur un succès. C'est la seule
  /// chose que l'app transmette au serveur — le reste de la réponse ne
  /// l'intéresse pas.
  final String? transactionId;
}

/// Ouvre la feuille de paiement KKiaPay et attend son verdict.
///
/// Le SDK n'offre pas de `Future` : il pousse une route et rappelle un
/// callback. On referme ici cette route et on rend un résultat, pour que
/// l'écran appelant lise le paiement comme n'importe quel autre appel.
///
/// [name] et [email] ne servent qu'au confort du payeur — le widget les
/// pré-remplit. Ils ne décident de rien.
Future<KkiapayResult> openKkiapayCheckout(
  BuildContext context, {
  required KkiapayWidget widget,
  String? name,
  String? email,
}) async {
  final completer = Completer<KkiapayResult>();

  void settle(BuildContext sheet, KkiapayResult result) {
    // On referme la route du SDK, pas la nôtre : son contexte est celui
    // qu'il a poussé.
    Navigator.of(sheet).pop();
    if (!completer.isCompleted) completer.complete(result);
  }

  final checkout = KKiaPay(
    amount: widget.amount,
    // Ce que le payeur lira sur son relevé.
    reason: widget.reason ?? 'KORA BUS',
    phone: widget.phone,
    name: name,
    email: email,
    // `partnerId` rattache la transaction au paiement KORA, y compris
    // quand la confirmation arrive par le webhook plutôt que par l'app.
    partnerId: widget.partnerId,
    apikey: widget.publicKey,
    sandbox: widget.sandbox,
    theme: _koraNavy,
    countries: widget.countries.isEmpty ? null : widget.countries,
    paymentMethods: widget.methods.isEmpty ? null : widget.methods,
    callback: (response, sheet) {
      final result = kkiapayResultOf(response);
      // Statut non terminal — `INIT`, `PENDING`, un événement interne du
      // SDK : on ne referme pas, sinon la feuille disparaîtrait avant
      // que le payeur ait composé son code.
      if (result == null) return;

      settle(sheet, result);
    },
  );

  // Poussée sur le navigateur courant : la feuille occupe l'écran, et le
  // retour système la referme comme n'importe quelle page.
  final route = MaterialPageRoute<void>(builder: (_) => checkout);
  unawaited(
    Navigator.of(context).push(route).then((_) {
      // Refermée par le bouton retour, sans passer par le callback.
      if (!completer.isCompleted) {
        completer.complete(const KkiapayResult(KkiapayOutcome.cancelled));
      }
    }),
  );

  return completer.future;
}

/// Le marine de KORA, pour que la feuille ne détonne pas.
const String _koraNavy = '#001D4E';

/// Lit le verdict d'une réponse du SDK, ou `null` si elle n'en porte pas.
///
/// **Lecture volontairement souple.** Le SDK ne documente pas la liste
/// close de ses statuts, les nomme tantôt `name` tantôt `status`, et
/// émet des états intermédiaires entre l'ouverture et le paiement. On ne
/// reconnaît donc que les trois issues terminales, et on ignore tout le
/// reste : refermer la feuille sur un statut mal interprété la ferait
/// disparaître sous les doigts du payeur.
///
/// L'annulation et l'échec sont examinés **avant** le succès : un
/// « payment cancelled » contient le mot `payment`, et la recherche
/// inverse l'aurait pris pour une réussite.
@visibleForTesting
KkiapayResult? kkiapayResultOf(Map<String, dynamic> response) {
  final status = (response['name'] ?? response['status'])
      ?.toString()
      .toUpperCase();
  if (status == null || status.isEmpty) return null;

  if (status.contains('CANCEL')) {
    return const KkiapayResult(KkiapayOutcome.cancelled);
  }

  if (status.contains('FAIL') ||
      status.contains('ERROR') ||
      status.contains('REJECT')) {
    return const KkiapayResult(KkiapayOutcome.failed);
  }

  if (status.contains('SUCCESS') ||
      status.contains('PAID') ||
      status.contains('COMPLETE')) {
    return KkiapayResult(
      KkiapayOutcome.succeeded,
      transactionId: _transactionIdOf(response),
    );
  }

  return null;
}

/// L'identifiant de transaction, au premier des deux endroits où le SDK
/// le range.
String? _transactionIdOf(Map<String, dynamic> response) {
  final direct = response['transactionId'];
  if (direct != null && direct.toString().isNotEmpty) {
    return direct.toString();
  }

  final data = response['data'];
  if (data is Map) {
    final nested = data['transactionId'];
    if (nested != null && nested.toString().isNotEmpty) {
      return nested.toString();
    }
  }

  return null;
}
