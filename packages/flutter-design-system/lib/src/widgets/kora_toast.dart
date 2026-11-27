import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';
import '../motion/app_motion.dart';

/// `.toast` du prototype : le message qui confirme qu'une action a bien
/// eu lieu — « Un nouveau code a été envoyé. », « Origine et destination
/// inversées » — posé en bas à droite de l'écran, puis retiré tout seul.
///
/// À réserver à ce qui est **déjà fait** : un toast ne se lit pas
/// forcément, il ne doit donc jamais porter une information dont dépend
/// la suite (une erreur bloquante appelle un [KoraAlert] ou un écran
/// d'état, pas un message qui s'efface au bout de deux secondes).
///
/// Le plus souvent, on ne construit pas ce widget directement :
/// [KoraToaster.show] s'en charge, empilement et disparition compris.
///
/// Exemple d'utilisation :
/// ```dart
/// KoraToaster.show(context, 'Un nouveau code a été envoyé.');
/// ```
/// Couleurs du toast, résolues une fois par la région qui le porte.
@immutable
class _KoraToastPalette {
  const _KoraToastPalette({required this.isDark, required this.colors});

  factory _KoraToastPalette.of(BuildContext context) => _KoraToastPalette(
    isDark: Theme.of(context).brightness == Brightness.dark,
    colors: context.koraColors,
  );

  final bool isDark;
  final KoraColors colors;
}

class KoraToast extends StatelessWidget {
  const KoraToast({super.key, required this.message}) : _palette = null;

  /// Utilisé par [KoraToaster] : la région a déjà résolu les couleurs.
  const KoraToast._themed({
    required this.message,
    required _KoraToastPalette this._palette,
  });

  final String message;

  /// Couleurs déjà résolues par la région. Nul en usage direct, où le
  /// thème est lu au moment du rendu.
  final _KoraToastPalette? _palette;

  @override
  Widget build(BuildContext context) {
    // Le toast vit dans un `Overlay`, hors de l'arbre du `Scaffold` :
    // sans `Material` au-dessus de lui, Flutter dessine son texte avec
    // le style de secours — jaune et souligné.
    return Material(type: MaterialType.transparency, child: _body(context));
  }

