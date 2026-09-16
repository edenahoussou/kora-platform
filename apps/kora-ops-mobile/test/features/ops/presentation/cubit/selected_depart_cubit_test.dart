import 'dart:async';

import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:kora_ops_mobile/features/ops/data/datasources/selected_departure_local_datasource.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/depart.dart';
import 'package:kora_ops_mobile/features/ops/presentation/cubit/selected_depart_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockSelectedDepartureLocalDataSource extends Mock
    implements SelectedDepartureLocalDataSource {}

class MockAuthCubit extends Mock implements AuthCubit {}

void main() {
  setUpAll(() {
    registerFallbackValue(const Depart(
      id: 'fallback',
      route: '—',
      date: '—',
      heure: '—',
      vehiculeImmat: '—',
      totalPlaces: 0,
      placesOccupees: 0,
    ));
  });

  late MockSelectedDepartureLocalDataSource local;
  late MockAuthCubit authCubit;
  late StreamController<AuthState> authController;

  const Depart depart = Depart(
    id: 'dep-uuid-1',
    route: 'Cotonou → Porto-Novo',
    date: '16/08/2026',
    heure: '07:30',
    vehiculeImmat: 'AB-1234-RB',
    totalPlaces: 45,
    placesOccupees: 0,
  );

  setUp(() {
    local = MockSelectedDepartureLocalDataSource();
    authCubit = MockAuthCubit();
    authController = StreamController<AuthState>.broadcast();
    when(() => authCubit.stream).thenAnswer((_) => authController.stream);
    when(() => local.saveSnapshot(any())).thenAnswer((_) async {});
    when(() => local.clear()).thenAnswer((_) async {});
  });

  tearDown(() => authController.close());

  blocTest<SelectedDepartCubit, Depart?>(
    'bootstrap() restaure le départ en cache',
    setUp: () {
      when(() => local.readSnapshot()).thenAnswer((_) async => depart);
    },
    build: () => SelectedDepartCubit(local, authCubit),
    act: (cubit) => cubit.bootstrap(),
    expect: () => <Depart?>[depart],
  );

  blocTest<SelectedDepartCubit, Depart?>(
    'bootstrap() sans départ en cache : aucun état émis',
    setUp: () {
      when(() => local.readSnapshot()).thenAnswer((_) async => null);
    },
    build: () => SelectedDepartCubit(local, authCubit),
    act: (cubit) => cubit.bootstrap(),
    expect: () => <Depart?>[],
  );

  blocTest<SelectedDepartCubit, Depart?>(
    'select() émet le départ et le persiste',
    build: () => SelectedDepartCubit(local, authCubit),
    act: (cubit) => cubit.select(depart),
    expect: () => <Depart?>[depart],
    verify: (_) {
      verify(() => local.saveSnapshot(depart)).called(1);
    },
  );

  blocTest<SelectedDepartCubit, Depart?>(
    'clear() émet null et purge le stockage local',
    seed: () => depart,
    build: () => SelectedDepartCubit(local, authCubit),
    act: (cubit) => cubit.clear(),
    expect: () => <Depart?>[null],
    verify: (_) {
      verify(() => local.clear()).called(1);
    },
  );

  blocTest<SelectedDepartCubit, Depart?>(
    'une déconnexion (AuthUnauthenticated) efface le départ géré',
    seed: () => depart,
    build: () => SelectedDepartCubit(local, authCubit),
    act: (cubit) async {
      authController.add(const AuthUnauthenticated());
      await Future<void>.delayed(const Duration(milliseconds: 10));
    },
    expect: () => <Depart?>[null],
  );
}
