import 'dart:async';

import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/auth_user.dart';
import 'package:kora_ops_mobile/features/auth/domain/repositories/auth_repository.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:kora_ops_mobile/features/terminal/domain/entities/terminal.dart';
import 'package:kora_ops_mobile/features/terminal/domain/entities/terminal_status.dart';
import 'package:kora_ops_mobile/features/terminal/domain/repositories/terminal_repository.dart';
import 'package:kora_ops_mobile/features/terminal/presentation/cubit/terminal_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockTerminalRepository extends Mock implements TerminalRepository {}

// AuthCubit n'a pas d'effet de bord à la construction (voir sa
// docstring) : un AuthRepository jamais sollicité ici suffit à fournir un
// `.stream` réel à TerminalCubit, sans avoir à stubber quoi que ce soit.
class _UnusedAuthRepository extends Mock implements AuthRepository {}

void main() {
  late MockTerminalRepository repository;
  late AuthCubit authCubit;

  const Terminal terminal = Terminal(
    uuid: 't-1',
    serialNumber: 'P501-000123',
    status: TerminalStatus.active,
    agencyName: 'Agence Cotonou',
  );

  setUp(() {
    repository = MockTerminalRepository();
    authCubit = AuthCubit(_UnusedAuthRepository());
  });

  tearDown(() => authCubit.close());

  group('TerminalCubit.bootstrap', () {
    blocTest<TerminalCubit, TerminalState>(
      'émet TerminalUnlinked si aucun terminal en cache',
      setUp: () {
        when(() => repository.cachedTerminal()).thenAnswer((_) async => null);
      },
      build: () => TerminalCubit(repository, authCubit),
      act: (cubit) => cubit.bootstrap(),
      expect: () => <TerminalState>[const TerminalUnlinked()],
    );

    blocTest<TerminalCubit, TerminalState>(
      'émet TerminalLinked(fromCache: true) immédiatement si un terminal '
      'est en cache, sans attendre le handshake de rafraîchissement',
      setUp: () {
        when(() => repository.cachedTerminal())
            .thenAnswer((_) async => terminal);
        // Handshake de rafraîchissement jamais résolu dans ce test (via un
        // Completer non complété, pour ne pas laisser de Timer réel
        // pendant après la fin du test) : vérifie que bootstrap() ne
        // l'attend pas (voir docstring — non bloquant).
        when(() => repository.handshake(any()))
            .thenAnswer((_) => Completer<Result<Terminal>>().future);
      },
      build: () => TerminalCubit(repository, authCubit),
      act: (cubit) => cubit.bootstrap(),
      expect: () => <TerminalState>[
        const TerminalLinked(terminal, fromCache: true),
      ],
    );
  });

  group('TerminalCubit.link', () {
    blocTest<TerminalCubit, TerminalState>(
      'succès → TerminalLinking puis TerminalLinked',
      setUp: () {
        when(() => repository.handshake('P501-000123'))
            .thenAnswer((_) async => const Result<Terminal>.success(terminal));
      },
      build: () => TerminalCubit(repository, authCubit),
      act: (cubit) => cubit.link('P501-000123'),
      expect: () => <TerminalState>[
        const TerminalLinking(),
        const TerminalLinked(terminal),
      ],
    );

    blocTest<TerminalCubit, TerminalState>(
      'terminal_unknown → TerminalUnknown',
      setUp: () {
        when(() => repository.handshake('BAD')).thenAnswer(
          (_) async => const Result<Terminal>.failure(
            TerminalUnknownFailure('Ce terminal n’est pas enregistré.'),
          ),
        );
      },
      build: () => TerminalCubit(repository, authCubit),
      act: (cubit) => cubit.link('BAD'),
      expect: () => <TerminalState>[
        const TerminalLinking(),
        const TerminalUnknown('Ce terminal n’est pas enregistré.'),
      ],
    );

    blocTest<TerminalCubit, TerminalState>(
      'terminal_lost → TerminalNotUsable',
      setUp: () {
        when(() => repository.handshake('P501-LOST')).thenAnswer(
          (_) async => const Result<Terminal>.failure(
            TerminalNotUsableFailure('terminal_lost', 'Terminal perdu.'),
          ),
        );
      },
      build: () => TerminalCubit(repository, authCubit),
      act: (cubit) => cubit.link('P501-LOST'),
      expect: () => <TerminalState>[
        const TerminalLinking(),
        const TerminalNotUsable('terminal_lost', 'Terminal perdu.'),
      ],
    );

    blocTest<TerminalCubit, TerminalState>(
      'échec réseau → TerminalError',
      setUp: () {
        when(() => repository.handshake('P501-000123')).thenAnswer(
          (_) async => const Result<Terminal>.failure(NetworkFailure()),
        );
      },
      build: () => TerminalCubit(repository, authCubit),
      act: (cubit) => cubit.link('P501-000123'),
      expect: () => <dynamic>[
        const TerminalLinking(),
        isA<TerminalError>(),
      ],
    );
  });

  blocTest<TerminalCubit, TerminalState>(
    'forget() oublie le terminal et repasse à TerminalUnlinked',
    setUp: () {
      when(() => repository.forget()).thenAnswer((_) async {});
    },
    build: () => TerminalCubit(repository, authCubit),
    act: (cubit) => cubit.forget(),
    expect: () => <TerminalState>[const TerminalUnlinked()],
    verify: (_) {
      verify(() => repository.forget()).called(1);
    },
  );

  group('AuthAuthenticated → rejoue le handshake du terminal en cache', () {
    const AuthUser user = AuthUser(
      uuid: 'u-1',
      name: 'Aïcha Hounkpé',
      email: 'aicha.hounkpe@kora-express.com',
    );

    blocTest<TerminalCubit, TerminalState>(
      'terminal en cache → handshake rejoué avec le nouveau jeton, TerminalLinked à jour',
      setUp: () {
        when(() => repository.cachedTerminal())
            .thenAnswer((_) async => terminal);
        when(() => repository.handshake('P501-000123')).thenAnswer(
          (_) async => const Result<Terminal>.success(terminal),
        );
      },
      build: () => TerminalCubit(repository, authCubit),
      act: (_) => authCubit.authenticate(user),
      wait: const Duration(milliseconds: 1),
      expect: () => <TerminalState>[const TerminalLinked(terminal)],
      verify: (_) {
        verify(() => repository.handshake('P501-000123')).called(1);
      },
    );

    blocTest<TerminalCubit, TerminalState>(
      'aucun terminal en cache → rien ne se passe (AuthGuard gère déjà /terminal/setup)',
      setUp: () {
        when(() => repository.cachedTerminal()).thenAnswer((_) async => null);
      },
      build: () => TerminalCubit(repository, authCubit),
      act: (_) => authCubit.authenticate(user),
      wait: const Duration(milliseconds: 1),
      expect: () => <TerminalState>[],
      verify: (_) {
        verifyNever(() => repository.handshake(any()));
      },
    );
  });
}