  Widget _body(BuildContext context) {
    // Les couleurs sont résolues par l'appelant : un toast se démonte
    // pendant que son animation de sortie tourne, et interroger le thème
    // à ce moment-là lève « Looking up a deactivated widget's ancestor ».
    final palette = _palette ?? _KoraToastPalette.of(context);
    final isDark = palette.isDark;
    final koraColors = palette.colors;

    return Container(
      // `.toast { max-width: 310px }` — au-delà, un message tient sur
      // plusieurs lignes plutôt que de barrer tout l'écran.
      constraints: const BoxConstraints(maxWidth: 310),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        // `background: var(--kora)` vise une page claire. En thème
        // sombre c'est la couleur du fond : le toast s'y dissout. On lui
        // donne alors la surface des cards et un filet qui en dessine
        // les bords.
        color: isDark ? koraColors.surface : AppColors.kora,
        borderRadius: BorderRadius.circular(AppRadius.base),
        border: isDark ? Border.all(color: koraColors.line) : null,
        // `--shadow: 0 24px 60px rgba(0,29,78,.14)`
        boxShadow: [
          BoxShadow(
            color: AppColors.kora.withValues(alpha: 0.14),
            offset: const Offset(0, 24),
            blurRadius: 60,
          ),
        ],
      ),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            // `border-left: 4px solid var(--gold)`. Posé en élément et
            // non en bordure : une bordure de couleurs différentes selon
            // les côtés interdit le `borderRadius`.
            const SizedBox(width: 4, child: ColoredBox(color: AppColors.gold)),
            Flexible(
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 13,
                ),
                child: Text(
                  message,
                  style: const TextStyle(
                    color: AppColors.white,
                    fontSize: 12,
                    height: 1.35,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Affiche les [KoraToast] par-dessus l'écran courant, en bas à droite,
/// empilés du plus ancien au plus récent — la `.toast-region` du
/// prototype.
///
/// Passe par l'`Overlay` plutôt que par `ScaffoldMessenger` : un
/// `SnackBar` Material est centré en bas et occupe la largeur de l'écran,
/// là où la maquette pose un message court dans l'angle, sans bouger le
/// contenu ni bloquer les boutons du bas.
class KoraToaster {
  const KoraToaster._();

  /// `window.setTimeout(() => el.remove(), 2600)`
  static const Duration defaultDuration = Duration(milliseconds: 2600);

  static final Map<OverlayState, _ToastRegionHandle> _regions = {};

  /// Pose [message] en bas à droite et le retire au bout de [duration].
  ///
  /// Plusieurs appels successifs s'empilent au lieu de se remplacer : un
  /// utilisateur qui enchaîne deux actions doit voir les deux
  /// confirmations, pas seulement la dernière.
  static void show(
    BuildContext context,
    String message, {
    Duration duration = defaultDuration,
  }) {
    // `Overlay.of` lève sur un contexte posé **au-dessus** du
    // `Navigator` — un écouteur global, par exemple. Un message
    // d'information ne vaut pas de faire tomber l'app : faute
    // d'`Overlay`, on renonce au toast plutôt qu'à l'écran.
    final overlay = Overlay.maybeOf(context, rootOverlay: true);
    if (overlay == null) return;

    final handle = _regions.putIfAbsent(overlay, () {
      final key = GlobalKey<_ToastRegionState>();
      final entry = OverlayEntry(builder: (context) => _ToastRegion(key: key));
      overlay.insert(entry);
      return _ToastRegionHandle(entry: entry, key: key);
    });

    // La première frame de l'OverlayEntry n'est pas encore construite au
    // moment de l'insertion : son State n'existe donc pas. On attend la
    // fin de la frame en cours pour le joindre.
    void push() => handle.key.currentState?.push(
      message,
      duration,
      onEmpty: () {
        handle.entry.remove();
        _regions.remove(overlay);
      },
    );

    if (handle.key.currentState == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) => push());
    } else {
      push();
    }
  }
}

class _ToastRegionHandle {
  const _ToastRegionHandle({required this.entry, required this.key});

  final OverlayEntry entry;
  final GlobalKey<_ToastRegionState> key;
}

/// `.toast-region { position: fixed; right: 24px; bottom: 24px; gap: 8px }`
class _ToastRegion extends StatefulWidget {
  const _ToastRegion({super.key});

  @override
  State<_ToastRegion> createState() => _ToastRegionState();
}

class _ToastRegionState extends State<_ToastRegion> {
  final List<_ToastEntry> _toasts = [];
  int _nextId = 0;
  VoidCallback? _onEmpty;

  void push(String message, Duration duration, {VoidCallback? onEmpty}) {
    _onEmpty = onEmpty;
    setState(() => _toasts.add(_ToastEntry(_nextId++, message, duration)));
  }

  void _remove(int id) {
    if (!mounted) return;
    setState(() => _toasts.removeWhere((toast) => toast.id == id));
    if (_toasts.isEmpty) _onEmpty?.call();
  }

  @override
  Widget build(BuildContext context) {
    final palette = _KoraToastPalette.of(context);

    return Positioned(
      right: AppSpacing.xl,
      bottom: AppSpacing.xl,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        mainAxisSize: MainAxisSize.min,
        children: [
          for (final toast in _toasts) ...[
            if (toast != _toasts.first) const SizedBox(height: AppSpacing.sm),
            _AnimatedToast(
              key: ValueKey(toast.id),
              message: toast.message,
              palette: palette,
              duration: toast.duration,
              onDismissed: () => _remove(toast.id),
            ),
          ],
        ],
      ),
    );
  }
}

@immutable
class _ToastEntry {
  const _ToastEntry(this.id, this.message, this.duration);

  final int id;
  final String message;
  final Duration duration;
}

/// `@keyframes toastIn { from { opacity: 0; transform: translateY(8px) } }`,
/// rejoué à l'envers pour la sortie — un message qui disparaît d'un coup
/// laisse croire à un bug d'affichage.
class _AnimatedToast extends StatefulWidget {
  const _AnimatedToast({
    super.key,
    required this.message,
    required this.palette,
    required this.duration,
    required this.onDismissed,
  });

  final String message;
  final _KoraToastPalette palette;
  final Duration duration;
  final VoidCallback onDismissed;

  @override
  State<_AnimatedToast> createState() => _AnimatedToastState();
}

class _AnimatedToastState extends State<_AnimatedToast>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 250),
  );

  late final Animation<double> _curve = CurvedAnimation(
    parent: _controller,
    curve: AppMotion.ease,
  );

  @override
  void initState() {
    super.initState();
    _play();
  }

  Future<void> _play() async {
    await _controller.forward();
    await Future<void>.delayed(widget.duration);
    if (!mounted) return;
    await _controller.reverse();
    if (!mounted) return;
    widget.onDismissed();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: _curve,
      child: AnimatedBuilder(
        animation: _curve,
        builder: (context, child) => Transform.translate(
          offset: Offset(0, 8 * (1 - _curve.value)),
          child: child,
        ),
        child: KoraToast._themed(
          message: widget.message,
          palette: widget.palette,
        ),
      ),
    );
  }
}
