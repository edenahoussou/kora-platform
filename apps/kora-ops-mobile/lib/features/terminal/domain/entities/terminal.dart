import 'package:equatable/equatable.dart';
import 'terminal_status.dart';

/// Le terminal P501 lié à cet appareil (voir `TerminalP501Resource` côté
/// backend). Résultat de `handshake`/`acknowledgeSync`, mis en cache
/// localement pour un affichage immédiat au démarrage — voir
/// `TerminalCubit.bootstrap`.
class Terminal extends Equatable {
  const Terminal({
    required this.uuid,
    required this.serialNumber,
    required this.status,
    this.agencyName,
    this.assignedUserName,
    this.lastHandshakeAt,
    this.lastSyncAt,
  });

  final String uuid;
  final String serialNumber;
  final TerminalStatus status;
  final String? agencyName;
  final String? assignedUserName;
  final DateTime? lastHandshakeAt;
  final DateTime? lastSyncAt;

  bool get isUsable => status == TerminalStatus.active;

  Terminal copyWith({
    TerminalStatus? status,
    DateTime? lastHandshakeAt,
    DateTime? lastSyncAt,
  }) {
    return Terminal(
      uuid: uuid,
      serialNumber: serialNumber,
      status: status ?? this.status,
      agencyName: agencyName,
      assignedUserName: assignedUserName,
      lastHandshakeAt: lastHandshakeAt ?? this.lastHandshakeAt,
      lastSyncAt: lastSyncAt ?? this.lastSyncAt,
    );
  }

  @override
  List<Object?> get props => <Object?>[
        uuid,
        serialNumber,
        status,
        agencyName,
        assignedUserName,
        lastHandshakeAt,
        lastSyncAt,
      ];
}
