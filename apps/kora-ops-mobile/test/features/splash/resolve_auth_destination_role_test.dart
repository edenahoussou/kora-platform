import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/app/router/app_routes.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/auth_user.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:kora_ops_mobile/features/splash/domain/resolve_auth_destination.dart';
import 'package:kora_ops_mobile/features/terminal/domain/terminal_requirement.dart';
import 'package:kora_ops_mobile/features/terminal/presentation/cubit/terminal_cubit.dart';

void main() {
  const AuthUser driver = AuthUser(
    uuid: 'd-1',
    name: 'Koffi Chauffeur',
    email: 'koffi@kora-express.com',
    roles: <String>['bus_driver'],
    requiresTerminal: false,
    permissions: <String>['departures.view', 'trip-logs.create'],
  );
  const AuthUser conductor = AuthUser(
    uuid: 'c-1',
    name: 'Aïcha Convoyeuse',
    email: 'aicha@kora-express.com',
    roles: <String>['conductor'],
    permissions: <String>['boarding.scan', 'trip-logs.create'],
  );

  group('needsTerminal', () {
    test('le chauffeur est dispensé de terminal', () {
      expect(needsTerminal(driver), isFalse);
    });

    test('le convoyeur doit lier un terminal', () {
      expect(needsTerminal(conductor), isTrue);
    });

    test('un ancien cache sans requires_terminal garde le terminal', () {
      const AuthUser legacy =
          AuthUser(uuid: 'x', name: 'Ancien', email: 'old@kora.com');
      expect(needsTerminal(legacy), isTrue);
    });
  });

  group('resolveAuthDestination', () {
    test('chauffeur sans terminal lié → accueil', () {
      expect(
        resolveAuthDestination(
          const AuthAuthenticated(driver),
          const TerminalUnlinked(),
        ),
        AppRoutes.home,
      );
    });

    test('convoyeur sans terminal lié → liaison du terminal', () {
      expect(
        resolveAuthDestination(
          const AuthAuthenticated(conductor),
          const TerminalUnlinked(),
        ),
        AppRoutes.terminalSetup,
      );
    });
  });

  test('le carnet de bord est ouvert au chauffeur et au convoyeur', () {
    expect(driver.canRecordTripLog, isTrue);
    expect(conductor.canRecordTripLog, isTrue);
    expect(driver.isDriver, isTrue);
    expect(conductor.isConductor, isTrue);
  });
}
