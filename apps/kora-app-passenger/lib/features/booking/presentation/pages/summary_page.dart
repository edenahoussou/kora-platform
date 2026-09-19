import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../payment/presentation/pages/payment_args.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/group_quote.dart';
import '../../domain/entities/passenger.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/seat.dart';
import '../bloc/group_payer_cubit.dart';
import '../bloc/group_quote_cubit.dart';
import '../bloc/seat_hold_bloc.dart';
import '../widgets/booking_progress.dart';
import '../widgets/hold_countdown.dart';
import '../widgets/state_view.dart';

/// Données passées à [SummaryPage] par `go_router` (`extra`).
class SummaryArgs {
  const SummaryArgs({
    required this.departure,
    required this.passengers,
    this.returnDeparture,
    this.returnSeats,
  });

  final Departure departure;
  final List<Passenger> passengers;

  /// Le retour d’un aller-retour, porté jusqu’au devis.
  final Departure? returnDeparture;

  /// Les sièges du retour, un par voyageur et dans le même ordre.
  final List<Seat>? returnSeats;
}

/// Récapitulatif avant paiement — écran `summary` de la maquette : rappel
/// du voyage, liste des voyageurs avec leur siège, détail du prix, puis
/// l'accès au paiement.
///
/// C'est ici que le prix s'arrête et que les sièges sont retenus : le code
/// promo ne fait qu'un nouveau devis, et « Continuer vers le paiement »
/// bloque les sièges sur le devis affiché avant d'y rattacher les
/// voyageurs (voir [SeatHoldBloc]).
class SummaryPage extends StatefulWidget {
  const SummaryPage({
    super.key,
    required this.departure,
    this.passengers = const [],
    this.returnDeparture,
    this.returnSeats,
  });

  /// `null` si l'écran est ouvert sans données (lien profond).
  final Departure? departure;

  final List<Passenger> passengers;

  /// Le retour d'un aller-retour. Le devis doit le porter, sinon le prix
  /// affiché ne couvre que la moitié du voyage.
  final Departure? returnDeparture;

  /// Les sièges du retour : le voyageur en a deux, et n'en montrer qu'un
  /// laisserait croire que le retour n'a pas de place attribuée.
  final List<Seat>? returnSeats;

  @override
  State<SummaryPage> createState() => _SummaryPageState();
}

class _SummaryPageState extends State<SummaryPage> {
  @override
  void initState() {
    super.initState();

    // Le devis du serveur : lui seul connaît le barème de la remise de
    // groupe. Demandé une fois à l'ouverture, et non dans `build` — un
    // écran qui émet un état pendant sa construction fait sauter Flutter,
    // et celui-ci se reconstruit à chaque battement du décompte.
    final departure = widget.departure;
    if (departure == null || widget.passengers.isEmpty) return;

    context.read<GroupQuoteCubit>().load(
      departure: departure,
      passengers: widget.passengers.length,
      // Sans le retour, le devis ne couvrirait que l'aller : l'écran
      // annoncerait la moitié du prix, et le serveur débiterait le tout.
      returnDeparture: widget.returnDeparture,
    );
  }

  @override
  Widget build(BuildContext context) {
    final departure = widget.departure;
    final passengers = widget.passengers;

    if (departure == null || passengers.isEmpty) {
      return const Scaffold(
        body: SafeArea(
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: 18),
            child: _MissingReservation(),
          ),
        ),
      );
    }

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: _SummaryContent(
            departure: departure,
            passengers: passengers,
            returnDeparture: widget.returnDeparture,
            returnSeats: widget.returnSeats,
          ),
        ),
      ),
    );
  }
}

class _SummaryContent extends StatelessWidget {
  const _SummaryContent({
    required this.departure,
    required this.passengers,
    this.returnDeparture,
    this.returnSeats,
  });

