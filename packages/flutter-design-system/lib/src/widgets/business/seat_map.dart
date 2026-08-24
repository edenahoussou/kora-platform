import 'package:flutter/material.dart';

import '../../colors/app_colors.dart';
import '../../colors/kora_colors.dart';
import '../../dimens/app_dimens.dart';

/// Les 6 états d'un siège, repris de l'écran `seats` du prototype
/// (`docs/maquettes/kora-app-passenger/js/app.js`, L1660 ; légende
/// `.seat-legend` correspondante, L1672) : `Libre`, `Votre choix`,
/// `Réservé`, `Payé`, `Embarqué`, `Bloqué`.
enum KoraSeatStatus {
  /// "Libre" — sélectionnable.
  available,

  /// "Votre choix" — sélectionné par l'utilisateur courant, désélectionnable.
  selected,

  /// "Réservé" — bloqué temporairement le temps qu'un autre passager
  /// termine son paiement (`.seat.held`, bordure en pointillés, minuteur
  /// optionnel via [KoraSeat.timerLabel]).
  held,

  /// "Payé" — déjà vendu et payé.
  paid,

  /// "Embarqué" — passager déjà monté à bord.
  embarked,

  /// "Bloqué" — indisponible pour raison technique (texte barré).
  blocked,
}

/// Un siège du plan de bus.
class KoraSeat {
  const KoraSeat({
    required this.label,
    this.status = KoraSeatStatus.available,
    this.timerLabel,
  });

  /// Ex: "12A".
  final String label;
  final KoraSeatStatus status;

  /// Affiché à la place de [label] quand `status == held` (ex: `'06:18'`,
  /// le temps restant avant libération automatique du siège). CSS de
  /// référence : `label = cls === "held" ? <small>06:18</small> : id`
  /// (`js/app.js`, L1661). `null` : le label normal reste affiché.
  final String? timerLabel;

  KoraSeat copyWith({KoraSeatStatus? status}) {
    return KoraSeat(
      label: label,
      status: status ?? this.status,
      timerLabel: timerLabel,
    );
  }
}

/// Apparence résolue d'un état de siège — calculée une seule fois ici et
/// réutilisée à la fois par [_SeatButton] (le vrai siège) et [_Legend]
/// (sa pastille), pour que la légende représente *exactement* les
/// couleurs réellement affichées. Le prototype CSS ne fait pas ça : sa
/// légende (`.seat-legend .available`, etc.) utilise des teintes propres,
/// légèrement différentes des sièges eux-mêmes (`.seat`) — on évite
/// volontairement cette incohérence.
typedef _SeatVisual = ({
  Color background,
  Color foreground,
  Color border,
  bool dashed,
  bool strikethrough,
});

_SeatVisual _seatVisual(
  KoraSeatStatus status, [
  Map<KoraSeatStatus, Color>? palette,
]) {
  final override = palette?[status];
  if (override != null) {
    // Un état recoloré garde sa forme — barré pour un siège bloqué,
    // pointillés pour un siège retenu — seul son remplissage change.
    final base = _seatVisual(status);
    return (
      background: override,
      // Le texte suit le fond : blanc sur une couleur soutenue, navy
      // sur une couleur claire.
      foreground: override.computeLuminance() > 0.55
          ? AppColors.kora
          : AppColors.white,
      border: override,
      dashed: base.dashed,
      strikethrough: base.strikethrough,
    );
  }

  switch (status) {
    case KoraSeatStatus.available:
      return (
        background: AppColors.white,
        foreground: AppColors.kora,
        border: AppColors.seatBorderLight,
        dashed: false,
        strikethrough: false,
      );
    case KoraSeatStatus.selected:
      return (
        background: AppColors.gold,
        foreground: AppColors.kora,
        border: AppColors.gold,
        dashed: false,
        strikethrough: false,
      );
    case KoraSeatStatus.held:
      return (
        background: AppColors.seatHeldBackgroundLight,
        foreground: AppColors.kora,
        border: AppColors.gold,
        dashed: true,
        strikethrough: false,
      );
    case KoraSeatStatus.paid:
      return (
        background: AppColors.digital,
        foreground: AppColors.white,
        border: AppColors.digital,
        dashed: false,
        strikethrough: false,
      );
    case KoraSeatStatus.embarked:
      return (
        background: AppColors.danger,
        foreground: AppColors.white,
        border: AppColors.danger,
        dashed: false,
        strikethrough: false,
      );
    case KoraSeatStatus.blocked:
      return (
        background: AppColors.mist,
        foreground: AppColors.seatBlockedTextLight,
        border: AppColors.seatBorderLight,
        dashed: false,
        strikethrough: true,
      );
  }
}

