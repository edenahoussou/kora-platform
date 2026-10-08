import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../pages/mobile_money_page.dart';

/// La vignette d'un opérateur.
///
/// Les logos fournis par KORA vivent dans `assets/images/operators`, un
/// fichier par **code réseau** : la vignette se trouve alors sans table
/// de correspondance. Un réseau annoncé par le serveur sans logo au
/// dépôt garde sa place avec une pastille à ses initiales, plutôt qu'un
/// carré vide ou une image manquante.
class OperatorBadge extends StatelessWidget {
  const OperatorBadge({super.key, required this.network, this.size = 46});

  final String network;

  /// Le côté du carré, en pixels logiques : pleine taille dans la liste
  /// des opérateurs, réduite sur le pavé « Mobile Money » qui les
  /// annonce tous les trois.
  final double size;

  /// Les réseaux dont le logo est au dépôt. Une image absente ne se
  /// devine pas à l'exécution : Flutter afficherait une erreur de rendu.
  static const Set<String> logoNetworks = {
    'mtn_momo',
    'moov_flooz',
    'celtiis_cash',
  };

  /// Les initiales du réseau : « MTN », « MO », « CE »…
  String get _initials {
    final brand = mobileMoneyLabel(network).trim();
    if (brand.isEmpty) return '?';

    final first = brand.split(' ').first;
    return first.length <= 3
        ? first.toUpperCase()
        : first.substring(0, 2).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    if (logoNetworks.contains(network)) {
      return Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          // Le blanc derrière le logo : les trois marques portent leur
          // propre fond, et le MTN — plus large que haut — laisserait
          // sinon voir le gris de la tuile au-dessus et en dessous.
          color: AppColors.white,
          borderRadius: BorderRadius.circular(AppRadius.sm),
          border: Border.all(color: koraColors.line),
        ),
        clipBehavior: Clip.antiAlias,
        child: Padding(
          padding: const EdgeInsets.all(3),
          child: Image.asset(
            'assets/images/operators/$network.png',
            fit: BoxFit.contain,
          ),
        ),
      );
    }

    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: koraColors.line,
        borderRadius: BorderRadius.circular(AppRadius.sm),
      ),
      child: Text(
        _initials,
        style: TextStyle(
          color: koraColors.textPrimary,
          fontSize: 13,
          fontWeight: FontWeight.w900,
          letterSpacing: -0.2,
        ),
      ),
    );
  }
}
