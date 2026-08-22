import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/domain/entities/user_preferences.dart';
import '../../../auth/domain/repositories/auth_repository.dart';
import '../../../language/domain/entities/app_language.dart';
import '../../../language/presentation/bloc/language_bloc.dart';
import '../../../theme/domain/entities/app_theme_mode.dart';
import '../../../theme/presentation/bloc/theme_bloc.dart';

/// Préférences — écran `preferences` (PROFILE-04).
///
/// Les réglages vivent sur le serveur (`auth/preferences`) pour suivre
/// l'utilisateur d'un téléphone à l'autre. La langue et le thème ont en
/// plus une copie locale, sans laquelle l'app ne saurait pas comment
/// s'afficher avant de savoir qui est connecté : chaque changement part
/// donc aux deux endroits.
class PreferencesPage extends StatefulWidget {
  const PreferencesPage({super.key});

  @override
  State<PreferencesPage> createState() => _PreferencesPageState();
}

class _PreferencesPageState extends State<PreferencesPage> {
  UserPreferences? _preferences;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final result = await getIt<AuthRepository>().preferences();
    if (!mounted) return;

    setState(() {
      result.fold((failure) => _error = failure.message, (preferences) {
        _preferences = preferences;
        _error = null;
      });
    });
  }

  /// Applique le changement tout de suite, puis l'enregistre.
  ///
  /// Attendre la réponse du serveur pour bouger l'interrupteur donnerait
  /// l'impression qu'il ne répond pas. En cas d'échec on le remet où il
  /// était, avec le message du serveur.
  Future<void> _update(UserPreferences next) async {
    final previous = _preferences;
    setState(() {
      _preferences = next;
      _error = null;
    });

    final result = await getIt<AuthRepository>().savePreferences(next);
    if (!mounted) return;

    result.fold((failure) {
      setState(() {
        _preferences = previous;
        _error = failure.message;
      });
    }, (saved) => setState(() => _preferences = saved));
  }

  void _setLanguage(AppLanguage language) {
    context.read<LanguageBloc>().add(LanguageChanged(language));

    final current = _preferences;
    if (current != null) _update(current.copyWith(locale: language.code));
  }

  void _setDarkMode(bool dark) {
    context.read<ThemeBloc>().add(
      ThemeModeChanged(dark ? AppThemeMode.dark : AppThemeMode.light),
    );

    final current = _preferences;
    if (current != null) {
      _update(current.copyWith(theme: dark ? 'dark' : 'light'));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final preferences = _preferences;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.preferencesEyebrow,
                title: l10n.preferencesTitle,
              ),
              Expanded(
                child: preferences == null
                    ? _Loading(error: _error, onRetry: _load)
                    : _Content(
                        preferences: preferences,
                        error: _error,
                        onChanged: _update,
                        onLanguage: _setLanguage,
                        onDarkMode: _setDarkMode,
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Loading extends StatelessWidget {
  const _Loading({required this.error, required this.onRetry});

  final String? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    if (error == null) {
      return const Column(
        children: [
          KoraSkeleton(height: 190),
          SizedBox(height: AppSpacing.lg),
          KoraSkeleton(height: 120),
        ],
      );
    }

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.preferencesError,
            style: AppTextStyles.titleMedium(context.koraTitleColor),
          ),
          const SizedBox(height: 4),
          Text(error!, style: AppTextStyles.body(koraColors.textMuted)),
          const SizedBox(height: AppSpacing.md),
          KoraButton(
            label: l10n.actionRetry,
            variant: KoraButtonVariant.secondary,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

class _Content extends StatelessWidget {
  const _Content({
    required this.preferences,
    required this.error,
    required this.onChanged,
    required this.onLanguage,
    required this.onDarkMode,
  });

  final UserPreferences preferences;
  final String? error;
  final ValueChanged<UserPreferences> onChanged;
  final ValueChanged<AppLanguage> onLanguage;
  final ValueChanged<bool> onDarkMode;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final language = context.select<LanguageBloc, AppLanguage>(
      (bloc) => bloc.state.language,
    );
    final isDark =
        context.select<ThemeBloc, AppThemeMode>((bloc) => bloc.state.mode) ==
        AppThemeMode.dark;

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        KoraMenuList(
          items: [
            KoraMenuItem(
              icon: Icons.notifications_active_rounded,
              title: l10n.preferencesPush,
              subtitle: l10n.preferencesPushSub,
              trailing: KoraToggle(
                value: preferences.pushNotifications,
                semanticLabel: l10n.preferencesPush,
                onChanged: (value) =>
                    onChanged(preferences.copyWith(pushNotifications: value)),
              ),
            ),
            KoraMenuItem(
              icon: Icons.chat_rounded,
              title: l10n.preferencesWhatsapp,
              subtitle: l10n.preferencesWhatsappSub,
              trailing: KoraToggle(
                value: preferences.whatsappNotifications,
                semanticLabel: l10n.preferencesWhatsapp,
                onChanged: (value) => onChanged(
                  preferences.copyWith(whatsappNotifications: value),
                ),
              ),
            ),
            KoraMenuItem(
              icon: Icons.mail_rounded,
              title: l10n.preferencesEmail,
              subtitle: l10n.preferencesEmailSub,
              trailing: KoraToggle(
                value: preferences.emailNotifications,
                semanticLabel: l10n.preferencesEmail,
                onChanged: (value) =>
                    onChanged(preferences.copyWith(emailNotifications: value)),
              ),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),
        Text(
          l10n.preferencesDisplay,
          style: AppTextStyles.titleMedium(context.koraTitleColor),
        ),
        const SizedBox(height: AppSpacing.md),
        KoraMenuList(
          items: [
            KoraMenuItem(
              icon: Icons.translate_rounded,
              title: l10n.preferencesLanguage,
              subtitle: language.label,
              onTap: () => _pickLanguage(context, language),
            ),
            KoraMenuItem(
              icon: Icons.dark_mode_rounded,
              title: l10n.preferencesDark,
              subtitle: isDark
                  ? l10n.preferencesDarkOn
                  : l10n.preferencesDarkOff,
              trailing: KoraToggle(
                value: isDark,
                semanticLabel: l10n.preferencesDark,
                onChanged: onDarkMode,
              ),
            ),
          ],
        ),
        if (error != null) ...[
          const SizedBox(height: 12),
          Text(
            error!,
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
    );
  }

  Future<void> _pickLanguage(BuildContext context, AppLanguage current) async {
    final picked = await showModalBottomSheet<AppLanguage>(
      context: context,
      backgroundColor: context.koraColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadius.lg)),
      ),
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final language in AppLanguage.values)
              ListTile(
                title: Text(language.label),
                trailing: language == current
                    ? const Icon(Icons.check_rounded, color: AppColors.gold)
                    : null,
                onTap: () => Navigator.of(sheetContext).pop(language),
              ),
          ],
        ),
      ),
    );

    if (picked != null && picked != current) onLanguage(picked);
  }
}
