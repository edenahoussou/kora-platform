import 'package:flutter/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../booking/presentation/bloc/seat_hold_bloc.dart';
import '../bloc/payment_flow_bloc.dart';
import 'payment_args.dart';

/// Vrai quand l'état du parcours parle bien de la réservation en cours.
///
/// [PaymentFlowBloc] vit au niveau du parcours, pas de l'écran : il est
/// partagé par le formulaire, l'écran d'attente et celui de confirmation,
/// et il redemande au serveur, à l'entrée, l'état du paiement laissé en
/// attente au lancement précédent — le webhook de l'opérateur n'attend
/// pas que l'app revienne.
///
/// Ce paiement repris peut être celui d'**une autre réservation**. Sans ce
/// filtre, son statut pilotait quand même l'écran du voyageur en train
/// d'en payer une nouvelle : la feuille KKiaPay s'ouvrait avec le montant
/// de l'ancienne, et le bouton « Payer » devenait « Reprendre » — c'est-à-
/// dire que la nouvelle réservation ne se payait plus du tout.
///
/// Une **recharge** n'a pas de blocage de sièges : sans [args], tout
/// paiement repris passait ce filtre et pilotait l'écran de recharge —
/// c'est ainsi qu'un vieux paiement de billet y annonçait « votre siège
/// reste réservé ». On compare alors à la recharge en cours.
///
/// Un parcours encore vierge (`reservationId` nul) et un écran sans
/// blocage passent : il n'y a alors rien qui diverge.
bool paymentFlowMatchesHold(
  BuildContext context,
  PaymentFlowState flow, [
  PaymentArgs? args,
]) {
  final paying = flow.reservationId;
  if (paying == null) return true;

  final topUp = args?.topUpAmount;
  if (topUp != null) return paying == PaymentFlowBloc.topUpTag(topUp);

  final hold = context.read<SeatHoldBloc>().state.hold?.id;
  if (hold == null) return true;

  return hold == paying;
}
