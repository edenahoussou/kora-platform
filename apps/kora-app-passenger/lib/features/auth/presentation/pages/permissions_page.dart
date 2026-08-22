import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/services/app_permissions.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/phone_country.dart';
import '../../domain/repositories/auth_repository.dart';
import '../bloc/auth_bloc.dart';

/// Dernière étape de l'inscription — écran `permissions` (AUTH-07).
///
/// La maquette n'y met que les deux interrupteurs. On y ajoute le nom et
/// le téléphone : l'inscription ne demande qu'une adresse e-mail, et le
/// serveur crée le compte avec un nom déduit de celle-ci
/// (`AuthSessionService::defaultNameFromEmail`). C'est le dernier moment
/// du parcours pour obtenir le vrai nom, celui qui figurera sur les
/// billets.
///
/// Rien n'est bloquant : « Plus tard » mène à l'accueil sans rien
/// envoyer, comme les deux boutons de la maquette.
class PermissionsPage extends StatefulWidget {
  const PermissionsPage({super.key, this.onDone, this.permissions});

  /// Appelé une fois l'écran terminé, qu'on ait complété le profil ou
  /// non. Injecté par la route ; les tests montent l'écran sans routeur.
  final VoidCallback? onDone;

  /// Injectable pour les tests, qui n'ont pas de système à interroger.
  final AppPermissions? permissions;

  @override
  State<PermissionsPage> createState() => _PermissionsPageState();
}

class _PermissionsPageState extends State<PermissionsPage> {
  final _name = TextEditingController();
  final _phone = TextEditingController();

  late final AppPermissions _permissions =
      widget.permissions ?? getIt<AppPermissions>();

  PhoneCountry _country = PhoneCountry.benin;

  bool _location = false;
  bool _notifications = false;
  bool _locationBlocked = false;
  bool _notificationsBlocked = false;

  bool _saving = false;
  String? _nameError;
  String? _phoneError;
  String? _serverError;

