import 'dart:ui';

import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/app_spacing.dart';
import '../../app/theme/kora_colors.dart';

/// Une action d'[ActionSpeedDial] — icône, libellé, action à exécuter.
class SpeedDialAction {
  const SpeedDialAction({
    required this.icon,
    required this.label,
    required this.onPressed,
    this.danger = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onPressed;

  /// Rendu en rouge (ex. "Annuler l'embarquement", "Clôturer les
  /// no-show") — même sémantique que `KoraButtonVariant.danger` sur
  /// l'ancien empilement de boutons qu'`ActionSpeedDial` remplace.
  final bool danger;
}

/// FloatingActionButton extensible (Speed Dial, Material 3) — remplace
/// l'empilement linéaire de `KoraButton` au bas d'une page : un FAB
/// principal (+/×) qui déploie verticalement les actions réellement
/// disponibles, chacune sur un mini-FAB étiqueté. Partagé entre
/// `DetailPassagerPage` et `ManifestePage`.
///
/// À placer en `Positioned.fill` dans un `Stack` englobant le corps de
/// la page (pas dans `Scaffold.floatingActionButton`, qui ne donne accès
/// qu'à l'espace du bouton lui-même) : à l'ouverture, ce widget floute
/// tout ce qu'il recouvre (même traitement que les dialogues —
/// `BackdropFilter`) pour bien distinguer les actions affichées du reste
/// de l'écran qu'elles survolent ; un tap en dehors referme le menu.
/// Écrit sans dépendance externe (flutter_speed_dial n'est pas dans le
/// pubspec) pour rester autonome et prévisible.
///
/// [actions] peut être vide (ex. le temps qu'une permission/un état
/// arrive à se stabiliser juste après la navigation) : se rend alors
/// invisible ([SizedBox.shrink]) plutôt que de disparaître de l'arbre —
/// TOUJOURS monter ce widget, ne jamais l'entourer d'un
/// `if (actions.isNotEmpty)` côté appelant (voir échange du 23/09/2026,
/// clignotement du FAB) : un montage/démontage répété pendant que
/// `actions` se stabilise recrée `_ActionSpeedDialState` — donc son
/// `AnimationController` — à chaque fois, ce qui peut laisser
/// apparaître puis disparaître les mini-boutons déployés avant que
/// l'état ne se stabilise. Un widget qui reste monté en continu n'a pas
/// ce problème : son `_open`/`_controller` ne changent que sur une vraie
/// action du convoyeur.
///
/// [heroTag] doit être unique par PAGE où ce widget apparaît (jamais le
/// même sur deux pages simultanément empilées) — la valeur par défaut de
/// `FloatingActionButton` (partagée entre toutes les instances sans tag
/// explicite) peut sinon entrer en collision pendant une transition de
/// page et produire le même type de scintillement.
class ActionSpeedDial extends StatefulWidget {
  const ActionSpeedDial(
      {required this.actions, required this.heroTag, super.key});

  final List<SpeedDialAction> actions;
  final String heroTag;

  @override
  State<ActionSpeedDial> createState() => _ActionSpeedDialState();
}

class _ActionSpeedDialState extends State<ActionSpeedDial>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 220),
  );
  bool _open = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  /// Si les actions disparaissent pendant que le menu est ouvert (ex.
  /// permission perdue en cours de route), referme proprement plutôt que
  /// de laisser un menu ouvert sans rien dedans.
  @override
  void didUpdateWidget(ActionSpeedDial oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_open && widget.actions.isEmpty) {
      _open = false;
      _controller.reverse();
    }
  }

  void _toggle() {
    setState(() => _open = !_open);
    if (_open) {
      _controller.forward();
    } else {
      _controller.reverse();
    }
  }

  void _runAction(VoidCallback onPressed) {
    _toggle();
    onPressed();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.actions.isEmpty) return const SizedBox.shrink();

    return Stack(
      children: <Widget>[
        // Flou de fond à l'ouverture — mêmes codes visuels qu'un
        // dialogue modal. Un tap n'importe où dessus referme le menu
        // sans déclencher ce qu'il recouvre (IgnorePointer désactivé sur
        // les items le temps que l'animation ferme le menu).
        if (_open)
          Positioned.fill(
            child: GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: _toggle,
              child: BackdropFilter(
                filter: ImageFilter.blur(sigmaX: 5, sigmaY: 5),
                child: Container(color: Colors.black.withValues(alpha: 0.1)),
              ),
            ),
          ),
        Positioned(
          bottom: AppSpacing.md,
          right: AppSpacing.md,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: <Widget>[
              // Items déployés, du plus proche du FAB principal au plus
              // loin — opacité + décalage vertical pilotés par le même
              // AnimationController que la rotation du FAB, pour un
              // déploiement fluide et cohérent.
              for (int i = widget.actions.length - 1; i >= 0; i--)
                FadeTransition(
                  opacity: _controller,
                  child: SlideTransition(
                    position: Tween<Offset>(
                      begin: const Offset(0, 0.2),
                      end: Offset.zero,
                    ).animate(CurvedAnimation(
                        parent: _controller, curve: Curves.easeOut)),
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                      child: IgnorePointer(
                        ignoring: !_open,
                        child: _SpeedDialItem(
                          action: widget.actions[i],
                          onTap: () => _runAction(widget.actions[i].onPressed),
                        ),
                      ),
                    ),
                  ),
                ),
              FloatingActionButton(
                heroTag: widget.heroTag,
                onPressed: _toggle,
                // Doré, pas marine : le fond de page en mode sombre EST
                // `AppColors.kora` (`KoraColors.dark.background`) — un FAB
                // marine y devenait invisible, confondu avec la page
                // elle-même (échange du 24/09/2026). Le doré tranche sur
                // les deux thèmes, comme `KoraButton.primary` partout
                // ailleurs dans l'app.
                backgroundColor: AppColors.gold,
                foregroundColor: AppColors.onGold,
                child: AnimatedRotation(
                  turns: _open ? 0.125 : 0, // 45° — "+" devient "×".
                  duration: const Duration(milliseconds: 220),
                  child: const Icon(Icons.add),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SpeedDialItem extends StatelessWidget {
  const _SpeedDialItem({required this.action, required this.onTap});

  final SpeedDialAction action;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;
    // Même raison que le FAB principal : doré plutôt que marine, pour ne
    // jamais se fondre dans le fond de page en mode sombre.
    final Color accent = action.danger ? AppColors.danger : AppColors.gold;
    final Color accentForeground =
        action.danger ? AppColors.white : AppColors.onGold;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Material(
          color: koraColors.surface,
          elevation: 2,
          borderRadius: BorderRadius.circular(AppRadius.md),
          child: InkWell(
            borderRadius: BorderRadius.circular(AppRadius.md),
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.sm, vertical: 6),
              child: Text(
                action.label,
                style: TextStyle(
                    color: koraColors.textPrimary,
                    fontWeight: FontWeight.w600,
                    fontSize: 13),
              ),
            ),
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        FloatingActionButton.small(
          heroTag: action.label,
          onPressed: onTap,
          backgroundColor: accent,
          foregroundColor: accentForeground,
          child: Icon(action.icon, size: 20),
        ),
      ],
    );
  }
}
