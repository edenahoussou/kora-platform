import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../domain/entities/assisted_booking.dart';
import '../../domain/entities/group_quote.dart';
import '../../domain/entities/journey.dart';
import '../../domain/entities/passenger.dart';
import '../bloc/assistance_cubits.dart';
import '../bloc/group_payer_cubit.dart';
import '../bloc/seat_hold_bloc.dart';
import '../widgets/state_view.dart';
import '../widgets/whatsapp_glyph.dart';
import 'assistance_status_page.dart';

/// Données passées à [AssistanceRequestPage] par `go_router` (`extra`) :
/// tout ce que le récapitulatif sait, pour bloquer puis confier la même
/// réservation.
class AssistanceArgs {
  const AssistanceArgs({
    required this.journey,
    required this.passengers,
    this.quote,
  });

  /// Le voyage entier, sièges compris : l'agence reprend la réservation
  /// telle qu'elle a été composée, correspondances et retour inclus.
  final Journey journey;

  final List<Passenger> passengers;

  /// Le devis affiché au récapitulatif, code promo compris.
  final GroupQuote? quote;
}

/// Confier la réservation à un agent KORA (MEZ-81) : le voyageur choisit
/// par où il veut être joint, l'app bloque la réservation puis l'envoie, et
/// WhatsApp s'ouvre sur le message déjà rédigé.
///
/// **Le voyageur ne choisit plus d'agence** : la demande part au support
/// central, et c'est le réseau KORA qui affecte ensuite celle qui
/// finalisera.
///
/// **La réservation reste la même** : bloquée comme pour le paiement en
/// ligne — le serveur n'accepte d'envoyer qu'une réservation retenue, avec
/// ses voyageurs —, puis confiée. Pendant l'attente, le serveur relâche
/// les sièges : c'est l'agence qui les confirmera.
class AssistanceRequestPage extends StatelessWidget {
  const AssistanceRequestPage({super.key, this.args});

  final AssistanceArgs? args;

  @override
  Widget build(BuildContext context) {
    final args = this.args;

    if (args == null || args.passengers.isEmpty) {
      return Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: StateView(
              icon: Icons.help_outline_rounded,
              title: context.l10n.assistTitle,
              message: context.l10n.summaryMissingText,
              primaryLabel: context.l10n.summaryMissingPrimary,
              onPrimary: () => Navigator.of(context).pop(),
            ),
          ),
        ),
      );
    }

    return BlocProvider<AssistanceRequestCubit>(
      create: (_) => getIt<AssistanceRequestCubit>(),
      child: _RequestView(args: args),
    );
  }
}

class _RequestView extends StatefulWidget {
  const _RequestView({required this.args});

  final AssistanceArgs args;

  @override
  State<_RequestView> createState() => _RequestViewState();
}

class _RequestViewState extends State<_RequestView> {
  /// Le blocage des sièges est en cours : il précède l'envoi.
  var _holding = false;

  /// Le contact présenté à l'agent : le titulaire du compte, ou à défaut
  /// le premier voyageur — celui que le formulaire pré-remplit.
  ({String name, String phone})? _contact() {
    final user = context.read<AuthBloc>().state.session?.user;
    final first = widget.args.passengers.first;

    final phone = _international(user?.phone) ?? _international(first.phone);
    if (phone == null) return null;

    final name = (user?.name.trim().isNotEmpty ?? false)
        ? user!.name.trim().split(RegExp(r'\s+')).first
        : first.firstName;
    return (name: name, phone: phone);
  }

  Future<void> _send() async {
    final contact = _contact();
    final holdBloc = context.read<SeatHoldBloc>();
    final cubit = context.read<AssistanceRequestCubit>();
    if (contact == null ||
        _holding ||
        cubit.state.isSending ||
        holdBloc.state.isCheckingOut ||
        holdBloc.state.isRepricing) {
      return;
    }

    final args = widget.args;
    setState(() => _holding = true);

    // Le même blocage que « En ligne » — devis affiché, sièges, voyageurs.
    // Revenu ici après un premier essai, la réservation déjà retenue est
    // gardée : seuls les voyageurs repartent.
    holdBloc.add(
      SeatHoldCheckoutRequested(
        journey: args.journey,
        passengers: args.passengers,
        quote: args.quote,
        payer: context.read<GroupPayerCubit>().state.payer,
      ),
    );
    await holdBloc.stream.firstWhere((state) => state.isCheckingOut);
    final held = await holdBloc.stream.firstWhere(
      (state) => !state.isCheckingOut,
    );

    if (!mounted) return;
    setState(() => _holding = false);

    final hold = held.hold;
    if (!held.isHeld ||
        hold == null ||
        held.passengersStatus != PassengersStatus.saved) {
      _showHoldFailure(held.failure);
      return;
    }

    await cubit.send(
      reservationId: hold.id,
      contactName: contact.name,
      contactPhone: contact.phone,
      record: (booking) => AssistanceRecord(
        reservationId: booking.reservationId.isEmpty
            ? hold.id
            : booking.reservationId,
        reference: booking.reference,
        sentAt: DateTime.now(),
        origin: args.journey.outbound.origin.name,
        destination: args.journey.outbound.destination.name,
        departsAt: args.journey.outbound.departsAt,
        // Nulle à l'envoi : personne n'a encore pris le dossier. L'écran
        // de suivi la nommera dès que le réseau l'aura affectée.
        agencyName: booking.agency?.name,
        // Un siège par voyageur et par tronçon, sous le `leg_order` du
        // blocage : l'agence reprend le dossier avec toutes ses places.
        seats: [
          for (final (leg, seats) in args.journey.seatsByLeg.indexed)
            for (final (index, seat) in seats.indexed)
              AssistanceSeat(
                passengerIndex: index,
                legOrder: leg + 1,
                seatId: seat.id,
                label: seat.label,
              ),
        ],
      ),
    );
  }

