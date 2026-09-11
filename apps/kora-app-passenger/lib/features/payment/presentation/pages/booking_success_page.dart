import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../booking/presentation/bloc/seat_hold_bloc.dart';
import '../../../booking/presentation/pages/group_tickets_page.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import 'payment_flow_args.dart';
import 'payment_method_page.dart';

/// Écran `booking-success` de la maquette : le voyage est confirmé.
///
/// Le blocage est relâché en arrivant ici — les sièges ne sont plus
/// « retenus », ils sont vendus. Laisser courir le décompte ferait croire
/// que la réservation peut encore expirer, et le renverrait à l'écran de
/// fin de délai une fois le billet payé.
class BookingSuccessPage extends StatefulWidget {
  const BookingSuccessPage({super.key, this.args});

  final PaymentFlowArgs? args;

  @override
  State<BookingSuccessPage> createState() => _BookingSuccessPageState();
}

class _BookingSuccessPageState extends State<BookingSuccessPage> {
  /// La réservation qu'on vient de payer.
  ///
  /// Retenue ici parce que [SeatHoldSettled] vide le blocage — c'est son
  /// rôle, la réservation est payée et le décompte n'a plus lieu d'être.
  /// Mais « Afficher mon billet » en a besoin juste après : sans cette
  /// copie, le bouton ne trouvait plus d'identifiant et renvoyait à la
  /// liste des voyages, où la réservation ne figure pas encore.
  String? _reservationId;
  int _passengers = 1;

  @override
  void initState() {
    super.initState();
    // Après la première frame : `SeatHoldWatcher` écoute encore ce Bloc, et
    // émettre pendant la construction déclencherait une navigation au
    // milieu d'un build.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;

      final hold = context.read<SeatHoldBloc>().state;
      _reservationId = hold.hold?.id;
      _passengers = hold.passengers;

      context.read<SeatHoldBloc>().add(const SeatHoldSettled());
    });
  }

  /// Ouvre les billets émis pour la réservation qu'on vient de payer.
  ///
  /// Un voyageur seul y va comme un groupe : cet écran-là lit les billets
  /// du serveur (`bookings/{uuid}/tickets`), QR compris. Le renvoyer vers
  /// l'onglet Voyages, comme on le faisait, le laissait devant une liste
  /// qui ne connaît pas encore sa réservation — le bouton semblait ne
  /// rien faire.
  void _openTickets(BuildContext context) {
    final reservationId = _reservationId;

    // Sans réservation en mémoire il n'y a pas de billet à aller chercher :
    // reste la liste des voyages, seule porte ouverte.
    if (reservationId == null) {
      context.go('/voyages');
      return;
    }

    context.push(
      AppRouter.groupTicketsPath,
      extra: GroupTicketsArgs(
        reservationId: reservationId,
        passengers: _passengers,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = widget.args;

    if (args == null) return const PaymentMissingArgs();

    final departure = args.departure;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: ListView(
            children: [
              const SizedBox(height: AppSpacing.lg),
              StateView(
                icon: Icons.check_rounded,
                title: l10n.bookingSuccessTitle,
                message: l10n.bookingSuccessText,
                primaryLabel: l10n.bookingSuccessTicket,
                // Un groupe a autant de billets que de voyageurs, chacun
                // avec son QR : l'écran dédié les liste. Un voyageur seul
                // n'a qu'un billet, et la liste des voyages y mène déjà.
                onPrimary: () => _openTickets(context),
                secondaryLabel: l10n.holdExpiredSecondary,
                onSecondary: () => context.go('/home'),
              ),
              if (departure != null) ...[
                const SizedBox(height: AppSpacing.lg),
                KoraCard(
                  variant: KoraCardVariant.navy,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          KoraTag(
                            label: KoraDateFormats.dayMonth.format(
                              departure.departsAt,
                            ),
                          ),
                          if (args.seatLabel != null)
                            Text(
                              l10n.bookingSuccessSeat(args.seatLabel!),
                              style: const TextStyle(
                                color: AppColors.white,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.md),
                      Text(
                        '${departure.line.origin.name} → '
                        '${departure.line.destination.name}',
                        style: const TextStyle(
                          color: AppColors.white,
                          fontSize: 19,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        l10n.bookingSuccessDeparture(
                          KoraDateFormats.time.format(departure.departsAt),
                          departure.line.origin.label,
                        ),
                        style: TextStyle(
                          color: AppColors.white.withValues(alpha: 0.75),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.xl),
            ],
          ),
        ),
      ),
    );
  }
}
