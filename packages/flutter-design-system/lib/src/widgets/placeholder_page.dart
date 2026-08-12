import 'package:flutter/material.dart';

import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';

/// Page minimale utilisée pour les onglets pas encore développés
/// (Voyages, Colis, Wallet, Profil) : un titre et un texte centrés.
///
/// À remplacer par le vrai écran de la feature quand tu t'y attaques (voir
/// GUIDE.md, section "Créer une nouvelle feature").
///
/// Exemple d'utilisation :
/// ```dart
/// class VoyagesPage extends StatelessWidget {
///   const VoyagesPage({super.key});
///   @override
///   Widget build(BuildContext context) {
///     return const PlaceholderPage(
///       title: 'Voyages',
///       message: 'Ton historique de trajets Bus & Ride arrivera ici.',
///     );
///   }
/// }
/// ```
class PlaceholderPage extends StatelessWidget {
  const PlaceholderPage({
    super.key,
    required this.title,
    required this.message,
  });

  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                title,
                style: Theme.of(context).textTheme.headlineSmall,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                message,
                style: TextStyle(color: koraColors.textMuted, fontSize: 13),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
