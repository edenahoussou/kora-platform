import '../../../../core/network/api_json.dart';
import '../../domain/entities/payment_catalog.dart';

/// Couche DATA : le catalogue tel que le renvoie
/// `GET /api/v1/payments/methods`.
///
/// ```json
/// {
///   "data": {
///     "country": "BJ",
///     "currency": "XOF",
///     "methods": [
///       {
///         "code": "mobile_money",
///         "label": "Mobile Money",
///         "enabled": true,
///         "providers": [ … ]
///       }
///     ]
///   }
/// }
/// ```
///
/// Contrairement aux autres réponses de l'API, `data` est ici un **objet**
/// et non un tableau — d'où la lecture par [ApiJson.map].
class PaymentCatalogModel {
  const PaymentCatalogModel({
    required this.country,
    required this.currency,
    required this.methods,
  });

  factory PaymentCatalogModel.fromJson(Map<String, dynamic> json) {
    return PaymentCatalogModel(
      country: ApiJson.string(json['country']),
      currency: ApiJson.string(json['currency']),
      methods: [
        for (final method in ApiJson.list(json['methods']))
          PaymentMethodModel.fromJson(method),
      ],
    );
  }

  final String country;
  final String currency;
  final List<PaymentMethodModel> methods;

  PaymentCatalog toEntity() => PaymentCatalog(
    country: country,
    currency: currency,
    methods: [for (final method in methods) method.toEntity()],
  );
}

/// Couche DATA : un moyen de paiement du catalogue.
class PaymentMethodModel {
  const PaymentMethodModel({
    required this.code,
    required this.label,
    required this.enabled,
    required this.availability,
    required this.providers,
  });

  factory PaymentMethodModel.fromJson(Map<String, dynamic> json) {
    return PaymentMethodModel(
      code: ApiJson.string(json['code']),
      label: ApiJson.string(json['label']),
      // Défaut prudent : un moyen dont on ne sait pas s'il est actif est
      // traité comme inactif. L'inverse proposerait un paiement que le
      // serveur refusera.
      enabled: json['enabled'] == true,
      availability: ApiJson.stringOrNull(json['availability']),
      providers: [
        for (final provider in ApiJson.list(json['providers']))
          PaymentProviderModel.fromJson(provider),
      ],
    );
  }

  final String code;
  final String label;
  final bool enabled;
  final String? availability;
  final List<PaymentProviderModel> providers;

  PaymentMethod toEntity() => PaymentMethod(
    code: code,
    label: label,
    enabled: enabled,
    availability: availability,
    providers: [for (final provider in providers) provider.toEntity()],
  );
}

/// Couche DATA : un provider et ses contraintes d'appel.
class PaymentProviderModel {
  const PaymentProviderModel({
    required this.code,
    required this.label,
    required this.enabled,
    required this.networks,
    required this.requires,
    required this.interaction,
    required this.pollingIntervalSeconds,
    required this.verification,
  });

  factory PaymentProviderModel.fromJson(Map<String, dynamic> json) {
    return PaymentProviderModel(
      code: ApiJson.string(json['code']),
      label: ApiJson.string(json['label']),
      enabled: json['enabled'] == true,
      networks: ApiJson.strings(json['networks']),
      requires: ApiJson.strings(json['requires']),
      interaction: ApiJson.string(json['interaction']),
      pollingIntervalSeconds: ApiJson.intOrNull(
        json['polling_interval_seconds'],
      ),
      verification: ApiJson.string(json['verification']),
    );
  }

  final String code;
  final String label;
  final bool enabled;
  final List<String> networks;
  final List<String> requires;
  final String interaction;

  /// `null` quand le serveur n'annonce pas de cadence — voir
  /// [PaymentProvider.defaultPollingInterval].
  final int? pollingIntervalSeconds;

  final String verification;

  PaymentProvider toEntity() {
    final seconds = pollingIntervalSeconds;

    return PaymentProvider(
      code: code,
      label: label,
      enabled: enabled,
      networks: networks,
      requires: requires,
      interaction: interaction,
      // Une cadence nulle ou négative relancerait la vérification en
      // boucle serrée : on retombe sur la valeur conseillée.
      pollingInterval: seconds == null || seconds <= 0
          ? PaymentProvider.defaultPollingInterval
          : Duration(seconds: seconds),
      verification: verification,
    );
  }
}
