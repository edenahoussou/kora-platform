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

/// Ce qu'une case de la cabine occupe comme place.
///
/// Le plan d'un bus n'est pas qu'une suite de sièges : le couloir, la
/// porte et le poste de conduite tiennent chacun une case. Les décrire
/// permet de dessiner un véhicule qui n'est pas en 2+2.
enum KoraCellKind { seat, aisle, empty, door, driver }

/// Une case du plancher, à sa place exacte dans la grille.
class KoraSeatCell {
  const KoraSeatCell({
    required this.kind,
    required this.x,
    required this.y,
    this.seat,
  });

  final KoraCellKind kind;

  /// Colonne et rangée, données telles quelles : le plan ne déduit plus
  /// la position du libellé du siège.
  final int x;
  final int y;

  /// Le siège à cette case, quand [kind] vaut [KoraCellKind.seat].
  final KoraSeat? seat;
}

/// La géométrie d'une cabine.
class KoraCabinGrid {
  const KoraCabinGrid({
    required this.rows,
    required this.cols,
    required this.cells,
  });

  final int rows;
  final int cols;
  final List<KoraSeatCell> cells;

  bool get isUsable => rows > 0 && cols > 0 && cells.isNotEmpty;
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

_SeatVisual _seatVisual(KoraSeatStatus status) {
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
    this.grid,
    this.columns = 4,
    this.showLegend = true,
    this.legendStatuses,
    this.onSeatTap,
  });

  final List<KoraSeat> seats;

  /// La cabine telle que le serveur la décrit : chaque case à sa place,
  /// couloir, porte et poste de conduite compris.
  ///
  /// `null`, ou vide, et le plan retombe sur [seats] découpé en rangées
  /// de [columns] avec une allée au milieu — le seul rendu possible tant
  /// que la réponse ne portait aucune coordonnée, et qui déforme le plan
  /// dès que la numérotation sort de la convention 2+2.
  final KoraCabinGrid? grid;

  /// Nombre de sièges par rangée. L'allée est insérée automatiquement au
  /// milieu (entre les colonnes `columns ~/ 2 - 1` et `columns ~/ 2`).
  final int columns;

  /// Affiche la légende des 6 états au-dessus du plan — équivalent
  /// `.seat-legend`, toujours présente sur l'écran réel.
  final bool showLegend;

  /// Les états que la légende présente, dans son ordre habituel. `null` :
  /// les six. Un écran voyageur n'a que faire de « Embarqué » ou
  /// « Bloqué », que ses sièges ne distinguent pas.
  final Set<KoraSeatStatus>? legendStatuses;

  final ValueChanged<KoraSeat>? onSeatTap;

  @override
  Widget build(BuildContext context) {
    final plan = grid;
    final fromGrid = plan != null && plan.isUsable;
    final rows = fromGrid ? _gridRows(plan) : _chunkedRows();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (showLegend) ...[
          _Legend(statuses: legendStatuses),
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
              // Le volant et la porte sont dessinés en dur au-dessus du
              // plan tant qu'on ne sait pas où ils sont. Dès que la
              // grille les porte comme des cases, ils reprennent leur
              // vraie place et l'en-tête se réduit à l'orientation.
              if (fromGrid)
                const _BusLabel('AVANT DU BUS')
              else
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

  /// Le plan historique : les sièges débités en rangées, allée au milieu.
  List<Widget> _chunkedRows() {
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
        ),
      );
    }

    return rows;
  }

  /// Le plan tel que le serveur l'a calculé : une case par coordonnée.
  ///
  /// Aucune logique de placement ici — c'est tout l'intérêt. Une case
  /// absente de la grille reste un trou, ce qui est la bonne réponse
  /// pour un bus dont le plancher n'est pas plein.
  List<Widget> _gridRows(KoraCabinGrid plan) {
    final byPosition = <int, KoraSeatCell>{
      for (final cell in plan.cells) cell.y * plan.cols + cell.x: cell,
    };

    final rows = <Widget>[];
    for (var y = 0; y < plan.rows; y++) {
      if (rows.isNotEmpty) rows.add(const SizedBox(height: 8));
      rows.add(
        _CabinRow(
          cells: [
            for (var x = 0; x < plan.cols; x++) byPosition[y * plan.cols + x],
          ],
          onTap: onSeatTap,
        ),
      );
    }

    return rows;
  }
}

