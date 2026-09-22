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
/// l'empilement linéaire de `KoraButton` au bas d'une page (refonte
/// UI/UX du 21/09/2026) : un FAB principal (+/×) qui déploie
/// verticalement les actions réellement disponibles, chacune sur un
/// mini-FAB étiqueté. Partagé entre `DetailPassagerPage` et
/// `ManifestePage` (même style, mêmes deux écrans concernés par la
/// refonte) — écrit sans dépendance externe (flutter_speed_dial n'est
/// pas dans le pubspec) pour rester autonome et prévisible.
class ActionSpeedDial extends StatefulWidget {
  const ActionSpeedDial({required this.actions, super.key});

  final List<SpeedDialAction> actions;

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
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: <Widget>[
        // Items déployés, du plus proche du FAB principal au plus loin —
        // opacité + décalage vertical pilotés par le même AnimationController
        // que la rotation du FAB, pour un déploiement fluide et cohérent.
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
          onPressed: _toggle,
          backgroundColor: AppColors.kora,
          foregroundColor: AppColors.white,
          child: AnimatedRotation(
            turns: _open ? 0.125 : 0, // 45° — "+" devient "×".
            duration: const Duration(milliseconds: 220),
            child: const Icon(Icons.add),
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
    final Color accent = action.danger ? AppColors.danger : AppColors.kora;

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
          foregroundColor: AppColors.white,
          child: Icon(action.icon, size: 20),
        ),
      ],
    );
  }
}
