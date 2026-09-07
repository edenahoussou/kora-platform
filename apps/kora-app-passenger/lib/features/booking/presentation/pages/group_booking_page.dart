import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/group_booking_limits.dart';
import '../bloc/group_payer_cubit.dart';
import '../bloc/group_quote_cubit.dart';
import 'group_discount_page.dart';
import 'seats_page.dart';

/// Ce qu'attend [GroupBookingPage] : le départ retenu et le nombre de
/// voyageurs pressenti.
class GroupBookingArgs {
  const GroupBookingArgs({required this.departure, required this.passengers});

  final Departure departure;
  final int passengers;
}

/// Écran `group-booking` de la maquette (BUS-15) : le devis du groupe, et
/// le nombre exact de voyageurs.
///
/// Il vient **après** le choix du départ. C'est ce qui lui permet
/// d'afficher de vrais montants : tant que la ligne n'est pas connue, le
/// tarif n'existe pas, et la maquette n'y met qu'une valeur de
/// démonstration. Chaque changement du compteur redemande le devis — c'est
/// le serveur qui arrête le prix, jamais l'app.
class GroupBookingPage extends StatefulWidget {
  const GroupBookingPage({super.key, this.args});

  /// `null` si l'écran est ouvert sans données (lien profond).
  final GroupBookingArgs? args;

  @override
  State<GroupBookingPage> createState() => _GroupBookingPageState();
}

class _GroupBookingPageState extends State<GroupBookingPage> {
  late int _count = (widget.args?.passengers ?? kGroupMinimumPassengers).clamp(
    kGroupMinimumPassengers,
    kGroupMaximumPassengers,
  );

  @override
  void initState() {
    super.initState();
    _loadQuote();
    // Les comptes entreprise du voyageur : c'est ici qu'il désigne qui
    // paie, et le blocage s'en servira deux écrans plus loin.
    context.read<GroupPayerCubit>().loadAccounts();
  }

  void _change(int delta) {
    setState(() => _count += delta);
    _loadQuote();
  }

  /// Redemande le devis pour le nombre courant.
  ///
  /// Le prix d'un groupe n'est pas le prix unitaire multiplié : la remise
  /// dépend du seuil atteint, et seul le serveur connaît le barème.
  void _loadQuote() {
    final args = widget.args;
    if (args == null) return;

    context.read<GroupQuoteCubit>().load(
      departure: args.departure,
      passengers: _count,
    );
  }

