// Un code qui ne part pas doit se voir.
//
// Le serveur répond `503 otp_delivery_unavailable` quand aucun canal ne
// peut porter le code — WhatsApp indisponible, par exemple. Sans message
// à l'écran, le voyageur touche « Recevoir mon code », voit le bouton
// tourner, puis plus rien : il recommence, et recommence encore.

import 'package:dartz/dartz.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_challenge.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_purpose.dart';
import 'package:kora_app_passenger/features/auth/domain/repositories/auth_repository.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_flow_bloc.dart';
import 'package:mocktail/mocktail.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

void main() {
  late MockAuthRepository repository;
  late AuthFlowBloc bloc;

  setUpAll(() => registerFallbackValue(OtpPurpose.registration));

  setUp(() {
    repository = MockAuthRepository();
    bloc = AuthFlowBloc(repository);
  });

  tearDown(() => bloc.close());

  test('un envoi de code impossible laisse un message à afficher', () async {
    when(
      () => repository.requestOtp(
        identifier: any(named: 'identifier'),
        purpose: any(named: 'purpose'),
      ),
    ).thenAnswer(
      (_) async => const Left(
        AuthFailure('Impossible d’envoyer le code. Réessayez plus tard.'),
      ),
    );

    bloc.add(
      const AuthFlowIdentifierSubmitted(
        identifier: '+2290158353479',
        name: 'Félicité AGBO',
      ),
    );
    await bloc.stream.firstWhere((state) => !state.isSubmitting);

    expect(bloc.state.hasError, isTrue);
    expect(bloc.state.errorMessage, contains('Impossible'));
    // Et surtout : on ne passe pas à l'écran du code, puisqu'aucun code
    // n'est parti.
    expect(bloc.state.step, isNot(AuthFlowStep.code));
  });

  test('un envoi réussi mène à la saisie du code', () async {
    when(
      () => repository.requestOtp(
        identifier: any(named: 'identifier'),
        purpose: any(named: 'purpose'),
      ),
    ).thenAnswer(
      (_) async => Right(
        OtpChallenge(
          uuid: 'challenge-1',
          identifier: '+2290158353479',
          purpose: OtpPurpose.registration,
          expiresAt: DateTime(2026, 10, 4, 9),
        ),
      ),
    );

    bloc.add(const AuthFlowIdentifierSubmitted(identifier: '+2290158353479'));
    await bloc.stream.firstWhere((state) => !state.isSubmitting);

    expect(bloc.state.hasError, isFalse);
    expect(bloc.state.step, AuthFlowStep.code);
  });
}