  final Departure departure;
  final List<Passenger> passengers;
  final Departure? returnDeparture;
  final List<Seat>? returnSeats;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ScreenHeader(eyebrow: l10n.summaryEyebrow, title: l10n.summaryTitle),
        Expanded(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              BookingProgress(step: 5, label: l10n.summaryEyebrow),
              const SizedBox(height: 11),
              const HoldCountdown(margin: EdgeInsets.only(bottom: 11)),
              _TripCard(
                departure: departure,
                passengers: passengers,
                returnDeparture: returnDeparture,
                returnSeats: returnSeats,
              ),
              SectionTitle(
                title: l10n.summaryPassengers,
                actionLabel: l10n.summaryEdit,
                // Retour à l'écran précédent, celui de la saisie : la
                // correction se fait là où les champs existent déjà.
                onActionTap: () => Navigator.of(context).pop(),
              ),
              _PassengersCard(passengers: passengers),
              SectionTitle(title: l10n.summaryPromoTitle),
              _PromoCodeCard(
                departure: departure,
                passengers: passengers,
                returnDeparture: returnDeparture,
                returnSeats: returnSeats,
              ),
              SectionTitle(title: l10n.summaryPriceDetail),
              _PriceCard(departure: departure, passengers: passengers),
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.lg),
          child: _CheckoutButton(
            departure: departure,
            passengers: passengers,
            returnDeparture: returnDeparture,
            returnSeats: returnSeats,
          ),
        ),
      ],
    );
  }
}

/// « Continuer vers le paiement » : devis, blocage des sièges, voyageurs,
/// puis le paiement.
///
/// Trois issues à la fin du checkout :
/// * **tout est retenu** — on ouvre le paiement, avec le total du devis
///   bloqué ;
/// * **un siège vient d'être pris** — retour au plan, qui le montre occupé
///   et le dit ;
/// * **autre refus** — le message du serveur, et le voyageur reste ici.
class _CheckoutButton extends StatelessWidget {
  const _CheckoutButton({
    required this.departure,
    required this.passengers,
    this.returnDeparture,
    this.returnSeats,
  });

  final Departure departure;
  final List<Passenger> passengers;
  final Departure? returnDeparture;
  final List<Seat>? returnSeats;

  void _checkout(BuildContext context) {
    context.read<SeatHoldBloc>().add(
      SeatHoldCheckoutRequested(
        departure: departure,
        passengers: passengers,
        // Le devis que l'écran affiche : le voyageur paie le prix qu'il
        // vient de lire.
        quote: _shownQuote(context, departure, passengers.length),
        returnDeparture: returnDeparture,
        returnSeats: returnSeats,
        // Choisi sur l'écran de groupe, en amont du plan.
        payer: context.read<GroupPayerCubit>().state.payer,
      ),
    );
  }