/// Plan de sièges d'un bus — équivalent `.premium-bus-shell` +
/// `.premium-seat-grid` du prototype (`docs/maquettes/kora-app-passenger/
/// css/style.css`, L1541-1558), légende `.seat-legend` comprise
/// (L1672) : grille de sièges avec une allée centrale, sièges
/// sélectionnables tant qu'ils sont libres ou déjà choisis par
/// l'utilisateur courant.
///
/// **Grille en vraies rangées (`Row` par rangée), pas un `Wrap`** :
/// l'allée centrale doit rester à la même position sur chaque rangée
/// quelle que soit la largeur disponible — un `Wrap` ne garantit pas ça
/// (il replie ses enfants dès que la largeur ne tombe pas juste, ce qui
/// peut désaligner les colonnes d'une rangée à l'autre).
///
/// **Panneau et sièges "Libre" toujours clairs, quel que soit le thème**
/// — comme [DepartureCard]/[TicketView]/[ConnectionResultCard] : le
/// prototype ne définit aucune règle `.dark-mode` pour `.premium-bus-shell`
/// ou `.seat`. D'où l'usage de couleurs fixes (`AppColors.*`) partout dans
/// ce fichier plutôt que du thème contextuel (`koraColors.*`).
///
/// Exemple d'utilisation :
/// ```dart
/// SeatMap(
///   seats: [
///     KoraSeat(label: '1A'),
///     KoraSeat(label: '1B', status: KoraSeatStatus.paid),
///     KoraSeat(label: '1C', status: KoraSeatStatus.selected),
///     KoraSeat(label: '1D', status: KoraSeatStatus.held, timerLabel: '06:18'),
///   ],
///   onSeatTap: (seat) => ...,
/// )
/// ```
class SeatMap extends StatelessWidget {
  const SeatMap({
    super.key,
    required this.seats,
    this.columns = 4,
    this.showLegend = true,
    this.onSeatTap,
    this.palette,
    this.legendStatuses,
  });

  final List<KoraSeat> seats;

  /// Nombre de sièges par rangée. L'allée est insérée automatiquement au
  /// milieu (entre les colonnes `columns ~/ 2 - 1` et `columns ~/ 2`).
  final int columns;

  /// Affiche la légende des 6 états au-dessus du plan — équivalent
  /// `.seat-legend`, toujours présente sur l'écran réel.
  final bool showLegend;

  final ValueChanged<KoraSeat>? onSeatTap;

  /// Remplace les couleurs de certains états.
  ///
  /// Le plan sert deux applications : le passager, qui choisit une place,
  /// et l'équipage, qui contrôle un embarquement. Les couleurs de la
  /// maquette parlent à l'équipage — `payé`, `embarqué`, `retenu`. Le
  /// passager, lui, ne voit que quatre états et suit la légende de son
  /// ticket. Chacun passe donc la sienne plutôt que d'imposer l'autre.
  final Map<KoraSeatStatus, Color>? palette;

  /// Les états à montrer dans la légende. Les six par défaut.
  final List<KoraSeatStatus>? legendStatuses;

  @override
  Widget build(BuildContext context) {
    final aisleAfter = columns ~/ 2 - 1;
    final rows = <Widget>[];
    for (var start = 0; start < seats.length; start += columns) {
      if (rows.isNotEmpty) rows.add(const SizedBox(height: 8));
      rows.add(
        _SeatRow(
          seats: seats.sublist(start, (start + columns).clamp(0, seats.length)),
          columns: columns,
          aisleAfter: aisleAfter,
          onTap: onSeatTap,
          palette: palette,
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (showLegend) ...[
          _Legend(palette: palette, statuses: legendStatuses),
          const SizedBox(height: AppSpacing.md),
        ],
        Container(
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
          decoration: BoxDecoration(
            color: AppColors.white,
            border: Border.all(color: AppColors.seatPanelBorderLight),
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(42),
              topRight: Radius.circular(42),
              bottomLeft: Radius.circular(AppRadius.xl),
              bottomRight: Radius.circular(AppRadius.xl),
            ),
            boxShadow: AppShadows.card,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const _BusFront(),
              const SizedBox(height: AppSpacing.md),
              ...rows,
              const SizedBox(height: 10),
              const _BusRear(),
            ],
          ),
        ),
      ],
    );
  }
}

