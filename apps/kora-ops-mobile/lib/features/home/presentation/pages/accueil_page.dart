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
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/notification_button.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../manifeste/presentation/cubit/manifeste_cubit.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../ops/presentation/depart_progress.dart';
import '../../../tracking/domain/entities/tracking_availability.dart';
import '../../../tracking/presentation/cubit/tracking_cubit.dart';

/// Onglet Accueil — reproduit trait pour trait la mise en page de l'accueil
/// `kora-passager` (voir document.md § Design System / Accueil) : en-tête
/// marine incurvé (logo + avatar), salutation, carte blanche flottante qui
/// chevauche l'en-tête, grille "Accès rapides" (1ʳᵉ tuile mise en avant),
/// carte de statut du suivi de position — contenu adapté au métier OPS
/// (départ géré au lieu de recherche de voyage).
class AccueilPage extends StatelessWidget {
  const AccueilPage({super.key});

  String _firstName(String fullName) => fullName.trim().split(' ').first;

  @override
  Widget build(BuildContext context) {
    final AuthState authState = context.watch<AuthCubit>().state;
    final String prenom = authState is AuthAuthenticated
        ? _firstName(authState.user.name)
        : 'Agent';
    final Depart? depart = context.watch<SelectedDepartCubit>().state;
    final ManifesteState manifesteState = context.watch<ManifesteCubit>().state;
    final int embarques = manifesteState.embarques;
    // MEZ-92 : `GET /ops/departures` ne renvoie pas d'effectif (il
    // faudrait charger le manifeste de chaque ligne de liste) — le total
    // de passagers vient donc du manifeste déjà chargé pour le départ
    // sélectionné, pas de `Depart.placesOccupees` (toujours à 0 côté
    // données réelles).
    final int totalPassagers = manifesteState.passengers.length;
    final bool canProgressDeparture = authState is AuthAuthenticated &&
        authState.user.can('departures.progress');
    final DepartProgressStep? progressStep =
        depart != null && canProgressDeparture
            ? nextDepartProgressStep(depart)
            : null;
    final bool canReportIncident = depart != null &&
        authState is AuthAuthenticated &&
        authState.user.can('incidents.report');

    return Scaffold(
      backgroundColor: context.koraColors.background,
      body: AnnotatedRegion<SystemUiOverlayStyle>(
        sized: false,
        value: const SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.light,
          statusBarBrightness: Brightness.dark,
        ),
        // Utilisation de SingleChildScrollView pour garantir que le scroll
        // fonctionne parfaitement même avec les éléments superposés (Stack/Positioned).
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Stack(
                clipBehavior: Clip.none,
                children: <Widget>[
                  _NavyHeader(prenom: prenom),
                  Positioned(
                    left: AppSpacing.md,
                    right: AppSpacing
                        .md, // ✅ CORRIGÉ : Marge droite identique à la gauche pour un centrage parfait
                    bottom:
                        -140, // ✅ CORRIGÉ : La carte descend un peu plus (était à -126)
                    child: _DepartCard(
                        depart: depart,
                        embarques: embarques,
                        totalPassagers: totalPassagers),
                  ),
                ],
              ),
              const SizedBox(
                  height:
                      160), // Ajusté pour compenser le nouveau bottom (-140)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
                child: KoraButton(
                  label: depart == null
                      ? 'Choisir une course'
                      : 'Voir le manifeste',
                  onPressed: () => depart == null
                      ? context.go(AppRoutes.tournee)
                      : context.push(AppRoutes.manifeste),
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              // Accès rapide à la progression du départ / signalement
              // d'incident : sortis de l'empilement sous la carte,
              // intégrés directement à la rangée "Accès rapides" — à la
              // place de Scan/Descente, qui restent accessibles depuis
              // l'AppBar du Manifeste tant qu'un départ est sélectionné
              // (voir ManifestePage).
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
                        icon: Icons.directions_bus_filled,
                        label: 'Tournée',
                        highlighted: true,
                        onTap: () => context.go(AppRoutes.tournee),
                      ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: depart != null && progressStep != null
                          ? _QuickTile(
                              icon: progressStep.icon,
                              label: progressStep.label,
                              onTap: () => progressDeparture(
                                  context, depart, progressStep),
                            )
                          : _QuickTile(
                              icon: Icons.qr_code_scanner_rounded,
                              label: 'Scan',
                              onTap: () => context.go(AppRoutes.scanBoarding),
                            ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: depart != null && canReportIncident
                          ? _QuickTile(
                              icon: Icons.report_problem,
                              label: 'Incident',
                              onTap: () =>
                                  context.push(AppRoutes.incidentReport),
                            )
                          : _QuickTile(
                              icon: Icons.move_down_rounded,
                              label: 'Descente',
                              onTap: () =>
                                  context.go(AppRoutes.descenteAnticipee),
                            ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    // Ancien tile "Synchro" (avant remplacement par
                    // "Alertes", échange du 20/09/2026) — Synchro reste
                    // accessible depuis Profil, voir SynchroPage.
                    // Expanded(
                    //   child: _QuickTile(
                    //     icon: Icons.sync_rounded,
                    //     label: 'Synchro',
                    //     onTap: () => context.go(AppRoutes.synchro),
                    //   ),
                    // ),
                    Expanded(
                      child: _QuickTile(
                        icon: Icons.notifications_rounded,
                        label: 'Alertes',
                        onTap: () => depart == null
                            ? _promptSelectDepart(context)
                            : context.push(AppRoutes.notifications),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: AppSpacing.md),
                child: _TrackingStatusCard(),
              ),
              // ✅ CORRIGÉ : Ajout de l'espace système en bas (Home indicator)
              // pour s'assurer que la carte de suivi soit entièrement visible et scrollable.
              SizedBox(
                  height:
                      MediaQuery.of(context).padding.bottom + AppSpacing.xl),
            ],
          ),
        ),
      ),
    );
  }
}

