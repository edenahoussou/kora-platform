import 'package:equatable/equatable.dart';

/// Couche DOMAIN : ce que le serveur accepte comme paiement, ici et
/// maintenant.
///
/// **C'est le serveur qui décide, pas l'app.** Un provider dont les
/// identifiants ne sont plus valides, un réseau Mobile Money coupé, un
/// moyen hors périmètre : tout ça se voit ici, et l'écran de paiement ne
/// doit proposer que ce que ce catalogue contient. Une liste écrite en dur
/// dans l'app finit toujours par promettre un paiement que le serveur
/// refusera — et le voyageur ne l'apprend qu'après avoir tapé son numéro.
class PaymentCatalog extends Equatable {
  const PaymentCatalog({
    required this.country,
    required this.currency,
    this.methods = const [],
  });

  /// Catalogue vide — celui d'avant le chargement, ou d'un serveur qui n'a
  /// rien à proposer. Aucun écran de paiement ne doit s'ouvrir dessus.
  static const PaymentCatalog empty = PaymentCatalog(country: '', currency: '');

  /// Code pays ISO (`BJ` au MVP).
  final String country;

  /// Devise dans laquelle le passager est débité (`XOF`).
  final String currency;

  final List<PaymentMethod> methods;

  /// Le moyen de paiement portant [code], ou `null` s'il n'est pas
  /// proposé.
  PaymentMethod? methodOf(String code) {
    for (final method in methods) {
      if (method.code == code) return method;
    }
    return null;
  }

  /// Les moyens que l'app a le droit d'afficher.
  ///
  /// Un moyen désactivé n'est pas masqué par erreur : le serveur le
  /// renvoie exprès, avec sa raison ([PaymentMethod.availability]), pour
  /// que l'app puisse le griser plutôt que de le faire disparaître sans
  /// explication.
  List<PaymentMethod> get enabledMethods =>
      methods.where((method) => method.enabled).toList();

  /// Les providers Mobile Money utilisables.
  List<PaymentProvider> get mobileMoneyProviders =>
      methodOf(mobileMoneyCode)?.enabledProviders ?? const [];

  /// Les réseaux Mobile Money acceptés, tous providers confondus, sans
  /// doublon et dans l'ordre où le serveur les annonce.
  ///
  /// Deux providers peuvent servir le même réseau (FedaPay et MTN MoMo
  /// couvrent tous deux `mtn_momo`) : c'est le réseau que choisit le
  /// voyageur, le provider est un détail d'acheminement.
  List<String> get mobileMoneyNetworks {
    final networks = <String>[];
    for (final provider in mobileMoneyProviders) {
      for (final network in provider.networks) {
        if (!networks.contains(network)) networks.add(network);
      }
    }
    return networks;
  }

  /// Le provider à utiliser pour [network], ou `null` si aucun ne le sert.
  ///
  /// Le premier qui l'annonce l'emporte : le serveur range son catalogue
  /// par ordre de préférence, ce n'est pas à l'app de le réordonner.
  PaymentProvider? providerForNetwork(String network) {
    for (final provider in mobileMoneyProviders) {
      if (provider.networks.contains(network)) return provider;
    }
    return null;
  }

  bool get isEmpty => methods.isEmpty;

  /// Code du moyen « Mobile Money » dans le catalogue.
  static const String mobileMoneyCode = 'mobile_money';

  /// Code de la carte bancaire, servie par KKiaPay depuis MEZ-146.
  static const String cardCode = 'card';

  /// Code du Mini Wallet, renvoyé désactivé (`out_of_mvp`) au MVP.
  static const String walletCode = 'wallet';

  @override
  List<Object?> get props => [country, currency, methods];
}

/// Couche DOMAIN : un moyen de paiement (`mobile_money`, `wallet`).
class PaymentMethod extends Equatable {
  const PaymentMethod({
    required this.code,
    required this.label,
    required this.enabled,
    this.availability,
    this.providers = const [],
  });

  final String code;

  /// Libellé affichable, tel que le serveur veut qu'on le nomme.
  final String label;

  final bool enabled;

  /// Renseigné uniquement quand [enabled] est faux : `out_of_mvp` pour un
  /// moyen hors périmètre. C'est ce qui permet de dire *pourquoi* un
  /// paiement n'est pas proposé.
  final String? availability;

  final List<PaymentProvider> providers;

  /// Les providers réellement utilisables (identifiants valides côté
  /// serveur).
  List<PaymentProvider> get enabledProviders =>
      providers.where((provider) => provider.enabled).toList();

  /// Utilisable seulement si le moyen **et** au moins un de ses providers
  /// le sont : un moyen actif sans provider valide ne mène nulle part.
  ///
  /// Le Wallet fait exception : il n'a pas d'encaisseur, c'est le
  /// serveur qui débite le solde. Lui demander un provider le rendait
  /// inerte quoi qu'annonce le catalogue — la tuile restait grise alors
  /// que le paiement était ouvert.
  bool get isUsable =>
      enabled &&
      (code == PaymentCatalog.walletCode || enabledProviders.isNotEmpty);