  @override
  void initState() {
    super.initState();

    // Le compte existe déjà. Son nom vient de l'e-mail et ne vaut rien
    // ici, mais un téléphone déjà enregistré se repropose : l'écran sert
    // aussi de rattrapage après un « Plus tard ».
    final user = context.read<AuthBloc>().state.session?.user;
    if (user != null) {
      _phone.text = user.phone ?? '';
      _country = PhoneCountry.fromCode(user.countryCode);
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  /// Ne demande au système que ce qu'on active : décocher n'ouvre aucune
  /// boîte de dialogue — ni Android ni iOS ne laissent une app retirer
  /// une autorisation déjà accordée.
  Future<void> _toggleLocation(bool value) async {
    if (!value) {
      setState(() => _location = false);
      return;
    }

    final status = await _permissions.requestLocation();
    if (!mounted) return;

    setState(() {
      _location = status == AppPermissionStatus.granted;
      _locationBlocked = status == AppPermissionStatus.permanentlyDenied;
    });
  }

  Future<void> _toggleNotifications(bool value) async {
    if (!value) {
      setState(() => _notifications = false);
      return;
    }

    final status = await _permissions.requestNotifications();
    if (!mounted) return;

    setState(() {
      _notifications = status == AppPermissionStatus.granted;
      _notificationsBlocked = status == AppPermissionStatus.permanentlyDenied;
    });
  }

  Future<void> _submit() async {
    final l10n = context.l10n;
    final name = _name.text.trim();
    // L'utilisateur écrit « 97 00 00 00 » ou « 97-00-00-00 » : l'API
    // attend des chiffres, l'indicatif étant transmis à part.
    final phone = _phone.text.replaceAll(RegExp(r'\D'), '');

    setState(() {
      _nameError = name.isEmpty ? l10n.permissionsNameRequired : null;
      _phoneError = _validatePhone(phone, l10n);
      _serverError = null;
    });

    if (_nameError != null || _phoneError != null) return;

    setState(() => _saving = true);

    final result = await getIt<AuthRepository>().updateProfile(
      name: name,
      phone: phone.isEmpty ? null : phone,
      countryCode: phone.isEmpty ? null : _country.code,
    );
    if (!mounted) return;

    result.fold(
      (failure) => setState(() {
        _saving = false;
        _serverError = failure.message;
      }),
      (user) {
        // On rend la main au bouton avant de sortir : si la navigation
        // n'aboutissait pas, l'écran resterait sinon en chargement sans
        // aucun moyen de réessayer.
        setState(() => _saving = false);
        context.read<AuthBloc>().add(AuthProfileUpdated(user));
        widget.onDone?.call();
      },
    );
  }

  /// Le téléphone reste facultatif — l'API l'accepte vide. Mais un
  /// numéro entamé puis laissé à moitié, ou saisi à l'ancienne, ne part
  /// pas en silence.
  String? _validatePhone(String phone, AppLocalization l10n) {
    if (phone.isEmpty) return null;

    final prefix = _country.prefix;
    if (prefix.isNotEmpty && !phone.startsWith(prefix)) {
      return l10n.permissionsPhonePrefix(_country.name, prefix);
    }

    return phone.length == _country.digits
        ? null
        : l10n.permissionsPhoneInvalid;
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
                eyebrow: l10n.permissionsEyebrow,
                title: l10n.permissionsTitle,
                // Dernière étape de l'inscription : revenir en arrière
                // ramènerait sur la saisie du PIN, déjà consommée.
                showBack: false,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    Text(
                      l10n.permissionsLead,
                      style: AppTextStyles.lead(koraColors.textMuted),
                    ),
                    const SizedBox(height: 18),
                    _IdentityCard(
                      name: _name,
                      phone: _phone,
                      country: _country,
                      nameError: _nameError,
                      phoneError: _phoneError,
                      enabled: !_saving,
                      onCountry: (country) =>
                          setState(() => _country = country),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    _PermissionCard(
                      symbol: '⌖',
                      title: l10n.permissionsLocationTitle,
                      text: l10n.permissionsLocationText,
                      value: _location,
                      blocked: _locationBlocked,
                      onChanged: _toggleLocation,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    _PermissionCard(
                      symbol: '◉',
                      title: l10n.permissionsNotificationsTitle,
                      text: l10n.permissionsNotificationsText,
                      value: _notifications,
                      blocked: _notificationsBlocked,
                      onChanged: _toggleNotifications,
                    ),
                    // `<div style="margin-top:25px">` dans la maquette.
                    const SizedBox(height: 25),
                    KoraButton(
                      label: l10n.permissionsContinue,
                      isLoading: _saving,
                      onPressed: _saving ? null : _submit,
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
                    const SizedBox(height: 10),
                    KoraButton(
                      label: l10n.permissionsLater,
                      variant: KoraButtonVariant.ghost,
                      onPressed: _saving ? null : () => widget.onDone?.call(),
                    ),
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

/// Nom et téléphone. Le pays se choisit à côté du numéro : c'est lui qui
/// donne le `country_code` envoyé à l'API, sans champ supplémentaire.
class _IdentityCard extends StatelessWidget {
  const _IdentityCard({
    required this.name,
    required this.phone,
    required this.country,
    required this.nameError,
    required this.phoneError,
    required this.enabled,
    required this.onCountry,
  });

  final TextEditingController name;
  final TextEditingController phone;
  final PhoneCountry country;
  final String? nameError;
  final String? phoneError;
  final bool enabled;
  final ValueChanged<PhoneCountry> onCountry;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;
    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l10n.permissionsIdentityTitle,
            style: AppTextStyles.titleMedium(context.koraTitleColor),
          ),
          const SizedBox(height: 4),
          Text(
            l10n.permissionsIdentityLead,
            style: AppTextStyles.body(koraColors.textMuted),
          ),
          const SizedBox(height: AppSpacing.md),
          KoraInput(
            label: l10n.permissionsNameLabel,
            hint: l10n.permissionsNameHint,
            controller: name,
            enabled: enabled,
            errorText: nameError,
            keyboardType: TextInputType.name,
          ),
          const SizedBox(height: AppSpacing.md),
          // Même style que le libellé de `KoraInput`, qui n'expose pas de
          // champ à deux contrôles : le pays et le numéro forment une
          // seule ligne sous un seul libellé.
          Text(
            l10n.permissionsPhoneLabel,
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
              SizedBox(
                width: 124,
                child: DropdownButtonFormField<PhoneCountry>(
                  initialValue: country,
                  isExpanded: true,
                  onChanged: enabled
                      ? (value) => onCountry(value ?? PhoneCountry.benin)
                      : null,
                  items: [
                    for (final item in PhoneCountry.all)
                      DropdownMenuItem(
                        value: item,
                        child: Text(
                          '${item.flag}  ${item.code}',
                          style: AppTextStyles.body(koraColors.textPrimary),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: KoraInput(
                  hint: country.example,
                  controller: phone,
                  enabled: enabled,
                  errorText: phoneError,
                  keyboardType: TextInputType.phone,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// `permissionCard()` de la maquette : pictogramme, texte, interrupteur.
class _PermissionCard extends StatelessWidget {
  const _PermissionCard({
    required this.symbol,
    required this.title,
    required this.text,
    required this.value,
    required this.blocked,
    required this.onChanged,
  });

  final String symbol;
  final String title;
  final String text;
  final bool value;
  final bool blocked;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // `.transaction-icon` : 40×40, radius 13, fond `mist`.
          Container(
            width: 40,
            height: 40,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: koraColors.background,
              borderRadius: BorderRadius.circular(13),
            ),
            child: Text(
              symbol,
              style: TextStyle(
                color: context.koraTitleColor,
                fontSize: 18,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: AppTextStyles.titleMedium(context.koraTitleColor),
                ),
                Text(text, style: AppTextStyles.body(koraColors.textMuted)),
                // Un refus définitif ne se rattrape plus ici : on le dit,
                // plutôt que de laisser l'interrupteur revenir tout seul
                // sans explication.
                if (blocked) ...[
                  const SizedBox(height: 4),
                  Text(
                    context.l10n.permissionsDenied,
                    style: const TextStyle(
                      color: AppColors.danger,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          KoraToggle(
            value: value,
            onChanged: blocked ? null : onChanged,
            semanticLabel: title,
          ),
        ],
      ),
    );
  }
}
