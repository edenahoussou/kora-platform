import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/domain/entities/auth_user.dart';
import '../../../auth/domain/entities/phone_country.dart';
import '../../../auth/domain/repositories/auth_repository.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../auth/presentation/widgets/phone_country_picker.dart';

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
  final _phone = TextEditingController();

  DateTime? _birthDate;
  PhoneCountry _country = PhoneCountry.benin;

  /// L'adresse de substitution que le serveur fabrique pour un compte
  /// créé par téléphone (`traveler+<uuid>@accounts.kora.invalid`).
  ///
  /// Elle n'est jamais montrée — c'est un identifiant technique, pas une
  /// adresse — mais l'API exige un email à chaque enregistrement : on la
  /// garde pour la renvoyer telle quelle tant que le voyageur n'a pas
  /// donné la sienne.
  String? _placeholderEmail;

  /// Le compte porte déjà un numéro.
  bool _hasPhone = false;

  /// Par où le compte a été ouvert, tel que le serveur le dit.
  ///
  /// L'écran le déduisait de ce qu'il voyait, faute de mieux. Le serveur
  /// le rend maintenant, et distingue quatre cas au lieu de deux : une
  /// connexion sociale s'identifie par son adresse, un compte ouvert au
  /// guichet par son numéro.
  RegistrationOrigin _origin = RegistrationOrigin.unknown;

  bool _saving = false;
  String? _firstNameError;
  String? _emailError;
  String? _phoneError;
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
    _birthDate = user.birthDate;

    // Une adresse de substitution n'est pas une adresse : l'afficher
    // montrait « traveler+6cac8eb6-…@accounts.kora.invalid » dans un
    // champ censé porter l'email du voyageur.
    final placeholder = _isPlaceholder(user.email);
    _placeholderEmail = placeholder ? user.email : null;
    _email.text = placeholder ? '' : user.email;
    _hasPhone = (user.phone ?? '').trim().isNotEmpty;
    _origin = user.registrationOrigin;

    _fillPhone(user.phone, user.countryCode);
  }

  /// Le numéro se modifie sauf quand il **est** l'identifiant du
  /// compte : on se connecte avec, et le changer sans vérification
  /// ouvrirait une porte.
  ///
  /// Pour un compte d'avant `registration_origin`, on retombe sur la
  /// déduction d'avant : un compte qui porte un numéro s'est identifié
  /// avec.
  bool get _phoneEditable => _origin == RegistrationOrigin.unknown
      ? !_hasPhone
      : !_origin.identifiesByPhone;

  /// L'adresse se modifie sauf quand elle **est** l'identifiant du
  /// compte — une inscription par email, ou une connexion sociale dont
  /// l'adresse vient du fournisseur.
  bool get _emailEditable => _origin == RegistrationOrigin.unknown
      ? _placeholderEmail != null || _hasPhone
      : !_origin.identifiesByEmail;

  /// Vrai pour l'adresse que le serveur fabrique à un compte créé par
  /// téléphone. Le domaine `.invalid` est réservé : rien n'y arrive.
  static bool _isPlaceholder(String email) =>
      email.trim().toLowerCase().endsWith('@$_placeholderDomain');

  static const String _placeholderDomain = 'accounts.kora.invalid';

  /// Sépare l'indicatif du numéro, sans jamais en recoller un second.
  ///
  /// Un numéro d'un pays hors catalogue — `+212…` — n'était reconnu par
  /// personne : l'écran retombait sur le Bénin et affichait
  /// « +229 +212662026060 ». Dans ce cas on laisse le numéro entier dans
  /// le champ, avec son propre indicatif, plutôt que d'en inventer un.
  void _fillPhone(String? phone, String? countryCode) {
    final compact = phone?.replaceAll(RegExp(r'[^\d+]'), '') ?? '';
    final known = PhoneCountry.fromPhone(compact);

    if (known != null) {
      _country = known;
      _phone.text = known.nationalPart(compact);
      return;
    }

    _country = PhoneCountry.fromCode(countryCode);
    _phone.text = compact;
  }

  /// Le numéro à envoyer : l'indicatif choisi devant le numéro national,
  /// ou le numéro tel quel s'il porte déjà le sien.
  String? get _phoneToSend {
    final typed = _phone.text.replaceAll(RegExp(r'[^\d+]'), '');
    if (typed.isEmpty) return null;
    if (typed.startsWith('+')) return typed;

    return '${_country.code}$typed';
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
    _phone.dispose();
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
    final digits = _phone.text.replaceAll(RegExp(r'[^\d]'), '');

    setState(() {
      _firstNameError = first.isEmpty
          ? l10n.profileEditFirstNameRequired
          : null;
      // Un champ laissé vide sur un compte sans adresse n'est pas une
      // faute : on renverra la substitution du serveur. C'est une
      // adresse saisie et mal formée qui en est une.
      _emailError =
          !_emailEditable ||
              email.isEmpty && _placeholderEmail != null ||
              RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)
          ? null
          : l10n.profileEditEmailInvalid;
      // Le serveur compte les chiffres du pays : prévenir ici évite un
      // aller-retour pour un numéro visiblement trop court.
      _phoneError =
          _phoneEditable &&
              digits.isNotEmpty &&
              _country.digits > 0 &&
              !_phone.text.trim().startsWith('+') &&
              digits.length != _country.digits
          ? l10n.profileEditPhoneInvalid(_country.name, _country.digits)
          : null;
      _serverError = null;
    });

    if (_firstNameError != null || _emailError != null || _phoneError != null) {
      return;
    }

    setState(() => _saving = true);

    final result = await getIt<AuthRepository>().updateProfile(
      name: '$first $last'.trim(),
      // L'API exige une adresse à chaque enregistrement : sans saisie,
      // on lui rend la sienne plutôt que d'empêcher de corriger un nom.
      email: email.isEmpty ? (_placeholderEmail ?? '') : email,
      phone: _phoneToSend,
      countryCode: _phoneToSend == null ? null : _country.code,
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
                    // Indicatif et numéro restent deux champs, lisibles
                    // même figés : les coller en un seul affichait
                    // « +229 +212662026060 » pour un numéro étranger.
                    Text(
                      l10n.profileEditPhone,
                      style: TextStyle(
                        color: context.koraTitleColor,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _CountryButton(
                          country: _country,
                          enabled: _phoneEditable && !_saving,
                          onCountry: (country) => setState(() {
                            _touched = true;
                            _country = country;
                            _phoneError = null;
                          }),
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        Expanded(
                          child: KoraInput(
                            hint: _country.example.isEmpty
                                ? l10n.profileEditPhoneHintFree
                                : _country.example,
                            controller: _phone,
                            enabled: _phoneEditable && !_saving,
                            keyboardType: TextInputType.phone,
                            onChanged: (_) {
                              _touched = true;
                              if (_phoneError != null) {
                                setState(() => _phoneError = null);
                              }
                            },
                          ),
                        ),
                      ],
                    ),
                    if (_phoneError != null) ...[
                      const SizedBox(height: 6),
                      Text(
                        _phoneError!,
                        style: AppTextStyles.caption(AppColors.danger),
                      ),
                    ],
                    const SizedBox(height: 6),
                    Text(
                      _phoneEditable
                          ? l10n.profileEditPhoneAdd
                          : l10n.profileEditPhoneLocked,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraInput(
                      label: l10n.profileEditEmail,
                      hint: _emailEditable ? l10n.profileEditEmailHint : null,
                      controller: _email,
                      enabled: _emailEditable && !_saving,
                      errorText: _emailError,
                      keyboardType: TextInputType.emailAddress,
                      onChanged: (_) => _touched = true,
                    ),
                    if (!_emailEditable) ...[
                      const SizedBox(height: 6),
                      Text(
                        l10n.profileEditEmailLocked,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ] else if (_placeholderEmail != null &&
                        _emailError == null) ...[
                      const SizedBox(height: 6),
                      Text(
                        l10n.profileEditEmailNone,
                        style: AppTextStyles.caption(koraColors.textMuted),
                      ),
                    ],
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

/// L'indicatif, ouvert sur la liste complète des pays — le même geste
/// qu'à l'inscription, et la même feuille de recherche.
class _CountryButton extends StatelessWidget {
  const _CountryButton({
    required this.country,
    required this.enabled,
    required this.onCountry,
  });

  final PhoneCountry country;
  final bool enabled;
  final ValueChanged<PhoneCountry> onCountry;

  Future<void> _pick(BuildContext context) async {
    final picked = await pickPhoneCountry(context);
    if (picked != null) onCountry(picked);
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return SizedBox(
      width: 124,
      height: 56,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: enabled ? () => _pick(context) : null,
          borderRadius: BorderRadius.circular(AppRadius.base),
          child: Ink(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadius.base),
              border: Border.all(color: koraColors.line),
            ),
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    '${country.flag}  ${country.code}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppTextStyles.body(koraColors.textPrimary),
                  ),
                ),
                Icon(
                  Icons.arrow_drop_down,
                  color: koraColors.textMuted,
                  size: 20,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
