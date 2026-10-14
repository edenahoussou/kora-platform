import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../../../core/env/app_env.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/support_chat_configuration.dart';
import 'support_chat_skin.dart';

/// La conversation avec un agent KORA — écran `support-chat`.
///
/// tawk.to ne publie pas de SDK mobile : son « direct chat link » est une
/// page web faite pour être affichée telle quelle. L'écran est donc une
/// WebView, et tout ce que l'application y ajoute est l'identité du
/// passager, injectée au chargement.
class SupportChatPage extends StatefulWidget {
  const SupportChatPage({
    required this.configuration,
    super.key,
    this.visitorName,
    this.visitorEmail,
  });

  final SupportChatConfiguration configuration;

  /// L'identité du passager connecté, transmise à l'agent pour qu'il
  /// n'ait pas à la redemander.
  final String? visitorName;
  final String? visitorEmail;

  @override
  State<SupportChatPage> createState() => _SupportChatPageState();
}

/// Le nom du canal par lequel la page rend compte de ce qu'elle a fait.
const _channel = 'KoraSupport';

class _SupportChatPageState extends State<SupportChatPage> {
  late final WebViewController _controller;
  var _loading = true;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageFinished: (_) {
            _paintSkin();
            _identifyVisitor();
            if (mounted) setState(() => _loading = false);
          },
          // Une page qui ne charge pas laisse l'écran tel quel plutôt que
          // de tourner indéfiniment : le voyageur peut repartir, et les
          // autres canaux de contact l'attendent derrière.
          onWebResourceError: (_) {
            if (mounted) setState(() => _loading = false);
          },
        ),
      )
      ..addJavaScriptChannel(
        _channel,
        onMessageReceived: (message) => _log(message.message),
      )
      ..loadRequest(Uri.parse(widget.configuration.directChatUrl));

    // L'adresse est publique — elle s'ouvre dans n'importe quel
    // navigateur —, et la voir en clair évite de deviner quelle
    // configuration OPS a réellement rendue.
    _log('conversation ouverte sur ${widget.configuration.directChatUrl}');
  }

  /// Rend la page aux couleurs de KORA.
  ///
  /// Purement cosmétique : si le script échoue, la conversation s'ouvre
  /// quand même, simplement avec l'apparence d'origine de tawk.to.
  Future<void> _paintSkin() async {
    try {
      await _controller.runJavaScript(supportChatSkinScript);
    } on Exception {
      // Voir `support_chat_skin.dart` : l'habillage est un confort, pas
      // une condition pour écrire à l'assistance.
    }
  }

  /// Dit à tawk.to qui est en face.
  ///
  /// L'empreinte vient du serveur, qui seul détient la clé d'API : c'est
  /// elle qui empêche un visiteur de se faire passer pour un autre. Sans
  /// elle, le chat marche quand même, mais l'identité n'est pas vérifiée
  /// — et tawk.to refuse alors les attributs plutôt que de les croire.
  ///
  /// **Le script attend son tour.** `onPageFinished` arrive quand le
  /// document est chargé, pas quand le widget est prêt : `Tawk_API`
  /// n'existe pas encore à cet instant, et poser les attributs tout de
  /// suite revient à ne rien poser du tout. D'où la vérification répétée,
  /// jusqu'à dix secondes — au-delà, c'est que le widget n'arrivera pas.
  Future<void> _identifyVisitor() async {
    final hash = widget.configuration.secureHash;
    final name = widget.visitorName;
    final email = widget.visitorEmail;

    if (name == null || email == null || name.isEmpty || email.isEmpty) {
      _log('identité absente, rien à transmettre');
      return;
    }

    final attributes = <String, String>{
      'name': name,
      'email': email,
      if (hash != null && hash.isNotEmpty) 'hash': hash,
    };

    // `jsonEncode` plutôt qu'une interpolation : une apostrophe dans un
    // nom — « N'Diaye » — casserait le script, et un nom venu du serveur
    // n'a pas à pouvoir en exécuter.
    final script =
        """
(function () {
  var attempts = 0;
  function report(outcome) {
    if (window.$_channel) { window.$_channel.postMessage(outcome); }
  }
  function apply() {
    if (!window.Tawk_API || !window.Tawk_API.setAttributes) { return false; }
    window.Tawk_API.setAttributes(${jsonEncode(attributes)}, function (error) {
      report(error ? 'refusé : ' + error : 'transmise');
    });
    return true;
  }
  if (apply()) { return; }
  var timer = setInterval(function () {
    if (apply() || ++attempts > 40) {
      clearInterval(timer);
      if (attempts > 40) { report('widget absent après 10 s'); }
    }
  }, 250);
})();
""";

    try {
      await _controller.runJavaScript(script);
    } on Exception {
      // La page a changé sous nos pieds : la conversation reste ouverte,
      // et l'agent demandera le nom lui-même.
      _log('injection impossible');
    }
  }

  /// Journalise l'issue de la transmission d'identité.
  ///
  /// Rien ne se voit à l'écran : un voyageur n'a pas à savoir que son nom
  /// n'a pas pu être transmis, il écrira son message quand même. Mais
  /// sans cette trace, diagnostiquer « l'agent ne voit pas qui je suis »
  /// revient à deviner.
  void _log(String outcome) {
    if (AppEnv.current.enableNetworkLogs) {
      debugPrint('[support] identité : $outcome');
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18),
              child: ScreenHeader(
                eyebrow: l10n.supportChatEyebrow,
                title: l10n.supportChatTitle,
              ),
            ),
            Expanded(
              child: Stack(
                children: [
                  WebViewWidget(controller: _controller),
                  if (_loading)
                    ColoredBox(
                      color: context.koraColors.background,
                      child: const Center(child: CircularProgressIndicator()),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
