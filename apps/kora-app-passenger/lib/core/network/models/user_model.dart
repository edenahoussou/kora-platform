import 'package:freezed_annotation/freezed_annotation.dart';

part 'user_model.freezed.dart';
part 'user_model.g.dart';

/// **Exemple** de modèle utilisant `freezed` + `json_serializable`,
/// pour montrer le pattern à suivre quand tu créeras tes vrais modèles
/// API (`UserModel`, `TripModel`, `WalletTransactionModel`...).
///
/// Ce fichier génère automatiquement, via `build_runner` :
/// - `user_model.freezed.dart` : `copyWith`, `==`, `hashCode`, `toString`
/// - `user_model.g.dart` : `fromJson` / `toJson`
///
/// **Important** : ces deux fichiers générés ne sont PAS inclus dans le
/// zip (ils sont énormes et se régénèrent en une commande). Avant de
/// lancer l'app, exécute :
/// ```bash
/// dart run build_runner build --delete-conflicting-outputs
/// ```
/// Voir GUIDE.md, section "Génération de code (freezed / json_serializable)"
/// pour le détail.
///
/// Exemple d'utilisation dans un data source :
/// ```dart
/// final response = await dio.get(ApiConstants.profile);
/// final user = UserModel.fromJson(response.data);
/// ```
@freezed
abstract class UserModel with _$UserModel {
  const factory UserModel({
    required String id,
    required String fullName,
    required String email,
    String? phoneNumber,
    String? avatarUrl,
    @Default(false) bool isVerified,
  }) = _UserModel;

  factory UserModel.fromJson(Map<String, dynamic> json) =>
      _$UserModelFromJson(json);
}
