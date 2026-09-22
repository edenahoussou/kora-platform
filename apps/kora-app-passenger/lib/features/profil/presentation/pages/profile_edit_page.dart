import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/domain/entities/auth_user.dart';
import '../../../auth/domain/entities/phone_country.dart';
import '../../../auth/domain/repositories/auth_repository.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';

/// Modification du profil — écran `profile-edit` (PROFILE-02).
///
/// L'API ne connaît qu'un seul champ `name` ; la maquette en montre deux.
/// Le prénom est donc le premier mot, le nom ce qui suit, et les deux se
/// recollent avant l'envoi — un aller-retour qui laisse le nom intact,
/// quel que soit le nombre de mots qu'il compte.
class ProfileEditPage extends StatefulWidget {
  const ProfileEditPage({super.key, this.onSaved});

  /// Notifié après un enregistrement réussi.
  final VoidCallback? onSaved;

  @override
  State<ProfileEditPage> createState() => _ProfileEditPageState();
}

class _ProfileEditPageState extends State<ProfileEditPage> {
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _email = TextEditingController();

  DateTime? _birthDate;
  String? _phone;
  PhoneCountry _country = PhoneCountry.benin;

  bool _saving = false;
  String? _firstNameError;
  String? _emailError;
  String? _serverError;

  /// L'utilisateur a commencé à modifier un champ : le profil relu du
  /// serveur ne doit plus écraser sa saisie.
  bool _touched = false;

  @override
  void initState() {
    super.initState();

    // On peint tout de suite avec ce qu'on a en local, puis on relit le
    // profil : la copie stockée date de la dernière écriture, et peut
    // avoir été dépassée depuis un autre appareil.
    _fill(context.read<AuthBloc>().state.session?.user);
    _refresh();
  }

  void _fill(AuthUser? user) {
    if (user == null) return;

    final parts = user.name.trim().split(RegExp(r'\s+'));
    _firstName.text = parts.isEmpty ? '' : parts.first;
    _lastName.text = parts.length > 1 ? parts.sublist(1).join(' ') : '';
    _email.text = user.email;
    _birthDate = user.birthDate;
    _phone = user.phone;
    // Le numéro dit lui-même son pays quand il est international ;
    // `country_code` ne sert qu'aux numéros gardés au format local.
    _country =
        PhoneCountry.fromPhone(user.phone) ??
        PhoneCountry.fromCode(user.countryCode);
  }

  /// Le numéro tel qu'on le montre : indicatif, espace, numéro national.
  ///
  /// Un numéro déjà international n'en reçoit pas un second — c'est ce qui
  /// affichait « +229 +2290197000000 ».
  String get _phoneShown {
    final phone = _phone?.trim();
    if (phone == null || phone.isEmpty) return '';

    return '${_country.code} ${_country.nationalPart(phone)}';
  }

  Future<void> _refresh() async {
    final result = await getIt<AuthRepository>().refreshUser();
    if (!mounted) return;

    result.fold(
      // Un profil non rafraîchi n'est pas une erreur à montrer : les
      // champs affichent la copie locale, qui reste modifiable.
      (_) {},
      (user) {
        context.read<AuthBloc>().add(AuthProfileUpdated(user));
        if (_touched) return;

        setState(() => _fill(user));
      },
    );
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _email.dispose();
    super.dispose();
  }

  Future<void> _pickBirthDate() async {
    // Le calendrier KORA, celui du choix d'un trajet, plutôt que la boîte
    // de dialogue de Material : deux calendriers différents dans la même
    // app se remarquent.
    final picked = await context.push<DateTime>(
      AppRouter.birthDatePath,
      extra: _birthDate,
    );
    if (picked == null || !mounted) return;

    setState(() => _birthDate = picked);
  }

  Future<void> _save() async {
    final l10n = context.l10n;
    final first = _firstName.text.trim();
    final last = _lastName.text.trim();
    final email = _email.text.trim();

    setState(() {
      _firstNameError = first.isEmpty
          ? l10n.profileEditFirstNameRequired
          : null;
      _emailError = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)
          ? null
          : l10n.profileEditEmailInvalid;
      _serverError = null;
    });