/// `.premium-bus-front` : le volant du chauffeur, l'orientation du plan et
/// la porte de sortie. Sans ce repère, rien ne dit quel bout de la grille
/// est l'avant du bus.
class _BusFront extends StatelessWidget {
  const _BusFront();

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        // `.driver-wheel` : un cercle dans un cercle.
        Container(
          width: 25,
          height: 25,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.mist,
            border: Border.all(color: AppColors.seatBorderLight),
          ),
          child: Container(
            width: 11,
            height: 11,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.kora, width: 1.5),
            ),
          ),
        ),
        const Expanded(child: _BusLabel('AVANT DU BUS')),
        // `.bus-door`
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 5),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(5),
            border: Border.all(color: AppColors.seatBorderLight),
          ),
          child: const _BusLabel('SORTIE', size: 6),
        ),
      ],
    );
  }
}

/// `.bus-rear-label` : le repère de l'autre extrémité, entre deux filets.
class _BusRear extends StatelessWidget {
  const _BusRear();

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const _RearDash(),
        const SizedBox(width: 8),
        const _BusLabel('ARRIÈRE DU BUS'),
        const SizedBox(width: 8),
        const _RearDash(),
      ],
    );
  }
}

class _RearDash extends StatelessWidget {
  const _RearDash();

  @override
  Widget build(BuildContext context) {
    return Container(width: 34, height: 1, color: AppColors.seatBorderLight);
  }
}

/// Les libellés de carrosserie, tous dans le même gris discret : le
/// panneau restant blanc quel que soit le thème, cette couleur l'est aussi.
class _BusLabel extends StatelessWidget {
  const _BusLabel(this.text, {this.size = 7});

  final String text;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      textAlign: TextAlign.center,
      style: TextStyle(
        color: AppColors.mutedLight,
        fontSize: size,
        letterSpacing: size * 0.08,
      ),
    );
  }
}

/// Une rangée complète de [columns] sièges (avec l'allée centrale), même
/// si la dernière rangée de [seats] est incomplète — les emplacements
/// manquants restent vides mais gardent leur place pour que l'allée ne
/// bouge jamais d'une rangée à l'autre.
class _SeatRow extends StatelessWidget {
  const _SeatRow({
    required this.seats,
    required this.columns,
    required this.aisleAfter,
    this.onTap,
    this.palette,
  });

  final List<KoraSeat> seats;
  final int columns;
  final int aisleAfter;
  final ValueChanged<KoraSeat>? onTap;
  final Map<KoraSeatStatus, Color>? palette;

  @override
  Widget build(BuildContext context) {
    // `.seat-grid { grid-template-columns: repeat(5, 1fr) }` (4 sièges +
    // 1 "colonne" allée, toutes de largeur ÉGALE — `js/app.js`, L1655 :
    // `["A","B","aisle","C","D"]`). Ce n'est pas un gap plus large entre
    // deux sièges : l'allée est une vraie 5e colonne vide, aussi large
    // qu'un siège. D'où l'`Expanded` vide ci-dessous plutôt qu'un simple
    // `SizedBox` de quelques pixels.
    final slots = <Widget>[
      for (var c = 0; c < columns; c++) ...[
        if (c == aisleAfter + 1) const SizedBox(),
        c < seats.length
            ? _SeatButton(seat: seats[c], onTap: onTap, palette: palette)
            : const SizedBox(),
      ],
    ];

    return Row(
      children: [
        for (var i = 0; i < slots.length; i++) ...[
          if (i != 0) const SizedBox(width: 8),
          Expanded(child: AspectRatio(aspectRatio: 1, child: slots[i])),
        ],
      ],
    );
  }
}

class _SeatButton extends StatelessWidget {
  const _SeatButton({required this.seat, this.onTap, this.palette});

  final KoraSeat seat;
  final ValueChanged<KoraSeat>? onTap;
  final Map<KoraSeatStatus, Color>? palette;

  bool get _isTappable =>
      seat.status == KoraSeatStatus.available ||
      seat.status == KoraSeatStatus.selected;

