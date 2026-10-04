import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/phone_country.dart';
import '../bloc/auth_flow_bloc.dart';

/// Vérification du code envoyé par e-mail — écran `otp` de la maquette :
/// six cases, un délai de validité, et de quoi redemander un code.
///
/// Le code est vérifié par l'API via [AuthFlowBloc]. Le même écran sert
/// les quatre motifs d'OTP (inscription, appareil inconnu, PIN oublié,
/// déverrouillage) : c'est le parcours qui sait ce qui suit.
///
/// Le décompte part de l'échéance **renvoyée par le serveur**, pas d'une
/// durée fixe : un code demandé il y a dix minutes n'en a plus quinze.
class OtpPage extends StatefulWidget {
  const OtpPage({super.key, this.identifier, this.onVerified, this.validity});

  /// Adresse à laquelle le code a été envoyé — affichée masquée.
  ///
  /// Facultative : le parcours la connaît déjà. Utile aux tests, qui
  /// montent l'écran seul.
  /// Le compte visé : un téléphone au format international, ou une
  /// adresse e-mail à l'inscription.
  final String? identifier;

  final VoidCallback? onVerified;

  /// Force la durée du décompte. Sert aux tests ; en usage réel c'est
  /// l'échéance du défi qui fait foi.
  final Duration? validity;

  @override
  State<OtpPage> createState() => _OtpPageState();
}

class _OtpPageState extends State<OtpPage> {
  static const int _length = 6;

  final _controllers = List.generate(_length, (_) => TextEditingController());
  final _focusNodes = List.generate(_length, (_) => FocusNode());

  Timer? _ticker;
  Duration _remaining = Duration.zero;
  String? _error;

  @override
  void initState() {
    super.initState();
    _startCountdown();
  }

  /// Temps restant, mesuré sur l'échéance du serveur.
  Duration get _validity {
    final forced = widget.validity;
    if (forced != null) return forced;

    final challenge = context.read<AuthFlowBloc>().state.challenge;
    if (challenge == null) return const Duration(minutes: 15);

    return challenge.remainingAt(DateTime.now());
  }

  String get _account =>
      widget.identifier ??
      context.read<AuthFlowBloc>().state.challenge?.identifier ??
      '';

  @override
  void dispose() {
    _ticker?.cancel();
    for (final controller in _controllers) {
      controller.dispose();
    }
    for (final node in _focusNodes) {
      node.dispose();
    }
    super.dispose();
  }

