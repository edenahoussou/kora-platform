import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../domain/entities/ticket_document.dart';
import '../bloc/ticket_document_cubit.dart';
import '../pdf/ticket_document_actions.dart';

/// Le message d'une erreur de téléchargement, dit au voyageur.
String ticketDocumentErrorText(AppLocalization l10n, TicketDocumentError e) =>
    switch (e) {
      TicketDocumentError.network => l10n.ticketsPdfErrorNetwork,
      TicketDocumentError.sessionExpired => l10n.ticketsPdfErrorSession,
      TicketDocumentError.inaccessible => l10n.ticketsPdfErrorInaccessible,
      TicketDocumentError.unavailable => l10n.ticketsPdfErrorUnavailable,
      TicketDocumentError.notDownloadable =>
        l10n.ticketsPdfErrorNotDownloadable,
      TicketDocumentError.unexpected => l10n.ticketsPdfErrorUnexpected,
    };

/// Les lignes d'action du PDF officiel, à poser dans une liste (`KoraMenuList`).
///
/// Partagées par le détail d'un billet et la fenêtre du QR code : le même
/// document, les mêmes gestes, la même façon de dire où il en est.
List<KoraMenuItem> officialTicketMenuItems(
  BuildContext context, {
  required String ticketId,
  required TicketDocumentEntry entry,
  required TicketDocumentCubit cubit,
}) {
  final l10n = context.l10n;
  final document = entry.document;
  final error = entry.error;

  // Un billet inaccessible, ou annulé, ne le deviendra pas en réessayant :
  // la ligne s'efface plutôt que de promettre.
  final canDownload =
      error != TicketDocumentError.inaccessible &&
      error != TicketDocumentError.notDownloadable;

  return [
    if (document != null)
      KoraMenuItem(
        icon: Icons.picture_as_pdf_rounded,
        title: l10n.ticketsPdfOpen,
        subtitle: document.isStale
            ? l10n.ticketDetailPdfOfflineSubtitle
            : l10n.ticketDetailPdfSavedSubtitle,
        onTap: () => openTicketDocument(context, document),
      ),
    if (canDownload)
      KoraMenuItem(
        icon: Icons.file_download_outlined,
        title: switch (entry.phase) {
          TicketDocumentPhase.downloading => l10n.ticketsPdfDownloading,
          TicketDocumentPhase.failed => l10n.actionRetry,
          _ when document != null => l10n.ticketsPdfRedownload,
          _ => l10n.ticketsPdfDownload,
        },
        // L'erreur du serveur se lit ici, à la place du sous-titre : c'est
        // la ligne qui la corrigera.
        subtitle: error != null
            ? ticketDocumentErrorText(l10n, error)
            : l10n.ticketDetailPdfSubtitle,
        trailing: entry.isDownloading
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: AppColors.gold,
                ),
              )
            : null,
        onTap: entry.isDownloading
            ? null
            : () => downloadTicketDocument(context, cubit, ticketId),
      ),
    if (document != null)
      KoraMenuItem(
        icon: Icons.save_alt_rounded,
        title: l10n.ticketsPdfSaveCopy,
        subtitle: l10n.ticketDetailPdfCopySubtitle,
        onTap: () => saveTicketDocumentCopy(context, document),
      ),
  ];
}

/// Télécharge le PDF, puis dit ce qui s'est passé.
Future<void> downloadTicketDocument(
  BuildContext context,
  TicketDocumentCubit cubit,
  String ticketId,
) async {
  final l10n = context.l10n;
  final document = await cubit.download(ticketId);
  if (!context.mounted) return;

  final entry = cubit.state.of(ticketId);
  final error = entry.error;
  if (document == null && error != null) {
    final detail = entry.detail;
    final text = ticketDocumentErrorText(l10n, error);
    KoraToaster.show(context, detail == null ? text : '$text\n$detail');
  } else if (document != null) {
    KoraToaster.show(
      context,
      document.isStale ? l10n.ticketsPdfOffline : l10n.ticketsPdfReady,
    );
  }
}

/// Ouvre le PDF dans l'application du téléphone.
Future<void> openTicketDocument(
  BuildContext context,
  TicketDocument document,
) async {
  final l10n = context.l10n;
  try {
    final opened = await TicketDocumentActions.open(document);
    if (!opened && context.mounted) {
      KoraToaster.show(context, l10n.ticketsPdfOpenFailed);
    }
  } on Object {
    if (context.mounted) KoraToaster.show(context, l10n.ticketsPdfOpenFailed);
  }
}

Future<void> shareTicketDocument(
  BuildContext context,
  TicketDocument document,
) async {
  final l10n = context.l10n;
  try {
    await TicketDocumentActions.share(document);
  } on Object {
    if (context.mounted) {
      KoraToaster.show(context, l10n.ticketsPdfErrorUnexpected);
    }
  }
}

