import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/helpers/responsive.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/screen_header.dart';
import '../bloc/auth_flow_bloc.dart';
import '../cubit/auth_cubit.dart';

/// Confirmation d'un appareil non reconnu — seul cas d'usage de l'OTP côté
/// OPS (voir document.md § Flux d'authentification, pas de création de
/// compte). Route : `/login/otp`.
class OtpVerificationPage extends StatefulWidget {
  const OtpVerificationPage({super.key});

  @override
  State<OtpVerificationPage> createState() => _OtpVerificationPageState();
}

class _OtpVerificationPageState extends State<OtpVerificationPage> {
  static const int _length = 6;
  static const Duration _validity = Duration(minutes: 5);

  final List<TextEditingController> _controllers =
      List<TextEditingController>.generate(
          _length, (_) => TextEditingController());
  final List<FocusNode> _focusNodes =
      List<FocusNode>.generate(_length, (_) => FocusNode());

  Timer? _ticker;
  Duration _remaining = _validity;

  @override
  void initState() {
    super.initState();
    _startCountdown();
  }

  @override
  void dispose() {
    _ticker?.cancel();
    for (final TextEditingController controller in _controllers) {
      controller.dispose();
    }
    for (final FocusNode node in _focusNodes) {
      node.dispose();
    }
    super.dispose();
  }

  void _startCountdown() {
    _ticker?.cancel();
    setState(() => _remaining = _validity);
    _ticker = Timer.periodic(const Duration(seconds: 1), (Timer timer) {
      if (_remaining.inSeconds <= 1) {
        timer.cancel();
        setState(() => _remaining = Duration.zero);
        return;
      }
      setState(() => _remaining -= const Duration(seconds: 1));
    });
  }

  String get _code =>
      _controllers.map((TextEditingController c) => c.text).join();

  bool get _isExpired => _remaining == Duration.zero;

  void _onDigitChanged(int index, String value) {
    if (value.isNotEmpty && index < _length - 1) {
      _focusNodes[index + 1].requestFocus();
    } else if (value.isEmpty && index > 0) {
      _focusNodes[index - 1].requestFocus();
    }
    setState(() {});
  }

  Future<void> _verify(BuildContext context) async {
    if (_isExpired) {
      await KoraDialogs.warning(context,
          title: 'Code expiré', description: 'Demandez-en un nouveau.');
      return;
    }
    if (_code.length < _length) return;

    final AuthFlowBloc flowBloc = context.read<AuthFlowBloc>();
    flowBloc.add(AuthFlowCodeSubmitted(_code));
    final AuthFlowState next = await flowBloc.stream
        .firstWhere((AuthFlowState state) => !state.isSubmitting);

    if (!context.mounted) return;

    if (next.hasError) {
      await context.push(AppRoutes.loginOtpInvalid);
      return;
    }

    switch (next.step) {
      case AuthFlowStep.done:
        final session = next.session;
        if (session != null) {
          context.read<AuthCubit>().authenticate(session.user);
        }
        context.go(AppRoutes.home);
      case AuthFlowStep.newPin:
        // PIN oublié : le code confirme l'identité, il reste à en choisir
        // un nouveau.
        await context.push(AppRoutes.loginNewPin);
      case AuthFlowStep.identifier:
      case AuthFlowStep.pin:
      case AuthFlowStep.code:
        // Un appareil toujours inconnu après vérification (cas très rare :
        // le serveur redemande un OTP) — on reste sur cet écran, déjà
        // reconstruit avec le nouveau défi.
        break;
    }
  }

  Future<void> _resend(BuildContext context) async {
    for (final TextEditingController controller in _controllers) {
      controller.clear();
    }
    _focusNodes.first.requestFocus();
    context.read<AuthFlowBloc>().add(const AuthFlowCodeResent());
    if (mounted) _startCountdown();
    if (context.mounted) {
      await KoraDialogs.success(
        context,
        title: 'Code renvoyé',
        description: 'Un nouveau code a été envoyé.',
      );
    }
  }

