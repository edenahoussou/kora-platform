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
  String get formatted => '${group(amount)} $currency';

  /// Les milliers séparés, sans la devise : `12 500`.
  ///
  /// Pour les champs où le voyageur **saisit** un montant : la devise y
  /// est affichée à côté plutôt que dans le texte modifiable. Passe par
  /// le même format que [formatted], pour qu'un montant saisi et un
  /// montant affiché ne se lisent jamais différemment.
  static String group(int amount) => _format.format(amount);

  /// Prix total pour [passengers] voyageurs.
  Price operator *(int passengers) =>
      Price(amount * passengers, currency: currency);

  @override
  List<Object?> get props => [amount, currency];
}
