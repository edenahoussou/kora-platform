import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';

/// La configuration Firebase de l'app, telle que le serveur la livre.
///
/// **Elle n'est pas dans le dépôt.** Pas de `google-services.json`, pas
/// d'identifiant de projet figé dans le binaire : l'app demande ces
/// valeurs à `auth/passenger/firebase-configuration` et les passe à
/// `Firebase.initializeApp`. Changer de projet Firebase devient alors un
/// réglage OPS, pas une nouvelle version de l'application.
///
/// Ces valeurs sont **publiques** — on les retrouve dans n'importe quelle
/// app Firebase décompilée. Le secret, lui, est la clé du compte de
/// service, et elle ne quitte jamais le serveur : c'est lui qui envoie
/// les notifications, l'app ne fait que les recevoir.
class FirebaseConfiguration {
  const FirebaseConfiguration({
    required this.apiKey,
    required this.projectId,
    required this.messagingSenderId,
    required this.appId,
  });

  /// Ce que répond le serveur quand l'intégration n'est pas configurée
  /// côté OPS : `{enabled: false}`, sans aucune autre clé.
  static const FirebaseConfiguration disabled = FirebaseConfiguration(
    apiKey: '',
    projectId: '',
    messagingSenderId: '',
    appId: '',
  );

  final String apiKey;
  final String projectId;
  final String messagingSenderId;
  final String appId;

  /// Vrai quand les quatre valeurs sont là. Une configuration incomplète
  /// ferait échouer `Firebase.initializeApp` avec une erreur native
  /// illisible : mieux vaut la traiter comme absente.
  bool get isUsable =>
      apiKey.isNotEmpty &&
      projectId.isNotEmpty &&
      messagingSenderId.isNotEmpty &&
      appId.isNotEmpty;

  FirebaseOptions toOptions() => FirebaseOptions(
    apiKey: apiKey,
    appId: appId,
    messagingSenderId: messagingSenderId,
    projectId: projectId,
  );

  Map<String, dynamic> toJson() => {
    'api_key': apiKey,
    'project_id': projectId,
    'messaging_sender_id': messagingSenderId,
    'app_id': appId,
  };

  String encode() => jsonEncode(toJson());

  /// Lit la réponse du serveur, ou le cache : les deux portent les mêmes
  /// clés, ce qui évite un second format à maintenir.
  ///
  /// `enabled: false` — ou n'importe quelle valeur manquante — rend
  /// [disabled], que [isUsable] écarte ensuite.
  static FirebaseConfiguration fromJson(Map<String, dynamic> json) {
    if (json['enabled'] == false) return disabled;

    String read(String key) {
      final value = json[key];
      return value is String ? value : '';
    }

    return FirebaseConfiguration(
      apiKey: read('api_key'),
      projectId: read('project_id'),
      messagingSenderId: read('messaging_sender_id'),
      appId: read('app_id'),
    );
  }

  /// Relit une configuration mise en cache. Un cache illisible — version
  /// précédente, écriture interrompue — vaut absence de configuration,
  /// jamais une exception au réveil de l'isolat de fond.
  static FirebaseConfiguration? decode(String? raw) {
    if (raw == null || raw.isEmpty) return null;

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;

      return fromJson(Map<String, dynamic>.from(decoded));
    } on FormatException {
      return null;
    }
  }
}
