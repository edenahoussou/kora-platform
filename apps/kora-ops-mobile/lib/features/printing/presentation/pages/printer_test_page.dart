import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../core/widgets/app_button.dart';
import '../../../../core/widgets/app_card.dart';
import '../cubit/printing_cubit.dart';

/// Écran de test des adaptateurs P501 — permet de vérifier le scan et
/// l'impression **sans terminal physique**, en s'appuyant sur
/// [MockBarcodeScanner]/[MockThermalPrinter] (voir document.md § Adaptateurs
/// P501 pour la procédure complète).
class PrinterTestPage extends StatelessWidget {
  const PrinterTestPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Test scanner & imprimante')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: BlocConsumer<PrintingCubit, PrintingState>(
            listener: (BuildContext context, PrintingState state) {
              if (state case PrintingFailure(:final String message)) {
                ScaffoldMessenger.of(context)
                    .showSnackBar(SnackBar(content: Text(message)));
              }
              if (state is PrintingSuccess) {
                ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Ticket imprimé ✓')));
              }
            },
            builder: (BuildContext context, PrintingState state) {
              final bool isBusy =
                  state is PrintingScanning || state is PrintingInProgress;
              final String? lastScannedCode =
                  state is PrintingIdle ? state.lastScannedCode : null;
              final List<String>? previewLines = switch (state) {
                PrintingIdle(:final List<String>? lastPreviewLines) =>
                  lastPreviewLines,
                PrintingInProgress(:final List<String> previewLines) =>
                  previewLines,
                PrintingSuccess(:final List<String> previewLines) =>
                  previewLines,
                _ => null,
              };

              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text('Scanner',
                            style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          lastScannedCode == null
                              ? 'Aucun scan effectué.'
                              : 'Dernier code : $lastScannedCode',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        const SizedBox(height: AppSpacing.md),
                        AppButton(
                          label: 'Scanner (mock)',
                          isLoading: state is PrintingScanning,
                          onPressed: isBusy
                              ? null
                              : () => context.read<PrintingCubit>().scan(),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text('Imprimante 58mm',
                            style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: AppSpacing.sm),
                        AppButton(
                          label: 'Imprimer un ticket de test',
                          isLoading: state is PrintingInProgress,
                          variant: AppButtonVariant.secondary,
                          onPressed: isBusy
                              ? null
                              : () => context
                                  .read<PrintingCubit>()
                                  .printTestTicket(),
                        ),
                        if (previewLines != null) ...<Widget>[
                          const SizedBox(height: AppSpacing.md),
                          _TicketPreview(lines: previewLines),
                        ],
                      ],
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

/// Rendu monospace du ticket formaté par [TicketFormatter], à largeur fixe
/// 32 caractères — sert de "prévisualisation imprimante" à l'écran.
class _TicketPreview extends StatelessWidget {
  const _TicketPreview({required this.lines});

  final List<String> lines;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.background,
        border: Border.all(color: AppColors.border),
      ),
      child: Text(
        lines.join('\n'),
        style:
            const TextStyle(fontFamily: 'monospace', fontSize: 12, height: 1.4),
      ),
    );
  }
}
