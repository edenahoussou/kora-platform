import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/main_app_bar.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';
import '../cubit/scan_boarding_cubit.dart';

/// Onglet Scan boarding : **vrai scanner caméra** (package `mobile_scanner`,
/// voir document.md § Scan boarding) — la caméra s'allume dès l'ouverture
/// de l'écran et détecte les QR codes en continu, sans bouton à presser.
/// Le résultat (valide/refusé) est rapproché du manifeste courant (voir
/// `ScanBoardingCubit`) puis affiché sur `ScanResultPage`.
///
/// Un bouton "Scanner (P501)" reste disponible pour déclencher l'adaptateur
/// matériel dédié (MEZ-10, Mock ou scanner physique P501) plutôt que la
/// caméra du téléphone — utile sur le terminal P501 en production.
class ScanBoardingPage extends StatefulWidget {
  const ScanBoardingPage({super.key});

  @override
  State<ScanBoardingPage> createState() => _ScanBoardingPageState();
}

class _ScanBoardingPageState extends State<ScanBoardingPage>
    with WidgetsBindingObserver {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    formats: const <BarcodeFormat>[BarcodeFormat.qrCode],
  );

  bool _torchOn = false;
  bool _resultPushed = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!_controller.value.hasCameraPermission) return;
    switch (state) {
      case AppLifecycleState.detached:
      case AppLifecycleState.hidden:
      case AppLifecycleState.paused:
        return;
      case AppLifecycleState.resumed:
        unawaited(_controller.start());
      case AppLifecycleState.inactive:
        unawaited(_controller.stop());
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    unawaited(_controller.dispose());
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture, BuildContext context) async {
    if (_resultPushed) return;
    final String? rawValue =
        capture.barcodes.isNotEmpty ? capture.barcodes.first.rawValue : null;
    if (rawValue == null || rawValue.isEmpty) return;

    _resultPushed = true;
    context.read<ScanBoardingCubit>().handleDetectedCode(rawValue);
    await context.push(AppRoutes.scanResult);
    // De retour du résultat : on autorise un nouveau scan.
    if (mounted) {
      // ignore: use_build_context_synchronously
      context.read<ScanBoardingCubit>().reset();
      _resultPushed = false;
    }
  }

  Future<void> _scanWithP501(BuildContext context) async {
    final ScanBoardingCubit cubit = context.read<ScanBoardingCubit>();
    await cubit.scan();
    if (!context.mounted) return;
    if (cubit.state.status == ScanBoardingStatus.valide ||
        cubit.state.status == ScanBoardingStatus.refuse) {
      await context.push(AppRoutes.scanResult);
      if (mounted) cubit.reset();
    } else if (cubit.state.status == ScanBoardingStatus.erreur) {
      await KoraDialogs.error(context,
          title: 'Scanner P501', description: cubit.state.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    // Contrôle d'accès par permission : un profil sans `boarding.scan`
    // (ex. agent d'agence) ne doit jamais atteindre la caméra — masquer la
    // fonction plutôt que de la laisser échouer en 403 au premier scan.
    final AuthState authState = context.watch<AuthCubit>().state;
    if (authState is AuthAuthenticated &&
        !authState.user.can('boarding.scan')) {
      return const _ScanNotAuthorized();
    }

    final Depart? depart = context.watch<SelectedDepartCubit>().state;

    return Scaffold(
      appBar: MainAppBar(
        eyebrow: 'Embarquement',
        title: 'Scan boarding',
        extraActions: <Widget>[
          IconButton(
            icon: Icon(_torchOn ? Icons.flash_on : Icons.flash_off),
            tooltip: 'Torche',
            onPressed: () {
              _controller.toggleTorch();
              setState(() => _torchOn = !_torchOn);
            },
          ),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: depart == null
              ? Center(
                  child: Text(
                    'Sélectionnez un départ à gérer pour scanner des billets.',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                )
              : Column(
                  children: <Widget>[
                    const Align(
                        alignment: Alignment.centerLeft,
                        child: SyncStatusBanner()),
                    const SizedBox(height: AppSpacing.sm),
                    Text(depart.route,
                        style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: AppSpacing.sm),
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(AppRadius.lg),
                        child: Stack(
                          fit: StackFit.expand,
                          children: <Widget>[
                            MobileScanner(
                              controller: _controller,
                              fit: BoxFit.cover,
                              onDetect: (BarcodeCapture capture) =>
                                  _onDetect(capture, context),
                              errorBuilder: (BuildContext context,
                                      MobileScannerException error) =>
                                  _CameraError(error: error),
                            ),
                            // Cadre de visée — purement visuel, la
                            // détection porte sur toute l'image caméra.
                            Center(
                              child: Container(
                                width: 220,
                                height: 220,
                                decoration: BoxDecoration(
                                  border: Border.all(
                                      color: AppColors.gold, width: 3),
                                  borderRadius:
                                      BorderRadius.circular(AppRadius.lg),
                                ),
                              ),
                            ),
                            const Positioned(
                              bottom: 16,
                              left: 0,
                              right: 0,
                              child: Text(
                                'Visez le QR code du billet',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 12,
                                  shadows: <Shadow>[
                                    Shadow(blurRadius: 6, color: Colors.black)
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    BlocBuilder<ScanBoardingCubit, ScanBoardingState>(
                      builder:
                          (BuildContext context, ScanBoardingState state) =>
                              KoraButton(
                        label: 'Scanner (P501)',
                        variant: KoraButtonVariant.ghost,
                        icon: Icons.point_of_sale,
                        isLoading: state.status == ScanBoardingStatus.scanning,
                        onPressed: () => _scanWithP501(context),
                      ),
                    ),
                  ],
                ),
        ),
      ),
    );
  }
}

class _CameraError extends StatelessWidget {
  const _CameraError({required this.error});

  final MobileScannerException error;

  @override
  Widget build(BuildContext context) {
    final String message = switch (error.errorCode) {
      MobileScannerErrorCode.permissionDenied =>
        'Accès à la caméra refusé. Autorisez KORA OPS à utiliser la caméra dans les réglages du '
            'téléphone pour scanner les billets.',
      _ => 'Impossible d\'accéder à la caméra sur cet appareil.',
    };

    return Container(
      color: AppColors.kora,
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(Icons.videocam_off_outlined,
                color: Colors.white70, size: 40),
            const SizedBox(height: AppSpacing.sm),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white70, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}

/// Affiché à la place du scanner pour un profil sans la permission
/// `boarding.scan` (ex. agent d'agence) — voir périmètre du ticket
/// "empêcher l'accès aux fonctions et aux données qui ne sont pas
/// autorisées pour le profil connecté".
class _ScanNotAuthorized extends StatelessWidget {
  const _ScanNotAuthorized();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      appBar: MainAppBar(
        eyebrow: 'Embarquement',
        title: 'Scan boarding',
      ),
      body: Center(
        child: Padding(
          padding: EdgeInsets.all(AppSpacing.lg),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(Icons.lock_outline, size: 40),
              SizedBox(height: AppSpacing.sm),
              Text(
                'Votre profil n\'est pas autorisé à scanner les embarquements.',
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
