import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/domain/entities/group_booking_limits.dart';

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
    this.maxPassengers = kGroupMaximumPassengers,
  });

  final int selected;

  /// Le plafond proposé par le compteur.
  final int maxPassengers;

  @override
  State<PassengersPickerPage> createState() => _PassengersPickerPageState();
}

class _PassengersPickerPageState extends State<PassengersPickerPage> {
  late int _count = widget.selected.clamp(1, widget.maxPassengers);

  void _change(int delta) => setState(() => _count += delta);

  /// Bascule en réservation de groupe et rend la main à l'accueil.
  ///
  /// Le nombre exact ne se règle pas ici : il se choisit sur l'écran de
  /// réservation de groupe (BUS-15), une fois le départ connu — c'est là
  /// seulement que les montants existent. Le compteur repart donc du seuil,
  /// et l'accueil affiche « Groupe 5–10 » plutôt qu'un nombre d'adultes.
  void _chooseGroup() => Navigator.of(context).pop(kGroupMinimumPassengers);

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
              ScreenHeader(
                eyebrow: context.l10n.homePassengersEyebrow,
                title: context.l10n.homePassengersTitle,
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
                                  context.l10n.homePassengersLabel,
                                  style: AppTextStyles.titleMedium(
                                    context.koraTitleColor,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  context.l10n.homePassengersRange(
                                    widget.maxPassengers,
                                    kGroupMinimumPassengers,
                                  ),
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
                        context.l10n.homePassengersNotice,
                        style: AppTextStyles.body(koraColors.textMuted),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    // Le raccourci groupe amène au seuil de la remise ; le
                    // compte entreprise, lui, désigne un **payeur** et se
                    // choisit au paiement, pas ici.
                    Row(
                      children: [
                        Expanded(
                          child: KoraButton(
                            label: context.l10n.homePassengersGroup(
                              kGroupMinimumPassengers,
                              kGroupMaximumPassengers,
                            ),
                            variant: KoraButtonVariant.secondary,
                            onPressed: _chooseGroup,
                          ),
                        ),
                        const SizedBox(width: 9),
                        Expanded(
                          child: KoraButton(
                            label: context.l10n.homePassengersBusiness,
                            variant: KoraButtonVariant.secondary,
                            onPressed: () => _notAvailableYet(
                              context,
                              context.l10n.homePassengersBusinessPayer,
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
                  label: context.l10n.homePassengersConfirm(_count),
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
