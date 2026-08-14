part of 'printing_cubit.dart';

sealed class PrintingState extends Equatable {
  const PrintingState();

  @override
  List<Object?> get props => <Object?>[];
}

class PrintingIdle extends PrintingState {
  const PrintingIdle({this.lastScannedCode, this.lastPreviewLines});

  final String? lastScannedCode;
  final List<String>? lastPreviewLines;

  @override
  List<Object?> get props => <Object?>[lastScannedCode, lastPreviewLines];
}

class PrintingScanning extends PrintingState {
  const PrintingScanning();
}

class PrintingInProgress extends PrintingState {
  const PrintingInProgress(this.previewLines);

  final List<String> previewLines;

  @override
  List<Object?> get props => <Object?>[previewLines];
}

class PrintingSuccess extends PrintingState {
  const PrintingSuccess(this.previewLines);

  final List<String> previewLines;

  @override
  List<Object?> get props => <Object?>[previewLines];
}

class PrintingFailure extends PrintingState {
  const PrintingFailure(this.message);

  final String message;

  @override
  List<Object?> get props => <Object?>[message];
}
