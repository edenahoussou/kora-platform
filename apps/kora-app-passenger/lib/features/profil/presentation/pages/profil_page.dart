import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../auth/domain/entities/auth_user.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../booking/domain/entities/price.dart';

/// Écran `profile` de la maquette — l'onglet « Profil » : l'en-tête navy
/// avec l'identité, les soldes, les rubriques du compte et l'assistance.
///
/// L'identité vient de la session ouverte. Les soldes, eux, restent à
/// zéro : la feature `wallet` n'existe pas, et afficher un montant
/// inventé sur l'écran du compte serait pire que de n'en afficher aucun.
class ProfilPage extends StatelessWidget {
  const ProfilPage({super.key});

  /// Rien à afficher tant que `wallet` n'est pas branché.
  static const _balance = Price(0);
  static const _points = 0;

  void _soon(BuildContext context) =>
      KoraToaster.show(context, context.l10n.profileSoon);

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final user = context.select<AuthBloc, AuthUser?>(
      (bloc) => bloc.state.session?.user,
    );

    return Scaffold(
      body: ListView(
        padding: const EdgeInsets.only(bottom: 100),
        children: [
          // L'en-tête déborde sur les côtés (`margin: 0 -18px`) : il touche
          // les bords de l'écran, contrairement au reste de la page.
          _Header(
            initials: user?.initials ?? '',
            name: user?.name ?? '',
            avatarUrl: user?.avatarUrl,
            memberSince: DateTime.now().year,
          ),
          // `.profile-balance-strip { margin-top: -16px }` : la bande des
          // soldes chevauche l'en-tête.
          Transform.translate(
            offset: const Offset(0, -16),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 30),
              child: _BalanceStrip(
                balance: _balance,
                points: _points,
                onTap: () => _soon(context),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _SectionTitle(title: l10n.profileAccount),
                KoraMenuList(
                  items: [
                    KoraMenuItem(
                      icon: Icons.person_outline_rounded,
                      title: l10n.profileInfoTitle,
                      subtitle: l10n.profileInfoSubtitle,
                      onTap: () => context.push(AppRouter.profileEditPath),
                    ),
                    KoraMenuItem(
                      icon: Icons.shield_outlined,
                      title: l10n.profileSecurityTitle,
                      subtitle: l10n.profileSecuritySubtitle,
                      onTap: () => context.push(AppRouter.securityPath),
                    ),
                    KoraMenuItem(
                      icon: Icons.tune_rounded,
                      title: l10n.profilePreferencesTitle,
                      subtitle: l10n.profilePreferencesSubtitle,
                      onTap: () => context.push(AppRouter.preferencesPath),
                    ),
                  ],
                ),
                _SectionTitle(title: l10n.profileHelpSection),
                _AssistanceCard(
                  onHelp: () => context.push(AppRouter.helpPath),
                  onWhatsapp: () => context.push(AppRouter.contactPath),
                ),
                const SizedBox(height: 12),
                // Seule action réellement branchée de cet écran : le
                // reste attend ses features. La redirection vers la
                // bienvenue est l'affaire du garde de navigation, qui
                // écoute `AuthBloc`.
                const _LogoutButton(),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// `.profile-premium-header` : le bandeau navy, son cercle en fond, son
/// biseau doré et la route pointillée.
class _Header extends StatelessWidget {
  const _Header({
    required this.initials,
    required this.name,
    required this.avatarUrl,
    required this.memberSince,
  });

  final String initials;
  final String name;

  /// Photo du fournisseur social, quand la connexion en a rapporté une.
  final String? avatarUrl;

  final int memberSince;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      // L'en-tête est navy dans les deux thèmes : la barre d'état doit
      // toujours s'y afficher en clair.
      sized: false,
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      child: Container(
        clipBehavior: Clip.antiAlias,
        decoration: const BoxDecoration(
          // `linear-gradient(155deg, #00153e, var(--kora))`
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomRight,
            colors: [Color(0xFF00153E), AppColors.kora],
          ),
          borderRadius: BorderRadius.vertical(bottom: Radius.circular(34)),
        ),
        // Le dégradé finit sur `--kora`, qui est aussi le fond de page en
        // thème sombre : l'arrondi du bas s'y dissout. Un filet le
        // redessine. En `foregroundDecoration` car une bordure posée dans
        // `decoration` se peint sous les enfants.
        foregroundDecoration: isDark
            ? BoxDecoration(
                border: Border.all(color: koraColors.line),
                borderRadius: const BorderRadius.vertical(
                  bottom: Radius.circular(34),
                ),
              )
            : null,
        child: Stack(
          children: [
            // `radial-gradient(circle at 95% 0%, rgba(0,87,184,.55),
            // transparent 35%)`
            const Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: RadialGradient(
                    center: Alignment(0.9, -1),
                    radius: 0.7,
                    colors: [Color(0x8C0057B8), Colors.transparent],
                  ),
                ),
              ),
            ),
            // `::before` : le grand cercle qui sort par la droite.
            Positioned(
              right: -145,
              top: 55,
              child: Container(
                width: 240,
                height: 240,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: AppColors.white.withValues(alpha: 0.1),
                  ),
                ),
              ),
            ),
            // `::after` : le biseau doré, doublé d'un trait blanc.
            Positioned(
              right: -65,
              bottom: 34,
              child: Transform.rotate(
                angle: -24 * 3.1415926535 / 180,
                child: SizedBox(
                  width: 215,
                  height: 17,
                  child: Stack(
                    children: [
                      Positioned(
                        left: 0,
                        right: 0,
                        top: 0,
                        child: Container(
                          height: 5,
                          color: AppColors.white.withValues(alpha: 0.1),
                        ),
                      ),
                      Positioned(
                        left: 0,
                        right: 0,
                        bottom: 0,
                        child: Container(height: 5, color: AppColors.gold),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(19, 12, 19, 35),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const _HeaderLogo(),
                        _HeaderNotificationButton(
                          onTap: () =>
                              context.push(AppRouter.notificationsPath),
                        ),
                      ],
                    ),
                    const SizedBox(height: 29),
                    Row(
                      children: [
                        _Avatar(initials: initials, avatarUrl: avatarUrl),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                l10n.profileWelcome,
                                style: TextStyle(
                                  color: AppColors.white.withValues(
                                    alpha: 0.58,
                                  ),
                                  fontSize: 11,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                name,
                                style: const TextStyle(
                                  color: AppColors.white,
                                  fontSize: 20,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: -0.5,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                l10n.profileMemberSince(memberSince),
                                style: TextStyle(
                                  color: AppColors.white.withValues(
                                    alpha: 0.65,
                                  ),
                                  fontSize: 11,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 17),
                    // `.profile-route` : trois segments, celui du milieu
                    // doré — la ligne de trajet en réduction.
                    Padding(
                      padding: const EdgeInsets.only(left: 93),
                      child: Row(
                        children: [
                          _RouteSegment(width: 42),
                          SizedBox(width: 7),
                          _RouteSegment(width: 17, isGold: true),
                          SizedBox(width: 7),
                          _RouteSegment(width: 42),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RouteSegment extends StatelessWidget {
  const _RouteSegment({required this.width, this.isGold = false});

  final double width;
  final bool isGold;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: 2,
      decoration: BoxDecoration(
        color: isGold
            ? AppColors.gold
            : AppColors.white.withValues(alpha: 0.27),
        borderRadius: BorderRadius.circular(3),
      ),
    );
  }
}

/// `.profile-topline img` : le logo, un peu plus large que sur les autres
/// écrans.
class _HeaderLogo extends StatelessWidget {
  const _HeaderLogo();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 106,
      height: 42,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: Image.asset('assets/images/kora_logo.png', fit: BoxFit.cover),
      ),
    );
  }
}

/// `.profile-notification` : rond translucide bordé de blanc, sur le navy
/// de l'en-tête — contrairement à la cloche blanche des autres écrans.
class _HeaderNotificationButton extends StatelessWidget {
  const _HeaderNotificationButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 40,
      height: 40,
      child: Material(
        color: AppColors.white.withValues(alpha: 0.09),
        shape: CircleBorder(
          side: BorderSide(color: AppColors.white.withValues(alpha: 0.14)),
        ),
        child: InkWell(
          onTap: onTap,
          customBorder: const CircleBorder(),
          child: const Icon(
            Icons.notifications_none_rounded,
            size: 19,
            color: AppColors.white,
          ),
        ),
      ),
    );
  }
}

/// `.avatar-premium` : les initiales dans un carré clair, avec l'étoile
/// dorée du statut membre.
class _Avatar extends StatelessWidget {
  const _Avatar({required this.initials, this.avatarUrl});

  final String initials;
  final String? avatarUrl;

  /// La photo quand le fournisseur social en a rapporté une, les
  /// initiales sinon — et encore les initiales si l'image ne se charge
  /// pas, plutôt qu'un carré vide.
  Widget _avatarContent() {
    final url = avatarUrl;

    if (url == null || url.isEmpty) return _initialsText();

    return Image.network(
      url,
      width: 72,
      height: 72,
      fit: BoxFit.cover,
      errorBuilder: (context, error, stack) => _initialsText(),
    );
  }

  Widget _initialsText() => Text(
    initials,
    style: const TextStyle(
      color: AppColors.kora,
      fontSize: 21,
      fontWeight: FontWeight.w900,
    ),
  );

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 78,
      height: 78,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            width: 72,
            height: 72,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [AppColors.white, Color(0xFFDFE8F1)],
              ),
              borderRadius: BorderRadius.circular(23),
              border: Border.all(
                color: AppColors.white.withValues(alpha: 0.75),
                width: 2,
              ),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.18),
                  offset: const Offset(0, 15),
                  blurRadius: 35,
                ),
              ],
            ),
            clipBehavior: Clip.antiAlias,
            child: _avatarContent(),
          ),
          Positioned(
            right: 0,
            bottom: 0,
            child: Container(
              width: 25,
              height: 25,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.gold,
                border: Border.all(color: AppColors.kora, width: 3),
              ),
              child: const Icon(
                Icons.star_rounded,
                size: 12,
                color: AppColors.kora,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// `.profile-balance-strip` : solde et points, séparés par un trait.
class _BalanceStrip extends StatelessWidget {
  const _BalanceStrip({
    required this.balance,
    required this.points,
    required this.onTap,
  });

  final Price balance;
  final int points;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      constraints: const BoxConstraints(minHeight: 67),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(19),
        border: Border.all(color: koraColors.line),
        boxShadow: koraColors.cardShadow,
      ),
      child: IntrinsicHeight(
        child: Row(
          children: [
            Expanded(
              child: _BalanceHalf(
                label: l10n.profileBalanceWallet,
                value: balance.formatted,
                onTap: onTap,
              ),
            ),
            Container(width: 1, height: 31, color: koraColors.line),
            Expanded(
              child: _BalanceHalf(
                label: l10n.profileBalancePoints,
                value: '$points pts',
                onTap: onTap,
                alignEnd: true,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _BalanceHalf extends StatelessWidget {
  const _BalanceHalf({
    required this.label,
    required this.value,
    required this.onTap,
    this.alignEnd = false,
  });

  final String label;
  final String value;
  final VoidCallback onTap;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        child: Column(
          crossAxisAlignment: alignEnd
              ? CrossAxisAlignment.end
              : CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              label.toUpperCase(),
              style: AppTextStyles.eyebrow(koraColors.textMuted),
            ),
            const SizedBox(height: 4),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: context.koraTitleColor,
                fontSize: 13,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// `.assistance-card` : la carte navy d'aide, avec ses deux actions
/// séparées par un filet.
class _AssistanceCard extends StatelessWidget {
  const _AssistanceCard({required this.onHelp, required this.onWhatsapp});

  final VoidCallback onHelp;
  final VoidCallback onWhatsapp;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      clipBehavior: Clip.antiAlias,
      foregroundDecoration: isDark
          ? BoxDecoration(
              borderRadius: BorderRadius.circular(19),
              border: Border.all(color: koraColors.line),
            )
          : null,
      decoration: BoxDecoration(
        color: AppColors.kora,
        borderRadius: BorderRadius.circular(19),
        boxShadow: [
          BoxShadow(
            color: AppColors.kora.withValues(alpha: 0.14),
            offset: const Offset(0, 14),
            blurRadius: 35,
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                Container(
                  width: 35,
                  height: 35,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: AppColors.gold,
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: const Text(
                    'K',
                    style: TextStyle(
                      color: AppColors.kora,
                      fontSize: 16,
                      fontStyle: FontStyle.italic,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        l10n.profileHelpTitle,
                        style: const TextStyle(
                          color: AppColors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        l10n.profileHelpText,
                        style: TextStyle(
                          color: AppColors.white.withValues(alpha: 0.58),
                          fontSize: 10,
                          height: 1.35,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Container(height: 1, color: AppColors.white.withValues(alpha: 0.1)),
          IntrinsicHeight(
            child: Row(
              children: [
                Expanded(
                  child: _AssistanceAction(
                    icon: Icons.help_outline_rounded,
                    label: l10n.profileHelpCenter,
                    onTap: onHelp,
                  ),
                ),
                Container(
                  width: 1,
                  color: AppColors.white.withValues(alpha: 0.1),
                ),
                Expanded(
                  child: _AssistanceAction(
                    icon: Icons.chat_bubble_outline_rounded,
                    label: l10n.profileHelpWhatsapp,
                    onTap: onWhatsapp,
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

class _AssistanceAction extends StatelessWidget {
  const _AssistanceAction({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.white.withValues(alpha: 0.045),
      child: InkWell(
        onTap: onTap,
        child: Container(
          constraints: const BoxConstraints(minHeight: 48),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 15, color: AppColors.white),
              const SizedBox(width: 7),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.profile-logout` : un lien discret, pas un bouton de danger — se
/// déconnecter ne détruit rien.
class _LogoutButton extends StatelessWidget {
  const _LogoutButton();

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<AuthBloc, AuthState>(
      buildWhen: (previous, current) =>
          previous.isLoggingOut != current.isLoggingOut,
      builder: (context, auth) => TextButton(
        onPressed: auth.isLoggingOut
            ? null
            : () => context.read<AuthBloc>().add(const AuthLogoutRequested()),
        style: TextButton.styleFrom(
          minimumSize: const Size.fromHeight(42),
          foregroundColor: context.koraColors.textMuted,
        ),
        child: Text(
          auth.isLoggingOut
              ? context.l10n.profileLogoutPending
              : context.l10n.profileLogout,
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
        ),
      ),
    );
  }
}

/// `.profile-section-title` : même titre de section que le reste de l'app,
/// avec les marges propres à cet écran.
class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 19, bottom: 9),
      child: Text(title, style: AppTextStyles.heading2(context.koraTitleColor)),
    );
  }
}
