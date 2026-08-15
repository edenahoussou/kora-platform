import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../data/formatters/ticket_formatter.dart';
import '../../domain/entities/ticket_payload.dart';
import '../../domain/usecases/print_ticket.dart';
import '../../domain/usecases/scan_barcode.dart';

part 'printing_state.dart';

/// PrintingCubit plutôt qu'un PrintingBloc : chaque action (scanner,
/// imprimer) est déclenchée directement par l'UI, sans combinaison
/// d'événements — voir document.md § Bloc vs Cubit. Si un flux de scan
/// continu (mode "boarding" en rafale) est ajouté plus tard, migrer vers
/// un Bloc dédié à ce moment-là.
class PrintingCubit extends Cubit<PrintingState> {
  PrintingCubit(
      {required ScanBarcode scanBarcode, required PrintTicket printTicket})
      : _scanBarcode = scanBarcode,
        _printTicket = printTicket,
        super(const PrintingIdle());

  final ScanBarcode _scanBarcode;
  final PrintTicket _printTicket;

  Future<void> scan() async {
    emit(const PrintingScanning());
    final result = await _scanBarcode();
    result.when(
      onSuccess: (String code) => emit(PrintingIdle(lastScannedCode: code)),
      onFailure: (failure) => emit(PrintingFailure(failure.message)),
    );
  }

  /// Construit un ticket de test (58mm) à partir du dernier code scanné —
  /// ou d'une référence par défaut si aucun scan n'a encore été effectué —
  /// puis l'envoie à [PrintTicket]. L'aperçu formaté est conservé dans le
  /// state pour être affiché à l'écran (utile sans hardware P501).
  Future<void> printTestTicket() async {
    final String qrData = switch (state) {
      PrintingIdle(:final String? lastScannedCode) =>
        lastScannedCode ?? 'KORA-TICKET-TEST',
      _ => 'KORA-TICKET-TEST',
    };

    final TicketPayload payload = TicketPayload(
      tripRoute: 'Cotonou - Porto-Novo',
      tripDate: '14/08/2026',
      tripTime: '07:30',
      passengerName: 'Agent Test',
      seatNumber: '12A',
      qrData: qrData,
    );

    final List<String> preview = TicketFormatter.format(payload);
    emit(PrintingInProgress(preview));

    final result = await _printTicket(payload);
    result.when(
      onSuccess: (bool success) {
        emit(success
            ? PrintingSuccess(preview)
            : const PrintingFailure('Impression refusée par le matériel.'));
      },
      onFailure: (failure) => emit(PrintingFailure(failure.message)),
    );
  }
}
