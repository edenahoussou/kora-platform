import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../app/theme/theme_cubit.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../manifeste/presentation/cubit/manifeste_cubit.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/cubit/sync_cubit.dart';

/// Onglet Accueil — reproduit trait pour trait la mise en page de l'accueil
/// `kora-passager` (voir document.md § Design System / Accueil) : en-tête
/// marine incurvé (logo + avatar), salutation, carte blanche flottante qui
/// chevauche l'en-tête, grille "Accès rapides" (1ʳᵉ tuile mise en avant),
/// bandeau promo marine — contenu adapté au métier OPS (départ géré au lieu
/// de recherche de voyage).
class AccueilPage extends StatelessWidget {
  const AccueilPage({super.key});

  String _firstName(String fullName) => fullName.trim().split(' ').first;

  @override
  Widget build(BuildContext context) {
    final AuthState authState = context.watch<AuthCubit>().state;
    final String prenom = authState is AuthAuthenticated
        ? _firstName(authState.user.fullName)
        : 'Agent';
    final Depart? depart = context.watch<SelectedDepartCubit>().state;
    final int embarques = context.watch<ManifesteCubit>().state.embarques;
    final int pending = context.watch<SyncCubit>().state.pending.length;

    return Scaffold(
      backgroundColor: context.koraColors.background,
      body: AnnotatedRegion<SystemUiOverlayStyle>(
        sized: false,
        value: const SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.light,
          statusBarBrightness: Brightness.dark,
        ),
        child: ListView(
          padding: EdgeInsets.zero,
          children: <Widget>[
            Stack(
              clipBehavior: Clip.none,
              children: <Widget>[
                _NavyHeader(prenom: prenom, pendingCount: pending),
                Positioned(
                  left: AppSpacing.md,
                  right: AppSpacing.md,
                  bottom: -108,
                  child: _DepartCard(depart: depart, embarques: embarques),
                ),
              ],
            ),
            const SizedBox(height: 128),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              child: KoraButton(
                label: 'Voir le manifeste',
                onPressed: () => context.go(AppRoutes.manifeste),
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              child: Text('Accès rapides',
                  style:
                      AppTextStyles.heading2(context.koraColors.textPrimary)),
            ),
            const SizedBox(height: AppSpacing.sm),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: _QuickTile(
                      icon: Icons.list_alt_rounded,
                      label: 'Manifeste',
                      highlighted: true,
                      onTap: () => context.go(AppRoutes.manifeste),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: _QuickTile(
                      icon: Icons.qr_code_scanner_rounded,
                      label: 'Scan',
                      onTap: () => context.go(AppRoutes.scanBoarding),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: _QuickTile(
                      icon: Icons.move_down_rounded,
                      label: 'Descente',
                      onTap: () => context.go(AppRoutes.descenteAnticipee),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: _QuickTile(
                      icon: Icons.sync_rounded,
                      label: 'Synchro',
                      onTap: () => context.go(AppRoutes.synchro),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              child: _P501PromoBanner(
                  onTap: () => context.push(AppRoutes.printerTest)),
            ),
            const SizedBox(height: AppSpacing.xxxl * 2.5),
          ],
        ),
      ),
    );
  }
}

/// En-tête marine incurvé — logo KORA à gauche, avatar/bascule de thème à
/// droite (avec pastille dorée si des opérations de synchro sont en
/// attente), salutation et accroche sur deux lignes.
class _NavyHeader extends StatelessWidget {
  const _NavyHeader({required this.prenom, required this.pendingCount});

  final String prenom;
  final int pendingCount;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: const BorderRadius.only(
        bottomLeft: Radius.circular(32),
        bottomRight: Radius.circular(32),
      ),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.md, AppSpacing.md, AppSpacing.md, 140),
        decoration: const BoxDecoration(color: AppColors.kora),
        child: Stack(
          clipBehavior: Clip.none,
          children: <Widget>[
            // Décor discret : cercle et diagonale dorée, dans l'esprit de la
            // maquette (simplifié — pas de tracé SVG source disponible).
            Positioned(
              top: -40,
              right: -30,
              child: Container(
                width: 140,
                height: 140,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                      color: AppColors.white.withValues(alpha: 0.06),
                      width: 24),
                ),
              ),
            ),
            Positioned(
              bottom: 30,
              right: -20,
              child: Transform.rotate(
                angle: -0.5,
                child: Container(
                    width: 140,
                    height: 3,
                    color: AppColors.gold.withValues(alpha: 0.5)),
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                SafeArea(
                  bottom: false,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: <Widget>[
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          color: AppColors.white,
                          borderRadius: BorderRadius.circular(AppRadius.md),
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(AppRadius.sm),
                          child: Image.asset('assets/images/kora_logo.png',
                              height: 28, fit: BoxFit.contain),
                        ),
                      ),
                      BlocBuilder<ThemeCubit, ThemeMode>(
                        builder: (BuildContext context, ThemeMode mode) =>
                            Stack(
                          clipBehavior: Clip.none,
                          children: <Widget>[
                            Material(
                              color: AppColors.white,
                              shape: const CircleBorder(),
                              child: InkWell(
                                customBorder: const CircleBorder(),
                                onTap: () => context.read<ThemeCubit>().cycle(),
                                child: Padding(
                                  padding: const EdgeInsets.all(10),
                                  child: Icon(
                                    switch (mode) {
                                      ThemeMode.light =>
                                        Icons.light_mode_outlined,
                                      ThemeMode.dark =>
                                        Icons.dark_mode_outlined,
                                      ThemeMode.system =>
                                        Icons.brightness_auto_outlined,
                                    },
                                    color: AppColors.kora,
                                    size: 18,
                                  ),
                                ),
                              ),
                            ),
                            if (pendingCount > 0)
                              Positioned(
                                top: -2,
                                right: -2,
                                child: Container(
                                  width: 12,
                                  height: 12,
                                  decoration: BoxDecoration(
                                    color: AppColors.gold,
                                    shape: BoxShape.circle,
                                    border: Border.all(
                                        color: AppColors.kora, width: 2),
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                Text(
                  'Bonjour $prenom,',
                  style: TextStyle(
                      color: AppColors.white.withValues(alpha: 0.7),
                      fontSize: 14),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Votre départ à gérer\nvous attend.',
                  style: TextStyle(
                    color: AppColors.white,
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    height: 1.2,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Carte blanche flottante — bandeau "DÉPART" en accent, origine/arrivée,
/// bouton "changer de départ", date/heure et embarqués.
class _DepartCard extends StatelessWidget {
  const _DepartCard({required this.depart, required this.embarques});

  final Depart? depart;
  final int embarques;

  @override
  Widget build(BuildContext context) {
    final List<String> segments =
        depart != null ? depart!.route.split('→') : const <String>['—', '—'];
    final String origine = segments.first.trim();
    final String destination = segments.length > 1 ? segments.last.trim() : '';

    return Material(
      elevation: 8,
      shadowColor: Colors.black26,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: Container(
        decoration: BoxDecoration(
          color: context.koraColors.surface,
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Container(
              padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.md, vertical: AppSpacing.sm),
              decoration: const BoxDecoration(
                color: AppColors.digital,
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(AppRadius.lg),
                  topRight: Radius.circular(AppRadius.lg),
                ),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: <Widget>[
                  Text(
                    'Départ géré aujourd\'hui',
                    style: TextStyle(
                        color: AppColors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 13),
                  ),
                  Text(
                    'KORA OPS',
                    style: TextStyle(
                        color: AppColors.gold,
                        fontWeight: FontWeight.w800,
                        fontSize: 11),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      const Icon(Icons.trip_origin,
                          size: 14, color: AppColors.digital),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text('DÉPART',
                                style: AppTextStyles.eyebrow(
                                    context.koraColors.textMuted)),
                            Text(origine,
                                style: Theme.of(context).textTheme.titleMedium),
                          ],
                        ),
                      ),
                      Material(
                        color: AppColors.gold,
                        shape: const CircleBorder(),
                        child: InkWell(
                          customBorder: const CircleBorder(),
                          onTap: () => context.push(AppRoutes.selectionDepart),
                          child: const Padding(
                            padding: EdgeInsets.all(8),
                            child: Icon(Icons.swap_vert,
                                color: AppColors.onGold, size: 18),
                          ),
                        ),
                      ),
                    ],
                  ),
                  Padding(
                    padding:
                        const EdgeInsets.symmetric(vertical: AppSpacing.sm),
                    child: Divider(height: 1, color: context.koraColors.line),
                  ),
                  Row(
                    children: <Widget>[
                      const Icon(Icons.location_on,
                          size: 14, color: AppColors.gold),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text('ARRIVÉE',
                                style: AppTextStyles.eyebrow(
                                    context.koraColors.textMuted)),
                            Text(destination,
                                style: Theme.of(context).textTheme.titleMedium),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: _MiniStat(
                          label: 'DATE',
                          value: depart != null
                              ? '${depart!.date}  ${depart!.heure}'
                              : '—',
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: _MiniStat(
                          label: 'EMBARQUÉS',
                          value: depart != null
                              ? '$embarques / ${depart!.placesOccupees}'
                              : '—',
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.sm),
      decoration: BoxDecoration(
        color: context.koraColors.background,
        borderRadius: BorderRadius.circular(AppRadius.sm),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(label,
              style: AppTextStyles.eyebrow(context.koraColors.textMuted)),
          const SizedBox(height: 2),
          Text(
            value,
            style: TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 13,
                color: context.koraColors.textPrimary),
          ),
        ],
      ),
    );
  }
}

class _QuickTile extends StatelessWidget {
  const _QuickTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.highlighted = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool highlighted;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: context.koraColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(
              vertical: AppSpacing.md, horizontal: 4),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(
                color: highlighted ? AppColors.gold : Colors.transparent,
                width: 1.5),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: highlighted
                      ? AppColors.gold.withValues(alpha: 0.15)
                      : context.koraColors.background,
                  shape: BoxShape.circle,
                ),
                child: Icon(icon,
                    size: 20,
                    color: highlighted ? AppColors.gold : AppColors.kora),
              ),
              const SizedBox(height: 6),
              Text(
                label,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: context.koraColors.textPrimary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _P501PromoBanner extends StatelessWidget {
  const _P501PromoBanner({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.kora,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: <Widget>[
              Container(
                padding: const EdgeInsets.all(10),
                decoration: const BoxDecoration(
                    color: AppColors.gold, shape: BoxShape.circle),
                child: const Icon(Icons.qr_code_scanner,
                    color: AppColors.onGold, size: 20),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text('ADAPTATEURS P501',
                        style: AppTextStyles.eyebrow(AppColors.gold)),
                    const SizedBox(height: 2),
                    const Text(
                      'Tester le scanner ?',
                      style: TextStyle(
                          color: AppColors.white,
                          fontWeight: FontWeight.w700,
                          fontSize: 14),
                    ),
                    Text(
                      'Scanner un billet et voir le résultat immédiatement.',
                      style: TextStyle(
                          color: AppColors.white.withValues(alpha: 0.65),
                          fontSize: 11),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: const BoxDecoration(
                    color: AppColors.gold, shape: BoxShape.circle),
                child: const Icon(Icons.arrow_forward,
                    color: AppColors.onGold, size: 16),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
