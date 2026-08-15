import 'package:equatable/equatable.dart';
import 'package:intl/intl.dart';

/// Couche DOMAIN : un montant monétaire.
///
/// **Pourquoi une classe plutôt qu'un simple `int` ?** Parce qu'un montant
/// sans sa devise n'a pas de sens, et surtout parce que le formatage
/// (`12 500 FCFA`) doit être identique partout dans l'app. En passant un
/// [Price] aux widgets plutôt qu'une `String` déjà formatée, on garde une
/// seule et unique implémentation du format — ici, dans [formatted].
///
/// Le montant est stocké en **unité entière** (le franc CFA n'a pas de
/// sous-unité en usage courant), jamais en `double` : les flottants
/// arrondissent mal et n'ont rien à faire dans un calcul de prix.
class Price extends Equatable {
  const Price(this.amount, {this.currency = 'FCFA'});

  final int amount;
  final String currency;

  /// `fr_FR` : séparateur de milliers par espace, comme dans la maquette
  /// (`12 500 FCFA`). Les symboles numériques sont compilés dans `intl`,
  /// aucune initialisation n'est nécessaire (contrairement aux dates, voir
  /// `bootstrap.dart`).
  static final NumberFormat _format = NumberFormat.decimalPattern('fr_FR');

  /// Montant prêt à être affiché : `12 500 FCFA`.
  String get formatted => '${_format.format(amount)} $currency';

  /// Prix total pour [passengers] voyageurs.
  Price operator *(int passengers) =>
      Price(amount * passengers, currency: currency);

  @override
  List<Object?> get props => [amount, currency];
}
