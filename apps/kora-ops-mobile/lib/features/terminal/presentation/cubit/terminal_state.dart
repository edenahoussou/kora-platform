part of 'terminal_cubit.dart';

sealed class TerminalState extends Equatable {
  const TerminalState();

  @override
  List<Object?> get props => <Object?>[];
}

/// Avant tout `bootstrap()` — l'app ne sait pas encore si un terminal est
/// lié sur cet appareil.
class TerminalInitial extends TerminalState {
  const TerminalInitial();
}

/// Aucun terminal lié sur cet appareil : `AuthGuard` doit rediriger vers
/// l'écran de liaison.
class TerminalUnlinked extends TerminalState {
  const TerminalUnlinked();
}

/// Un handshake est en cours (liaison initiale ou rafraîchissement en
/// tâche de fond au démarrage — voir `TerminalCubit.bootstrap`).
class TerminalLinking extends TerminalState {
  const TerminalLinking();
}

/// Terminal lié et utilisable. [fromCache] distingue un instantané relu
/// localement (affiché immédiatement, avant confirmation serveur — voir
/// docstring `bootstrap`) d'une confirmation serveur fraîche.
class TerminalLinked extends TerminalState {
  const TerminalLinked(this.terminal, {this.fromCache = false});

  final Terminal terminal;
  final bool fromCache;

  @override
  List<Object?> get props => <Object?>[terminal, fromCache];
}

/// Le numéro de série saisi n'est pas reconnu (`terminal_unknown`).
class TerminalUnknown extends TerminalState {
  const TerminalUnknown(this.message);

  final String message;

  @override
  List<Object?> get props => <Object?>[message];
}

/// Terminal reconnu mais inutilisable (`terminal_lost`,
/// `terminal_maintenance` ou `terminal_inactive`).
class TerminalNotUsable extends TerminalState {
  const TerminalNotUsable(this.code, this.message);

  final String code;
  final String message;

  @override
  List<Object?> get props => <Object?>[code, message];
}

/// Échec réseau/serveur pendant le handshake — distinct de `TerminalUnknown`
/// / `TerminalNotUsable` : ici, retenter a du sens.
class TerminalError extends TerminalState {
  const TerminalError(this.message);

  final String message;

  @override
  List<Object?> get props => <Object?>[message];
}
