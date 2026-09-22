import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../payment/presentation/pages/payment_args.dart';
import '../../domain/entities/assisted_booking.dart';
import '../bloc/assistance_cubits.dart';
import '../bloc/seat_hold_bloc.dart';
import '../widgets/assistance_card.dart';
import '../widgets/state_view.dart';
import '../widgets/whatsapp_glyph.dart';

/// Données passées à [AssistanceStatusPage] par `go_router` (`extra`).
class AssistanceStatusArgs {
  const AssistanceStatusArgs({
    required this.reservationId,
    this.initial,
    this.openChannel = false,
  });

  final String reservationId;

  /// Le dossier que l'envoi vient de rendre : l'écran s'ouvre dessus sans
  /// attendre le réseau.
  final AssistedBooking? initial;

  /// Ouvrir WhatsApp (ou l'appel) dès l'arrivée : c'est la suite de
  /// l'envoi, le voyageur n'a plus qu'à appuyer sur « envoyer ».
  final bool openChannel;
}

/// Ouvre un lien hors de l'app. Injectable pour les tests, comme sur
/// l'écran de contact.
typedef ExternalLauncher = Future<bool> Function(Uri uri);

/// La demande confiée à une agence : confirmation juste après l'envoi,
/// puis suivi (MEZ-81).
///
/// **Tout tient en deux blocs** : la carte de la demande — la même que
/// dans « Mes voyages », complétée de l'agence et du prix — puis la liste
/// des actions, comme sur le détail d'un billet. La référence `KBR-…` est
/// le fil rouge entre le voyageur et l'agence.
///
/// L'écran se relit en revenant au premier plan : le voyageur y revient
/// depuis WhatsApp, et l'agence a pu prendre le dossier entre-temps.
class AssistanceStatusPage extends StatelessWidget {
  const AssistanceStatusPage({super.key, this.args, this.launcher});

  final AssistanceStatusArgs? args;
  final ExternalLauncher? launcher;

  @override
  Widget build(BuildContext context) {
    final args = this.args;

    if (args == null) {
      return Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18),
            child: StateView(
              icon: Icons.help_outline_rounded,
              title: context.l10n.assistStatusTitle,
              message: context.l10n.assistRefreshError,
              primaryLabel: context.l10n.assistHome,
              onPrimary: () => context.go('/home'),
            ),
          ),
        ),
      );
    }

    return BlocProvider<AssistanceStatusCubit>(
      create: (_) =>
          getIt<AssistanceStatusCubit>()
            ..load(args.reservationId, initial: args.initial),
      child: _StatusView(
        // Le dossier qui vient d'être envoyé : le cubit le recevra aussi,
        // mais après la première frame — trop tard pour ouvrir WhatsApp.
        openWith: args.openChannel ? args.initial : null,
        launcher:
            launcher ??
            (uri) => launchUrl(uri, mode: LaunchMode.externalApplication),
      ),
    );
  }
}

class _StatusView extends StatefulWidget {
  const _StatusView({required this.launcher, this.openWith});

  /// Le dossier dont ouvrir le canal dès l'arrivée, s'il y a lieu.
  final AssistedBooking? openWith;
  final ExternalLauncher launcher;

  @override
  State<_StatusView> createState() => _StatusViewState();
}