  String _maskIdentifier(String? identifier) {
    if (identifier == null || identifier.isEmpty) return '';

    final int at = identifier.indexOf('@');
    if (at > 1) {
      // Email : première lettre visible, domaine intact.
      return '${identifier[0]}${'•' * (at - 1)}${identifier.substring(at)}';
    }

    // Téléphone (+22961234567) : indicatif pays + 2 derniers chiffres
    // visibles, le reste masqué.
    final RegExpMatch? country = RegExp(r'^\+\d{1,3}').firstMatch(identifier);
    final int prefixEnd = country?.end ?? 1;
    if (identifier.length <= prefixEnd + 2) return identifier;

    final String prefix = identifier.substring(0, prefixEnd);
    final String tail = identifier.substring(identifier.length - 2);
    final int maskedLength = identifier.length - prefixEnd - 2;
    return '$prefix${'•' * maskedLength}$tail';
  }

  String _formatRemaining(Duration remaining) {
    final String minutes = remaining.inMinutes.toString().padLeft(2, '0');
    final String seconds =
        remaining.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$minutes:$seconds';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const ScreenHeader(
          eyebrow: 'Sécurité', title: 'Code de vérification'),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: Responsive.value<double>(context,
                  mobile: double.infinity, tablet: 480),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
              child: BlocBuilder<AuthFlowBloc, AuthFlowState>(
                builder: (BuildContext context, AuthFlowState state) {
                  final bool isSubmitting = state.isSubmitting;
                  return ListView(
                    padding: EdgeInsets.zero,
                    children: <Widget>[
                      Text.rich(
                        TextSpan(
                          style: Theme.of(context).textTheme.bodyLarge,
                          children: <InlineSpan>[
                            const TextSpan(text: 'Saisissez le code envoyé à '),
                            TextSpan(
                              text: _maskIdentifier(state.identifier),
                              style:
                                  const TextStyle(fontWeight: FontWeight.w800),
                            ),
                            const TextSpan(text: '.'),
                          ],
                        ),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      Row(
                        children: <Widget>[
                          for (int i = 0; i < _length; i++) ...<Widget>[
                            if (i != 0) const SizedBox(width: 7),
                            Expanded(
                              child: _CodeBox(
                                controller: _controllers[i],
                                focusNode: _focusNodes[i],
                                autofocus: i == 0,
                                onChanged: (String value) =>
                                    _onDigitChanged(i, value),
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: <Widget>[
                          Flexible(
                            child: Text(
                              _isExpired
                                  ? 'Code expiré'
                                  : 'Code valable pendant ${_formatRemaining(_remaining)}',
                              style: TextStyle(
                                color: _isExpired
                                    ? AppColors.danger
                                    : context.koraColors.textMuted,
                                fontSize: 10,
                              ),
                            ),
                          ),
                          TextButton(
                            onPressed:
                                isSubmitting ? null : () => _resend(context),
                            child: const Text('Renvoyer'),
                          ),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      KoraButton(
                        label: 'Vérifier le code',
                        isLoading: isSubmitting,
                        onPressed: _code.length == _length
                            ? () => _verify(context)
                            : null,
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      KoraButton(
                        label: 'Je n\'ai pas reçu le code',
                        variant: KoraButtonVariant.ghost,
                        onPressed: isSubmitting ? null : () => _resend(context),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                    ],
                  );
                },
              ),
            ),
          ),
        ),
      ),
    );
  }
}

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
    final bool isActive = widget.focusNode.hasFocus;

    return Container(
      height: 48,
      decoration: BoxDecoration(
        color: context.koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(
            color: isActive ? AppColors.gold : context.koraColors.line),
        boxShadow: isActive
            ? <BoxShadow>[
                BoxShadow(
                    color: AppColors.gold.withValues(alpha: 0.15),
                    spreadRadius: 3)
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
        inputFormatters: <TextInputFormatter>[
          FilteringTextInputFormatter.digitsOnly
        ],
        onChanged: widget.onChanged,
        style: TextStyle(
            color: context.koraColors.textPrimary,
            fontSize: 18,
            fontWeight: FontWeight.w900),
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