Future<void> saveTicketDocumentCopy(
  BuildContext context,
  TicketDocument document,
) async {
  final l10n = context.l10n;
  try {
    final saved = await TicketDocumentActions.saveCopy(document);
    // Le sélecteur refermé sans choix : rien n'a été enregistré.
    if (saved && context.mounted) {
      KoraToaster.show(context, l10n.ticketsDownloadDone);
    }
  } on Object {
    if (context.mounted) {
      KoraToaster.show(context, l10n.ticketsPdfErrorUnexpected);
    }
  }
}

/// Le PDF officiel en liste d'actions, pour la fenêtre du QR code.
class OfficialTicketMenu extends StatefulWidget {
  const OfficialTicketMenu({super.key, required this.ticketId, this.cubit});

  final String ticketId;
  final TicketDocumentCubit? cubit;

  @override
  State<OfficialTicketMenu> createState() => _OfficialTicketMenuState();
}

class _OfficialTicketMenuState extends State<OfficialTicketMenu> {
  late final TicketDocumentCubit _cubit =
      widget.cubit ?? getIt<TicketDocumentCubit>();

  @override
  void initState() {
    super.initState();
    _cubit.restore(widget.ticketId);
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<TicketDocumentCubit, TicketDocumentState>(
      bloc: _cubit,
      buildWhen: (previous, current) =>
          previous.of(widget.ticketId) != current.of(widget.ticketId),
      builder: (context, state) => KoraMenuList(
        items: officialTicketMenuItems(
          context,
          ticketId: widget.ticketId,
          entry: state.of(widget.ticketId),
          cubit: _cubit,
        ),
      ),
    );
  }
}

/// Le PDF officiel d'un billet : le télécharger, puis l'ouvrir, le partager
/// ou en garder une copie.
///
/// Chaque état se lit à l'écran : téléchargement en cours, billet
/// enregistré, copie hors ligne, ou l'erreur et ce qu'on peut y faire.
class OfficialTicketDownload extends StatefulWidget {
  const OfficialTicketDownload({super.key, required this.ticketId, this.cubit});

  /// L'UUID du billet.
  final String ticketId;

  /// Par défaut, le cubit partagé par toute l'app.
  final TicketDocumentCubit? cubit;

  @override
  State<OfficialTicketDownload> createState() => _OfficialTicketDownloadState();
}

class _OfficialTicketDownloadState extends State<OfficialTicketDownload> {
  late final TicketDocumentCubit _cubit =
      widget.cubit ?? getIt<TicketDocumentCubit>();

  @override
  void initState() {
    super.initState();
    _cubit.restore(widget.ticketId);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return BlocBuilder<TicketDocumentCubit, TicketDocumentState>(
      bloc: _cubit,
      buildWhen: (previous, current) =>
          previous.of(widget.ticketId) != current.of(widget.ticketId),
      builder: (context, state) {
        final entry = state.of(widget.ticketId);
        final document = entry.document;
        final error = entry.error;

        // Un billet inaccessible, ou annulé, ne le deviendra pas en
        // réessayant : le bouton s'efface plutôt que de promettre.
        final canDownload =
            error != TicketDocumentError.inaccessible &&
            error != TicketDocumentError.notDownloadable;

        final status = switch (entry.phase) {
          TicketDocumentPhase.failed when error != null =>
            ticketDocumentErrorText(l10n, error),
          TicketDocumentPhase.ready when document?.isStale ?? false =>
            l10n.ticketsPdfOffline,
          TicketDocumentPhase.ready => l10n.ticketsPdfReady,
          _ => null,
        };

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            if (status != null) ...[
              Text(
                status,
                textAlign: TextAlign.center,
                style: AppTextStyles.caption(
                  entry.phase == TicketDocumentPhase.failed
                      ? AppColors.danger
                      : koraColors.textMuted,
                ),
              ),
              // La réponse du serveur, telle quelle : code HTTP, code
              // d'erreur et message — de quoi savoir ce qui bloque.
              if (entry.phase == TicketDocumentPhase.failed &&
                  entry.detail != null) ...[
                const SizedBox(height: 4),
                Text(
                  entry.detail!,
                  textAlign: TextAlign.center,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
              const SizedBox(height: AppSpacing.sm),
            ],
            if (document != null) ...[
              KoraButton(
                label: l10n.ticketsPdfOpen,
                icon: Icons.picture_as_pdf_outlined,
                onPressed: entry.isDownloading
                    ? null
                    : () => _open(context, document),
              ),
              const SizedBox(height: 8),
              KoraButton(
                label: l10n.ticketsShare,
                variant: KoraButtonVariant.ghost,
                icon: Icons.ios_share_rounded,
                onPressed: entry.isDownloading
                    ? null
                    : () => _share(context, document),
              ),
              const SizedBox(height: 8),
              KoraButton(
                label: l10n.ticketsPdfSaveCopy,
                variant: KoraButtonVariant.ghost,
                icon: Icons.save_alt_rounded,
                onPressed: entry.isDownloading
                    ? null
                    : () => _saveCopy(context, document),
              ),
              const SizedBox(height: 8),
            ],
            if (canDownload)
              KoraButton(
                label: switch (entry.phase) {
                  TicketDocumentPhase.downloading => l10n.ticketsPdfDownloading,
                  TicketDocumentPhase.failed => l10n.actionRetry,
                  _ when document != null => l10n.ticketsPdfRedownload,
                  _ => l10n.ticketsPdfDownload,
                },
                variant: document == null
                    ? KoraButtonVariant.primary
                    : KoraButtonVariant.ghost,
                icon: Icons.file_download_outlined,
                isLoading: entry.isDownloading,
                onPressed: entry.isDownloading
                    ? null
                    : () => _cubit.download(widget.ticketId),
              ),
          ],
        );
      },
    );
  }

