import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/phone_number.dart';
import '../../domain/entities/otp_purpose.dart';
import '../../domain/entities/phone_country.dart';
import '../bloc/auth_flow_bloc.dart';
import '../widgets/pin_pad.dart';

/// Connexion par code PIN — écran `login` de la maquette : écran navy,
/// logo, rappel du compte, quatre pastilles de saisie et un pavé numérique.
///
/// Le compte vient du dernier connecté sur cet appareil : la maquette
/// suppose qu'on sait déjà de qui il s'agit et ne demande que le PIN.
/// **Quand aucun compte n'est mémorisé** — première utilisation, données
/// effacées — un champ téléphone apparaît au-dessus du pavé : la maquette
/// ne couvre pas ce cas, et l'API exige les deux.
///
/// L'écran s'ouvre sur l'**adresse e-mail**, et un lien bascule vers le
/// **téléphone** pour qui préfère son numéro. L'API accepte les deux sur
/// le même champ `identifier` depuis qu'elle sait résoudre un compte par
/// son numéro : c'est donc un choix d'écran, pas de protocole.
///
/// Un appareil que le serveur ne connaît pas répond 202 plutôt qu'un
/// token : le parcours enchaîne alors sur l'écran OTP, puis rejoue la
/// connexion tout seul.
class LoginPage extends StatefulWidget {
  const LoginPage({super.key, this.identifier, this.onSuccess});

  /// Compte auquel se rattache le PIN, quand l'app s'en souvient :
  /// téléphone au format international, ou e-mail pour un compte créé
  /// avant la bascule.
  final String? identifier;

  final VoidCallback? onSuccess;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

/// Pourquoi l'écran demande un numéro plutôt qu'un PIN.
enum _AskPurpose {
  /// Vérifier cet appareil pour un compte.
  newDevice,

  /// Recevoir un code pour choisir un nouveau PIN.
  pinReset,
}

/// Ce que l'écran demande pour désigner le compte.
enum _AccountMode { email, phone }

class _LoginPageState extends State<LoginPage> {
  static const int _pinLength = 4;

  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();

  /// Le téléphone par défaut : c'est ce que les voyageurs ont sur eux et
  /// ce qu'ils retiennent. L'adresse se prend par le lien du bas, pour
  /// ceux qui ont créé leur compte avec.
  ///
  /// Un compte mémorisé sur ce téléphone l'emporte sur ce choix : s'il
  /// porte une adresse, l'écran s'ouvre sur l'adresse (voir plus bas).
  _AccountMode _mode = _AccountMode.phone;

  /// L'utilisateur a choisi de désigner le compte lui-même, alors même
  /// qu'un compte est mémorisé. Sans ce drapeau, le lien « Connexion avec
  /// votre numéro » ne ferait rien de visible sur un téléphone qui se
  /// souvient déjà de quelqu'un.
  bool _entering = false;

  /// Indicatif du numéro saisi. Le Bénin par défaut : c'est le réseau
  /// principal de KORA, et le serveur retient lui aussi le +229 quand un
  /// numéro arrive sans indicatif.
  PhoneCountry _country = PhoneCountry.benin;

  String _pin = '';
  String? _error;

  /// Pourquoi l'écran ne demande plus que le numéro — `null` en saisie
  /// de PIN, c'est-à-dire dans le cas ordinaire.
  ///
  /// Les deux liens du bas n'ont que faire du code : l'un vérifie un
  /// appareil, l'autre réinitialise un PIN, et tous deux partent d'un
  /// compte pour faire envoyer un code. Laisser le pavé à l'écran faisait
  /// taper quatre chiffres pour rien — et sur un appareil neuf le compte
  /// n'est même pas mémorisé.
  _AskPurpose? _asking;