/// Une rangée de la cabine, une case par colonne.
///
/// Les cases gardent une largeur égale — y compris le couloir, qui est
/// une colonne à part entière et non un écart entre deux sièges.
class _CabinRow extends StatelessWidget {
  const _CabinRow({required this.cells, this.onTap});

  final List<KoraSeatCell?> cells;
  final ValueChanged<KoraSeat>? onTap;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (var i = 0; i < cells.length; i++) ...[
          if (i != 0) const SizedBox(width: 8),
          Expanded(
            child: AspectRatio(
              aspectRatio: 1,
              child: _CabinCell(cell: cells[i], onTap: onTap),
            ),
          ),
        ],
      ],
    );
  }
}

/// Ce qu'on dessine à une case : un siège, un repère de carrosserie, ou
/// rien du tout.
class _CabinCell extends StatelessWidget {
  const _CabinCell({required this.cell, this.onTap});

  final KoraSeatCell? cell;
  final ValueChanged<KoraSeat>? onTap;

  @override
  Widget build(BuildContext context) {
    final cell = this.cell;
    if (cell == null) return const SizedBox();

    return switch (cell.kind) {
      KoraCellKind.seat when cell.seat != null => _SeatButton(
        seat: cell.seat!,
        onTap: onTap,
      ),
      KoraCellKind.driver => const Center(child: _DriverWheel()),
      KoraCellKind.door => const Center(child: _BusDoor()),
      // Le couloir, le vide, et un siège que l'inventaire ne connaît
      // pas : la case existe, elle ne porte rien.
      _ => const SizedBox(),
    };
  }
}

/// `.premium-bus-front` : le volant du chauffeur, l'orientation du plan et
/// la porte de sortie. Sans ce repère, rien ne dit quel bout de la grille
/// est l'avant du bus.
class _BusFront extends StatelessWidget {
  const _BusFront();

  @override
  Widget build(BuildContext context) {
    return const Row(
      children: [
        _DriverWheel(),
        Expanded(child: _BusLabel('AVANT DU BUS')),
        _BusDoor(),
      ],
    );
  }
}

/// `.driver-wheel` : un cercle dans un cercle.
///
/// Dessiné dans l'en-tête tant que le plan ignore où se trouve le poste
/// de conduite, et posé à sa vraie case dès que la grille le situe.
class _DriverWheel extends StatelessWidget {
  const _DriverWheel();

  @override
  Widget build(BuildContext context) {
    return Container(
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
    );
  }
}

/// `.bus-door` : la porte, en en-tête ou à sa case selon ce que le plan
/// sait de la cabine.
class _BusDoor extends StatelessWidget {
  const _BusDoor();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 5),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(5),
        border: Border.all(color: AppColors.seatBorderLight),
      ),
      child: const _BusLabel('SORTIE', size: 6),
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
  });

  final List<KoraSeat> seats;
  final int columns;
  final int aisleAfter;
  final ValueChanged<KoraSeat>? onTap;

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
            ? _SeatButton(seat: seats[c], onTap: onTap)
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
  const _SeatButton({required this.seat, this.onTap});

  final KoraSeat seat;
  final ValueChanged<KoraSeat>? onTap;

  bool get _isTappable =>
      seat.status == KoraSeatStatus.available ||
      seat.status == KoraSeatStatus.selected;

  @override
  Widget build(BuildContext context) {
    final visual = _seatVisual(seat.status);
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
  const _Legend({this.statuses});

  /// Voir [SeatMap.legendStatuses].
  final Set<KoraSeatStatus>? statuses;

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
    final entries = [
      for (final entry in _entries)
        if (statuses?.contains(entry.$2) ?? true) entry,
    ];

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
          for (final entry in entries) ...[
            if (entry != entries.first) const SizedBox(width: 11),
            _LegendSwatch(status: entry.$2),
            const SizedBox(width: 4),
            Text(entry.$1, style: TextStyle(color: textMuted, fontSize: 9)),
          ],
        ],
      ),
    );
  }
}

class _LegendSwatch extends StatelessWidget {
  const _LegendSwatch({required this.status});

  final KoraSeatStatus status;

  @override
  Widget build(BuildContext context) {
    final visual = _seatVisual(status);
    return Container(
      width: 9,
      height: 9,
      decoration: BoxDecoration(
        color: visual.background,
        border: Border.all(color: visual.border),
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
