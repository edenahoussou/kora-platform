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
import '../../domain/entities/group_quote.dart';
import '../../domain/entities/itinerary.dart';
import '../../domain/entities/journey.dart';
import '../../domain/entities/passenger.dart';
import '../../domain/entities/price.dart';
import '../bloc/group_payer_cubit.dart';
import '../bloc/group_quote_cubit.dart';
import '../bloc/seat_hold_bloc.dart';
import '../widgets/booking_progress.dart';
import '../widgets/hold_countdown.dart';
import '../widgets/state_view.dart';
import '../widgets/whatsapp_glyph.dart';
import 'assistance_request_page.dart';

/// Données passées à [SummaryPage] par `go_router` (`extra`).
class SummaryArgs {
  const SummaryArgs({required this.journey, required this.passengers});

  /// Le voyage entier — aller, retour éventuel, et les sièges de chaque
  /// tronçon. C'est lui que porte le devis : n'en envoyer qu'une partie
  /// afficherait une fraction du prix que le serveur débitera.
  final Journey journey;

  final List<Passenger> passengers;
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
    required this.journey,
    this.passengers = const [],
  });

  /// `null` si l'écran est ouvert sans données (lien profond).
  final Journey? journey;

  final List<Passenger> passengers;

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
    final journey = widget.journey;
    if (journey == null || widget.passengers.isEmpty) return;

    // Le voyage entier : sans ses correspondances ni son retour, le
    // devis ne couvrirait qu'un bus — l'écran annoncerait une fraction
    // du prix, et le serveur débiterait le tout.
    context.read<GroupQuoteCubit>().load(
      journey: journey,
      passengers: widget.passengers.length,
    );
  }

  @override
  Widget build(BuildContext context) {
    final journey = widget.journey;
    final passengers = widget.passengers;

    if (journey == null || passengers.isEmpty) {
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
          child: _SummaryContent(journey: journey, passengers: passengers),
        ),
      ),
    );
  }
}

class _SummaryContent extends StatelessWidget {
  const _SummaryContent({required this.journey, required this.passengers});

  final Journey journey;
  final List<Passenger> passengers;

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
              _TripCard(journey: journey, passengers: passengers),
              SectionTitle(
                title: l10n.summaryPassengers,
                actionLabel: l10n.summaryEdit,
                // Retour à l'écran précédent, celui de la saisie : la
                // correction se fait là où les champs existent déjà.
                onActionTap: () => Navigator.of(context).pop(),
              ),
              _PassengersCard(passengers: passengers),
              SectionTitle(title: l10n.summaryPromoTitle),
              _PromoCodeCard(journey: journey, passengers: passengers),
              SectionTitle(title: l10n.summaryPriceDetail),
              _PriceCard(journey: journey, passengers: passengers),
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.lg),
          child: _CheckoutButton(journey: journey, passengers: passengers),
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
class _CheckoutButton extends StatefulWidget {
  const _CheckoutButton({required this.journey, required this.passengers});

  final Journey journey;
  final List<Passenger> passengers;

  @override
  State<_CheckoutButton> createState() => _CheckoutButtonState();
}

class _CheckoutButtonState extends State<_CheckoutButton> {
  /// Le checkout en cours est le nôtre. L'envoi à une agence bloque les
  /// sièges par le même Bloc, depuis l'écran du dessus : sans ce drapeau,
  /// sa fin ouvrirait aussi le paiement d'ici.
  var _mine = false;

  Journey get journey => widget.journey;
  List<Passenger> get passengers => widget.passengers;

  void _checkout(BuildContext context) {
    _mine = true;
    context.read<SeatHoldBloc>().add(
      SeatHoldCheckoutRequested(
        journey: journey,
        passengers: passengers,
        // Le devis que l'écran affiche : le voyageur paie le prix qu'il
        // vient de lire.
        quote: _shownQuote(context, journey, passengers.length),
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
        total: _payableTotal(context, journey, passengers.length),
        // Le numéro du compte, proposé au débit Mobile Money : c'est celui
        // que le voyageur a sous la main, et le retaper à ce stade
        // n'apporte rien.
        phone: context.read<AuthBloc>().state.session?.user.phone,
        departure: journey.outbound.first,
        seatLabel: passengers
            .map((passenger) => passenger.seat.label)
            .join(', '),
      ),
    );
  }