  @override
  void dispose() {
    _emailController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  /// L'écran attend-il une saisie, plutôt que le seul PIN ?
  bool get _isTyping => !_knowsAccount || _entering || _asking != null;

  /// Compte visé : celui mémorisé, ou le numéro que l'utilisateur
  /// compose.
  ///
  /// Un compte **vide** n'est pas un compte mémorisé. Le parcours remis à
  /// zéro repasse ici avec une chaîne vide plutôt qu'un `null`, et s'y
  /// fier rendait le champ inutile : il s'affichait, on pouvait y taper,
  /// mais la saisie n'était jamais lue.
  String get _identifier {
    if (_isTyping) return _typed;

    return widget.identifier ?? '';
  }

  /// Ce que l'utilisateur vient de désigner, selon le champ affiché.
  String get _typed => switch (_mode) {
    _AccountMode.email => _emailController.text.trim(),
    _AccountMode.phone => _typedPhone,
  };

  /// Le numéro composé, au format international attendu par l'API.
  ///
  /// L'indicatif vit dans la liste déroulante et le champ ne porte que la
  /// partie nationale : les deux se recollent ici, une fois les
  /// séparateurs de saisie retirés.
  String get _typedPhone {
    final digits = _phoneController.text.replaceAll(RegExp(r'\D'), '');
    return digits.isEmpty ? '' : '${_country.code}$digits';
  }

  bool get _knowsAccount => (widget.identifier ?? '').isNotEmpty;

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

  /// Passe à la saisie du numéro, pour l'un ou l'autre motif.
  ///
  /// Le compte mémorisé, s'il y en a un, sert de point de départ : on le
  /// corrige plutôt que de le retaper. Un numéro international s'y range
  /// en deux morceaux — son indicatif dans la liste, le reste dans le
  /// champ —, sans quoi le `+229` se retrouverait écrit deux fois.
  void _ask(_AskPurpose purpose) {
    setState(() {
      _asking = purpose;
      _error = null;
      _pin = '';

      final remembered = widget.identifier ?? '';
      if (remembered.isEmpty) return;

      // Le compte mémorisé décide du champ : un numéro se range en deux
      // morceaux sous l'indicatif qu'il porte, une adresse va telle
      // quelle dans le champ e-mail.
      final country = PhoneCountry.fromPhone(remembered);
      if (country != null) {
        _mode = _AccountMode.phone;
        if (_phoneController.text.trim().isEmpty) {
          _country = country;
          _phoneController.text = country.nationalPart(remembered);
        }
      } else {
        _mode = _AccountMode.email;
        if (_emailController.text.trim().isEmpty) {
          _emailController.text = remembered;
        }
      }
    });
  }

  /// Change d'indicatif **et** réaccorde le numéro déjà saisi — même
  /// règle partagée que les écrans Autorisations, voyageurs et paiement.
  void _onCountryChanged(PhoneCountry country) {
    final rebased = PhoneNumberInput.rebase(
      _phoneController.text,
      from: _country,
      to: country,
    );

    setState(() {
      _country = country;
      if (rebased != _phoneController.text) _phoneController.text = rebased;
      _error = null;
    });
  }

  /// Le message à afficher pour le compte saisi, ou `null` s'il tient
  /// debout.
  String? _accountError(BuildContext context) {
    if (_mode == _AccountMode.email) {
      // La forme exacte, c'est l'API qui la tranche : on n'arrête ici que
      // le champ vide, pour ne pas refuser une adresse valide sur une
      // règle maison plus étroite que la sienne.
      return _emailController.text.trim().isEmpty
          ? context.l10n.loginEmailFirst
          : null;
    }

    final issue = PhoneNumberInput.validate(_phoneController.text, _country);

    return switch (issue) {
      null => null,
      PhoneIssue.missing => context.l10n.loginPhoneFirst,
      PhoneIssue.wrongPrefix => context.l10n.loginPhonePrefix(
        _country.name,
        _country.prefix,
      ),
      PhoneIssue.tooShort => context.l10n.loginPhoneIncomplete,
    };
  }

  /// Bascule e-mail ↔ téléphone, et fait apparaître le champ si l'écran
  /// n'en montrait pas.
  void _switchMode() {
    setState(() {
      _mode = _mode == _AccountMode.email
          ? _AccountMode.phone
          : _AccountMode.email;
      _entering = true;
      _error = null;
    });
  }

  /// Engage le parcours pour le compte saisi.
  void _submitIdentifier(BuildContext context) {
    final purpose = _asking;
    if (purpose == null) return;

    final issue = _accountError(context);
    if (issue != null) {
      setState(() => _error = issue);
      return;
    }

    setState(() => _error = null);

    final identifier = _typed;
    final bloc = context.read<AuthFlowBloc>();
    switch (purpose) {
      // La vérification d'appareil s'explique avant de demander un code :
      // l'écran dédié dit lequel, et depuis quand.
      case _AskPurpose.newDevice:
        bloc.add(AuthFlowNewDeviceRequested(identifier));
      // Le PIN oublié part droit au code, puis au choix d'un nouveau.
      case _AskPurpose.pinReset:
        bloc.add(
          AuthFlowIdentifierSubmitted(
            identifier: identifier,
            purpose: OtpPurpose.pinReset,
          ),
        );
    }
  }

  void _submit() {
    if (_pin.length < _pinLength) {
      _error = 'Saisissez vos $_pinLength chiffres';
      return;
    }
    // Le compte n'est validé que lorsqu'il est saisi ici : un compte
    // mémorisé vient du serveur, et le retoquer sur la longueur
    // attendue par son pays enfermerait dehors un numéro étranger
    // parfaitement valide.
    if (_isTyping) {
      final issue = _accountError(context);
      if (issue != null) {
        _error = issue;
        return;
      }
    }

    _error = null;
    context.read<AuthFlowBloc>().add(
      AuthFlowLoginSubmitted(identifier: _identifier, pin: _pin),
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
                        account: _isTyping ? null : widget.identifier,
                        title: switch (_asking) {
                          _AskPurpose.newDevice =>
                            context.l10n.loginNewDeviceTitle,
                          _AskPurpose.pinReset => context.l10n.loginResetTitle,
                          null => null,
                        },
                      ),
                      // Le champ s'affiche dès qu'aucun compte n'est
                      // mémorisé — la maquette ne prévoit pas ce cas, mais
                      // l'API veut le compte avec le PIN —, quand c'est
                      // le compte qu'on demande, et quand on a choisi de
                      // le désigner soi-même.
                      if (_isTyping) ...[
                        const SizedBox(height: 20),
                        if (_mode == _AccountMode.email)
                          _EmailField(controller: _emailController)
                        else
                          _PhoneField(
                            controller: _phoneController,
                            country: _country,
                            onCountry: _onCountryChanged,
                          ),
                      ],
                      if (_asking case final purpose?) ...[
                        const SizedBox(height: 12),
                        Text(
                          switch ((purpose, _mode)) {
                            (_AskPurpose.newDevice, _AccountMode.email) =>
                              context.l10n.loginNewDeviceHint,
                            (_AskPurpose.newDevice, _AccountMode.phone) =>
                              context.l10n.loginNewDevicePhoneHint,
                            (_AskPurpose.pinReset, _AccountMode.email) =>
                              context.l10n.loginResetHint,
                            (_AskPurpose.pinReset, _AccountMode.phone) =>
                              context.l10n.loginResetPhoneHint,
                          },
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
                            // Le refus passe avant le pavé : en saisie
                            // d'identifiant c'est le clavier système qui
                            // occupe le bas de l'écran, et le message s'y
                            // perdait.
                            if (flow.hasError) ...[
                              Text(
                                flow.errorMessage,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: AppColors.gold,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 12),
                            ],
                            // Le pavé disparaît en saisie d'adresse ; le
                            // bouton qui le remplace vit en bas d'écran,
                            // hors de cette liste.
                            if (_asking == null)
                              PinKeypad(
                                onKey: _press,
                                onNavy: true,
                                isSubmitting: flow.isSubmitting,
                              ),
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
                                      AuthFlowIdentifierSubmitted(
                                        identifier: _identifier,
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
                        // Au-dessus des deux autres : c'est le seul qui
                        // change la façon de se nommer, les suivants
                        // s'en servent.
                        _LinkButton(
                          label: _mode == _AccountMode.email
                              ? context.l10n.loginSwitchToPhone
                              : context.l10n.loginSwitchToEmail,
                          onPressed: _switchMode,
                        ),
                        const SizedBox(height: 8),
                        _LinkButton(
                          label: 'PIN oublié ?',
                          // Même parcours que l'inscription, autre motif :
                          // code par e-mail, puis choix d'un nouveau PIN.
                          onPressed: () => _ask(_AskPurpose.pinReset),
                        ),
                        const SizedBox(height: 8),
                        _LinkButton(
                          label: context.l10n.loginNewDeviceLink,
                          onPressed: () => _ask(_AskPurpose.newDevice),
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
                              : () => _submitIdentifier(context),
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

/// Un champ sur fond navy : les bordures du thème clair y seraient
/// invisibles.
InputDecoration _navyDecoration({String? hint}) {
  final border = OutlineInputBorder(
    borderRadius: BorderRadius.circular(AppRadius.base),
    borderSide: BorderSide(color: AppColors.white.withValues(alpha: 0.2)),
  );

  return InputDecoration(
    hintText: hint,
    hintStyle: TextStyle(color: AppColors.white.withValues(alpha: 0.45)),
    filled: true,
    fillColor: AppColors.white.withValues(alpha: 0.08),
    border: border,
    enabledBorder: border,
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(AppRadius.base),
      borderSide: const BorderSide(color: AppColors.gold),
    ),
  );
}

/// Champ e-mail, affiché quand le compte se désigne par son adresse.
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
      decoration: _navyDecoration(hint: 'vous@exemple.com'),
    );
  }
}

/// Indicatif + numéro, affichés seulement quand l'app ne sait pas encore
/// à quel compte se rattache le PIN.
///
/// Deux contrôles pour une seule valeur : l'API attend un numéro au
/// format international, et demander à chacun de taper `+229` devant son
/// numéro serait le meilleur moyen de le voir écrit de six façons
/// différentes.
class _PhoneField extends StatelessWidget {
  const _PhoneField({
    required this.controller,
    required this.country,
    required this.onCountry,
  });

  final TextEditingController controller;
  final PhoneCountry country;
  final ValueChanged<PhoneCountry> onCountry;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        SizedBox(
          width: 118,
          child: DropdownButtonFormField<PhoneCountry>(
            initialValue: country,
            isExpanded: true,
            dropdownColor: AppColors.kora,
            iconEnabledColor: AppColors.white,
            style: const TextStyle(color: AppColors.white, fontSize: 14),
            decoration: _navyDecoration(),
            onChanged: (value) => onCountry(value ?? PhoneCountry.benin),
            items: [
              for (final item in PhoneCountry.all)
                DropdownMenuItem(
                  value: item,
                  child: Text(
                    '${item.flag}  ${item.code}',
                    style: const TextStyle(
                      color: AppColors.white,
                      fontSize: 14,
                    ),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: TextField(
            controller: controller,
            keyboardType: TextInputType.phone,
            autocorrect: false,
            style: const TextStyle(color: AppColors.white, fontSize: 14),
            decoration: _navyDecoration(hint: country.example),
          ),
        ),
      ],
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.account, this.title});

  /// Le compte mémorisé, affiché sous le titre : c'est lui qui dit au
  /// voyageur de quel PIN on parle.
  final String? account;

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
        if (account != null && account!.isNotEmpty) ...[
          const SizedBox(height: 6),
          Text(
            account!,
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