  /// Décompte réel : un code qui affiche un délai figé laisserait croire
  /// qu'il reste du temps alors qu'il en manque.
  void _startCountdown() {
    _ticker?.cancel();
    setState(() => _remaining = _validity);

    _ticker = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_remaining.inSeconds <= 1) {
        timer.cancel();
        setState(() => _remaining = Duration.zero);
        return;
      }
      setState(() => _remaining -= const Duration(seconds: 1));
    });
  }

  String get _code => _controllers.map((c) => c.text).join();

  bool get _isExpired => _remaining == Duration.zero;

  void _onDigitChanged(int index, String value) {
    setState(() => _error = null);

    // Saisie : on avance à la case suivante. Effacement : on revient à la
    // précédente, pour pouvoir corriger sans viser chaque case.
    if (value.isNotEmpty && index < _length - 1) {
      _focusNodes[index + 1].requestFocus();
    } else if (value.isEmpty && index > 0) {
      _focusNodes[index - 1].requestFocus();
    }
  }

  void _verify() {
    if (_isExpired) {
      setState(() => _error = 'Code expiré, demandez-en un nouveau');
      return;
    }
    if (_code.length < _length) {
      setState(() => _error = 'Saisissez les $_length chiffres du code');
      return;
    }

    setState(() => _error = null);
    context.read<AuthFlowBloc>().add(AuthFlowCodeSubmitted(_code));
    widget.onVerified?.call();
  }

  void _resend() {
    for (final controller in _controllers) {
      controller.clear();
    }
    _focusNodes.first.requestFocus();
    setState(() => _error = null);

    context.read<AuthFlowBloc>().add(const AuthFlowCodeResent());
    KoraToaster.show(context, 'Un nouveau code a été envoyé.');
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return BlocListener<AuthFlowBloc, AuthFlowState>(
      // Un nouveau défi (« Renvoyer ») remet le décompte à sa durée
      // pleine : sans ça l'écran continuerait de décompter l'ancien.
      listenWhen: (previous, current) =>
          previous.challenge?.uuid != current.challenge?.uuid,
      listener: (context, flow) => _startCountdown(),
      child: Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const ScreenHeader(
                  eyebrow: 'Sécurité',
                  title: 'Code de vérification',
                ),
                Expanded(
                  child: ListView(
                    padding: EdgeInsets.zero,
                    children: [
                      Text.rich(
                        TextSpan(
                          children: [
                            const TextSpan(text: 'Saisissez le code envoyé à '),
                            // Mise en gras seulement : la maquette laisse
                            // l'adresse dans la couleur atténuée du
                            // paragraphe (`.lead strong` n'a pas de couleur
                            // propre), et non dans celle du texte courant,
                            // presque noire en thème clair.
                            TextSpan(
                              text: _maskAccount(_account),
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const TextSpan(text: '.'),
                          ],
                        ),
                        style: AppTextStyles.lead(koraColors.textMuted),
                      ),
                      const SizedBox(height: 24),
                      Row(
                        children: [
                          for (var i = 0; i < _length; i++) ...[
                            if (i != 0) const SizedBox(width: 7),
                            Expanded(
                              child: _CodeBox(
                                controller: _controllers[i],
                                focusNode: _focusNodes[i],
                                autofocus: i == 0,
                                onChanged: (value) => _onDigitChanged(i, value),
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 24),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Flexible(
                            child: Text(
                              _isExpired
                                  ? 'Code expiré'
                                  : 'Code valable pendant '
                                        '${_formatRemaining(_remaining)}',
                              style: TextStyle(
                                color: _isExpired
                                    ? AppColors.danger
                                    : koraColors.textMuted,
                                fontSize: 10,
                              ),
                            ),
                          ),
                          TextButton(
                            onPressed: _resend,
                            style: TextButton.styleFrom(
                              padding: EdgeInsets.zero,
                              minimumSize: Size.zero,
                              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                            ),
                            child: const Text('Renvoyer'),
                          ),
                        ],
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: 12),
                        Text(
                          _error!,
                          style: const TextStyle(
                            color: AppColors.danger,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                      const SizedBox(height: 25),
                      BlocBuilder<AuthFlowBloc, AuthFlowState>(
                        builder: (context, flow) => Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            // Au-dessus du bouton : le clavier numérique
                            // reste ouvert sur cet écran, et tout ce qui
                            // suit le bouton passe dessous. Un code refusé
                            // doit se lire sans avoir à faire défiler.
                            if (flow.hasError) ...[
                              Text(
                                flow.errorMessage,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: AppColors.danger,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 12),
                            ],
                            KoraButton(
                              label: 'Vérifier le code',
                              isLoading: flow.isSubmitting,
                              onPressed: flow.isSubmitting ? null : _verify,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                      KoraButton(
                        label: 'Je n’ai pas reçu le code',
                        variant: KoraButtonVariant.ghost,
                        onPressed: _resend,
                      ),
                      const SizedBox(height: AppSpacing.lg),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// `.code-box` : une case de saisie, dorée quand elle a le focus.
class _CodeBox extends StatefulWidget {
  const _CodeBox({
    required this.controller,
    required this.focusNode,
    required this.onChanged,
    this.autofocus = false,
  });

  final TextEditingController controller;
  final FocusNode focusNode;
  final ValueChanged<String> onChanged;
  final bool autofocus;

  @override
  State<_CodeBox> createState() => _CodeBoxState();
}

class _CodeBoxState extends State<_CodeBox> {
  @override
  void initState() {
    super.initState();
    widget.focusNode.addListener(_onFocusChanged);
  }

  @override
  void dispose() {
    widget.focusNode.removeListener(_onFocusChanged);
    super.dispose();
  }

  void _onFocusChanged() => setState(() {});

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isActive = widget.focusNode.hasFocus;

    return Container(
      height: 48,
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.base),
        border: Border.all(color: isActive ? AppColors.gold : koraColors.line),
        // `.code-box.active { box-shadow: 0 0 0 3px rgba(246,176,0,.15) }`
        boxShadow: isActive
            ? [
                BoxShadow(
                  color: AppColors.gold.withValues(alpha: 0.15),
                  spreadRadius: 3,
                ),
              ]
            : null,
      ),
      child: TextField(
        controller: widget.controller,
        focusNode: widget.focusNode,
        autofocus: widget.autofocus,
        textAlign: TextAlign.center,
        keyboardType: TextInputType.number,
        maxLength: 1,
        inputFormatters: [FilteringTextInputFormatter.digitsOnly],
        onChanged: widget.onChanged,
        style: TextStyle(
          color: koraColors.textPrimary,
          fontSize: 18,
          fontWeight: FontWeight.w900,
        ),
        decoration: const InputDecoration(
          counterText: '',
          filled: false,
          border: InputBorder.none,
          enabledBorder: InputBorder.none,
          focusedBorder: InputBorder.none,
          contentPadding: EdgeInsets.zero,
        ),
      ),
    );
  }
}

/// `a•••••@exemple.com`, `+229 •• •• 18 60` — on ne réaffiche pas le
/// compte en clair sur un écran de vérification : il sert à confirmer
/// qu'on a visé la bonne boîte ou le bon téléphone, pas à le divulguer si
/// l'écran est vu par-dessus l'épaule.
String _maskAccount(String account) {
  final at = account.indexOf('@');
  if (at > 1) {
    return '${account[0]}${'•' * (at - 1)}${account.substring(at)}';
  }
  if (at != -1) return account;

  // Un téléphone : on garde l'indicatif, qui ne désigne personne, et les
  // deux derniers chiffres, qui suffisent à se reconnaître.
  final country = PhoneCountry.fromPhone(account);
  if (country == null || account.length <= country.code.length + 2) {
    return account;
  }

  final national = country.nationalPart(account);
  final hidden = '•' * (national.length - 2);
  return '${country.code} $hidden${national.substring(national.length - 2)}';
}

/// `04:12`
String _formatRemaining(Duration remaining) {
  final minutes = remaining.inMinutes.toString().padLeft(2, '0');
  final seconds = remaining.inSeconds.remainder(60).toString().padLeft(2, '0');
  return '$minutes:$seconds';
}
