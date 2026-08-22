import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../domain/entities/otp_purpose.dart';
import '../bloc/auth_flow_bloc.dart';
import '../widgets/pin_pad.dart';

/// Connexion par code PIN — écran `login` de la maquette : écran navy,
/// logo, rappel du compte, quatre pastilles de saisie et un pavé numérique.
///
/// L'e-mail vient du dernier compte connecté sur cet appareil : la
/// maquette suppose qu'on sait déjà de qui il s'agit et ne demande que le
/// PIN. **Quand aucun compte n'est mémorisé** — première utilisation,
/// données effacées — un champ e-mail apparaît au-dessus du pavé : la
/// maquette ne couvre pas ce cas, et l'API exige les deux.
///
/// Un appareil que le serveur ne connaît pas répond 202 plutôt qu'un
/// token : le parcours enchaîne alors sur l'écran OTP, puis rejoue la
/// connexion tout seul.
class LoginPage extends StatefulWidget {
  const LoginPage({super.key, this.email, this.onSuccess});

  /// Compte auquel se rattache le PIN, quand l'app s'en souvient.
  final String? email;

  final VoidCallback? onSuccess;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  static const int _pinLength = 4;

  final _emailController = TextEditingController();

  String _pin = '';
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  /// Compte visé : celui mémorisé, ou celui que l'utilisateur saisit.
  String get _email => widget.email ?? _emailController.text.trim();

  bool get _knowsAccount => (widget.email ?? '').isNotEmpty;

  void _press(String key) {
    setState(() {
      _error = null;

      switch (key) {
        case PinKeypad.backspace:
          if (_pin.isNotEmpty) _pin = _pin.substring(0, _pin.length - 1);
        case PinKeypad.validate:
          _submit();
        default:
          if (_pin.length < _pinLength) _pin += key;
      }
    });
  }

  /// « PIN oublié ? » : demande un code, puis l'écran de PIN servira à en
  /// choisir un nouveau. Sans adresse connue, impossible d'envoyer quoi
  /// que ce soit — on le dit plutôt que de ne rien faire.
  void _forgotPin(BuildContext context) {
    if (_email.isEmpty) {
      setState(() => _error = 'Saisissez d’abord votre adresse e-mail');
      return;
    }

    context.read<AuthFlowBloc>().add(
      AuthFlowEmailSubmitted(email: _email, purpose: OtpPurpose.pinReset),
    );
  }

  /// « Connexion sur un nouvel appareil » : l'écran qui l'explique a
  /// besoin du compte visé, l'appareil se vérifiant toujours pour
  /// quelqu'un.
  void _newDevice(BuildContext context) {
    if (_email.isEmpty) {
      setState(() => _error = context.l10n.loginEmailFirst);
      return;
    }

    context.read<AuthFlowBloc>().add(AuthFlowNewDeviceRequested(_email));
  }