    if (_firstNameError != null || _emailError != null) return;

    setState(() => _saving = true);

    final result = await getIt<AuthRepository>().updateProfile(
      name: '$first $last'.trim(),
      email: email,
      // Le téléphone n'est pas modifiable ici : on le renvoie tel quel,
      // faute de quoi l'API l'effacerait.
      phone: _phone,
      countryCode: _phone == null ? null : _country.code,
      birthDate: _birthDate,
    );
    if (!mounted) return;

    setState(() => _saving = false);

    result.fold((failure) => setState(() => _serverError = failure.message), (
      user,
    ) {
      context.read<AuthBloc>().add(AuthProfileUpdated(user));
      KoraToaster.show(context, l10n.profileEditSaved);
      widget.onSaved?.call();

      // Retour au Profil : l'écran n'a plus rien à montrer, et le toast
      // survit à sa fermeture — il vit dans l'overlay de l'app.
      // `Navigator` plutôt que `context.pop()` de go_router : l'écran
      // doit aussi pouvoir se monter seul, sans routeur, dans un test.
      Navigator.of(context).maybePop();
    });
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.profileEditEyebrow,
                title: l10n.profileEditTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    KoraInput(
                      label: l10n.profileEditFirstName,
                      controller: _firstName,
                      enabled: !_saving,
                      errorText: _firstNameError,
                      keyboardType: TextInputType.name,
                      onChanged: (_) => _touched = true,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraInput(
                      label: l10n.profileEditLastName,
                      controller: _lastName,
                      enabled: !_saving,
                      keyboardType: TextInputType.name,
                      onChanged: (_) => _touched = true,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    // `disabled` dans la maquette : le numéro identifie le
                    // compte auprès de l'opérateur, le changer sans
                    // vérification ouvrirait une porte.
                    KoraInput(
                      label: l10n.profileEditPhone,
                      controller: TextEditingController(text: _phoneShown),
                      enabled: false,
                    ),
                    const SizedBox(height: 6),
                    Text(
                      l10n.profileEditPhoneLocked,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraInput(
                      label: l10n.profileEditEmail,
                      controller: _email,
                      enabled: !_saving,
                      errorText: _emailError,
                      keyboardType: TextInputType.emailAddress,
                      onChanged: (_) => _touched = true,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    _BirthDateField(
                      value: _birthDate,
                      enabled: !_saving,
                      onTap: _pickBirthDate,
                    ),
                    // `style="margin-top:8px"` dans la maquette.
                    const SizedBox(height: 8),
                    KoraButton(
                      label: l10n.profileEditSave,
                      isLoading: _saving,
                      onPressed: _saving ? null : _save,
                    ),
                    if (_serverError != null) ...[
                      const SizedBox(height: 12),
                      Text(
                        _serverError!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: AppColors.danger,
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                    const SizedBox(height: AppSpacing.lg),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Date de naissance : la maquette montre un champ texte « 18 / 04 /
/// 1994 », mais une date se choisit mieux qu'elle ne se tape — et l'API
/// la refuse au moindre écart de format.
class _BirthDateField extends StatelessWidget {
  const _BirthDateField({
    required this.value,
    required this.enabled,
    required this.onTap,
  });

  final DateTime? value;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    String pad(int number) => number.toString().padLeft(2, '0');

    return GestureDetector(
      onTap: enabled ? onTap : null,
      // Le champ ne prend pas le focus : c'est le sélecteur qui saisit.
      child: AbsorbPointer(
        child: KoraInput(
          label: l10n.profileEditBirthDate,
          hint: l10n.profileEditBirthDateHint,
          enabled: enabled,
          controller: TextEditingController(
            text: value == null
                ? ''
                : '${pad(value!.day)} / ${pad(value!.month)} / ${value!.year}',
          ),
        ),
      ),
    );
  }
}