  Future<void> _open(BuildContext context, TicketDocument document) async {
    final l10n = context.l10n;
    try {
      final opened = await TicketDocumentActions.open(document);
      if (!opened && context.mounted) {
        KoraToaster.show(context, l10n.ticketsPdfOpenFailed);
      }
    } on Object {
      if (context.mounted) KoraToaster.show(context, l10n.ticketsPdfOpenFailed);
    }
  }

  Future<void> _share(BuildContext context, TicketDocument document) async {
    final l10n = context.l10n;
    try {
      await TicketDocumentActions.share(document);
    } on Object {
      if (context.mounted) {
        KoraToaster.show(context, l10n.ticketsPdfErrorUnexpected);
      }
    }
  }

  Future<void> _saveCopy(BuildContext context, TicketDocument document) async {
    final l10n = context.l10n;
    try {
      final saved = await TicketDocumentActions.saveCopy(document);
      // Le sélecteur refermé sans choix : rien n'a été enregistré.
      if (saved && context.mounted) {
        KoraToaster.show(context, l10n.ticketsDownloadDone);
      }
    } on Object {
      if (context.mounted) {
        KoraToaster.show(context, l10n.ticketsPdfErrorUnexpected);
      }
    }
  }
}

/// La version compacte, pour une carte de la liste : une icône qui dit
/// l'état et fait la seule chose utile à cet instant — télécharger, ouvrir,
/// ou réessayer.
class OfficialTicketDownloadIcon extends StatefulWidget {
  const OfficialTicketDownloadIcon({
    super.key,
    required this.ticketId,
    this.cubit,
  });

  final String ticketId;
  final TicketDocumentCubit? cubit;

  @override
  State<OfficialTicketDownloadIcon> createState() =>
      _OfficialTicketDownloadIconState();
}

class _OfficialTicketDownloadIconState
    extends State<OfficialTicketDownloadIcon> {
  late final TicketDocumentCubit _cubit =
      widget.cubit ?? getIt<TicketDocumentCubit>();

  @override
  void initState() {
    super.initState();
    _cubit.restore(widget.ticketId);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return BlocBuilder<TicketDocumentCubit, TicketDocumentState>(
      bloc: _cubit,
      buildWhen: (previous, current) =>
          previous.of(widget.ticketId) != current.of(widget.ticketId),
      builder: (context, state) {
        final entry = state.of(widget.ticketId);
        final document = entry.document;

        if (entry.isDownloading) {
          return Semantics(
            label: l10n.ticketsPdfDownloading,
            child: const SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: AppColors.gold,
              ),
            ),
          );
        }

        final (icon, tooltip) = switch (entry.phase) {
          _ when document != null => (
            Icons.picture_as_pdf_rounded,
            l10n.ticketsPdfOpen,
          ),
          TicketDocumentPhase.failed => (
            Icons.refresh_rounded,
            l10n.actionRetry,
          ),
          _ => (Icons.file_download_outlined, l10n.ticketsPdfDownload),
        };

        return IconButton(
          tooltip: tooltip,
          visualDensity: VisualDensity.compact,
          constraints: const BoxConstraints(),
          padding: EdgeInsets.zero,
          icon: Icon(icon, size: 20, color: AppColors.gold),
          onPressed: () =>
              document != null ? _open(context, document) : _download(context),
        );
      },
    );
  }

  Future<void> _download(BuildContext context) async {
    final l10n = context.l10n;
    final document = await _cubit.download(widget.ticketId);
    if (!context.mounted) return;

    final entry = _cubit.state.of(widget.ticketId);
    final error = entry.error;
    if (document == null && error != null) {
      final text = ticketDocumentErrorText(l10n, error);
      final detail = entry.detail;
      KoraToaster.show(context, detail == null ? text : '$text\n$detail');
    } else if (document != null) {
      KoraToaster.show(
        context,
        document.isStale ? l10n.ticketsPdfOffline : l10n.ticketsPdfReady,
      );
    }
  }

  Future<void> _open(BuildContext context, TicketDocument document) async {
    final l10n = context.l10n;
    try {
      final opened = await TicketDocumentActions.open(document);
      if (!opened && context.mounted) {
        KoraToaster.show(context, l10n.ticketsPdfOpenFailed);
      }
    } on Object {
      if (context.mounted) KoraToaster.show(context, l10n.ticketsPdfOpenFailed);
    }
  }
}
