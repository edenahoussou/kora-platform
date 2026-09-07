import '../../../../core/network/api_json.dart';
import '../../domain/entities/company_account.dart';
import '../../domain/entities/price.dart';

/// Couche DATA : un élément de `GET /company-accounts`.
class CompanyAccountModel {
  const CompanyAccountModel._(this._json);

  factory CompanyAccountModel.fromJson(Map<String, dynamic> json) =>
      CompanyAccountModel._(json);

  final Map<String, dynamic> _json;

  CompanyAccount toEntity() {
    return CompanyAccount(
      id: ApiJson.string(_json['uuid']),
      name: ApiJson.string(_json['name']),
      reference: ApiJson.stringOrNull(_json['reference']),
      // Nuls pour qui n'a pas la permission `company-credit.view` : le
      // serveur les masque au voyageur ordinaire, ce n'est pas une erreur.
      monthlyLimit: _price(_json['monthly_credit_limit']),
      availableCredit: _price(_json['available_credit']),
    );
  }

  static Price? _price(Object? raw) {
    final amount = ApiJson.intOrNull(raw);
    return amount == null ? null : Price(amount);
  }
}