  void _submit() {
    if (_pin.length < _pinLength) {
      _error = 'Saisissez vos $_pinLength chiffres';
      return;
    }
    if (_email.isEmpty) {
      _error = 'Adresse e-mail requise';
      return;
    }

    _error = null;
    context.read<AuthFlowBloc>().add(
      AuthFlowLoginSubmitted(email: _email, pin: _pin),
    );
    widget.onSuccess?.call();
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      // `{ navy: true }` : l'écran est bleu marine dans les deux thèmes,
      // la barre d'état doit donc toujours s'y afficher en clair.
      sized: false,
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: AppColors.kora,
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // La maquette n'a pas de retour sur cet écran ; empilé
                // au-dessus de la bienvenue, il en faut un — le geste
                // système ne suffit pas sur toutes les plateformes.
                Align(
                  alignment: Alignment.centerLeft,
                  child: KoraIconButton(
                    icon: Icons.chevron_left_rounded,
                    // Même pastille que sur les autres écrans : nu, le
                    // chevron flottait seul en haut de l'écran.
                    onNavy: true,
                    onTap: () => Navigator.of(context).pop(),
                  ),
                ),
                Expanded(
                  child: ListView(
                    padding: EdgeInsets.zero,
                    children: [
                      const SizedBox(height: 20),
                      const Center(child: _Logo()),
                      const SizedBox(height: 32),
                      _Header(email: widget.email),
                      // Aucun compte mémorisé : il faut bien demander de
                      // qui il s'agit. La maquette ne prévoit pas ce cas,
                      // mais l'API veut l'e-mail avec le PIN.
                      if (!_knowsAccount) ...[
                        const SizedBox(height: 20),
                        _EmailField(controller: _emailController),
                      ],
                      const SizedBox(height: 27),
                      PinDots(
                        filled: _pin.length,
                        total: _pinLength,
                        onNavy: true,
                      ),
                      if (_error != null) ...[
                        Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: AppColors.gold,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 12),
                      ],
                      BlocBuilder<AuthFlowBloc, AuthFlowState>(
                        builder: (context, flow) => Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            PinKeypad(onKey: _press, onNavy: true),
                            if (flow.hasError) ...[
                              const SizedBox(height: 12),
                              Text(
                                flow.errorMessage,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: AppColors.gold,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ],
                            // Compte bloqué après trop d'essais : le
                            // déverrouillage passe par un code, pas par
                            // un nouveau PIN. On l'annonce ici plutôt que
                            // de laisser l'utilisateur réessayer en vain.
                            if (flow.isAccountLocked) ...[
                              const SizedBox(height: 12),
                              _LinkButton(
                                label: 'Débloquer mon compte',
                                onPressed: () =>
                                    context.read<AuthFlowBloc>().add(
                                      AuthFlowEmailSubmitted(
                                        email: _email,
                                        purpose: OtpPurpose.accountUnlock,
                                      ),
                                    ),
                              ),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      _LinkButton(
                        label: 'PIN oublié ?',
                        // Même parcours que l'inscription, autre motif :
                        // code par e-mail, puis choix d'un nouveau PIN.
                        onPressed: () => _forgotPin(context),
                      ),
                      const SizedBox(height: 8),
                      _LinkButton(
                        label: context.l10n.loginNewDeviceLink,
                        onPressed: () => _newDevice(context),
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

class _Logo extends StatelessWidget {
  const _Logo();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 150,
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(13),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: Image.asset(
          'assets/images/kora_logo.png',
          height: 62,
          fit: BoxFit.cover,
        ),
      ),
    );
  }
}

/// Champ e-mail, affiché seulement quand l'app ne sait pas encore à quel
/// compte se rattache le PIN.
class _EmailField extends StatelessWidget {
  const _EmailField({required this.controller});

  final TextEditingController controller;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: TextInputType.emailAddress,
      autocorrect: false,
      style: const TextStyle(color: AppColors.white, fontSize: 14),
      decoration: InputDecoration(
        hintText: 'vous@exemple.com',
        hintStyle: TextStyle(color: AppColors.white.withValues(alpha: 0.45)),
        filled: true,
        fillColor: AppColors.white.withValues(alpha: 0.08),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.base),
          borderSide: BorderSide(color: AppColors.white.withValues(alpha: 0.2)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.base),
          borderSide: BorderSide(color: AppColors.white.withValues(alpha: 0.2)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.base),
          borderSide: const BorderSide(color: AppColors.gold),
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.email});

  final String? email;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          'BON RETOUR PARMI NOUS',
          style: AppTextStyles.eyebrow(AppColors.white.withValues(alpha: 0.65)),
        ),
        const SizedBox(height: 6),
        const Text(
          'Votre PIN KORA',
          style: TextStyle(
            color: AppColors.white,
            fontSize: 23,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.575,
          ),
        ),
        if (email != null && email!.isNotEmpty) ...[
          const SizedBox(height: 6),
          Text(
            email!,
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.65),
              fontSize: 13,
            ),
          ),
        ],
      ],
    );
  }
}

class _LinkButton extends StatelessWidget {
  const _LinkButton({required this.label, required this.onPressed});

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return TextButton(
      onPressed: onPressed,
      style: TextButton.styleFrom(foregroundColor: AppColors.white),
      child: Text(label),
    );
  }
}
