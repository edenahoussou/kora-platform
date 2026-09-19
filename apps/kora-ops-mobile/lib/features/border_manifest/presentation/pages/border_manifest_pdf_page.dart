import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:printing/printing.dart';

import '../../../../core/widgets/screen_header.dart';
import '../../../../injection/injection.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../terminal/domain/entities/terminal.dart';
import '../../../terminal/domain/entities/terminal_activity_type.dart';
import '../../../terminal/domain/repositories/terminal_repository.dart';
import '../../domain/repositories/border_manifest_pdf_repository.dart';

/// Arguments passés via `GoRouterState.extra` pour ouvrir
/// [BorderManifestPdfPage] — voir `ManifestePage`, seul point d'entrée.
class BorderManifestPdfArgs {
  const BorderManifestPdfArgs({required this.departureUuid, this.label});

  final String departureUuid;

  /// Trajet affiché en surtitre (ex : « Cotonou → Lomé »).
  final String? label;
}

/// Aperçu + partage/impression système du manifeste frontière en PDF A4 —
/// mode téléphone uniquement (MEZ-94), équivalent du PDF de confirmation
/// d'embarquement (`BoardingPdfPreviewPage`) : pas d'imprimante thermique,
/// donc pas de notion de « 3 exemplaires » — un seul PDF partageable ou
/// imprimable via le système. Jamais de montant. Route :
/// `/tournee/manifeste/frontiere/pdf`.
class BorderManifestPdfPage extends StatefulWidget {
  const BorderManifestPdfPage({super.key, required this.args});

  final BorderManifestPdfArgs args;

  @override
  State<BorderManifestPdfPage> createState() => _BorderManifestPdfPageState();
}

class _BorderManifestPdfPageState extends State<BorderManifestPdfPage> {
  late Future<Uint8List> _pdfBytes;

  @override
  void initState() {
    super.initState();
    _pdfBytes = _load();
  }

  void _retry() => setState(() => _pdfBytes = _load());

  Future<Uint8List> _load() async {
    final result = await getIt<BorderManifestPdfRepository>()
        .fetch(departureUuid: widget.args.departureUuid);

    return result.when<Future<Uint8List>>(
      onSuccess: (Uint8List bytes) async {
        await _log(success: true);
        return bytes;
      },
      onFailure: (failure) async {
        await _log(success: false, error: failure.message);
        throw StateError(failure.message);
      },
    );
  }

  /// Journalise la génération (mode téléphone : 1 document, pas
  /// d'exemplaires). Best-effort, jamais de donnée passager.
  Future<void> _log({required bool success, String? error}) async {
    try {
      final TerminalRepository terminals = getIt<TerminalRepository>();
      final Terminal? terminal = await terminals.cachedTerminal();
      final AuthState auth = getIt<AuthCubit>().state;

      await terminals.logActivity(
        TerminalActivityType.print,
        context: <String, dynamic>{
          'document': 'border_manifest',
          'mode': 'phone_pdf',
          'result': success ? 'pdf_generated' : 'pdf_failed',
          'departure_uuid': widget.args.departureUuid,
          'copies_requested': 1,
          'copies_confirmed': success ? 1 : 0,
          if (error != null) 'error': error,
          'user_uuid': auth is AuthAuthenticated ? auth.user.uuid : null,
          'terminal_serial': terminal?.serialNumber,
          'recorded_at': DateTime.now().toIso8601String(),
        },
      );
    } on Object {
      // Journal best-effort.
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: ScreenHeader(
        eyebrow: widget.args.label ?? 'Frontière',
        title: 'Manifeste frontière',
      ),
      body: SafeArea(
        child: FutureBuilder<Uint8List>(
          future: _pdfBytes,
          builder: (BuildContext context, AsyncSnapshot<Uint8List> snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }

            if (snapshot.hasError) {
              final Object? error = snapshot.error;
              final String message =
                  error is StateError ? error.message : '$error';
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      const Text(
                        'Le manifeste frontière n\'a pas pu être généré.',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 12),
                      Text(message, textAlign: TextAlign.center),
                      const SizedBox(height: 16),
                      TextButton.icon(
                        onPressed: _retry,
                        icon: const Icon(Icons.refresh),
                        label: const Text('Réessayer'),
                      ),
                    ],
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
              pdfFileName: 'manifeste-frontiere.pdf',
            );
          },
        ),
      ),
    );
  }
}
