import 'package:equatable/equatable.dart';

/// Couche DOMAIN : ce qu'il faut pour ouvrir le widget KKiaPay.
///
/// KKiaPay n'a pas d'API de poussée serveur : son modèle est un widget
/// client qui encaisse, puis rend un identifiant de transaction que le
/// serveur vérifie. `POST payments/initiate` ne lance donc aucun appel
/// chez le provider — il rend cette configuration, que l'app passe
/// telle quelle au SDK.
///
/// **Rien ici ne décide d'un paiement.** Le widget peut annoncer un
/// succès ; seule la vérification serveur fait foi (voir ADR 0019).
class KkiapayWidget extends Equatable {
  const KkiapayWidget({
    required this.publicKey,
    required this.amount,
    required this.partnerId,
    this.sandbox = true,
    this.methods = const [],
    this.countries = const [],
    this.phone,
    this.reason,
  });

  /// La clé **publique**, délivrée par le serveur et non par le dépôt :
  /// elle diffère d'un environnement à l'autre, et une clé compilée dans
  /// une version publiée ne se change pas sans republier l'app. La clé
  /// secrète, elle, ne quitte jamais le serveur — c'est elle qui signe
  /// la vérification.
  final String publicKey;

  /// Montant en francs CFA, arrêté par le serveur.
  ///
  /// L'app ne le recalcule pas : le serveur a déjà tranché places,
  /// remises et arrondis, et recalculer ici désynchroniserait la somme
  /// débitée de la somme enregistrée.
  final int amount;

  /// `partnerId` : l'identifiant du paiement côté KORA.
  ///
  /// C'est lui qui rattache la transaction KKiaPay à la réservation,
  /// y compris quand la confirmation arrive par le webhook plutôt que
  /// par l'app.
  final String partnerId;

  /// Bac à sable : aucun argent ne bouge. Le serveur le décide, selon
  /// les clés qu'il a chargées.
  final bool sandbox;

  /// `momo`, `card`, `wallet` — ce que le widget accepte.
  final List<String> methods;

  /// Les pays desservis par les clés chargées (`BJ`, `TG`, `CI`).
  final List<String> countries;

  /// Numéro pré-rempli, quand le passager l'a déjà saisi.
  final String? phone;

  /// Le libellé que le payeur verra sur son relevé.
  final String? reason;

  /// Vrai quand la configuration suffit à ouvrir le widget.
  ///
  /// Un widget sans clé ou sans montant ne mène nulle part : mieux vaut
  /// le dire que d'ouvrir un écran qui échouera au premier geste.
  bool get isUsable => publicKey.isNotEmpty && amount > 0;

  @override
  List<Object?> get props => [
    publicKey,
    amount,
    partnerId,
    sandbox,
    methods,
    countries,
    phone,
    reason,
  ];
}

/// Restreint un widget à un moyen précis.
///
/// Le serveur autorise `momo`, `card` et `wallet` d'un même paiement : le
/// widget sait tout encaisser. Mais un voyageur arrivé par « Carte
/// bancaire » a déjà écarté le Mobile Money, et le lui reproposer
/// annulerait son choix.
extension KkiapayWidgetOf on KkiapayWidget {
  KkiapayWidget restrictedToCard() => _restrictedTo(const ['card']);

  /// Le pendant pour le Mobile Money.
  ///
  /// Sans lui, la feuille ouverte depuis « KKiaPay · Mobile Money »
  /// proposait aussi « Cartes Bancaires » : le voyageur repartait par où
  /// l'écran « Carte bancaire » devait le faire passer, et l'écran
  /// d'attente annonçait un règlement Mobile Money qui n'en était pas un.
  KkiapayWidget restrictedToMomo() => _restrictedTo(const ['momo']);

  KkiapayWidget _restrictedTo(List<String> only) => KkiapayWidget(
    publicKey: publicKey,
    amount: amount,
    partnerId: partnerId,
    sandbox: sandbox,
    methods: only,
    countries: countries,
    phone: phone,
    reason: reason,
  );
}