  void _openPayment(BuildContext context) {
    context.push(
      AppRouter.paymentMethodPath,
      // Le total est arrêté ici, puis transporté : le recalculer sur chaque
      // écran de paiement ferait dépendre la somme du chemin emprunté.
      extra: PaymentArgs(
        total: _payableTotal(context, departure, passengers.length),
        // Le numéro du compte, proposé au débit Mobile Money : c'est celui
        // que le voyageur a sous la main, et le retaper à ce stade
        // n'apporte rien.
        phone: context.read<AuthBloc>().state.session?.user.phone,
        departure: departure,
        seatLabel: passengers
            .map((passenger) => passenger.seat.label)
            .join(', '),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<SeatHoldBloc, SeatHoldState>(
      listenWhen: (previous, current) =>
          previous.isCheckingOut && !current.isCheckingOut,
      listener: (context, hold) {
        if (hold.isHeld && hold.passengersStatus == PassengersStatus.saved) {
          _openPayment(context);
          return;
        }

        final failure = hold.failure;
        if (failure is SeatUnavailableFailure) {
          // Le plan écoute le même bloc : il marque le siège occupé et
          // prévient le voyageur. Il reste à l'y ramener.
          Navigator.of(context).popUntil(
            (route) =>
                route.settings.name == AppRouter.seatsPath || route.isFirst,
          );
          return;
        }

        if (failure != null) KoraToaster.show(context, failure.message);
      },
      builder: (context, hold) => KoraButton(
        label: context.l10n.summaryContinue,
        isLoading: hold.isCheckingOut,
        // Un code en cours d'application change le prix : on ne part pas
        // bloquer pendant que le serveur le recalcule.
        onPressed: hold.isRepricing || hold.isCheckingOut
            ? null
            : () => _checkout(context),
      ),
    );
  }
}

/// Le devis affiché : celui du code promo quand il y en a un, celui du
/// tunnel sinon. `null` si le serveur n'a pas encore répondu.
GroupQuote? _shownQuote(BuildContext context, Departure departure, int count) {
  final promo = context.read<SeatHoldBloc>().state.quote;
  if (promo != null) return promo;

  final state = context.read<GroupQuoteCubit>().state;
  return state.matches(departure.id, count) ? state.quote : null;
}

/// Le montant à afficher sur les écrans de paiement.
///
/// Le devis du serveur s'il est là, le tarif de la recherche multiplié
/// sinon. La somme réellement débitée reste celle que le serveur arrête à
/// partir du `reservation_uuid` : ce total-ci ne sert qu'à l'affichage, et
/// c'est justement pour cela qu'il doit s'accorder avec le récapitulatif
/// que le voyageur vient de lire.
Price _payableTotal(BuildContext context, Departure departure, int count) {
  // Le devis bloqué, ou à défaut celui qu'affiche l'écran : c'est son total
  // que le serveur débitera.
  final quote = _shownQuote(context, departure, count);
  if (quote != null) return quote.total;

  return departure.price * count.clamp(1, 99);
}

/// `.card.navy` : le voyage tel qu'il a été choisi.
class _TripCard extends StatelessWidget {
  const _TripCard({
    required this.departure,
    required this.passengers,
    this.returnDeparture,
    this.returnSeats,
  });

  final Departure departure;
  final List<Passenger> passengers;
  final Departure? returnDeparture;
  final List<Seat>? returnSeats;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final line = departure.line;
    final returnSeats = this.returnSeats;

    // Les sièges du voyageur : le sien à l'aller, et celui du retour quand
    // il y en a un.
    final seats = [
      for (final (index, passenger) in passengers.indexed)
        if (returnSeats != null && index < returnSeats.length)
          l10n.summarySeatsRound(passenger.seat.label, returnSeats[index].label)
        else
          passenger.seat.label,
    ].join(', ');

    return KoraCard(
      variant: KoraCardVariant.navy,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              // La maquette affiche ici un code de ligne (`Ligne L2`) que
              // nos données n'ont pas ; la gamme du bus le remplace, comme
              // sur l'écran de détail.
              KoraTag(
                label: departure.comfort.label,
                variant: KoraTagVariant.gold,
              ),
              Flexible(
                child: Text(
                  passengers.length > 1
                      ? context.l10n.summarySeatMany(seats)
                      : context.l10n.summarySeatOne(seats),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.right,
                  style: TextStyle(
                    color: AppColors.white.withValues(alpha: 0.8),
                    fontSize: 11,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            '${line.origin.name} → ${line.destination.name}',
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            '${KoraDateFormats.capitalize(KoraDateFormats.weekdayDayMonth.format(departure.departsAt))} · '
            '${KoraDateFormats.time.format(departure.departsAt)}',
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.7),
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            _boardingPoint(l10n, line.origin.station, line.origin.name),
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.7),
              fontSize: 12,
            ),
          ),
          // Le retour a son propre bus et son propre horaire : le taire
          // laisserait le voyageur vérifier la moitié de ce qu'il paie.
          if (returnDeparture != null) ...[
            const Divider(height: 26, color: Colors.white24),
            Text(
              l10n.summaryReturn.toUpperCase(),
              style: TextStyle(
                color: AppColors.gold.withValues(alpha: 0.9),
                fontSize: 9,
                fontWeight: FontWeight.w900,
                letterSpacing: 0.9,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              '${returnDeparture!.line.origin.name} → '
              '${returnDeparture!.line.destination.name}',
              style: const TextStyle(
                color: AppColors.white,
                fontSize: 15,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              '${KoraDateFormats.capitalize(KoraDateFormats.weekdayDayMonth.format(returnDeparture!.departsAt))} · '
              '${KoraDateFormats.time.format(returnDeparture!.departsAt)}',
              style: TextStyle(
                color: AppColors.white.withValues(alpha: 0.7),
                fontSize: 12,
              ),
            ),
          ],
        ],
      ),
    );
  }

  /// `Gare KORA St Michel` quand la ville a une gare nommée, sinon son nom
  /// seul — toutes n'en ont qu'une, sans nom propre.
  String _boardingPoint(
    AppLocalization l10n,
    String? station,
    String cityName,
  ) {
    return station == null ? cityName : l10n.summaryStation(station);
  }
}

/// `.passenger-summary-row` : une ligne par voyageur, avec son siège.
class _PassengersCard extends StatelessWidget {
  const _PassengersCard({required this.passengers});

