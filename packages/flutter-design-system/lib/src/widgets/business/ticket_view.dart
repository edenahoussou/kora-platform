import 'package:flutter/material.dart';

import '../../colors/app_colors.dart';
import '../../dimens/app_dimens.dart';
import '../kora_tag.dart';

/// Billet numérique — équivalent `.digital-ticket` du prototype
/// (`docs/maquettes/kora-app-passenger/css/style.css`, L619-656 ;
/// markup `ticketMarkup()` dans `js/app.js`, L273-304) : en-tête navy
/// (marque + statut + trajet en codes de gare), encoches de billet, puis
/// corps blanc (QR, date/départ/siège, code billet + passager).
///
/// **Toujours ces couleurs, quel que soit le thème de l'app** — même
/// raisonnement que [DepartureCard]/[SeatMap]/[ConnectionResultCard] :
/// le prototype ne définit aucune règle `.dark-mode` pour `.digital-ticket`.
///
/// **Marque KORA** : [_BrandMark] affiche le vrai logo
/// (`assets/brand/kora-logo.png`), recadré depuis l'asset source de
/// l'app passager (`apps/kora-app-passenger/assets/images/kora_logo.png`,
/// 1080×1350 avec une large marge blanche — recadrée ici sur le contenu
/// visible uniquement, sinon le logo apparaît minuscule dans le badge
/// blanc du billet). Ré-encodé via l'encodeur PNG de Flutter
/// (`dart:ui`, `Image.toByteData(format: ImageByteFormat.png)`) plutôt
/// que gardé tel quel : le PNG produit par l'outil de recadrage
/// (System.Drawing/.NET) provoquait un blocage indéfini du décodage
/// dans `flutter test` sur cet environnement (confirmé indépendant du
/// contenu de l'image — même un PNG minimal généré à la volée par le
/// même outil reproduisait le blocage) ; le fichier ré-encodé par
/// Flutter lui-même décode normalement.
///
/// Exemple d'utilisation :
/// ```dart
/// TicketView(
///   passengerName: 'A. HOUNKPÈ',
///   originCode: 'CTN',
///   origin: 'Cotonou',
///   destinationCode: 'MLV',
///   destination: 'Malanville',
///   date: '04 AOÛ',
///   departureTime: '07:30',
///   seatLabel: '4A',
///   ticketCode: 'KE-20260804-78342',
/// )
/// ```
class TicketView extends StatelessWidget {
  const TicketView({
    super.key,
    required this.passengerName,
    required this.originCode,
    required this.origin,
    required this.destinationCode,
    required this.destination,
    required this.date,
    required this.departureTime,
    required this.seatLabel,
    required this.ticketCode,
    this.isCancelled = false,
  });

  final String passengerName;

  /// Code de gare, 3 lettres (ex: `'CTN'`).
  final String originCode;
  final String origin;

  /// Code de gare, 3 lettres (ex: `'MLV'`).
  final String destinationCode;
  final String destination;

  final String date;
  final String departureTime;
  final String seatLabel;
  final String ticketCode;

  /// Billet annulé — équivalent `.digital-ticket.cancelled` : billet
  /// désaturé (`filter: grayscale(.7)`) et tampon "ANNULÉ" (`.cancel-stamp`).
  final bool isCancelled;

  @override
  Widget build(BuildContext context) {
    final ticket = ClipRRect(
      borderRadius: BorderRadius.circular(25),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: AppColors.kora,
          boxShadow: AppShadows.ticket,
        ),
        child: Stack(
          children: [
            Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _Header(isCancelled: isCancelled),
                _Route(
                  originCode: originCode,
                  origin: origin,
                  destinationCode: destinationCode,
                  destination: destination,
                ),
                const _DashedCut(),
                const _NotchStraddle(),
                _Body(
                  date: date,
                  departureTime: departureTime,
                  seatLabel: seatLabel,
                  ticketCode: ticketCode,
                  passengerName: passengerName,
                ),
              ],
            ),
            if (isCancelled)
              const Positioned.fill(child: Center(child: _CancelStamp())),
          ],
        ),
      ),
    );

    if (!isCancelled) return ticket;

    // `filter: grayscale(.7)` — désaturation partielle (70%), pas un
    // grayscale total : matrice standard grayscale/identité mélangées
    // 70/30 (poids de luminance sRGB 0.2126/0.7152/0.0722, comme la
    // spec CSS Filter Effects).
    return ColorFiltered(
      colorFilter: const ColorFilter.matrix(<double>[
        0.4488,
        0.5006,
        0.0505,
        0,
        0,
        0.1488,
        0.8006,
        0.0505,
        0,
        0,
        0.1488,
        0.5006,
        0.3505,
        0,
        0,
        0,
        0,
        0,
        1,
        0,
      ]),
      child: ticket,
    );
  }
}

