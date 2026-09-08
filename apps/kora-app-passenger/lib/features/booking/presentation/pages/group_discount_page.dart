import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/departure.dart';
import '../bloc/group_quote_cubit.dart';
import 'seats_page.dart';

/// Ce qu'attend [GroupDiscountPage] : le départ choisi et le nombre de
/// voyageurs, de quoi demander le devis puis enchaîner sur les sièges.
class GroupDiscountArgs {
  const GroupDiscountArgs({required this.departure, required this.passengers});

  final Departure departure;
  final int passengers;
}

/// Écran `group-discount` de la maquette (BUS-16) : ce que le groupe
/// économise, avant de choisir les sièges.
///
/// Il vient **après** le choix du départ, et non juste après le compteur
/// comme dans la maquette : le montant économisé n'existe qu'une fois la
/// ligne connue, puisque c'est le serveur qui l'arrête. Placé plus tôt,
/// l'écran n'aurait eu qu'un chiffre inventé à montrer.
///
/// Trois cas, et trois discours différents. Le serveur applique une
/// remise : on l'annonce. Il connaît une règle que le groupe n'atteint pas
/// encore : on dit à partir de combien elle vaudra, avec **son** seuil et
/// non un nombre de mémoire. Il ne connaît aucune règle : l'écran ne
/// s'ouvre pas — c'est l'appelant qui le saute, un « Avantage appliqué »
/// sans avantage mentant au voyageur.
class GroupDiscountPage extends StatefulWidget {
  const GroupDiscountPage({super.key, this.args});

  /// `null` si l'écran est ouvert sans données (lien profond).
  final GroupDiscountArgs? args;

  @override
  State<GroupDiscountPage> createState() => _GroupDiscountPageState();
}

class _GroupDiscountPageState extends State<GroupDiscountPage> {
  @override
  void initState() {
    super.initState();

    final args = widget.args;
    if (args == null) return;

    context.read<GroupQuoteCubit>().load(
      departure: args.departure,
      passengers: args.passengers,
    );
  }

