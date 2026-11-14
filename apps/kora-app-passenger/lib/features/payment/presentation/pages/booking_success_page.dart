import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../booking/presentation/bloc/cancel_ticket_cubit.dart';
import '../../../booking/presentation/bloc/seat_hold_bloc.dart';
import '../../../booking/presentation/pages/group_tickets_page.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../../../wallet/presentation/bloc/wallet_refresh.dart';
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

      // L'onglet Voyages est resté en mémoire derrière le tunnel : sans ce
      // signal, il continuerait d'afficher la liste d'avant l'achat, et le
      // voyage qu'on vient de payer n'y figurerait qu'après une
      // reconnexion.
      if (getIt.isRegistered<BookingRefresh>()) {
        getIt<BookingRefresh>().notify();
      }

      // L'onglet Wallet est resté en mémoire lui aussi. Un billet réglé
      // en partie avec le solde le laissait sur le montant d'avant
      // l'achat : le voyageur venait vérifier son crédit après avoir
      // payé, et lisait un chiffre faux jusqu'à ce qu'il tire pour
      // rafraîchir.
      //
      // Le signal part sans condition. Savoir *si* le Wallet a servi
      // demanderait de porter la répartition jusqu'ici, et c'est
      // précisément un champ recopié d'écran en écran qui avait déjà
      // laissé tomber cette part en route. Une lecture du solde de trop
      // ne coûte rien ; un solde faux coûte un appel au support.
      if (getIt.isRegistered<WalletRefresh>()) {
        getIt<WalletRefresh>().notify();
      }
    });
  }

  /// Ouvre les billets émis pour la réservation qu'on vient de payer.
  ///
  /// Un voyageur seul y va comme un groupe : cet écran-là lit les billets
  /// du serveur (`bookings/{uuid}/tickets`), QR compris. Le renvoyer vers
  /// l'onglet Voyages, comme on le faisait, le laissait devant une liste
  /// qui ne connaît pas encore sa réservation — le bouton semblait ne
  /// rien faire.
  /// Ramène à l'onglet Wallet.
  ///
  /// Il n'a rien à redemander ici : le signal est parti dès l'arrivée
  /// sur cet écran, pour que l'onglet soit juste aussi quand on y va
  /// par la barre du bas plutôt que par ce bouton.
  void _openWallet(BuildContext context) => context.go(AppRouter.walletPath);

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
        exitToHome: true,
        passengers: _passengers,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = widget.args;

    if (args == null) return const PaymentMissingArgs();

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: ListView(
            children: [
              const SizedBox(height: AppSpacing.lg),
              StateView(
                icon: Icons.check_rounded,
                // Une recharge n'émet pas de billet : « Votre voyage
                // est confirmé » suivi de « Afficher mon billet »
                // enverrait le voyageur chercher un titre inexistant.
                title: args.isTopUp
                    ? l10n.paymentTopUpSuccessTitle
                    : l10n.bookingSuccessTitle,
                message: args.isTopUp
                    ? l10n.paymentTopUpSuccessText(args.total.formatted)
                    : l10n.bookingSuccessText,
                primaryLabel: args.isTopUp
                    ? l10n.paymentTopUpSuccessAction
                    : l10n.bookingSuccessTicket,
                // Un groupe a autant de billets que de voyageurs, chacun
                // avec son QR : l'écran dédié les liste. Un voyageur seul
                // n'a qu'un billet, et la liste des voyages y mène déjà.
                //
                // Une recharge, elle, n'a pas de billet : le bouton
                // disait « Voir mon Wallet » et menait aux voyages. On
                // changeait le mot sans changer la porte.
                onPrimary: () =>
                    args.isTopUp ? _openWallet(context) : _openTickets(context),
                secondaryLabel: l10n.holdExpiredSecondary,
                onSecondary: () => context.go('/home'),
              ),
              const SizedBox(height: AppSpacing.xl),
            ],
          ),
        ),
      ),
    );
  }
}