  @override
  Widget build(BuildContext context) {
    final visual = _seatVisual(seat.status, palette);
    final showTimer =
        seat.status == KoraSeatStatus.held && seat.timerLabel != null;
    final label = showTimer ? seat.timerLabel! : seat.label;

    return Material(
      color: visual.background,
      borderRadius: BorderRadius.circular(AppRadius.sm),
      child: InkWell(
        onTap: _isTappable ? () => onTap?.call(seat) : null,
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: DecoratedBox(
          // `.seat.held { border-style: dashed }` : Flutter n'a pas de
          // style de bordure pointillée natif — bordure pleine ici,
          // remplacée par un vrai pointillé via [_DashedBorderPainter]
          // quand `dashed` (voir plus bas).
          decoration: BoxDecoration(
            border: visual.dashed ? null : Border.all(color: visual.border),
            borderRadius: BorderRadius.circular(AppRadius.sm),
          ),
          child: CustomPaint(
            foregroundPainter: visual.dashed
                ? _DashedBorderPainter(
                    color: visual.border,
                    radius: AppRadius.sm,
                  )
                : null,
            child: Center(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: showTimer ? 7 : 9,
                  fontWeight: FontWeight.w800,
                  color: visual.foreground,
                  decoration: visual.strikethrough
                      ? TextDecoration.lineThrough
                      : null,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Légende des 6 états — équivalent `.seat-legend` (`docs/maquettes/
/// kora-app-passenger/js/app.js`, L1672). Chaque pastille réutilise
/// [_seatVisual], donc représente fidèlement la couleur réelle du siège
/// (contrairement au CSS d'origine, voir la note sur [_SeatVisual]).
class _Legend extends StatelessWidget {
  const _Legend({this.palette, this.statuses});

  final Map<KoraSeatStatus, Color>? palette;
  final List<KoraSeatStatus>? statuses;

  static const _entries = [
    ('Libre', KoraSeatStatus.available),
    ('Votre choix', KoraSeatStatus.selected),
    ('Réservé', KoraSeatStatus.held),
    ('Payé', KoraSeatStatus.paid),
    ('Embarqué', KoraSeatStatus.embarked),
    ('Bloqué', KoraSeatStatus.blocked),
  ];

  @override
  Widget build(BuildContext context) {
    // Contrairement aux sièges (toujours sur le panneau blanc fixe), la
    // légende est posée directement sur le fond de l'écran — qui, lui,
    // change bien avec le thème (`koraColors.background`). Un
    // `AppColors.*` fixe y serait donc invisible en mode sombre (texte
    // sombre sur fond sombre), d'où `koraColors.textMuted` ici.
    final textMuted = context.koraColors.textMuted;

    // Les 6 états tiennent sur UNE ligne : passer à deux couperait la
    // légende en deux blocs déséquilibrés, avec un état orphelin en
    // dessous. `FittedBox` la réduit juste ce qu'il faut sur les écrans
    // les plus étroits plutôt que de la replier.
    return FittedBox(
      fit: BoxFit.scaleDown,
      alignment: Alignment.centerLeft,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (final entry in _entries.where(
            (e) => statuses?.contains(e.$2) ?? true,
          )) ...[
            if (entry != _entries.first) const SizedBox(width: 11),
            _LegendSwatch(status: entry.$2, palette: palette),
            const SizedBox(width: 4),
            Text(entry.$1, style: TextStyle(color: textMuted, fontSize: 9)),
          ],
        ],
      ),
    );
  }
}

class _LegendSwatch extends StatelessWidget {
  const _LegendSwatch({required this.status, this.palette});

  final KoraSeatStatus status;
  final Map<KoraSeatStatus, Color>? palette;

  @override
  Widget build(BuildContext context) {
    final visual = _seatVisual(status, palette);
    final koraColors = context.koraColors;

    // Les vrais sièges reposent sur le panneau blanc du bus, toujours
    // clair ; les pastilles de la légende, elles, sont posées sur le fond
    // de l'écran. Une pastille de la couleur de ce fond s'y dissout — le
    // navy de « votre choix » disparaît en thème sombre. On la cerne donc
    // dès que sa bordure ne suffit pas à la détacher.
    final border = visual.border == visual.background
        ? koraColors.line
        : visual.border;

    return Container(
      width: 9,
      height: 9,
      decoration: BoxDecoration(
        color: visual.background,
        border: Border.all(color: border),
        borderRadius: BorderRadius.circular(3),
      ),
    );
  }
}

/// Bordure pointillée d'un siège "Réservé" (`.seat.held { border-style:
/// dashed }`) — Flutter n'a pas d'équivalent natif à `BorderStyle`, d'où
/// ce petit painter dédié plutôt qu'une dépendance externe pour un seul
/// trait.
class _DashedBorderPainter extends CustomPainter {
  const _DashedBorderPainter({required this.color, required this.radius});

  final Color color;
  final double radius;

  static const _dashWidth = 3.0;
  static const _dashGap = 2.0;

  @override
  void paint(Canvas canvas, Size size) {
    final rrect = RRect.fromRectAndRadius(
      Offset.zero & size,
      Radius.circular(radius),
    );
    final path = Path()..addRRect(rrect);
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1;

    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        final next = distance + _dashWidth;
        canvas.drawPath(
          metric.extractPath(distance, next.clamp(0, metric.length)),
          paint,
        );
        distance = next + _dashGap;
      }
    }
  }

  @override
  bool shouldRepaint(covariant _DashedBorderPainter oldDelegate) =>
      oldDelegate.color != color || oldDelegate.radius != radius;
}