  /// Confier la réservation à une agence (MEZ-81) plutôt que payer ici.
  void _handOff(BuildContext context) {
    context.push(
      AppRouter.assistanceRequestPath,
      extra: AssistanceArgs(
        journey: journey,
        passengers: passengers,
        quote: _shownQuote(context, journey, passengers.length),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return BlocConsumer<SeatHoldBloc, SeatHoldState>(
      listenWhen: (previous, current) =>
          _mine && previous.isCheckingOut && !current.isCheckingOut,
      listener: (context, hold) {
        _mine = false;
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
      builder: (context, hold) {
        // Un code en cours d'application change le prix : on ne part pas
        // bloquer pendant que le serveur le recalcule.
        final locked = hold.isRepricing || hold.isCheckingOut;

        // Une question, puis les deux réponses côte à côte, de même
        // poids : payer soi-même, ou confier la réservation à une agence.
        // Les libellés sont courts pour tenir en demi-largeur sans aller
        // à la ligne.
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            // Même titre que les sections du récapitulatif — hors de la
            // liste, il porte les boutons plutôt qu'une carte.
            Text(
              l10n.summaryPayQuestion,
              style: AppTextStyles.heading2(context.koraTitleColor),
            ),
            const SizedBox(height: 11),
            Row(
              children: [
                // Le libellé de gauche est court : il cède la largeur au
                // second, qui doit tenir « Avec un agent KORA » en entier.
                Expanded(
                  flex: 3,
                  child: KoraButton(
                    label: l10n.summaryContinue,
                    isLoading: _mine && hold.isCheckingOut,
                    onPressed: locked ? null : () => _checkout(context),
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Expanded(
                  flex: 5,
                  child: KoraButton(
                    label: l10n.summaryAssistCta,
                    glyph: const WhatsAppGlyph(size: 18),
                    variant: KoraButtonVariant.ghost,
                    onPressed: locked ? null : () => _handOff(context),
                  ),
                ),
              ],
            ),
          ],
        );
      },
    );
  }
}

/// Le devis affiché : celui du code promo quand il y en a un, celui du
/// tunnel sinon. `null` si le serveur n'a pas encore répondu.
GroupQuote? _shownQuote(BuildContext context, Journey journey, int count) {
  final promo = context.read<SeatHoldBloc>().state.quote;
  if (promo != null) return promo;

  final state = context.read<GroupQuoteCubit>().state;
  return state.matches(journey.outbound.id, count) ? state.quote : null;
}

/// Le montant à afficher sur les écrans de paiement.
///
/// Le devis du serveur s'il est là, le tarif de la recherche multiplié
/// sinon. La somme réellement débitée reste celle que le serveur arrête à
/// partir du `reservation_uuid` : ce total-ci ne sert qu'à l'affichage, et
/// c'est justement pour cela qu'il doit s'accorder avec le récapitulatif
/// que le voyageur vient de lire.
Price _payableTotal(BuildContext context, Journey journey, int count) {
  // Le devis bloqué, ou à défaut celui qu'affiche l'écran : c'est son total
  // que le serveur débitera.
  final quote = _shownQuote(context, journey, count);
  if (quote != null) return quote.total;

  return journey.total * count.clamp(1, 99);
}

/// `.card.navy` : le voyage tel qu'il a été choisi.
class _TripCard extends StatelessWidget {
  const _TripCard({required this.journey, required this.passengers});

  final Journey journey;
  final List<Passenger> passengers;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final departure = journey.outbound.first;
    final origin = journey.outbound.origin;

    // Les sièges du voyageur : un par bus qu'il prendra. N'en montrer
    // qu'un laisserait croire que les autres n'ont pas de place
    // attribuée.
    final seats = [
      for (final index in List.generate(passengers.length, (i) => i))
        journey.seatsOf(index).isEmpty
            ? passengers[index].seat.label
            : journey.seatsOf(index).map((seat) => seat.label).join(' · '),
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
            '${journey.outbound.origin.name} → '
            '${journey.outbound.destination.name}',
            style: const TextStyle(
              color: AppColors.white,
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            '${KoraDateFormats.capitalize(KoraDateFormats.weekdayDayMonth.format(journey.outbound.departsAt))} · '
            '${KoraDateFormats.time.format(journey.outbound.departsAt)}',
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.7),
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            _boardingPoint(l10n, origin.station, origin.name),
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.7),
              fontSize: 12,
            ),
          ),
          // Le changement de bus se dit ici aussi : c'est le dernier
          // écran avant de payer, et personne ne doit découvrir sa
          // correspondance en montant dans le bus.
          if (!journey.outbound.isDirect) ...[
            const SizedBox(height: 4),
            _ConnectionLine(itinerary: journey.outbound),
          ],
          // Le retour a son propre bus et son propre horaire : le taire
          // laisserait le voyageur vérifier la moitié de ce qu'il paie.
          if (journey.inbound case final inbound?) ...[
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
              '${inbound.origin.name} → ${inbound.destination.name}',
              style: const TextStyle(
                color: AppColors.white,
                fontSize: 15,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              '${KoraDateFormats.capitalize(KoraDateFormats.weekdayDayMonth.format(inbound.departsAt))} · '
              '${KoraDateFormats.time.format(inbound.departsAt)}',
              style: TextStyle(
                color: AppColors.white.withValues(alpha: 0.7),
                fontSize: 12,
              ),
            ),
            if (!inbound.isDirect) ...[
              const SizedBox(height: 4),
              _ConnectionLine(itinerary: inbound),
            ],
          ],
        ],
      ),
    );
  }

  /// Le point d'embarquement : `Gare KORA St Michel` quand l'arrêt a un
  /// nom propre, sinon le nom de la ville.
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
/// Le changement de bus, sur la carte navy du récapitulatif.
class _ConnectionLine extends StatelessWidget {
  const _ConnectionLine({required this.itinerary});