/// `.ticket-top` : marque à gauche, statut à droite.
class _Header extends StatelessWidget {
  const _Header({required this.isCancelled});

  final bool isCancelled;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 17, 18, 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _BrandMark(),
          KoraTag(
            label: isCancelled ? 'Annulé' : 'Confirmé',
            variant: KoraTagVariant.gold,
          ),
        ],
      ),
    );
  }
}

/// Logo KORA Express — voir la note sur [TicketView] pour l'origine de
/// l'asset. CSS de référence : `.ticket-top img { width: 105px; padding:
/// 5px; border-radius: 8px; background: white }`.
class _BrandMark extends StatelessWidget {
  const _BrandMark();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(5),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Image.asset(
        'assets/brand/kora-logo.png',
        // Voir la note dans kora_vehicle_icon.dart : asset déclaré dans
        // le pubspec de ce package, pas dans celui de l'app consommatrice
        // — nécessaire pour que ça résolve correctement une fois ce
        // widget utilisé PAR une app qui dépend de ce package (le cas
        // réel : kora-app-passenger).
        //
        // Repli sans `package:` en cas d'échec : `widgetbook/` n'a pas
        // son propre pubspec, il tourne directement sur celui de ce
        // package (`flutter run -t widgetbook/main.dart`) — dans ce cas
        // précis, ce package EST l'app racine, donc ses propres assets
        // sont servis sous leur chemin nu, sans préfixe
        // `packages/flutter_design_system/`, et la résolution avec
        // `package:` échoue (404). Le repli couvre ce second contexte
        // sans sacrifier le premier (le cas réel, prioritaire).
        package: 'flutter_design_system',
        width: 105,
        errorBuilder: (context, error, stackTrace) =>
            Image.asset('assets/brand/kora-logo.png', width: 105),
      ),
    );
  }
}

/// `.ticket-route` : codes de gare en grand, villes en dessous, flèche
/// dorée au centre.
class _Route extends StatelessWidget {
  const _Route({
    required this.originCode,
    required this.origin,
    required this.destinationCode,
    required this.destination,
  });

  final String originCode;
  final String origin;
  final String destinationCode;
  final String destination;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 10, 18, 22),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: _CityBlock(code: originCode, city: origin, alignEnd: false),
          ),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 8),
            child: Text(
              '⟶',
              style: TextStyle(color: AppColors.gold, fontSize: 22),
            ),
          ),
          Expanded(
            child: _CityBlock(
              code: destinationCode,
              city: destination,
              alignEnd: true,
            ),
          ),
        ],
      ),
    );
  }
}

class _CityBlock extends StatelessWidget {
  const _CityBlock({
    required this.code,
    required this.city,
    required this.alignEnd,
  });

  final String code;
  final String city;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    final crossAxis = alignEnd
        ? CrossAxisAlignment.end
        : CrossAxisAlignment.start;
    final textAlign = alignEnd ? TextAlign.right : TextAlign.left;

    return Column(
      crossAxisAlignment: crossAxis,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          code,
          textAlign: textAlign,
          style: const TextStyle(
            color: AppColors.white,
            fontSize: 20,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          city,
          textAlign: textAlign,
          // CSS : `.ticket-city small { color: rgba(255,255,255,.55) }`
          style: const TextStyle(color: Color(0x8CFFFFFF), fontSize: 11),
        ),
      ],
    );
  }
}