  /// Enchaîne sur l'écran de remise — ou l'évite quand il n'aurait rien à
  /// dire.
  ///
  /// Le serveur peut ne connaître aucun barème : un écran intitulé
  /// « Avantage appliqué » qui n'annonce aucun avantage ment au voyageur,
  /// et lui coûte un geste pour rien. On va alors droit aux sièges.
  void _continue(GroupBookingArgs args) {
    final state = context.read<GroupQuoteCubit>().state;
    final quote = state.matches(args.departure.id, _count) ? state.quote : null;
    final worthShowing = quote?.discount.hasSomethingToSay ?? false;

    context.push(
      worthShowing ? AppRouter.groupDiscountPath : AppRouter.seatsPath,
      extra: worthShowing
          ? GroupDiscountArgs(departure: args.departure, passengers: _count)
          : SeatsArgs(departure: args.departure, passengers: _count),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final args = widget.args;

    if (args == null) return const _MissingDeparture();

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.groupBookingEyebrow(
                  kGroupMinimumPassengers,
                  kGroupMaximumPassengers,
                ),
                title: l10n.groupBookingTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    _GroupHero(count: _count),
                    const SizedBox(height: AppSpacing.md),
                    KoraCard(
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  l10n.groupBookingCountLabel,
                                  style: AppTextStyles.titleMedium(
                                    context.koraTitleColor,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                Text(
                                  l10n.groupBookingCountHint(
                                    kGroupMinimumPassengers,
                                    kGroupMaximumPassengers,
                                  ),
                                  style: AppTextStyles.body(
                                    koraColors.textMuted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          _GroupStepper(
                            count: _count,
                            onDecrement: _count > kGroupMinimumPassengers
                                ? () => _change(-1)
                                : null,
                            onIncrement: _count < kGroupMaximumPassengers
                                ? () => _change(1)
                                : null,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    const _PayerCard(),
                    const SizedBox(height: AppSpacing.md),
                    _GroupPriceCard(
                      departure: args.departure,
                      passengers: _count,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      child: Text(
                        l10n.groupBookingDiscountNotice,
                        style: AppTextStyles.body(koraColors.textMuted),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: KoraButton(
                  label: l10n.groupBookingConfirm(_count),
                  onPressed: () => _continue(args),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.group-hero` : le nombre en pastille dorée, sur fond navy.
class _GroupHero extends StatelessWidget {
  const _GroupHero({required this.count});

  final int count;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: AppColors.kora,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.gold,
              borderRadius: BorderRadius.circular(17),
            ),
            child: Text(
              '$count',
              style: const TextStyle(
                color: AppColors.kora,
                fontSize: 20,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.groupBookingHeroTitle,
                  style: const TextStyle(
                    color: AppColors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  l10n.groupBookingHeroText(kGroupMaximumPassengers),
                  style: TextStyle(
                    color: AppColors.white.withValues(alpha: .6),
                    fontSize: 11,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Le compteur `− n +`, borné aux limites du groupe.
class _GroupStepper extends StatelessWidget {
  const _GroupStepper({
    required this.count,
    required this.onDecrement,
    required this.onIncrement,
  });

  final int count;
  final VoidCallback? onDecrement;
  final VoidCallback? onIncrement;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        _StepperButton(icon: Icons.remove_rounded, onPressed: onDecrement),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          child: Text(
            '$count',
            style: AppTextStyles.titleMedium(context.koraTitleColor),
          ),
        ),
        _StepperButton(icon: Icons.add_rounded, onPressed: onIncrement),
      ],
    );
  }
}

class _StepperButton extends StatelessWidget {
  const _StepperButton({required this.icon, required this.onPressed});

  final IconData icon;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final enabled = onPressed != null;

    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(13),
      child: Container(
        width: 36,
        height: 36,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: koraColors.line),
        ),
        child: Icon(
          icon,
          size: 18,
          color: enabled ? context.koraTitleColor : koraColors.textMuted,
        ),
      ),
    );
  }
}

/// `detailRows` de la maquette : passagers, tarif initial, remise, total.
///
/// Les montants viennent du serveur. Tant qu'il n'a pas répondu — ou s'il a
/// refusé —, la carte retombe sur le tarif de la recherche multiplié par le
/// nombre : juste à la remise près, et bien plus utile qu'un écran vide.
class _GroupPriceCard extends StatelessWidget {
  const _GroupPriceCard({required this.departure, required this.passengers});

  final Departure departure;
  final int passengers;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return BlocBuilder<GroupQuoteCubit, GroupQuoteState>(
      builder: (context, state) {
        final quote = state.matches(departure.id, passengers)
            ? state.quote
            : null;
        final subtotal = quote?.subtotal ?? departure.price * passengers;
        final total = quote?.total ?? subtotal;
        final discount = quote?.discount;

        return KoraCard(
          child: Column(
            children: [
              _GroupPriceRow(
                label: l10n.groupBookingRowPassengers,
                value: '$passengers',
              ),
              const Divider(height: 26),
              _GroupPriceRow(
                label: l10n.groupBookingRowSubtotal,
                value: subtotal.formatted,
              ),
              if (discount != null && discount.isVisible) ...[
                const Divider(height: 26),
                _GroupPriceRow(
                  label: l10n.summaryGroupDiscount,
                  value: '−${discount.amount.formatted}',
                  isCredit: true,
                ),
              ],
              const Divider(height: 26),
              _GroupPriceRow(
                label: l10n.groupBookingRowTotal,
                value: total.formatted,
                isTotal: true,
              ),
            ],
          ),
        );
      },
    );
  }
}

class _GroupPriceRow extends StatelessWidget {
  const _GroupPriceRow({
    required this.label,
    required this.value,
    this.isTotal = false,
    this.isCredit = false,
  });

  final String label;
  final String value;
  final bool isTotal;

  /// Une somme retirée du total : peinte en vert, un montant négatif en
  /// bleu se lisant trop vite comme une ligne de plus à payer.
  final bool isCredit;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Flexible(
          child: Text(
            label,
            style: isTotal
                ? AppTextStyles.titleMedium(context.koraTitleColor)
                : AppTextStyles.body(koraColors.textMuted),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Text(
          value,
          style: TextStyle(
            color: isCredit ? AppColors.success : context.koraTitleColor,
            fontSize: isTotal ? 20 : 13,
            fontWeight: isTotal ? FontWeight.w900 : FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

/// L'écran ouvert sans départ — un lien profond, ou un retour après
/// expiration.
class _MissingDeparture extends StatelessWidget {
  const _MissingDeparture();

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

/// Qui règle la réservation.
///
/// Le ticket demande de distinguer clairement le **payeur** des voyageurs
/// bénéficiaires : celui qui paie n'est pas forcément du voyage, et il peut
/// engager le compte de son entreprise plutôt que le sien.
///
/// La carte disparaît quand le voyageur n'appartient à aucune entreprise :
/// un sélecteur à une seule option n'apprendrait rien, et le paiement
/// personnel reste le défaut.
class _PayerCard extends StatelessWidget {
  const _PayerCard();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return BlocBuilder<GroupPayerCubit, GroupPayerState>(
      builder: (context, state) {
        if (!state.hasChoice) return const SizedBox.shrink();

        final cubit = context.read<GroupPayerCubit>();

        return KoraCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                l10n.groupPayerTitle,
                style: AppTextStyles.titleMedium(context.koraTitleColor),
              ),
              const SizedBox(height: 3),
              Text(
                l10n.groupPayerHint,
                style: AppTextStyles.body(context.koraColors.textMuted),
              ),
              const SizedBox(height: AppSpacing.md),
              _PayerOption(
                label: l10n.groupPayerPersonal,
                selected: !state.payer.isCompany,
                onTap: cubit.choosePersonal,
              ),
              for (final account in state.accounts) ...[
                const SizedBox(height: 8),
                _PayerOption(
                  label: account.name,
                  // Le crédit restant n'est visible que pour qui en a le
                  // droit : le serveur le masque au membre ordinaire.
                  detail: account.showsCredit
                      ? l10n.groupPayerCredit(
                          account.availableCredit!.formatted,
                        )
                      : null,
                  selected:
                      state.payer.isCompany &&
                      state.payer.companyAccountId == account.id,
                  onTap: () => cubit.chooseCompany(account),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

/// Une ligne du sélecteur de payeur.
class _PayerOption extends StatelessWidget {
  const _PayerOption({
    required this.label,
    required this.selected,
    required this.onTap,
    this.detail,
  });

  final String label;
  final String? detail;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final subtitle = detail;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? AppColors.gold : koraColors.line,
            width: selected ? 1.5 : 1,
          ),
        ),
        child: Row(
          children: [
            Icon(
              selected
                  ? Icons.radio_button_checked_rounded
                  : Icons.radio_button_unchecked_rounded,
              size: 18,
              color: selected ? AppColors.gold : koraColors.textMuted,
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    label,
                    style: AppTextStyles.body(context.koraTitleColor),
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: AppTextStyles.caption(koraColors.textMuted),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
