import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';

/// Écran de choix du nombre de voyageurs — équivalent `passengerScreen` de
/// la maquette (`js/app.js`, écran `passengers`) : compteur −/+, note sur
/// les billets, raccourcis groupe/entreprise, bouton de confirmation.
///
/// Écran **plein**, sans bottom nav, comme les autres étapes du formulaire.
/// Il renvoie le nombre choisi à l'appelant via son `pop`.
class PassengersPickerPage extends StatefulWidget {
  const PassengersPickerPage({
    super.key,
    required this.selected,
    this.maxPassengers = 6,
  });

  final int selected;

  /// Au-delà, c'est une réservation de groupe, qui a son propre parcours
  /// (`1 à 6 · groupe jusqu'à 10` dans la maquette).
  final int maxPassengers;

  @override
  State<PassengersPickerPage> createState() => _PassengersPickerPageState();
}

class _PassengersPickerPageState extends State<PassengersPickerPage> {
  late int _count = widget.selected.clamp(1, widget.maxPassengers);

  void _change(int delta) => setState(() => _count += delta);

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const ScreenHeader(
                eyebrow: 'Réservation',
                title: 'Combien de passagers ?',
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    KoraCard(
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  'Passagers',
                                  style: AppTextStyles.titleMedium(
                                    context.koraTitleColor,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  '1 à ${widget.maxPassengers} · groupe '
                                  'jusqu’à 10',
                                  style: AppTextStyles.body(
                                    koraColors.textMuted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          _Stepper(
                            count: _count,
                            onDecrement: _count > 1 ? () => _change(-1) : null,
                            onIncrement: _count < widget.maxPassengers
                                ? () => _change(1)
                                : null,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      child: Text(
                        'Chaque passager recevra un siège et un QR Code '
                        'individuel. Un seul paiement couvre toute la '
                        'réservation.',
                        style: AppTextStyles.body(koraColors.textMuted),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    // Les deux parcours dédiés (groupe, entreprise) ne font
                    // pas partie du MVP Bus : les raccourcis restent
                    // affichés, conformes à la maquette, mais annoncent
                    // leur indisponibilité plutôt que d'ouvrir un écran
                    // vide.
                    Row(
                      children: [
                        Expanded(
                          child: KoraButton(
                            label: 'Groupe 5–10',
                            variant: KoraButtonVariant.secondary,
                            onPressed: () => _notAvailableYet(
                              context,
                              'La réservation de groupe arrive après le '
                              'MVP Bus.',
                            ),
                          ),
                        ),
                        const SizedBox(width: 9),
                        Expanded(
                          child: KoraButton(
                            label: 'Compte entreprise',
                            variant: KoraButtonVariant.secondary,
                            onPressed: () => _notAvailableYet(
                              context,
                              'Les comptes entreprise arrivent après le '
                              'MVP Bus.',
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: KoraButton(
                  label: _count == 1
                      ? 'Continuer avec 1 passager'
                      : 'Continuer avec $_count passagers',
                  onPressed: () => Navigator.of(context).pop(_count),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Le compteur `− 1 +` de la maquette.
class _Stepper extends StatelessWidget {
  const _Stepper({
    required this.count,
    required this.onDecrement,
    required this.onIncrement,
  });

  final int count;

  /// `null` aux bornes (1 et le maximum) : la flèche est alors atténuée,
  /// `KoraIconButton` n'ayant pas d'état désactivé propre.
  final VoidCallback? onDecrement;
  final VoidCallback? onIncrement;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Opacity(
          opacity: onDecrement == null ? 0.35 : 1,
          child: KoraIconButton(
            icon: Icons.remove_rounded,
            onTap: onDecrement,
            size: 38,
          ),
        ),
        SizedBox(
          width: 44,
          child: Text(
            '$count',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w900,
              color: context.koraTitleColor,
            ),
          ),
        ),
        Opacity(
          opacity: onIncrement == null ? 0.35 : 1,
          child: KoraIconButton(
            icon: Icons.add_rounded,
            onTap: onIncrement,
            size: 38,
          ),
        ),
      ],
    );
  }
}

void _notAvailableYet(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}