  final Itinerary itinerary;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const Icon(Icons.alt_route_rounded, size: 13, color: AppColors.gold),
        const SizedBox(width: AppSpacing.xs),
        Expanded(
          child: Text(
            context.l10n.departuresConnections(
              itinerary.connectionsCount,
              itinerary.connectionCities.map((city) => city.name).join(' · '),
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: AppColors.gold,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }
}

class _PriceCard extends StatelessWidget {
  const _PriceCard({required this.journey, required this.passengers});

  final Journey journey;
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
            (state.matches(journey.outbound.id, count) ? state.quote : null);

        // Le tarif unitaire du serveur s'il l'a donné, celui de la
        // recherche sinon — et c'est le prix du voyage entier, tous ses
        // bus compris.
        final unit = quote?.unitPrice ?? journey.total;
        final subtotal = quote?.subtotal ?? journey.total * count;
        final total = quote?.total ?? subtotal;
        final discount = quote?.discount;

        return KoraCard(
          child: Column(
            children: [
              _PriceRow(
                label: count > 1
                    ? '$count billets ${journey.outbound.first.comfort.label}'
                    : '1 billet ${journey.outbound.first.comfort.label}',
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
  const _PromoCodeCard({required this.journey, required this.passengers});

  final Journey journey;
  final List<Passenger> passengers;

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

  void _apply(BuildContext context, {String? code}) {
    context.read<SeatHoldBloc>().add(
      SeatHoldRepriceRequested(
        journey: widget.journey,
        passengers: widget.passengers,
        // Vide : le serveur retire la remise en cours.
        promoCode: code ?? _controller.text,
        payer: context.read<GroupPayerCubit>().state.payer,
      ),
    );
  }

  /// Retire le code appliqué, et vide le champ : ce qui y restait
  /// grisait le bouton, et n'est plus le code de la réservation.
  void _remove(BuildContext context) {
    _controller.clear();
    _apply(context, code: '');
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return BlocBuilder<SeatHoldBloc, SeatHoldState>(
      buildWhen: (previous, current) =>
          previous.quote != current.quote ||
          previous.promoStatus != current.promoStatus ||
          previous.promoError != current.promoError,
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
                    // Le serveur recalcule la réservation en place : un
                    // retrait ne lâche plus les sièges.
                    TextButton(
                      onPressed: busy ? null : () => _remove(context),
                      style: TextButton.styleFrom(
                        padding: EdgeInsets.zero,
                        minimumSize: Size.zero,
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                      child: Text(l10n.summaryPromoRemove),
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
              if (state.promoError != null) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(
                  state.promoError!,
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
