import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/support_chat_config.dart';

/// Chat support tawk.to plein écran. Route : `/profil/support-chat`
/// (reçoit un [SupportChatConfig] en `extra`).
///
/// Le widget est chargé via une page HTML locale (script d'embed officiel
/// `embed.tawk.to/{propertyId}/{widgetId}`) afin d'identifier le convoyeur
/// automatiquement (`Tawk_API.visitor` + hash sécurisé). Le script étant une
/// sous-ressource, une panne réseau n'émet pas d'erreur WebView : un délai
/// maximum ([_loadTimeout]) sans signal « loaded » bascule sur l'état
/// dégradé avec bouton Réessayer — jamais de page blanche ni de crash.
class SupportChatPage extends StatefulWidget {
  const SupportChatPage({required this.config, super.key});

  final SupportChatConfig config;

  @override
  State<SupportChatPage> createState() => _SupportChatPageState();
}

enum _LoadState { loading, loaded, failed }

class _SupportChatPageState extends State<SupportChatPage> {
  static const Duration _loadTimeout = Duration(seconds: 25);
  static final RegExp _safeId = RegExp(r'^[A-Za-z0-9_-]+$');

  late final WebViewController _controller;
  _LoadState _loadState = _LoadState.loading;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white)
      ..addJavaScriptChannel(
        'KoraChat',
        onMessageReceived: (JavaScriptMessage message) {
          if (message.message == 'loaded') {
            _timer?.cancel();
            if (mounted) setState(() => _loadState = _LoadState.loaded);
          } else if (message.message == 'minimized' && mounted) {
            Navigator.of(context).maybePop();
          }
        },
      )
      ..setNavigationDelegate(NavigationDelegate(
        onWebResourceError: (WebResourceError error) {
          if (error.isForMainFrame ?? true) _fail();
        },
        // Reste dans l'écosystème tawk.to (about:blank + tawk.to).
        onNavigationRequest: (NavigationRequest request) {
          final Uri? uri = Uri.tryParse(request.url);
          final bool allowed = uri == null ||
              uri.scheme == 'about' ||
              uri.scheme == 'data' ||
              uri.host.endsWith('tawk.to');
          return allowed
              ? NavigationDecision.navigate
              : NavigationDecision.prevent;
        },
      ));
    _start();
  }

  void _start() {
    final String? html = _buildHtml(widget.config);
    if (html == null) {
      _fail();
      return;
    }
    setState(() => _loadState = _LoadState.loading);
    _timer?.cancel();
    _timer = Timer(_loadTimeout, _fail);
    unawaited(_controller.loadHtmlString(html, baseUrl: 'https://tawk.to'));
  }

  void _fail() {
    _timer?.cancel();
    if (mounted) setState(() => _loadState = _LoadState.failed);
  }

  /// Échappe une valeur pour l'insérer dans un littéral JS d'une page HTML.
  static String _js(String? value) =>
      jsonEncode(value ?? '').replaceAll('<', r'<');

  String? _buildHtml(SupportChatConfig c) {
    if (!_safeId.hasMatch(c.propertyId) || !_safeId.hasMatch(c.widgetId)) {
      return null;
    }
    final bool identified =
        (c.visitorEmail ?? '').isNotEmpty && (c.secureHash ?? '').isNotEmpty;
    final String visitor = identified
        ? 'Tawk_API.visitor = {name: ${_js(c.visitorName)}, '
            'email: ${_js(c.visitorEmail)}, hash: ${_js(c.secureHash)}};'
        : '';

    return '''
<!DOCTYPE html>
<html><head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>html,body{margin:0;height:100%;background:#fff}</style>
</head><body>
<script>
var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
$visitor
Tawk_API.onLoad = function () {
  KoraChat.postMessage('loaded');
  Tawk_API.maximize();
};
Tawk_API.onChatMinimized = function () { KoraChat.postMessage('minimized'); };
(function () {
  var s1 = document.createElement('script'),
      s0 = document.getElementsByTagName('script')[0];
  s1.async = true;
  s1.src = 'https://embed.tawk.to/${c.propertyId}/${c.widgetId}';
  s1.charset = 'UTF-8';
  s1.setAttribute('crossorigin', '*');
  s0.parentNode.insertBefore(s1, s0);
})();
</script>
</body></html>''';
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.koraColors.background,
      appBar: const ScreenHeader(eyebrow: 'Aide', title: 'Chat support'),
      body: SafeArea(
        child: Stack(
          children: <Widget>[
            Positioned.fill(child: WebViewWidget(controller: _controller)),
            if (_loadState == _LoadState.loading)
              Positioned.fill(
                child: ColoredBox(
                  color: context.koraColors.background,
                  child: const Center(child: CircularProgressIndicator()),
                ),
              ),
            if (_loadState == _LoadState.failed)
              Positioned.fill(
                child: ColoredBox(
                  color: context.koraColors.background,
                  child: AppStatePlaceholder.error(
                    message: 'Le chat support est indisponible pour le moment. '
                        'Vérifiez votre connexion et réessayez.',
                    actionLabel: 'Réessayer',
                    onAction: _start,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
