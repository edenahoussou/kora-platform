import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/date_formats.dart';
import '../../../booking/domain/entities/city.dart';

/// Carte de recherche de l'accueil — équivalent `.floating-search` de la
/// maquette : bandeau bleu « Où souhaitez-vous voyager ? », champs départ /
/// arrivée avec bouton d'inversion, ligne date / passagers, puis le bouton
/// de recherche.
///
/// C'est un widget **purement présentationnel** : il reçoit les valeurs
/// affichées et remonte les intentions de l'utilisateur via ses callbacks.
/// Il ne connaît ni le Bloc, ni la navigation, ce qui le rend réutilisable
/// tel quel sur l'écran de résultats (où la maquette réaffiche le même
/// bloc en version compacte).
class HomeSearchCard extends StatelessWidget {
  const HomeSearchCard({
    super.key,
    required this.origin,
    required this.destination,
    required this.date,
    required this.passengers,
    required this.onOriginTap,
    required this.onDestinationTap,
    required this.onSwapTap,
    required this.onDateTap,
    required this.onPassengersTap,
    required this.onSearch,
  });

  final City? origin;
  final City? destination;
  final DateTime? date;
  final int passengers;

  final VoidCallback onOriginTap;
  final VoidCallback onDestinationTap;
  final VoidCallback onSwapTap;
  final VoidCallback onDateTap;
  final VoidCallback onPassengersTap;

  /// `null` désactive le bouton — trajet incomplet ou identique.
  final VoidCallback? onSearch;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const _SearchCardLabel(),
        // Le bandeau bleu déborde de 12px sous la carte blanche
        // (`.search-card-label { margin-bottom: -12px }`) : on remonte donc
        // tout ce qui suit d'autant. L'écran compense ces 12px dans son
        // espacement (voir `HomePage`).
        Transform.translate(
          offset: const Offset(0, -12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // `.route-fields` est en `position: relative` dans la
              // maquette : en CSS, un élément positionné se peint APRÈS
              // ses frères statiques, même s'il les précède dans le DOM.
              // La carte de trajet passe donc par-dessus la bande
              // Date/Passagers, et c'est ce qui rend son ombre visible —
              // elle se projette sur cette bande.
              //
              // Une `Column` fait l'inverse (le dernier enfant peint
              // au-dessus). D'où `verticalDirection: up` : les enfants
              // sont posés du bas vers le haut, donc la bande écrite en
              // premier se retrouve EN DESSOUS à l'écran tout en étant
              // peinte en premier, et la carte de trajet la recouvre.
              Column(
                mainAxisSize: MainAxisSize.min,
                verticalDirection: VerticalDirection.up,
                children: [
                  _SearchMeta(
                    date: date,
                    passengers: passengers,
                    onDateTap: onDateTap,
                    onPassengersTap: onPassengersTap,
                  ),
                  _RouteFields(
                    origin: origin,
                    destination: destination,
                    onOriginTap: onOriginTap,
                    onDestinationTap: onDestinationTap,
                    onSwapTap: onSwapTap,
                  ),
                ],
              ),
              const SizedBox(height: 10),
              KoraButton(label: 'Rechercher un voyage', onPressed: onSearch),
            ],
          ),
        ),
      ],
    );
  }
}

/// Couleur d'ombre à utiliser pour un `box-shadow` de la maquette exprimé
/// en bleu KORA translucide (`rgba(0,29,78,…)`).
///
/// Telle quelle, une ombre navy ne se voit pas en thème sombre : le fond
/// d'écran y est ce même bleu KORA. On la rejoue alors en noir — une
/// ombre reste une ombre, seule sa teinte dépend du support sur lequel
/// elle tombe. [alpha] est l'opacité donnée par le CSS, valable pour le
/// thème clair ; le noir a besoin d'être plus soutenu pour rester lisible
/// sur un fond déjà sombre.
Color _shadowColor(BuildContext context, double alpha) {
  return Theme.of(context).brightness == Brightness.dark
      ? Colors.black.withValues(alpha: (alpha * 3).clamp(0.0, 0.5))
      : AppColors.kora.withValues(alpha: alpha);
}

/// `.search-card-label` : bandeau dégradé bleu, coins arrondis en haut
/// seulement, légèrement plus étroit que la carte blanche.
class _SearchCardLabel extends StatelessWidget {
  const _SearchCardLabel();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 10),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppColors.digital, Color(0xFF0069D7)],
        ),
        borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
        boxShadow: [
          BoxShadow(
            color: AppColors.digital.withValues(alpha: 0.22),
            offset: const Offset(0, 12),
            blurRadius: 30,
          ),
        ],
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Flexible(
            child: Text(
              'Où souhaitez-vous voyager ?',
              style: TextStyle(
                color: AppColors.white,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          Text(
            'KORA BUS',
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.68),
              fontSize: 9,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.9, // .1em × 9
            ),
          ),
        ],
      ),
    );
  }
}