  void _showHoldFailure(Failure? failure) {
    if (failure is SeatUnavailableFailure) {
      // Le plan écoute le même bloc : il marque le siège pris et le dit.
      Navigator.of(context).popUntil(
        (route) => route.settings.name == AppRouter.seatsPath || route.isFirst,
      );
      return;
    }
    if (failure != null) KoraToaster.show(context, failure.message);
  }

  void _onSent(AssistedBooking booking) {
    // Les sièges ne sont plus retenus par l'app : l'agence les confirmera.
    // Laisser courir le décompte enverrait sur l'écran d'expiration.
    context.read<SeatHoldBloc>().add(const SeatHoldSettled());
    context.go(
      AppRouter.assistanceStatusPath,
      extra: AssistanceStatusArgs(
        reservationId: booking.reservationId,
        initial: booking,
        openChannel: true,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final contact = _contact();

    return BlocConsumer<AssistanceRequestCubit, AssistanceRequestState>(
      listenWhen: (previous, current) =>
          previous.sent != current.sent ||
          (previous.failure != current.failure && current.failure != null),
      listener: (context, state) {
        final sent = state.sent;
        if (sent != null) {
          _onSent(sent);
          return;
        }
        // Le refus d'un envoi : réseau, réservation non éligible, support
        // injoignable.
        final failure = state.failure;
        if (failure != null) KoraToaster.show(context, failure.message);
      },
      builder: (context, state) {
        final busy = _holding || state.isSending;

        return Scaffold(
          body: SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ScreenHeader(
                    eyebrow: l10n.assistEyebrow,
                    title: l10n.assistTitle,
                  ),
                  Expanded(
                    child: ListView(
                      padding: EdgeInsets.zero,
                      children: [
                        Text(
                          l10n.assistIntro,
                          style: AppTextStyles.body(
                            context.koraColors.textMuted,
                          ),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        KoraCard(
                          variant: KoraCardVariant.mist,
                          child: Text(
                            l10n.assistWarning,
                            style: AppTextStyles.body(
                              context.koraColors.textMuted,
                            ),
                          ),
                        ),
                        SectionTitle(title: l10n.assistChannelTitle),
                        _ChannelTile(
                          glyph: const WhatsAppGlyph(),
                          title: l10n.assistChannelWhatsapp,
                          subtitle: l10n.assistChannelWhatsappSub,
                          selected: state.channel == AssistanceChannel.whatsapp,
                          onTap: busy
                              ? null
                              : () => context
                                    .read<AssistanceRequestCubit>()
                                    .chooseChannel(AssistanceChannel.whatsapp),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        _ChannelTile(
                          icon: Icons.call_rounded,
                          title: l10n.assistChannelCall,
                          subtitle: l10n.assistChannelCallSub,
                          selected: state.channel == AssistanceChannel.call,
                          onTap: busy
                              ? null
                              : () => context
                                    .read<AssistanceRequestCubit>()
                                    .chooseChannel(AssistanceChannel.call),
                        ),
                        const SizedBox(height: AppSpacing.md),
                        Text(
                          contact == null
                              ? l10n.assistNoPhone
                              : l10n.assistContact(contact.phone),
                          style: AppTextStyles.caption(
                            contact == null
                                ? AppColors.danger
                                : context.koraColors.textMuted,
                          ),
                        ),
                        const SizedBox(height: AppSpacing.lg),
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                    child: KoraButton(
                      label: busy
                          ? l10n.assistSending
                          : (state.channel == AssistanceChannel.call
                                ? l10n.assistSendCall
                                : l10n.assistSendWhatsapp),
                      icon: Icons.send_rounded,
                      isLoading: busy,
                      // Désactivé pendant l'envoi : un second appui ne doit
                      // pas partir, même si la clé d'idempotence le
                      // rattraperait.
                      onPressed: busy || contact == null ? null : _send,
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

/// Un canal proposé au voyageur : WhatsApp, ou un appel.
class _ChannelTile extends StatelessWidget {
  const _ChannelTile({
    required this.title,
    required this.subtitle,
    required this.selected,
    this.icon,
    this.glyph,
    this.onTap,
  });

  final String title;
  final String subtitle;
  final bool selected;
  final IconData? icon;

  /// Le logo WhatsApp, là où une icône de marque est attendue.
  final Widget? glyph;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.md),
        decoration: BoxDecoration(
          color: koraColors.surface,
          borderRadius: BorderRadius.circular(AppRadius.md),
          border: Border.all(
            color: selected ? AppColors.gold : koraColors.line,
            width: selected ? 2 : 1,
          ),
        ),
        child: Row(
          children: [
            Icon(
              selected
                  ? Icons.radio_button_checked_rounded
                  : Icons.radio_button_off_rounded,
              color: selected ? AppColors.gold : koraColors.textMuted,
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: AppTextStyles.caption(koraColors.textMuted),
                  ),
                ],
              ),
            ),
            glyph ?? Icon(icon, size: 20, color: koraColors.textMuted),
          ],
        ),
      ),
    );
  }
}

/// Un numéro au format international (`+2290197000000`), ou `null` s'il
/// n'en est pas un : c'est la seule forme que le serveur accepte.
String? _international(String? phone) {
  if (phone == null) return null;
  final compact = phone.replaceAll(RegExp(r'[^\d+]'), '');
  return RegExp(r'^\+[1-9]\d{7,14}$').hasMatch(compact) ? compact : null;
}
