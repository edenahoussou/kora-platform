import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../booking/domain/entities/price.dart';

/// Écran `profile` de la maquette — l'onglet « Profil » : l'en-tête navy
/// avec l'identité, les soldes, les rubriques du compte et l'assistance.
///
/// Données figées : ni `auth` ni `wallet` n'existent encore. Chaque action
/// ouvre un écran qui n'est pas écrit et le dit par un toast, plutôt que
/// de mener à une page vide.
class ProfilPage extends StatelessWidget {
  const ProfilPage({super.key});

  static const _initials = 'AH';
  static const _name = 'Aïcha HOUNKPÈ';
  static const _memberSince = 2026;
  static const _balance = Price(18750);
  static const _points = 2480;

  void _soon(BuildContext context) =>
      KoraToaster.show(context, context.l10n.profileSoon);

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: ListView(
        padding: const EdgeInsets.only(bottom: 100),
        children: [
          // L'en-tête déborde sur les côtés (`margin: 0 -18px`) : il touche
          // les bords de l'écran, contrairement au reste de la page.
          const _Header(
            initials: _initials,
            name: _name,
            memberSince: _memberSince,
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
                      onTap: () => _soon(context),
                    ),
                    KoraMenuItem(
                      icon: Icons.shield_outlined,
                      title: l10n.profileSecurityTitle,
                      subtitle: l10n.profileSecuritySubtitle,
                      onTap: () => _soon(context),
                    ),
                    KoraMenuItem(
                      icon: Icons.tune_rounded,
                      title: l10n.profilePreferencesTitle,
                      subtitle: l10n.profilePreferencesSubtitle,
                      onTap: () => _soon(context),
                    ),
                  ],
                ),
                _SectionTitle(title: l10n.profileHelpSection),
                _AssistanceCard(onTap: () => _soon(context)),
                const SizedBox(height: 12),
                _LogoutButton(onTap: () => _soon(context)),
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
    required this.memberSince,
  });

  final String initials;
  final String name;
  final int memberSince;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

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
                          onTap: () => KoraToaster.show(
                            context,
                            l10n.profileNotifications,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 29),
                    Row(
                      children: [
                        _Avatar(initials: initials),
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
  const _Avatar({required this.initials});

  final String initials;

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
            child: Text(
              initials,
              style: const TextStyle(
                color: AppColors.kora,
                fontSize: 21,
                fontWeight: FontWeight.w900,
              ),
            ),
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
  const _AssistanceCard({required this.onTap});

  final VoidCallback onTap;

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
                    onTap: onTap,
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
                    onTap: onTap,
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
  const _LogoutButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return TextButton(
      onPressed: onTap,
      style: TextButton.styleFrom(
        minimumSize: const Size.fromHeight(42),
        foregroundColor: context.koraColors.textMuted,
      ),
      child: Text(
        context.l10n.profileLogout,
        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
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
