import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:printing/printing.dart';

import '../../../../core/widgets/screen_header.dart';
import '../../../../injection/injection.dart';
import '../../domain/repositories/boarding_pdf_repository.dart';

/// Arguments passés via `GoRouterState.extra` pour ouvrir
/// [BoardingPdfPreviewPage] — voir `DetailPassagerPage`, seul point
/// d'entrée (mode téléphone uniquement, action du speed dial).
class BoardingPdfPreviewArgs {
  const BoardingPdfPreviewArgs({
    required this.departureUuid,
    required this.ticketUuid,
    this.passengerName,
  });

  final String departureUuid;
  final String ticketUuid;
  final String? passengerName;
}

/// Aperçu + partage/impression système du PDF de confirmation
/// d'embarquement — remplace l'impression thermique en mode téléphone
/// (pas de terminal P501). Voir ticket "Supporter le scan par caméra
/// téléphone en alternative au P501" et, côté backend,
/// `OpsBoardingController::pdf` : jamais de montant/prix dans ce document,
/// contrairement au billet passager dont il reprend la mise en page.
class BoardingPdfPreviewPage extends StatefulWidget {
  const BoardingPdfPreviewPage({super.key, required this.args});

  final BoardingPdfPreviewArgs args;

  @override
  State<BoardingPdfPreviewPage> createState() => _BoardingPdfPreviewPageState();
}

class _BoardingPdfPreviewPageState extends State<BoardingPdfPreviewPage> {
  late Future<Uint8List> _pdfBytes;

  @override
  void initState() {
    super.initState();
    _pdfBytes = _load();
  }

  Future<Uint8List> _load() async {
    final result = await getIt<BoardingPdfRepository>().fetch(
      departureUuid: widget.args.departureUuid,
      ticketUuid: widget.args.ticketUuid,
    );

    return result.when(
      onSuccess: (bytes) => bytes,
      onFailure: (failure) => throw StateError(failure.message),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: ScreenHeader(
        eyebrow: 'Embarquement',
        title: widget.args.passengerName ?? 'Confirmation',
      ),
      body: SafeArea(
        child: FutureBuilder<Uint8List>(
          future: _pdfBytes,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }

            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(
                    'Le PDF de confirmation n’a pas pu être chargé.\n'
                    '${snapshot.error}',
                    textAlign: TextAlign.center,
                  ),
                ),
              );
            }

            final Uint8List bytes = snapshot.data!;
            return PdfPreview(
              build: (format) async => bytes,
              canDebug: false,
              canChangeOrientation: false,
              canChangePageFormat: false,
              allowPrinting: true,
              allowSharing: true,
              pdfFileName: 'confirmation-embarquement.pdf',
            );
          },
        ),
      ),
    );
  }
}