  final List<Passenger> passengers;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
      child: Column(
        children: [
          for (final passenger in passengers)
            Container(
              constraints: const BoxConstraints(minHeight: 48),
              decoration: BoxDecoration(
                border: passenger == passengers.last
                    ? null
                    : Border(bottom: BorderSide(color: koraColors.line)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          passenger.fullName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: context.koraTitleColor,
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          passenger.phone,
                          style: TextStyle(
                            color: koraColors.textMuted,
                            fontSize: 10,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  KoraTag(
                    label: passenger.seat.label,
                    variant: KoraTagVariant.gold,
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// Le détail du prix, ligne à ligne, jusqu'au total à payer.
///
/// Les montants viennent du **serveur** dès que son devis est là : lui
/// seul connaît le barème de la remise de groupe. Tant qu'il n'a pas
/// répondu — ou s'il a échoué —, l'écran retombe sur le tarif de la
/// recherche, juste à la remise près. Faire attendre le voyageur devant un
/// écran vide pour une ligne de détail coûterait plus que de lui montrer
/// un total sans son détail.
class _PriceCard extends StatelessWidget {
  const _PriceCard({required this.departure, required this.passengers});

  final Departure departure;
  final List<Passenger> passengers;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final count = passengers.length;

    return BlocBuilder<GroupQuoteCubit, GroupQuoteState>(
      builder: (context, state) {
        // Le devis du code promo — puis celui du blocage — l'emporte :
        // sous-total, remise promo et total viennent tous du serveur.
        final repriced = context.select<SeatHoldBloc, GroupQuote?>(
          (bloc) => bloc.state.quote,
        );
        final quote =
            repriced ??
            (state.matches(departure.id, count) ? state.quote : null);

        // Le tarif unitaire du serveur s'il l'a donné, celui de la
        // recherche sinon.
        final unit = quote?.unitPrice ?? departure.price;
        final subtotal = quote?.subtotal ?? departure.price * count;
        final total = quote?.total ?? subtotal;
        final discount = quote?.discount;

        return KoraCard(
          child: Column(
            children: [
              _PriceRow(
                label: count > 1
                    ? '$count billets ${departure.comfort.label}'
                    : '1 billet ${departure.comfort.label}',
                value: subtotal.formatted,
              ),
              if (count > 1) ...[
                const Divider(height: 26),
                _PriceRow(label: l10n.summaryUnitPrice, value: unit.formatted),
              ],
              // Avant la remise groupe : c'est l'ordre du calcul serveur,
              // qui applique la remise groupe au sous-total déjà remisé.
              if (quote?.promotion case final promotion?) ...[
                const Divider(height: 26),
                _PriceRow(
                  label: l10n.summaryPromoLine(promotion.code),
                  value: '−${promotion.discount.formatted}',
                  isCredit: true,
                ),
              ],
              if (discount != null && discount.isVisible) ...[
                const Divider(height: 26),
                _PriceRow(
                  // Le seuil vient du serveur : l'annoncer de mémoire
                  // exposerait à promettre une remise à partir d'un nombre
                  // que l'exploitation aurait changé.
                  label: discount.threshold != null
                      ? l10n.summaryGroupDiscountFrom(discount.threshold!)
                      : l10n.summaryGroupDiscount,
                  value: '−${discount.amount.formatted}',
                  isCredit: true,
                ),
              ],
              const Divider(height: 26),
              _PriceRow(
                label: l10n.summaryServiceFee,
                value: l10n.summaryServiceFeeValue,
              ),
              const Divider(height: 26),
              _PriceRow(
                label: l10n.summaryTotal,
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

class _PriceRow extends StatelessWidget {
  const _PriceRow({
    required this.label,
    required this.value,
    this.isTotal = false,
    this.isCredit = false,
  });

  final String label;
  final String value;
  final bool isTotal;

  /// Une somme retirée du total — la remise de groupe. Peinte en vert
  /// plutôt qu'en bleu : un montant négatif se lit trop vite comme une
  /// ligne de plus à payer.
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
                ? TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  )
                : AppTextStyles.body(koraColors.textMuted),
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Text(
          value,
          // `.amount` : le total est l'information que l'on vient vérifier.
          style: isTotal
              ? TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 23,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.69,
                )
              : TextStyle(
                  color: isCredit ? AppColors.success : context.koraTitleColor,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
        ),
      ],
    );
  }
}

class _MissingReservation extends StatelessWidget {
  const _MissingReservation();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          eyebrow: l10n.departuresEyebrow,
          title: l10n.summaryMissingEyebrow,
        ),
        StateView(
          icon: Icons.help_outline_rounded,
          title: l10n.summaryMissingTitle,
          message: l10n.summaryMissingText,
          primaryLabel: l10n.summaryMissingPrimary,
          onPrimary: () => Navigator.of(context).pop(),
        ),
      ],
    );
  }
}

/// Le code promo : le saisir, l'appliquer, le remplacer ou le retirer.
///
/// Rien ne se calcule ici. Appliquer un code demande au serveur un devis
/// avec ce code, puis rebloque les mêmes sièges à ce prix ; le retirer
/// fait de même sans code. Un code refusé ne lâche rien : il affiche la
/// raison du serveur, et le voyageur peut payer au tarif normal.
class _PromoCodeCard extends StatefulWidget {
  const _PromoCodeCard({
    required this.departure,
    required this.passengers,
    this.returnDeparture,
    this.returnSeats,
  });

  final Departure departure;
  final List<Passenger> passengers;
  final Departure? returnDeparture;
  final List<Seat>? returnSeats;

  @override
  State<_PromoCodeCard> createState() => _PromoCodeCardState();
}

class _PromoCodeCardState extends State<_PromoCodeCard> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _apply(BuildContext context) {
    context.read<SeatHoldBloc>().add(
      SeatHoldRepriceRequested(
        departure: widget.departure,
        passengers: widget.passengers,
        promoCode: _controller.text,
        returnDeparture: widget.returnDeparture,
        returnSeats: widget.returnSeats,
        payer: context.read<GroupPayerCubit>().state.payer,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return BlocBuilder<SeatHoldBloc, SeatHoldState>(
      buildWhen: (previous, current) =>
          previous.quote != current.quote ||
          previous.promoStatus != current.promoStatus ||
          previous.promoError != current.promoError ||
          previous.promoUnavailable != current.promoUnavailable,
      builder: (context, state) {
        final promotion = state.quote?.promotion;
        final busy = state.isRepricing;

        // Le code déjà appliqué ne se réapplique pas : un second appui
        // redemanderait le même devis — et, sièges retenus, les
        // relâcherait pour rien. Un autre code saisi rend le bouton.
        bool alreadyApplied(String text) =>
            promotion != null &&
            text.trim().toUpperCase() == promotion.code.toUpperCase();

        return KoraCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (promotion != null) ...[
                Row(
                  children: [
                    const Icon(
                      Icons.check_circle_rounded,
                      color: AppColors.success,
                      size: 20,
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: Text(
                        l10n.summaryPromoApplied(promotion.code),
                        style: TextStyle(
                          color: context.koraTitleColor,
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.sm),
              ],
              // Le champ reste ouvert après un code appliqué : remplacer un
              // code, c'est en saisir un autre.
              TextField(
                controller: _controller,
                enabled: !busy,
                textCapitalization: TextCapitalization.characters,
                textInputAction: TextInputAction.done,
                onSubmitted: (text) {
                  if (!alreadyApplied(text)) _apply(context);
                },
                decoration: InputDecoration(hintText: l10n.summaryPromoHint),
              ),
              const SizedBox(height: AppSpacing.sm),
              // Suit la saisie : le bouton se grise dès que le champ reprend
              // le code appliqué, et revient dès qu'on en tape un autre.
              ValueListenableBuilder<TextEditingValue>(
                valueListenable: _controller,
                builder: (context, value, _) {
                  final applied = alreadyApplied(value.text);

                  // Estompé à la main : `KoraButton` désactivé garde son
                  // libellé en pleine couleur, et le bouton paraissait
                  // encore actif.
                  return Opacity(
                    opacity: applied ? 0.4 : 1,
                    child: KoraButton(
                      label: busy
                          ? l10n.summaryPromoChecking
                          : l10n.summaryPromoApply,
                      variant: KoraButtonVariant.ghost,
                      isLoading: busy,
                      onPressed: busy || applied ? null : () => _apply(context),
                    ),
                  );
                },
              ),
              if (state.promoError != null || state.promoUnavailable) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(
                  state.promoError ?? l10n.summaryPromoUnavailable,
                  style: AppTextStyles.caption(AppColors.danger),
                ),
                const SizedBox(height: 2),
                // Un code refusé n'arrête pas l'achat, et ne retire pas celui
                // qui était déjà appliqué : le dire, plutôt que d'annoncer un
                // tarif normal que le voyageur ne paiera pas.
                Text(
                  promotion != null
                      ? l10n.summaryPromoKept(promotion.code)
                      : l10n.summaryPromoContinue,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}