/// Convoyeur sans départ sélectionné qui tape sur "Alertes" (Accès rapides)
/// — on ne l'y envoie pas silencieusement vide : popup explicite, puis
/// redirection vers Tournée au OK (voir échange du 20/09/2026).
Future<void> _promptSelectDepart(BuildContext context) async {
  await KoraDialogs.info(
    context,
    title: 'Aucun départ sélectionné',
    description: 'Veuillez sélectionner un départ.',
  );
  if (context.mounted) context.go(AppRoutes.tournee);
}

/// En-tête marine incurvé — logo KORA à gauche, avatar/bascule de thème à
/// droite (avec pastille dorée si des opérations de synchro sont en
/// attente), salutation et accroche sur deux lignes.
class _NavyHeader extends StatelessWidget {
  const _NavyHeader({required this.prenom});

  final String prenom;

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
                      Image.asset(
                        'assets/images/kora_logo_light.png',
                        height: 50,
                        fit: BoxFit.contain,
                      ),
                      const NotificationButton(onNavy: true),
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

/// Bloc "trajet" soudé en 3 pièces, fidèle au schéma fourni (échange du
/// 17/08/2026) :
///   - CARD (blanche, DÉPART/ARRIVÉE) : pleine largeur — la plus large des
///     trois ;
///   - HEADER (bleu) et FOOTER (DATE/EMBARQUÉS) : en retrait par rapport à
///     la carte (marge horizontale supplémentaire), empilés **sans aucun
///     espace** — le bas du header se fond dans le haut de la carte, le bas
///     de la carte se fond dans le haut du footer ;
///   - le footer a un fond légèrement plus sombre qu'un blanc pur
///     (`context.koraColors.background`, pas `surface`) ;
///   - bouton switch : entièrement à l'extérieur de la carte, à droite,
///     centré verticalement sur le séparateur départ/arrivée.
class _DepartCard extends StatelessWidget {
  const _DepartCard(
      {required this.depart,
      required this.embarques,
      required this.totalPassagers});

  final Depart? depart;
  final int embarques;
  final int totalPassagers;

  /// Retrait du header/footer par rapport à la carte (plus large qu'eux).
  static const double _capInset = AppSpacing.md;

