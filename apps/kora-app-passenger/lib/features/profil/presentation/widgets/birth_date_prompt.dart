import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../data/birth_date_prompt_store.dart';

/// Invite à renseigner sa date de naissance, une fois connecté.
///
/// Elle ne s'ouvre que si trois choses sont vraies : une session est
/// ouverte, le profil n'a pas de date de naissance, et la question n'a
/// jamais été posée sur cet appareil. Le jour où la date est remplie,
/// plus rien ne s'ouvre — même si la question n'avait jamais été vue.
Future<void> showBirthDatePrompt(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: context.koraColors.surface,
    isScrollControlled: true,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadius.xl)),
    ),
    builder: (_) => const _BirthDateSheet(),
  );
}

class _BirthDateSheet extends StatelessWidget {
  const _BirthDateSheet();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 44,
                height: 4,
                decoration: BoxDecoration(
                  color: koraColors.line,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Center(
              child: Container(
                width: 56,
                height: 56,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.gold.withValues(alpha: 0.18),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Icon(
                  Icons.cake_rounded,
                  size: 28,
                  color: context.koraTitleColor,
                ),
              ),
            ),
            const SizedBox(height: 14),
            Text(
              l10n.birthDatePromptTitle,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: context.koraTitleColor,
                fontSize: 19,
                fontWeight: FontWeight.w900,
                letterSpacing: -0.4,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              l10n.birthDatePromptText,
              textAlign: TextAlign.center,
              style: AppTextStyles.body(koraColors.textMuted),
            ),
            const SizedBox(height: 18),
            KoraButton(
              label: l10n.birthDatePromptAction,
              onPressed: () {
                Navigator.of(context).pop();
                context.push(AppRouter.profileEditPath);
              },
            ),
            const SizedBox(height: 8),
            KoraButton(
              label: l10n.birthDatePromptLater,
              variant: KoraButtonVariant.ghost,
              onPressed: () => Navigator.of(context).pop(),
            ),
          ],
        ),
      ),
    );
  }
}

/// Pose la question une fois, au premier passage par l'accueil.
///
/// L'accueil est le seul écran que tout le monde traverse après la
/// connexion : l'y accrocher évite d'interrompre un paiement ou une
/// recherche de départ par une feuille qui tombe du haut.
class BirthDatePromptGate extends StatefulWidget {
  const BirthDatePromptGate({
    super.key,
    required this.child,
    required this.hasBirthDate,
    required this.isSignedIn,
  });

  final Widget child;

  /// Le profil porte déjà une date de naissance.
  final bool hasBirthDate;

  /// Une session est ouverte : sans compte, il n'y a pas de profil à
  /// compléter.
  final bool isSignedIn;

  @override
  State<BirthDatePromptGate> createState() => _BirthDatePromptGateState();
}

class _BirthDatePromptGateState extends State<BirthDatePromptGate> {
  var _asked = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _maybeAsk();
  }

  @override
  void didUpdateWidget(BirthDatePromptGate old) {
    super.didUpdateWidget(old);
    // La session arrive souvent après le premier rendu : l'accueil
    // s'affiche, puis le profil se relit. Sans ce second passage, la
    // question ne serait jamais posée au lancement.
    _maybeAsk();
  }

  void _maybeAsk() {
    if (_asked || !widget.isSignedIn || widget.hasBirthDate) return;
    if (!getIt.isRegistered<BirthDatePromptStore>()) return;

    final store = getIt<BirthDatePromptStore>();
    if (store.hasSeen) return;

    _asked = true;

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) return;

      // Retenue avant d'ouvrir : une feuille fermée d'un glissement ne
      // repasse pas par un bouton, et reviendrait sans cela à chaque
      // retour sur l'accueil.
      await store.markSeen();
      if (!mounted) return;

      await showBirthDatePrompt(context);
    });
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
