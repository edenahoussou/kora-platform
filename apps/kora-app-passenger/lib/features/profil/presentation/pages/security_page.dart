import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/domain/entities/auth_device.dart';
import '../../../auth/domain/repositories/auth_repository.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';

/// Sécurité et appareils — écran `security` (PROFILE-03).
///
/// Les appareils viennent de `GET auth/devices`. Retirer sa confiance à
/// l'un d'eux est le pendant nécessaire de l'écran « Nouvel appareil » :
/// sans révocation, un téléphone perdu garde un accès valide jusqu'à
/// l'expiration de son token.
class SecurityPage extends StatefulWidget {
  const SecurityPage({super.key, this.onChangePin, this.onNewDevice});

  final VoidCallback? onChangePin;
  final VoidCallback? onNewDevice;

  @override
  State<SecurityPage> createState() => _SecurityPageState();
}

class _SecurityPageState extends State<SecurityPage> {
  List<AuthDevice>? _devices;
  String? _error;

  /// Appareil dont la révocation est en cours : sa ligne l'annonce.
  String? _revoking;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final result = await getIt<AuthRepository>().devices();
    if (!mounted) return;

    setState(() {
      result.fold((failure) => _error = failure.message, (devices) {
        _devices = devices;
        _error = null;
      });
    });
  }

  Future<void> _revoke(AuthDevice device) async {
    setState(() => _revoking = device.uuid);

    final result = await getIt<AuthRepository>().revokeDevice(device.uuid);
    if (!mounted) return;

    setState(() => _revoking = null);

    result.fold((failure) => setState(() => _error = failure.message), (_) {
      KoraToaster.show(context, context.l10n.securityDeviceRevoked);
      // La liste se relit plutôt que de se corriger de mémoire : le
      // serveur reste seul juge de ce qui a encore accès au compte.
      _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    // L'appareil courant est celui de la session : il ne se révoque pas
    // depuis lui-même, ce serait se déconnecter par un chemin détourné.
    final currentUuid = context.select<AuthBloc, String?>(
      (bloc) => bloc.state.session?.device?.uuid,
    );

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.securityEyebrow,
                title: l10n.securityTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    KoraMenuList(
                      items: [
                        KoraMenuItem(
                          icon: Icons.password_rounded,
                          title: l10n.securityChangePin,
                          subtitle: l10n.securityChangePinSub,
                          onTap: widget.onChangePin,
                        ),
                        KoraMenuItem(
                          icon: Icons.smartphone_rounded,
                          title: l10n.securityNewDevice,
                          subtitle: l10n.securityNewDeviceSub,
                          onTap: widget.onNewDevice,
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Text(
                      l10n.securityDevices,
                      style: AppTextStyles.titleMedium(context.koraTitleColor),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    ..._deviceCards(context, currentUuid),
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

  List<Widget> _deviceCards(BuildContext context, String? currentUuid) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final devices = _devices;

    if (_error != null && devices == null) {
      return [
        KoraCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                l10n.securityDevicesError,
                style: AppTextStyles.titleMedium(context.koraTitleColor),
              ),
              const SizedBox(height: 4),
              Text(_error!, style: AppTextStyles.body(koraColors.textMuted)),
              const SizedBox(height: AppSpacing.md),
              KoraButton(
                label: l10n.actionRetry,
                variant: KoraButtonVariant.secondary,
                onPressed: _load,
              ),
            ],
          ),
        ),
      ];
    }

    if (devices == null) {
      return const [
        KoraSkeleton(height: 86),
        SizedBox(height: AppSpacing.md),
        KoraSkeleton(height: 86),
      ];
    }

    if (devices.isEmpty) {
      return [
        KoraCard(
          variant: KoraCardVariant.mist,
          child: Text(
            l10n.securityDevicesEmpty,
            style: AppTextStyles.body(koraColors.textMuted),
          ),
        ),
      ];
    }

    return [
      for (final device in devices) ...[
        _DeviceCard(
          device: device,
          isCurrent: device.uuid == currentUuid,
          isRevoking: _revoking == device.uuid,
          onRevoke: () => _revoke(device),
        ),
        const SizedBox(height: AppSpacing.md),
      ],
    ];
  }
}

/// Une carte par appareil : nom, dernière activité, et selon les cas la
/// pastille « Actif » ou le lien de révocation.
class _DeviceCard extends StatelessWidget {
  const _DeviceCard({
    required this.device,
    required this.isCurrent,
    required this.isRevoking,
    required this.onRevoke,
  });

  final AuthDevice device;
  final bool isCurrent;
  final bool isRevoking;
  final VoidCallback onRevoke;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;

    final seen = device.lastSeenAt;
    final subtitle = [
      if (isCurrent) l10n.securityDeviceCurrent,
      if (seen != null)
        l10n.securityDeviceLastSeen(
          KoraDateFormats.dayMonthYearIn(
            seen,
            Localizations.localeOf(context).languageCode,
          ),
        ),
    ].join(' · ');

    return KoraCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: koraColors.background,
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(
              Icons.smartphone_rounded,
              size: 19,
              color: context.koraTitleColor,
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  device.name,
                  style: AppTextStyles.titleMedium(context.koraTitleColor),
                ),
                if (subtitle.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    style: AppTextStyles.body(koraColors.textMuted),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          // L'appareil courant porte une pastille au lieu du lien : se
          // révoquer soi-même reviendrait à se déconnecter sans le dire.
          if (isCurrent)
            KoraTag(
              label: l10n.securityDeviceActive,
              variant: KoraTagVariant.gold,
            )
          else
            TextButton(
              onPressed: isRevoking ? null : onRevoke,
              style: TextButton.styleFrom(foregroundColor: AppColors.danger),
              child: Text(
                l10n.securityDeviceRevoke,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
        ],
      ),
    );
  }
}