/// `.route-fields` : la carte blanche portant les deux champs de trajet et
/// le bouton d'inversion.
class _RouteFields extends StatelessWidget {
  const _RouteFields({
    required this.origin,
    required this.destination,
    required this.onOriginTap,
    required this.onDestinationTap,
    required this.onSwapTap,
  });

  final City? origin;
  final City? destination;
  final VoidCallback onOriginTap;
  final VoidCallback onDestinationTap;
  final VoidCallback onSwapTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 2),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(19),
        // `box-shadow: 0 18px 42px rgba(0,29,78,.13)`
        boxShadow: [
          BoxShadow(
            color: _shadowColor(context, 0.13),
            offset: const Offset(0, 18),
            blurRadius: 42,
          ),
        ],
      ),
      child: Stack(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(15, 10, 44, 10),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _RouteField(
                  label: 'Départ',
                  value: origin?.label,
                  placeholder: 'Choisir une ville',
                  dotColor: AppColors.kora,
                  onTap: onOriginTap,
                ),
                const Divider(
                  height: 1,
                  thickness: 1,
                  color: AppColors.lineLight,
                ),
                _RouteField(
                  label: 'Arrivée',
                  value: destination?.label,
                  placeholder: 'Choisir une ville',
                  dotColor: AppColors.gold,
                  onTap: onDestinationTap,
                ),
              ],
            ),
          ),
          // `.swap-button` : centré verticalement, collé à droite.
          Positioned.fill(
            child: Align(
              alignment: Alignment.centerRight,
              child: Padding(
                padding: const EdgeInsets.only(right: 9),
                child: _SwapButton(onTap: onSwapTap),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// `.route-field` : pastille de couleur, libellé en petites capitales,
/// valeur en gras.
class _RouteField extends StatelessWidget {
  const _RouteField({
    required this.label,
    required this.value,
    required this.placeholder,
    required this.dotColor,
    required this.onTap,
  });

  final String label;
  final String? value;
  final String placeholder;
  final Color dotColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final hasValue = value != null;

    return InkWell(
      onTap: onTap,
      child: SizedBox(
        height: 55,
        child: Row(
          children: [
            // `.route-dot` : anneau de 3px, centre transparent.
            Container(
              width: 11,
              height: 11,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: dotColor, width: 3),
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    label.toUpperCase(),
                    style: const TextStyle(
                      color: AppColors.mutedLight,
                      fontSize: 9,
                      letterSpacing: 0.81, // .09em × 9
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    value ?? placeholder,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: hasValue ? AppColors.kora : AppColors.mutedLight,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// `.swap-button` : rond doré qui intervertit départ et arrivée.
class _SwapButton extends StatelessWidget {
  const _SwapButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 35,
      height: 35,
      child: Material(
        color: AppColors.gold,
        shape: const CircleBorder(),
        child: InkWell(
          onTap: onTap,
          customBorder: const CircleBorder(),
          child: const Center(
            child: Icon(Icons.swap_vert_rounded, size: 19, color: AppColors.kora),
          ),
        ),
      ),
    );
  }
}

/// `.home-search-meta` : les deux tuiles Date / Passagers, séparées par un
/// filet d'1px et arrondies en bas seulement.
class _SearchMeta extends StatelessWidget {
  const _SearchMeta({
    required this.date,
    required this.passengers,
    required this.onDateTap,
    required this.onPassengersTap,
  });

  final DateTime? date;
  final int passengers;
  final VoidCallback onDateTap;
  final VoidCallback onPassengersTap;

  @override
  Widget build(BuildContext context) {
    const radius = BorderRadius.vertical(bottom: Radius.circular(14));

    return Container(
      // `margin: 1px 11px 0` — le filet d'1px sous la carte de trajet.
      margin: const EdgeInsets.only(top: 1, left: 11, right: 11),
      decoration: BoxDecoration(
        // Le fond translucide sert de filet de séparation : c'est lui
        // qu'on voit dans l'espace d'1px entre les deux tuiles blanches.
        color: AppColors.lineLight,
        borderRadius: radius,
        // `box-shadow: 0 9px 25px rgba(0,29,78,.08)`
        boxShadow: [
          BoxShadow(
            color: _shadowColor(context, 0.08),
            offset: const Offset(0, 9),
            blurRadius: 25,
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: radius,
        child: Row(
          children: [
            Expanded(
              child: _MetaTile(
                label: 'Date',
                value: date == null
                    ? 'Choisir'
                    : KoraDateFormats.dayMonthYear.format(date!),
                onTap: onDateTap,
              ),
            ),
            const SizedBox(width: 1),
            Expanded(
              child: _MetaTile(
                label: 'Passagers',
                value: passengers == 1 ? '1 adulte' : '$passengers adultes',
                onTap: onPassengersTap,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MetaTile extends StatelessWidget {
  const _MetaTile({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.white,
      child: InkWell(
        onTap: onTap,
        child: Container(
          constraints: const BoxConstraints(minHeight: 55),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: 9),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                label.toUpperCase(),
                style: const TextStyle(
                  color: AppColors.mutedLight,
                  fontSize: 8,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.64, // .08em × 8
                ),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: AppColors.kora,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