/// `.ticket-cut { border-top: 1px dashed rgba(255,255,255,.3) }`.
class _DashedCut extends StatelessWidget {
  const _DashedCut();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 18),
      child: SizedBox(
        height: 1,
        child: LayoutBuilder(
          builder: (context, constraints) {
            const dashWidth = 4.0;
            const dashSpace = 3.0;
            final count = (constraints.maxWidth / (dashWidth + dashSpace))
                .floor();
            return Row(
              children: List.generate(
                count,
                (_) => Padding(
                  padding: const EdgeInsets.only(right: dashSpace),
                  child: Container(
                    width: dashWidth,
                    height: 1,
                    color: const Color(0x4DFFFFFF),
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

/// Les deux encoches qui "mordent" les bords gauche/droit du billet,
/// pile à la jonction entre la partie navy et le corps blanc —
/// équivalent `.digital-ticket::before/::after` (cercles `mist` en
/// position absolue, `top: 238px`, `border-radius: 50%`). Plutôt qu'un
/// positionnement en pixels absolus (fragile : dépend de la hauteur
/// exacte du contenu au-dessus, qui n'est pas forcément la même que
/// dans le prototype), les encoches sont ici posées exactement à la
/// jonction navy/blanc quelle que soit la hauteur réelle : un
/// [SizedBox] de hauteur nulle dans le flux, dont le contenu (22px de
/// haut) déborde de 11px de part et d'autre via [OverflowBox] — donc
/// toujours centré pile sur la ligne de jonction.
class _NotchStraddle extends StatelessWidget {
  const _NotchStraddle();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 0,
      child: OverflowBox(
        minHeight: 22,
        maxHeight: 22,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Transform.translate(
              offset: const Offset(-11, 0),
              child: const _Notch(),
            ),
            Transform.translate(
              offset: const Offset(11, 0),
              child: const _Notch(),
            ),
          ],
        ),
      ),
    );
  }
}

class _Notch extends StatelessWidget {
  const _Notch();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 22,
      height: 22,
      decoration: const BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.mist,
      ),
    );
  }
}

/// `.ticket-body` : QR, données de voyage, code billet + passager.
class _Body extends StatelessWidget {
  const _Body({
    required this.date,
    required this.departureTime,
    required this.seatLabel,
    required this.ticketCode,
    required this.passengerName,
  });

  final String date;
  final String departureTime;
  final String seatLabel;
  final String ticketCode;
  final String passengerName;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(color: AppColors.white),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 20, 18, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const _Qr(),
            const SizedBox(height: 15),
            Row(
              children: [
                Expanded(
                  child: _DataField(label: 'Date', value: date),
                ),
                Expanded(
                  child: _DataField(label: 'Départ', value: departureTime),
                ),
                Expanded(
                  child: _DataField(label: 'Siège', value: seatLabel),
                ),
              ],
            ),
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 13),
              child: Divider(
                height: 1,
                thickness: 1,
                color: AppColors.lineLight,
              ),
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  ticketCode,
                  style: const TextStyle(
                    color: AppColors.mutedLight,
                    fontSize: 9,
                  ),
                ),
                Text(
                  passengerName,
                  style: const TextStyle(
                    color: AppColors.kora,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
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

class _DataField extends StatelessWidget {
  const _DataField({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          label.toUpperCase(),
          style: const TextStyle(color: AppColors.mutedLight, fontSize: 8),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: const TextStyle(
            color: AppColors.kora,
            fontSize: 12,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    );
  }
}

/// QR code stylisé (grille 9×9) — pas un vrai QR scannable, juste une
/// reproduction visuelle de `.qr` avec le motif fixe du prototype
/// (`js/app.js`, fonction `qr()`, L268-271) : motif stable d'un build à
/// l'autre, utile pour les stories/golden tests.
class _Qr extends StatelessWidget {
  const _Qr();

  static const _pattern =
      '111011101101001011111010111001101001111110100101001111010101110010111001011111001';

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 148,
      height: 148,
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: AppColors.white,
        border: Border.all(color: AppColors.lineLight),
      ),
      child: GridView.count(
        crossAxisCount: 9,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 2,
        crossAxisSpacing: 2,
        children: [
          for (final bit in _pattern.split(''))
            ColoredBox(color: bit == '1' ? AppColors.kora : AppColors.white),
        ],
      ),
    );
  }
}

/// `.cancel-stamp` : tampon "ANNULÉ" superposé, incliné.
class _CancelStamp extends StatelessWidget {
  const _CancelStamp();

  @override
  Widget build(BuildContext context) {
    return Transform.rotate(
      angle: -0.21, // -12deg
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          border: Border.all(color: AppColors.kora, width: 3),
          color: const Color(0xE6FFFFFF), // rgba(255,255,255,.9)
        ),
        child: const Text(
          'ANNULÉ',
          style: TextStyle(
            color: AppColors.kora,
            fontSize: 18,
            fontWeight: FontWeight.w900,
            letterSpacing: 1.8,
          ),
        ),
      ),
    );
  }
}