  @override
  Widget build(BuildContext context) {
    final List<String> segments =
        depart != null ? depart!.route.split('→') : const <String>['—', '—'];
    final String origine = segments.first.trim();
    final String destination = segments.length > 1 ? segments.last.trim() : '';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        // --- HEADER : en retrait, coins bas carrés (soudé à la carte) ---
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: _capInset),
          child: Container(
            padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.md, vertical: AppSpacing.sm),
            decoration: const BoxDecoration(
              color: AppColors.digital,
              borderRadius: BorderRadius.only(
                topLeft: Radius.circular(AppRadius.lg),
                topRight: Radius.circular(AppRadius.lg),
              ),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: <Widget>[
                const Text(
                  'Départ géré aujourd\'hui',
                  style: TextStyle(
                      color: AppColors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 13),
                ),
                Text(
                  'KORA OPS',
                  style: TextStyle(
                      color: AppColors.white.withValues(alpha: 0.65),
                      fontWeight: FontWeight.w800,
                      fontSize: 11),
                ),
              ],
            ),
          ),
        ),
        // --- CARD : pleine largeur, coins carrés en haut/bas là où
        // header/footer sont soudés — mais comme ils sont plus étroits,
        // les coins arrondis de la carte restent visibles sur les côtés
        // (effet "étagère"). Le bouton switch est scopé à cette carte
        // seule, pour se centrer verticalement sur son séparateur. ---
        Stack(
          clipBehavior: Clip.none,
          children: <Widget>[
            Material(
              elevation: 6,
              shadowColor: Colors.black26,
              borderRadius: BorderRadius.circular(AppRadius.lg),
              child: Container(
                padding: const EdgeInsets.all(AppSpacing.md),
                decoration: BoxDecoration(
                  color: context.koraColors.surface,
                  borderRadius: BorderRadius.circular(AppRadius.lg),
                ),
                child: Column(
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        const Icon(Icons.circle,
                            size: 10, color: AppColors.digital),
                        const SizedBox(width: AppSpacing.sm),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text('DÉPART',
                                  style: AppTextStyles.eyebrow(
                                      context.koraColors.textMuted)),
                              Text(origine,
                                  style:
                                      Theme.of(context).textTheme.titleMedium),
                            ],
                          ),
                        ),
                        const SizedBox(width: 32),
                      ],
                    ),
                    Padding(
                      padding:
                          const EdgeInsets.symmetric(vertical: AppSpacing.sm),
                      child: Divider(height: 1, color: context.koraColors.line),
                    ),
                    Row(
                      children: <Widget>[
                        const Icon(Icons.circle_outlined,
                            size: 10, color: AppColors.gold),
                        const SizedBox(width: AppSpacing.sm),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text('ARRIVÉE',
                                  style: AppTextStyles.eyebrow(
                                      context.koraColors.textMuted)),
                              Text(destination,
                                  style:
                                      Theme.of(context).textTheme.titleMedium),
                            ],
                          ),
                        ),
                        const SizedBox(width: 32),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            // Bouton switch : entièrement à l'extérieur de la carte, à
            // droite, centré verticalement sur le séparateur départ/arrivée
            // Positioned(
            //   right: 10,
            //   top: 0,
            //   bottom: 0,
            //   child: Center(
            //     child: Material(
            //       color: AppColors.gold,
            //       shape: const CircleBorder(),
            //       elevation: 3,
            //       child: InkWell(
            //         customBorder: const CircleBorder(),
            //         onTap: () => context.go(AppRoutes.tournee),
            //         child: const Padding(
            //           padding: EdgeInsets.all(9),
            //           child: Icon(Icons.swap_vert,
            //               color: AppColors.onGold, size: 18),
            //         ),
            //       ),
            //     ),
            //   ),
            // ),
          ],
        ),
        // --- FOOTER : en retrait (identique au header), coins hauts
        // carrés (soudé à la carte), fond légèrement plus sombre qu'un
        // blanc pur. ---
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: _capInset),
          child: Container(
            decoration: BoxDecoration(
              color: context.koraColors.line,
              borderRadius: const BorderRadius.only(
                bottomLeft: Radius.circular(AppRadius.lg),
                bottomRight: Radius.circular(AppRadius.lg),
              ),
            ),
            child: IntrinsicHeight(
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: _MiniStat(
                      label: 'DATE',
                      value: depart != null
                          ? '${depart!.date}  ${depart!.heure}'
                          : '—',
                    ),
                  ),
                  VerticalDivider(
                      width: 1, thickness: 1, color: context.koraColors.line),
                  Expanded(
                    child: _MiniStat(
                      label: 'EMBARQUÉS',
                      value:
                          depart != null ? '$embarques / $totalPassagers' : '—',
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(label,
              style: AppTextStyles.eyebrow(context.koraColors.textMuted)),
          const SizedBox(height: 4),
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
                    color: highlighted
                        ? AppColors.gold
                        : context.koraColors.textPrimary),
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

/// Remplace le bandeau promo "Adaptateurs P501 / Tester le scanner ?"
/// (retiré, échange du 23/09/2026 : sans utilité pour le convoyeur une
/// fois le terminal en service) par un état réel et actionnable du suivi
/// de position (MEZ-101) : ce que `TrackingStatusBanner` affichait en
/// pastille technique sur la Tournée, présenté ici en carte lisible,
/// au bon endroit — l'Accueil, pas une page de sélection de départ.
class _TrackingStatusCard extends StatelessWidget {
  const _TrackingStatusCard();

  ({IconData icon, String title, String subtitle, VoidCallback? onTap})
      _contentFor(BuildContext context, TrackingState state) {
    if (!state.isActive && state.availability != TrackingAvailability.ready) {
      return (
        icon: Icons.location_off_outlined,
        title: state.availability.label,
        subtitle: state.availability.correctiveAction,
        onTap: () => context.read<TrackingCubit>().requestPermission(),
      );
    }

    if (state.isActive) {
      // `lastError` : voir docstring `TrackingState.lastError` — un envoi
      // refusé pour une raison NON réseau (permission, session expirée,
      // validation...) est le cas le plus trompeur pour le convoyeur :
      // `isOnline` reste vrai (le réseau fonctionne), mais rien ne part
      // quand même — sans ce message, "en attente d'envoi" seul laisse
      // penser à tort à un simple problème de réseau.
      final String subtitle = !state.isOnline
          ? 'Hors ligne — ${state.pendingCount} point(s) en attente'
          : state.isSyncing
              ? 'Synchronisation en cours…'
              : state.lastError != null
                  ? 'Envoi refusé — ${state.lastError}'
                  : state.pendingCount > 0
                      ? '${state.pendingCount} point(s) en attente d\'envoi'
                      : 'Position transmise en temps réel.';
      return (
        icon: state.isOnline
            ? (state.lastError != null
                ? Icons.error_outline
                : Icons.gps_fixed_rounded)
            : Icons.cloud_off_outlined,
        title: 'Suivi de position actif',
        subtitle: subtitle,
        onTap: null,
      );
    }

    return (
      icon: Icons.gps_not_fixed_rounded,
      title: 'Suivi automatique',
      subtitle: 'Démarre dès que le bus quitte l\'agence, s\'arrête à '
          'l\'arrivée.',
      onTap: null,
    );
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<TrackingCubit, TrackingState>(
      builder: (BuildContext context, TrackingState state) {
        final content = _contentFor(context, state);

        return Material(
          color: AppColors.kora,
          borderRadius: BorderRadius.circular(AppRadius.lg),
          child: InkWell(
            borderRadius: BorderRadius.circular(AppRadius.lg),
            onTap: content.onTap,
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Row(
                children: <Widget>[
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: const BoxDecoration(
                        color: AppColors.gold, shape: BoxShape.circle),
                    child:
                        Icon(content.icon, color: AppColors.onGold, size: 20),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text('SUIVI DE POSITION',
                            style: AppTextStyles.eyebrow(AppColors.gold)),
                        const SizedBox(height: 2),
                        Text(
                          content.title,
                          style: const TextStyle(
                              color: AppColors.white,
                              fontWeight: FontWeight.w700,
                              fontSize: 14),
                        ),
                        Text(
                          content.subtitle,
                          style: TextStyle(
                              color: AppColors.white.withValues(alpha: 0.65),
                              fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                  if (content.onTap != null)
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
      },
    );
  }
}