  @override
  List<Object?> get props => [code, label, enabled, availability, providers];
}

/// Couche DOMAIN : un encaisseur (`fedapay`, `mtn_momo`) et ce qu'il exige.
class PaymentProvider extends Equatable {
  const PaymentProvider({
    required this.code,
    required this.label,
    required this.enabled,
    this.networks = const [],
    this.requires = const [],
    this.interaction = '',
    this.pollingInterval = defaultPollingInterval,
    this.verification = '',
  });

  final String code;
  final String label;
  final bool enabled;

  /// Réseaux Mobile Money que ce provider sait débiter (`mtn_momo`,
  /// `moov_flooz`, `celtiis_cash`).
  final List<String> networks;

  /// Champs que `POST /payments/initiate` exigera (`phone`,
  /// `mobile_money_network`).
  final List<String> requires;

  /// `push` (USSD poussé sur le téléphone) ou `push_or_redirect`.
  final String interaction;

  /// Délai conseillé entre deux appels de vérification.
  ///
  /// Vient du serveur : c'est lui qui sait à quelle cadence son provider
  /// tolère d'être interrogé. Une valeur choisie par l'app finirait par
  /// être trop lente pour le voyageur ou trop agressive pour le provider.
  final Duration pollingInterval;

  /// Toujours `server_verify` : l'app ne conclut jamais un paiement
  /// elle-même, elle appelle `POST /payments/{uuid}/verify`.
  final String verification;

  /// Cadence retenue si le serveur n'en annonce pas.
  static const Duration defaultPollingInterval = Duration(seconds: 10);

  /// Vrai si le paiement peut basculer vers une page web du provider —
  /// l'écran de validation ne raconte alors pas la même histoire qu'un
  /// simple push USSD.
  bool get mayRedirect => interaction == 'push_or_redirect';

  /// Le paiement s'encaisse **dans l'app**, par le widget du provider.
  ///
  /// KKiaPay n'a pas d'API de poussée serveur : `initiate` ne contacte
  /// personne et rend de quoi ouvrir son widget, qui recueille lui-même
  /// le code du payeur. L'écran d'attente ne doit donc pas lui parler de
  /// demande poussée sur son téléphone — il n'y en a pas.
  bool get collectsInApp => interaction == 'sdk_widget';

  /// Les suffixes de pays que le serveur accole aux réseaux.
  ///
  /// `mtn_momo_ci`, `moov_flooz_tg` : le pays est déduit du réseau plutôt
  /// que porté par un champ à part, pour qu'ils ne puissent pas diverger
  /// (voir ADR 0019).
  static const Map<String, String> countrySuffixes = {
    'BJ': '',
    'CI': '_ci',
    'TG': '_tg',
  };

  /// Les **marques** servies, sans le suffixe de pays, dans l'ordre du
  /// serveur et sans doublon.
  ///
  /// Le voyageur choisit MTN, pas « MTN Côte d'Ivoire » : la liste des
  /// opérateurs afficherait sinon trois fois Moov, une fois par pays
  /// desservi. Le pays, lui, vient de l'indicatif qu'il compose.
  List<String> get networkBrands {
    final brands = <String>[];

    for (final network in networks) {
      final brand = _brandOf(network);
      if (!brands.contains(brand)) brands.add(brand);
    }

    return brands;
  }

  /// Le réseau exact à envoyer pour cette marque dans ce pays, ou `null`
  /// si ce provider ne la sert pas là-bas.
  ///
  /// C'est ce code-là que `POST payments/initiate` attend : une marque
  /// seule ne lui suffit pas, et un suffixe qui ne correspond pas au
  /// numéro composé ferait débiter un compte dans le mauvais pays.
  String? networkFor(String brand, String country) {
    final suffix = countrySuffixes[country];
    if (suffix == null) return null;

    final candidate = '$brand$suffix';
    return networks.contains(candidate) ? candidate : null;
  }

  /// Les pays où cette marque est servie, pour dire au voyageur ce qui
  /// l'attend avant qu'il compose son numéro.
  List<String> countriesServing(String brand) => [
    for (final entry in countrySuffixes.entries)
      if (networks.contains('$brand${entry.value}')) entry.key,
  ];

  static String _brandOf(String network) {
    for (final suffix in countrySuffixes.values) {
      if (suffix.isEmpty) continue;
      if (network.endsWith(suffix)) {
        return network.substring(0, network.length - suffix.length);
      }
    }

    return network;
  }

  bool servesNetwork(String network) => networks.contains(network);

  @override
  List<Object?> get props => [
    code,
    label,
    enabled,
    networks,
    requires,
    interaction,
    pollingInterval,
    verification,
  ];
}