  void _openSeats(GroupDiscountArgs args) {
    context.pushReplacement(
      AppRouter.seatsPath,
      extra: SeatsArgs(departure: args.departure, passengers: args.passengers),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = widget.args;

    if (args == null) return const _MissingGroup();

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              BlocBuilder<GroupQuoteCubit, GroupQuoteState>(
                builder: (context, state) {
                  final quote =
                      state.matches(args.departure.id, args.passengers)
                      ? state.quote
                      : null;
                  final discount = quote?.discount;
                  final applied = discount?.isVisible ?? false;

                  return Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        ScreenHeader(
                          // Le titre suit ce que le serveur accorde : parler
                          // d'avantage appliqué devant un tiret serait une
                          // promesse démentie par l'écran lui-même.
                          eyebrow: applied
                              ? l10n.groupDiscountEyebrow
                              : l10n.groupDiscountPendingEyebrow,
                          title: applied
                              ? l10n.groupDiscountTitle
                              : l10n.groupDiscountPendingTitle,
                        ),
                        Expanded(
                          child: ListView(
                            padding: EdgeInsets.zero,
                            children: [
                              if (applied)
                                _DiscountHero(
                                  amount: discount!.amount.formatted,
                                  percentage: discount.percentage,
                                )
                              else
                                _ThresholdHero(
                                  threshold: discount?.threshold,
                                  passengers: args.passengers,
                                  isLoading: state.isLoading,
                                ),
                              const SizedBox(height: AppSpacing.md),
                              KoraCard(
                                child: _GroupSteps(passengers: args.passengers),
                              ),
                              const SizedBox(height: AppSpacing.lg),
                            ],
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: KoraButton(
                  label: l10n.groupDiscountChooseSeats,
                  onPressed: () => _openSeats(args),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.discount-hero` : le montant économisé, en doré sur navy.
class _DiscountHero extends StatelessWidget {
  const _DiscountHero({required this.amount, required this.percentage});

  final String amount;
  final double percentage;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return _HeroShell(
      caption: l10n.groupDiscountSaved,
      value: Text(
        amount,
        style: const TextStyle(
          color: AppColors.gold,
          fontSize: 26,
          fontWeight: FontWeight.w900,
        ),
      ),
      footer: percentage > 0
          ? l10n.groupDiscountAutomaticPercent(_percentLabel(percentage))
          : l10n.groupDiscountAutomatic,
    );
  }

  /// `10` plutôt que `10.0`, `7.5` conservé tel quel : le serveur envoie un
  /// pourcentage décimal, la plupart du temps rond.
  static String _percentLabel(double percentage) =>
      percentage == percentage.roundToDouble()
      ? percentage.toStringAsFixed(0)
      : percentage.toStringAsFixed(1);
}

/// Le même bandeau, quand la remise n'est pas encore acquise.
///
/// Il annonce **combien de voyageurs il manque**, avec le seuil du serveur.
/// L'écran d'avant affichait un tiret muet sous « Vous économisez » : le
/// voyageur y lisait une promesse, et n'en voyait aucune trace.
class _ThresholdHero extends StatelessWidget {
  const _ThresholdHero({
    required this.threshold,
    required this.passengers,
    required this.isLoading,
  });

  /// `null` tant que le serveur n'a pas répondu.
  final int? threshold;
  final int passengers;
  final bool isLoading;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final needed = threshold;

    return _HeroShell(
      caption: l10n.groupDiscountPendingCaption,
      value: isLoading
          ? const SizedBox(
              height: 30,
              width: 30,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: AppColors.gold,
              ),
            )
          : Text(
              needed != null ? '$needed' : '—',
              style: const TextStyle(
                color: AppColors.gold,
                fontSize: 26,
                fontWeight: FontWeight.w900,
              ),
            ),
      footer: needed != null
          ? l10n.groupDiscountPendingFooter(needed - passengers)
          : l10n.groupDiscountPendingUnknown,
    );
  }
}

/// Le fond navy commun aux deux bandeaux.
class _HeroShell extends StatelessWidget {
  const _HeroShell({
    required this.caption,
    required this.value,
    required this.footer,
  });

  final String caption;
  final Widget value;
  final String footer;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: AppColors.kora,
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        children: [
          Text(
            caption,
            style: TextStyle(
              color: AppColors.white.withValues(alpha: .58),
              fontSize: 11,
            ),
          ),
          const SizedBox(height: 8),
          value,
          const SizedBox(height: 8),
          Text(
            footer,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: AppColors.white.withValues(alpha: .8),
              fontSize: 11,
            ),
          ),
        ],
      ),
    );
  }
}

/// `miniTimeline` : les trois temps de la réservation de groupe.
class _GroupSteps extends StatelessWidget {
  const _GroupSteps({required this.passengers});

  final int passengers;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final steps = [
      l10n.groupDiscountStepSeats(passengers),
      l10n.groupDiscountStepPassengers(passengers),
      l10n.groupDiscountStepPayment,
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var i = 0; i < steps.length; i++) ...[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 8,
                height: 8,
                margin: const EdgeInsets.only(top: 5),
                decoration: const BoxDecoration(
                  color: AppColors.gold,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  steps[i],
                  style: AppTextStyles.body(context.koraTitleColor),
                ),
              ),
            ],
          ),
          if (i < steps.length - 1) const SizedBox(height: 13),
        ],
      ],
    );
  }
}

/// L'écran ouvert sans données — un lien profond, ou un retour après
/// expiration.
class _MissingGroup extends StatelessWidget {
  const _MissingGroup();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                l10n.summaryMissingTitle,
                style: AppTextStyles.titleMedium(context.koraTitleColor),
              ),
              const SizedBox(height: AppSpacing.md),
              KoraButton(
                label: l10n.summaryMissingPrimary,
                onPressed: () => Navigator.of(context).pop(),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
