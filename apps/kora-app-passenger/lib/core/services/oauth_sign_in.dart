import 'package:flutter_facebook_auth/flutter_facebook_auth.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../env/app_env.dart';

/// Pourquoi une connexion sociale n'a pas abouti.
enum OAuthFailure {
  /// L'utilisateur a fermé la feuille du fournisseur. Rien à signaler.
  cancelled,

  /// Aucun identifiant client n'est configuré pour ce fournisseur : le
  /// bouton ne peut rien faire tant que le build n'en reçoit pas.
  notConfigured,

  /// Le fournisseur ne remet pas de jeton d'accès à l'application (cas de
  /// TikTok, voir [OAuthSignInImpl.signIn]).
  unsupported,

  /// Le fournisseur a répondu, mais pas par un succès.
  failed,
}

class OAuthSignInException implements Exception {
  const OAuthSignInException(this.reason, [this.message = '']);

  final OAuthFailure reason;
  final String message;

  @override
  String toString() => 'OAuthSignInException($reason, $message)';
}

/// Obtention d'un jeton d'accès auprès de Google, Facebook ou TikTok.
///
/// L'API KORA ne dialogue jamais avec le fournisseur pendant la
/// connexion : c'est l'app qui authentifie l'utilisateur via le SDK
/// natif, puis envoie le **jeton d'accès** à `POST auth/oauth/token`, que
/// le serveur valide avec `Socialite::userFromToken()`
/// (`AuthSocialService::loginWithOAuth`).
///
/// Cette interface tient les SDK à distance du reste de l'app : les
/// écrans et leurs tests n'ont ni Google ni Facebook à installer.
abstract class OAuthSignIn {
  /// Le jeton d'accès du fournisseur, prêt à être échangé.
  ///
  /// Lève [OAuthSignInException] dans tous les autres cas, l'annulation
  /// comprise — un écran ne peut pas distinguer « refusé » de « pas
  /// encore répondu » à partir d'un simple `null`.
  Future<String> signIn(String provider);

  /// Coupe la session locale du fournisseur, pour que le prochain appui
  /// repropose le choix du compte plutôt que de reconnecter le dernier.
  Future<void> signOut();

  /// Faux quand le build n'a pas reçu les identifiants du fournisseur.
  bool isConfigured(String provider);
}

class OAuthSignInImpl implements OAuthSignIn {
  OAuthSignInImpl();

  static const String google = 'google';
  static const String facebook = 'facebook';
  static const String tiktok = 'tiktok';

  /// Les deux portées suffisent : le serveur ne lit que l'identité et
  /// l'adresse e-mail du profil renvoyé.
  static const List<String> _googleScopes = <String>['email', 'profile'];

  bool _googleReady = false;

  @override
  bool isConfigured(String provider) => switch (provider) {
    google => AppEnv.googleServerClientId.isNotEmpty,
    facebook => AppEnv.facebookAppId.isNotEmpty,
    // TikTok ne dépend pas d'un identifiant manquant : voir [signIn].
    _ => false,
  };

  @override
  Future<String> signIn(String provider) async {
    if (provider == tiktok) {
      // Le Login Kit de TikTok ne remet à l'app qu'un **code**
      // d'autorisation, jamais un jeton d'accès : l'échanger réclame le
      // client secret, qui n'a rien à faire dans un APK. Il faudrait que
      // l'API accepte ce code et fasse l'échange elle-même — elle
      // n'expose aujourd'hui que `provider_token`.
      throw const OAuthSignInException(OAuthFailure.unsupported);
    }

    if (!isConfigured(provider)) {
      throw const OAuthSignInException(OAuthFailure.notConfigured);
    }

    return switch (provider) {
      google => _google(),
      facebook => _facebook(),
      _ => throw const OAuthSignInException(OAuthFailure.unsupported),
    };
  }

  Future<String> _google() async {
    // `initialize` n'est à appeler qu'une fois, mais le SDK ne le dit
    // pas : on s'en souvient ici.
    if (!_googleReady) {
      await GoogleSignIn.instance.initialize(
        serverClientId: AppEnv.googleServerClientId,
      );
      _googleReady = true;
    }

    try {
      final account = await GoogleSignIn.instance.authenticate(
        scopeHint: _googleScopes,
      );

      // Depuis la version 7 du paquet, l'authentification ne donne qu'un
      // `idToken` : le jeton d'accès s'obtient séparément, et c'est lui
      // que `Socialite::userFromToken()` attend.
      final authorization = await account.authorizationClient.authorizeScopes(
        _googleScopes,
      );

      return authorization.accessToken;
    } on GoogleSignInException catch (error) {
      throw OAuthSignInException(
        error.code == GoogleSignInExceptionCode.canceled
            ? OAuthFailure.cancelled
            : OAuthFailure.failed,
        error.description ?? '',
      );
    }
  }

  Future<String> _facebook() async {
    final result = await FacebookAuth.instance.login(
      permissions: const <String>['email', 'public_profile'],
    );

    return switch (result.status) {
      LoginStatus.success when result.accessToken != null =>
        result.accessToken!.tokenString,
      LoginStatus.cancelled => throw const OAuthSignInException(
        OAuthFailure.cancelled,
      ),
      _ => throw OAuthSignInException(
        OAuthFailure.failed,
        result.message ?? '',
      ),
    };
  }

  @override
  Future<void> signOut() async {
    // Chaque SDK garde sa propre session : ne pas les couper laisserait
    // le prochain « Continuer avec… » reconnecter le compte précédent
    // sans rien demander.
    if (isConfigured(google) && _googleReady) {
      await GoogleSignIn.instance.signOut();
    }
    if (isConfigured(facebook)) {
      await FacebookAuth.instance.logOut();
    }
  }
}