class _StatusViewState extends State<_StatusView> with WidgetsBindingObserver {
  /// WhatsApp (ou l'appel) n'a pas pu s'ouvrir : on propose les autres
  /// voies.
  var _channelFailed = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    final booking = widget.openWith;
    if (booking != null) {
      WidgetsBinding.instance.addPostFrameCallback(
        (_) => _openChannel(booking),
      );
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Le retour depuis WhatsApp : le dossier a pu bouger.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      context.read<AssistanceStatusCubit>().refresh();
    }
  }

  Future<void> _openChannel(AssistedBooking booking) async {
    final support = booking.support;
    final url = booking.channel == AssistanceChannel.call
        ? support.callUrl
        : (support.whatsappUrl ?? support.callUrl);
    await _open(url);
  }

  Future<void> _open(String? url) async {
    final uri = url == null ? null : Uri.tryParse(url);
    final opened = uri != null && await widget.launcher(uri);
    if (!mounted || opened) return;
    setState(() => _channelFailed = true);
  }

  Future<void> _copy(String text, String confirmation) async {
    await Clipboard.setData(ClipboardData(text: text));
    if (mounted) KoraToaster.show(context, confirmation);
  }

  void _onResumed(AssistanceStatusState state) {
    final hold = state.resumed;
    if (hold == null) return;

    context.read<SeatHoldBloc>().add(
      SeatHoldResumed(
        hold: hold,
        passengers: state.record?.passengerCount ?? 1,
      ),
    );
    context.push(
      AppRouter.paymentMethodPath,
      extra: PaymentArgs(
        total: hold.total,
        phone: context.read<AuthBloc>().state.session?.user.phone,
        seatLabel: hold.seatLabels.join(', '),
      ),
    );
  }

  /// Arrivé ici par l'envoi, la pile ne contient que cet écran : le
  /// retour mène à l'accueil plutôt que de fermer l'app.
  void _back() {
    if (context.canPop()) {
      context.pop();
    } else {
      context.go('/home');
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return PopScope(
      canPop: context.canPop(),
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go('/home');
      },
      child: BlocConsumer<AssistanceStatusCubit, AssistanceStatusState>(
        listenWhen: (previous, current) =>
            previous.resumed != current.resumed ||
            (previous.isResuming && !current.isResuming),
        listener: (context, state) {
          if (state.resumed != null) {
            _onResumed(state);
            return;
          }
          final failure = state.failure;
          if (failure != null) KoraToaster.show(context, failure.message);
        },
        builder: (context, state) {
          final booking = state.booking;

          return Scaffold(
            body: SafeArea(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    ScreenHeader(
                      eyebrow: l10n.assistEyebrow,
                      title: l10n.assistStatusTitle,
                      onBack: _back,
                    ),
                    Expanded(
                      child: booking == null
                          ? _NoBooking(state: state)
                          : RefreshIndicator(
                              onRefresh: () => context
                                  .read<AssistanceStatusCubit>()
                                  .refresh(),
                              child: _Details(
                                state: state,
                                booking: booking,
                                channelFailed: _channelFailed,
                                onOpen: _open,
                                onCopy: _copy,
                              ),
                            ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

/// Le dossier n'a pas encore pu être lu : la demande gardée sur le
/// téléphone, si on l'a, en attendant.
class _NoBooking extends StatelessWidget {
  const _NoBooking({required this.state});

  final AssistanceStatusState state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final record = state.record;

    if (state.isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        if (record != null) ...[
          AssistanceCard(
            reference: record.reference,
            status: null,
            origin: record.origin,
            destination: record.destination,
            departsAt: record.departsAt,
            seats: [for (final seat in record.seats) seat.label],
            agencyName: record.agencyName,
          ),
          const SizedBox(height: 13),
        ],
        StateView(
          icon: Icons.cloud_off_rounded,
          title: l10n.assistStatusTitle,
          message: state.failure?.message ?? l10n.assistRefreshError,
          primaryLabel: l10n.actionRetry,
          onPrimary: () => context.read<AssistanceStatusCubit>().refresh(),
        ),
      ],
    );
  }
}

/// La carte de la demande, puis ce qu'on peut en faire.
class _Details extends StatelessWidget {
  const _Details({
    required this.state,
    required this.booking,
    required this.channelFailed,
    required this.onOpen,
    required this.onCopy,
  });

  final AssistanceStatusState state;
  final AssistedBooking booking;
  final bool channelFailed;
  final Future<void> Function(String? url) onOpen;
  final Future<void> Function(String text, String confirmation) onCopy;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final status = booking.status;
    final leg = booking.legs.isEmpty ? null : booking.legs.first;
    final departsAt = leg?.departsAt ?? state.record?.departsAt;

    return ListView(
      padding: const EdgeInsets.only(bottom: AppSpacing.xl),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        AssistanceCard(
          reference: booking.reference,
          status: status,
          origin: leg?.origin ?? state.record?.origin ?? '',
          destination: leg?.destination ?? state.record?.destination ?? '',
          seats: booking.seatLabels,
          // Tant que le réseau n'a affecté personne, la carte le dit —
          // plutôt que de laisser un blanc là où l'agence se lira.
          agencyName: booking.agency?.name ?? l10n.assistAgencyPending,
          amount: booking.amount,
          // Le départ en pied de carte : c'est la date que le voyageur
          // vérifie, plus que la validité de la demande.
          footnote: departsAt == null
              ? null
              : l10n.assistDeparture(
                  '${KoraDateFormats.dayMonth.format(departsAt)} · '
                  '${KoraDateFormats.time.format(departsAt)}',
                ),
        ),
        const SizedBox(height: 13),
        // Ce que dit le statut, en une phrase : la pastille de la carte le
        // nomme, elle n'explique pas ce qu'il faut faire.
        Text(
          _statusText(l10n, status),
          style: AppTextStyles.body(koraColors.textMuted),
        ),
        if (channelFailed && !status.isClosed) ...[
          const SizedBox(height: AppSpacing.sm),
          Text(
            l10n.assistChannelFailed,
            style: AppTextStyles.caption(AppColors.danger),
          ),
        ],
        const SizedBox(height: 13),
        KoraMenuList(items: _actions(context)),
      ],
    );
  }

  List<KoraMenuItem> _actions(BuildContext context) {
    final l10n = context.l10n;
    final status = booking.status;

    if (status == AssistanceStatus.finalized ||
        status == AssistanceStatus.resumedOnline) {
      return [
        KoraMenuItem(
          icon: Icons.confirmation_number_rounded,
          title: l10n.assistViewTrips,
          subtitle: l10n.assistViewTripsSub,
          onTap: () => context.go('/voyages'),
        ),
      ];
    }

    if (status.isDeadEnd) {
      return [
        KoraMenuItem(
          icon: Icons.search_rounded,
          title: l10n.assistNewBooking,
          subtitle: l10n.assistNewBookingSub,
          onTap: () => context.go('/home'),
        ),
      ];
    }

    final whatsapp = booking.support.whatsappUrl;
    final call = booking.support.callUrl;
    final message = booking.whatsappMessage;

    return [
      if (whatsapp != null)
        KoraMenuItem(
          icon: Icons.chat_rounded,
          glyph: const WhatsAppGlyph(),
          title: l10n.assistOpenWhatsapp,
          subtitle: l10n.assistOpenWhatsappSub,
          onTap: () => onOpen(whatsapp),
        ),
      if (call != null)
        KoraMenuItem(
          icon: Icons.call_rounded,
          title: l10n.assistCall,
          subtitle: booking.support.callPhone ?? l10n.assistCallSub,
          onTap: () => onOpen(call),
        ),
      if (message != null)
        KoraMenuItem(
          icon: Icons.content_copy_rounded,
          title: l10n.assistCopyMessage,
          subtitle: l10n.assistCopyMessageSub,
          onTap: () => onCopy(message, l10n.assistMessageCopied),
        ),
      KoraMenuItem(
        icon: Icons.tag_rounded,
        title: l10n.assistCopyReference,
        subtitle: booking.reference,
        onTap: () => onCopy(booking.reference, l10n.assistReferenceCopied),
      ),
      if (state.canResumeOnline)
        KoraMenuItem(
          icon: Icons.credit_card_rounded,
          title: state.isResuming ? l10n.assistResuming : l10n.assistResume,
          subtitle: l10n.assistResumeSub,
          onTap: state.isResuming
              ? null
              : () => context.read<AssistanceStatusCubit>().resumeOnline(),
        ),
    ];
  }

  String _statusText(AppLocalization l10n, AssistanceStatus status) =>
      switch (status) {
        AssistanceStatus.pending => l10n.assistStatusPendingText,
        AssistanceStatus.inProgress => l10n.assistStatusInProgressText,
        AssistanceStatus.finalized => l10n.assistStatusFinalizedText,
        AssistanceStatus.refused => l10n.assistStatusRefusedText,
        AssistanceStatus.expired => l10n.assistStatusExpiredText,
        AssistanceStatus.unavailable => l10n.assistStatusUnavailableText,
        AssistanceStatus.cancelled => l10n.assistStatusCancelledText,
        AssistanceStatus.resumedOnline => l10n.assistStatusResumedText,
        AssistanceStatus.unknown => l10n.assistStatusUnknownText,
      };
}
