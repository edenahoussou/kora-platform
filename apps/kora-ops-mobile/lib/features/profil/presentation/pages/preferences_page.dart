import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:permission_handler/permission_handler.dart'
    show openAppSettings;
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../app/theme/theme_cubit.dart';
import '../../../../core/services/push_notification_service.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../injection/injection.dart';
import '../../../synchro/presentation/cubit/sync_cubit.dart';

/// "Préférences" — apparence (thème clair/sombre, déplacé ici depuis
/// l'Accueil, voir échange du 16/08/2026), accès à la Synchronisation
/// (l'onglet dédié a été retiré, voir échange du 17/08/2026) et aux
/// adaptateurs P501, et activation des notifications push (FCM).
/// Route : `/profil/preferences`.
class PreferencesPage extends StatelessWidget {
  const PreferencesPage({super.key});

  @override
  Widget build(BuildContext context) {
    final int pending = context.watch<SyncCubit>().state.totalPending;

    return Scaffold(
      appBar: const ScreenHeader(eyebrow: 'Mon compte', title: 'Préférences'),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: <Widget>[
            BlocBuilder<ThemeCubit, ThemeMode>(
              builder: (BuildContext context, ThemeMode mode) => KoraCard(
                child: Row(
                  children: <Widget>[
                    const _IconBadge(icon: Icons.brightness_6_outlined),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text('Apparence',
                              style: Theme.of(context).textTheme.titleMedium),
                          Text(
                            switch (mode) {
                              ThemeMode.light => 'Clair',
                              ThemeMode.dark => 'Sombre',
                              ThemeMode.system => 'Automatique (système)',
                            },
                            style: TextStyle(
                                color: context.koraColors.textMuted,
                                fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      icon: Icon(switch (mode) {
                        ThemeMode.light => Icons.light_mode_outlined,
                        ThemeMode.dark => Icons.dark_mode_outlined,
                        ThemeMode.system => Icons.brightness_auto_outlined,
                      }),
                      onPressed: () => context.read<ThemeCubit>().cycle(),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            const _PushNotificationsCard(),
            const SizedBox(height: AppSpacing.sm),
            KoraCard(
              onTap: () => context.push(AppRoutes.synchro),
              child: Row(
                children: <Widget>[
                  const _IconBadge(icon: Icons.sync),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text('Synchronisation',
                            style: Theme.of(context).textTheme.titleMedium),
                        Text(
                          pending > 0
                              ? '$pending opération(s) en attente'
                              : 'À jour',
                          style: TextStyle(
                              color: context.koraColors.textMuted,
                              fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: AppColors.digital),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            KoraCard(
              onTap: () => context.push(AppRoutes.printerTest),
              child: Row(
                children: <Widget>[
                  const _IconBadge(icon: Icons.print_outlined),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text('Adaptateurs P501',
                            style: Theme.of(context).textTheme.titleMedium),
                        Text(
                          'Tester le scanner et l\'imprimante',
                          style: TextStyle(
                              color: context.koraColors.textMuted,
                              fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: AppColors.digital),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Interrupteur « Notifications push » — préférence `push_notifications`
/// du compte (`PUT auth/preferences`). Hors ligne, l'état reste neutre et
/// le changement est refusé avec un message : rien ne bloque le terrain.
class _PushNotificationsCard extends StatefulWidget {
  const _PushNotificationsCard();

  @override
  State<_PushNotificationsCard> createState() => _PushNotificationsCardState();
}

class _PushNotificationsCardState extends State<_PushNotificationsCard> {
  final PushNotificationService _push = getIt<PushNotificationService>();

  bool _loading = true;
  bool _saving = false;
  bool? _enabled;
  bool _systemDenied = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final bool? enabled = await _push.fetchPushPreference();
    final bool denied = await _push.isSystemPermissionDenied();
    if (!mounted) return;
    setState(() {
      _enabled = enabled;
      _systemDenied = denied;
      _loading = false;
    });
  }

  Future<void> _toggle(bool value) async {
    setState(() => _saving = true);
    final bool ok = await _push.setPushEnabled(value);
    final bool denied = await _push.isSystemPermissionDenied();
    if (!mounted) return;
    setState(() {
      _saving = false;
      _systemDenied = denied;
      if (ok) _enabled = value;
    });
    if (!ok) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text(
            'Connexion requise pour modifier les notifications. Réessayez une fois en ligne.'),
      ));
    }
  }

  String get _subtitle {
    if (!_push.isAvailable) return 'Indisponibles sur cet appareil';
    if (_loading) return 'Chargement…';
    if (_enabled == null) return 'État inconnu (hors ligne)';
    if (_enabled! && _systemDenied) {
      return 'Bloquées dans les réglages Android';
    }
    return _enabled!
        ? 'Affectations, départs, manifeste, synchro'
        : 'Désactivées';
  }

  @override
  Widget build(BuildContext context) {
    final bool interactive =
        _push.isAvailable && !_loading && !_saving && _enabled != null;

    return KoraCard(
      onTap: _systemDenied ? openAppSettings : null,
      child: Row(
        children: <Widget>[
          const _IconBadge(icon: Icons.notifications_active_outlined),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('Notifications push',
                    style: Theme.of(context).textTheme.titleMedium),
                Text(
                  _subtitle,
                  style: TextStyle(
                      color: context.koraColors.textMuted, fontSize: 12),
                ),
              ],
            ),
          ),
          if (_saving)
            const SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          else
            Switch(
              value: _enabled ?? false,
              onChanged: interactive ? _toggle : null,
            ),
        ],
      ),
    );
  }
}

class _IconBadge extends StatelessWidget {
  const _IconBadge({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 36,
      height: 36,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: context.koraColors.background,
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Icon(icon, size: 18, color: context.koraColors.textPrimary),
    );
  }
}
