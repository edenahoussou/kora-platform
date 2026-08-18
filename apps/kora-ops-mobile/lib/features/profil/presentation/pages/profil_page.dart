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
import '../../../../core/widgets/notification_button.dart';
import '../../../../injection/injection.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../auth/presentation/cubit/login_cubit.dart';

/// Onglet Profil — reproduit trait pour trait l'écran Profil de
/// `kora-passager` (voir échange du 18/08/2026) : en-tête marine incurvé
/// (logo, avatar + badge étoile, identité), carte flottante à deux
/// indicateurs, section "Mon compte" (3 cartes), section "Besoin d'aide ?"
/// (carte marine), déconnexion. Les indicateurs Solde Wallet/Points
/// Fidélité (non pertinents pour un agent OPS) sont remplacés par des
/// statistiques d'activité — voir [_StatsCard].
class ProfilPage extends StatelessWidget {
  const ProfilPage({super.key});

  String _initials(String fullName) {
    final List<String> parts = fullName.trim().split(' ');
    if (parts.length < 2) {
      return parts.isEmpty ? '?' : parts.first.substring(0, 1).toUpperCase();
    }
    return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final AuthState authState = context.watch<AuthCubit>().state;
    final String fullName =
        authState is AuthAuthenticated ? authState.user.fullName : 'Agent';
    final String role =
        authState is AuthAuthenticated ? authState.user.role : '';

    return Scaffold(
      backgroundColor: context.koraColors.background,
      body: AnnotatedRegion<SystemUiOverlayStyle>(
        sized: false,
        value: const SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: Brightness.light,
          statusBarBrightness: Brightness.dark,
        ),
        // ✅ CORRIGÉ : Utilisation de SingleChildScrollView pour un scroll garanti
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Stack(
                clipBehavior: Clip.none,
                children: <Widget>[
                  _ProfilHeader(
                      fullName: fullName,
                      role: role,
                      initials: _initials(fullName)),
                  const Positioned(
                      left: AppSpacing.md,
                      right: AppSpacing.md,
                      bottom: -46,
                      child: _StatsCard()),
                ],
              ),
              const SizedBox(height: 64),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Text('Mon compte',
                        style: AppTextStyles.heading2(
                            context.koraColors.textPrimary)),
                    const SizedBox(height: AppSpacing.sm),
                    _AccountTile(
                      icon: Icons.person_outline,
                      title: 'Mes informations',
                      subtitle: 'Identité et coordonnées',
                      onTap: () => context.push(AppRoutes.mesInformations),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    _AccountTile(
                      icon: Icons.shield_outlined,
                      title: 'Sécurité',
                      subtitle: 'PIN, email et appareils',
                      highlighted: true,
                      onTap: () => context.push(AppRoutes.securite),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    _AccountTile(
                      icon: Icons.tune,
                      title: 'Préférences',
                      subtitle: 'Alertes, langue, thème',
                      onTap: () => context.push(AppRoutes.preferences),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Text('Besoin d\'aide ?',
                        style: AppTextStyles.heading2(
                            context.koraColors.textPrimary)),
                    const SizedBox(height: AppSpacing.sm),
                    const _HelpCard(),
                    const SizedBox(height: AppSpacing.lg),
                    Center(
                      child: TextButton(
                        onPressed: () async {
                          final bool confirmed = await KoraDialogs.confirm(
                            context,
                            title: 'Se déconnecter ?',
                            description:
                                'Vous devrez ressaisir votre PIN pour vous reconnecter.',
                            confirmLabel: 'OK',
                            cancelLabel: 'Non',
                          );
                          if (confirmed && context.mounted) {
                            getIt<LoginCubit>().reset();
                            await context.read<AuthCubit>().logout();
                          }
                        },
                        child: const Text('Se déconnecter',
                            style: TextStyle(color: AppColors.danger)),
                      ),
                    ),
                    // ✅ CORRIGÉ : Ajout de l'espace système en bas (Home indicator)
                    // pour s'assurer que le bouton de déconnexion soit entièrement visible
                    SizedBox(
                        height: MediaQuery.of(context).padding.bottom +
                            AppSpacing.xl),
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

class _ProfilHeader extends StatelessWidget {
  const _ProfilHeader(
      {required this.fullName, required this.role, required this.initials});

  final String fullName;
  final String role;
  final String initials;

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
            AppSpacing.md, AppSpacing.md, AppSpacing.md, 70),
        decoration: const BoxDecoration(color: AppColors.kora),
        child: Stack(
          clipBehavior: Clip.none,
          children: <Widget>[
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
              bottom: 20,
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
                      Image.asset('assets/images/kora_logo_light.png',
                          height: 40, fit: BoxFit.contain),
                      const NotificationButton(onNavy: true),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.lg),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: <Widget>[
                    Stack(
                      clipBehavior: Clip.none,
                      children: <Widget>[
                        Container(
                          width: 58,
                          height: 58,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: AppColors.white,
                            borderRadius: BorderRadius.circular(AppRadius.lg),
                          ),
                          child: Text(
                            initials,
                            style: const TextStyle(
                              color: AppColors.kora,
                              fontWeight: FontWeight.w800,
                              fontSize: 20,
                            ),
                          ),
                        ),
                        Positioned(
                          bottom: -4,
                          right: -4,
                          child: Container(
                            width: 22,
                            height: 22,
                            alignment: Alignment.center,
                            decoration: BoxDecoration(
                              color: AppColors.gold,
                              shape: BoxShape.circle,
                              border:
                                  Border.all(color: AppColors.kora, width: 2),
                            ),
                            child: const Icon(Icons.star,
                                size: 11, color: AppColors.onGold),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            'Bienvenue dans votre espace',
                            style: TextStyle(
                                color: AppColors.white.withValues(alpha: 0.65),
                                fontSize: 11),
                          ),
                          Text(
                            fullName,
                            style: const TextStyle(
                                color: AppColors.white,
                                fontWeight: FontWeight.w800,
                                fontSize: 17),
                            overflow: TextOverflow.ellipsis,
                          ),
                          Text(
                            role.isEmpty ? '' : 'Agent KORA · $role',
                            style: TextStyle(
                                color: AppColors.white.withValues(alpha: 0.65),
                                fontSize: 11),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Remplace Solde Wallet / Points Fidélité (non pertinents pour un agent
/// OPS, voir échange du 18/08/2026) par deux indicateurs d'activité.
class _StatsCard extends StatelessWidget {
  const _StatsCard();

  @override
  Widget build(BuildContext context) {
    return Material(
      elevation: 6,
      shadowColor: Colors.black26,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: Container(
        decoration: BoxDecoration(
          color: context.koraColors.surface,
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        child: IntrinsicHeight(
          child: Row(
            children: <Widget>[
              const Expanded(
                  child: _StatCell(label: 'COURSES EFFECTUÉES', value: '128')),
              VerticalDivider(
                  width: 1, thickness: 1, color: context.koraColors.line),
              const Expanded(
                  child:
                      _StatCell(label: 'TAUX DE PONCTUALITÉ', value: '96 %')),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatCell extends StatelessWidget {
  const _StatCell({required this.label, required this.value});

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
                fontWeight: FontWeight.w800,
                fontSize: 15,
                color: context.koraColors.textPrimary),
          ),
        ],
      ),
    );
  }
}

class _AccountTile extends StatelessWidget {
  const _AccountTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.highlighted = false,
  });

  final IconData icon;
  final String title;
  final String subtitle;
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
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(
                color: highlighted ? AppColors.gold : Colors.transparent,
                width: 1.5),
          ),
          child: Row(
            children: <Widget>[
              Container(
                width: 42,
                height: 42,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: context.koraColors.background,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child:
                    Icon(icon, size: 20, color: context.koraColors.textPrimary),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(title, style: Theme.of(context).textTheme.titleMedium),
                    Text(subtitle,
                        style: TextStyle(
                            color: context.koraColors.textMuted, fontSize: 12)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: AppColors.digital),
            ],
          ),
        ),
      ),
    );
  }
}

class _HelpCard extends StatelessWidget {
  const _HelpCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
          color: AppColors.kora,
          borderRadius: BorderRadius.circular(AppRadius.lg)),
      child: Column(
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.all(AppSpacing.md),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Container(
                  width: 36,
                  height: 36,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: AppColors.gold,
                    borderRadius: BorderRadius.circular(AppRadius.sm),
                  ),
                  child: const Text(
                    'K',
                    style: TextStyle(
                        color: AppColors.onGold,
                        fontWeight: FontWeight.w900,
                        fontSize: 16),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      const Text(
                        'Nous sommes à vos côtés',
                        style: TextStyle(
                            color: AppColors.white,
                            fontWeight: FontWeight.w700,
                            fontSize: 14),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Une question sur une tournée, un billet ou un incident terrain ?',
                        style: TextStyle(
                            color: AppColors.white.withValues(alpha: 0.65),
                            fontSize: 11),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Divider(height: 1, color: AppColors.white.withValues(alpha: 0.12)),
          IntrinsicHeight(
            child: Row(
              children: <Widget>[
                Expanded(
                  child: _HelpAction(
                    icon: Icons.help_outline,
                    label: 'Centre d\'aide',
                    onTap: () => KoraDialogs.info(
                      context,
                      title: 'Centre d\'aide',
                      description:
                          'Contactez votre superviseur d\'agence pour toute question.',
                    ),
                  ),
                ),
                VerticalDivider(
                    width: 1,
                    thickness: 1,
                    color: AppColors.white.withValues(alpha: 0.12)),
                Expanded(
                  child: _HelpAction(
                    icon: Icons.chat_bubble_outline,
                    label: 'WhatsApp KORA',
                    onTap: () => KoraDialogs.info(
                      context,
                      title: 'WhatsApp KORA',
                      description:
                          'Le support WhatsApp sera disponible prochainement.',
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HelpAction extends StatelessWidget {
  const _HelpAction(
      {required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(icon,
                size: 16, color: AppColors.white.withValues(alpha: 0.85)),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                label,
                style: TextStyle(
                    color: AppColors.white.withValues(alpha: 0.85),
                    fontSize: 12),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
