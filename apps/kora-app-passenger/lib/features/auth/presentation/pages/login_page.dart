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

/// Pourquoi l'écran demande une adresse plutôt qu'un PIN.
enum _EmailPurpose {
  /// Vérifier cet appareil pour un compte.
  newDevice,

  /// Recevoir un code pour choisir un nouveau PIN.
  pinReset,
}

class _LoginPageState extends State<LoginPage> {
  static const int _pinLength = 4;

  final _emailController = TextEditingController();

  String _pin = '';
  String? _error;

  /// Pourquoi l'écran ne demande plus que l'adresse — `null` en saisie de
  /// PIN, c'est-à-dire dans le cas ordinaire.
  ///
  /// Les deux liens du bas n'ont que faire du code : l'un vérifie un
  /// appareil, l'autre réinitialise un PIN, et tous deux partent d'un
  /// compte pour envoyer un courriel. Laisser le pavé à l'écran faisait
  /// taper quatre chiffres pour rien — et sur un appareil neuf l'adresse
  /// n'est même pas mémorisée, si bien qu'ils répondaient « Saisissez
  /// d'abord votre adresse e-mail » au-dessus d'un clavier numérique
  /// inutile.
  _EmailPurpose? _asking;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  /// Compte visé : celui mémorisé, ou celui que l'utilisateur saisit.
  ///
  /// Une adresse **vide** n'est pas une adresse mémorisée. Le parcours
  /// remis à zéro repasse ici avec une chaîne vide plutôt qu'un `null`,
  /// et s'y fier rendait le champ inutile : il s'affichait, on pouvait y
  /// taper, mais la saisie n'était jamais lue — l'écran répondait
  /// « Adresse e-mail requise » à chaque essai.
  String get _email {
    final remembered = widget.email ?? '';
    return remembered.isNotEmpty ? remembered : _emailController.text.trim();
  }

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

  /// Passe à la saisie de l'adresse, pour l'un ou l'autre motif.
  ///
  /// L'adresse mémorisée, s'il y en a une, sert de point de départ : on la
  /// corrige plutôt que de la retaper.
  void _ask(_EmailPurpose purpose) {
    setState(() {
      _asking = purpose;
      _error = null;
      _pin = '';

      final remembered = widget.email ?? '';
      if (remembered.isNotEmpty && _emailController.text.trim().isEmpty) {
        _emailController.text = remembered;
      }
    });
  }

  /// Engage le parcours pour le compte saisi.
  void _submitEmail(BuildContext context) {
    final purpose = _asking;
    if (purpose == null) return;

    final email = _emailController.text.trim();
    if (email.isEmpty) {
      setState(() => _error = context.l10n.loginEmailFirst);
      return;
    }

    setState(() => _error = null);

    final bloc = context.read<AuthFlowBloc>();
    switch (purpose) {
      // La vérification d'appareil s'explique avant de demander un code :
      // l'écran dédié dit lequel, et depuis quand.
      case _EmailPurpose.newDevice:
        bloc.add(AuthFlowNewDeviceRequested(email));
      // Le PIN oublié part droit au code, puis au choix d'un nouveau.
      case _EmailPurpose.pinReset:
        bloc.add(
          AuthFlowEmailSubmitted(email: email, purpose: OtpPurpose.pinReset),
        );
    }
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
                      _Header(
                        email: _asking == null ? widget.email : null,
                        title: switch (_asking) {
                          _EmailPurpose.newDevice =>
                            context.l10n.loginNewDeviceTitle,
                          _EmailPurpose.pinReset =>
                            context.l10n.loginResetTitle,
                          null => null,
                        },
                      ),
                      // Le champ s'affiche dès qu'aucun compte n'est
                      // mémorisé — la maquette ne prévoit pas ce cas, mais
                      // l'API veut l'e-mail avec le PIN — et toujours
                      // quand c'est l'adresse qu'on demande.
                      if (!_knowsAccount || _asking != null) ...[
                        const SizedBox(height: 20),
                        _EmailField(controller: _emailController),
                      ],
                      if (_asking case final purpose?) ...[
                        const SizedBox(height: 12),
                        Text(
                          purpose == _EmailPurpose.newDevice
                              ? context.l10n.loginNewDeviceHint
                              : context.l10n.loginResetHint,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: AppColors.white.withValues(alpha: 0.7),
                            fontSize: 12,
                            height: 1.45,
                          ),
                        ),
                      ],
                      // Les pastilles ne comptent que des chiffres : rien
                      // à compter quand on saisit une adresse.
                      if (_asking == null) ...[
                        const SizedBox(height: 27),
                        PinDots(
                          filled: _pin.length,
                          total: _pinLength,
                          onNavy: true,
                        ),
                      ],
                      if (_error != null) ...[
                        const SizedBox(height: 12),
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
                            // Le pavé disparaît en saisie d'adresse ; le
                            // bouton qui le remplace vit en bas d'écran,
                            // hors de cette liste.
                            if (_asking == null)
                              PinKeypad(
                                onKey: _press,
                                onNavy: true,
                                isSubmitting: flow.isSubmitting,
                              ),
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
                            // déverrouillage passe par un code, pas par un
                            // nouveau PIN. On l'annonce ici plutôt que de
                            // laisser l'utilisateur réessayer en vain.
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
                      if (_asking == null) ...[
                        const SizedBox(height: 12),
                        _LinkButton(
                          label: 'PIN oublié ?',
                          // Même parcours que l'inscription, autre motif :
                          // code par e-mail, puis choix d'un nouveau PIN.
                          onPressed: () => _ask(_EmailPurpose.pinReset),
                        ),
                        const SizedBox(height: 8),
                        _LinkButton(
                          label: context.l10n.loginNewDeviceLink,
                          onPressed: () => _ask(_EmailPurpose.newDevice),
                        ),
                      ],
                      const SizedBox(height: AppSpacing.lg),
                    ],
                  ),
                ),
                // Sous la liste, et non dedans : en saisie d'adresse
                // l'écran se vide de son pavé, et laisser les deux actions
                // flotter juste sous le champ les perdait au milieu du
                // vide. Elles tombent donc là où le pouce les attend.
                if (_asking != null)
                  BlocBuilder<AuthFlowBloc, AuthFlowState>(
                    builder: (context, flow) => Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        KoraButton(
                          label: context.l10n.loginEmailContinue,
                          isLoading: flow.isSubmitting,
                          onPressed: flow.isSubmitting
                              ? null
                              : () => _submitEmail(context),
                        ),
                        const SizedBox(height: 8),
                        _LinkButton(
                          label: context.l10n.loginEmailBack,
                          onPressed: () => setState(() {
                            _asking = null;
                            _error = null;
                          }),
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
  const _Header({required this.email, this.title});

  final String? email;

  /// Remplace « Votre PIN KORA » quand l'écran demande autre chose que le
  /// code — la vérification d'un nouvel appareil, par exemple.
  final String? title;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          'BON RETOUR PARMI NOUS',
          style: AppTextStyles.eyebrow(AppColors.white.withValues(alpha: 0.65)),
        ),
        const SizedBox(height: 6),
        Text(
          title ?? 'Votre PIN KORA',
          style: const TextStyle(
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
