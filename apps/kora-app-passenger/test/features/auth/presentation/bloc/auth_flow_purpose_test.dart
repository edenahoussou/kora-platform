// Après une connexion, l'écran des autorisations ne doit plus reparaître :
// le profil est déjà rempli, et le redemander à chaque fois donne
// l'impression d'une inscription qui recommence.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_purpose.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_flow_bloc.dart';

void main() {
  test('à l’ouverture, rien n’a été inscrit', () {
    expect(const AuthFlowState().justRegistered, isFalse);
  });

  test('le motif par défaut ne vaut pas inscription accomplie', () {
    // `purpose` vaut `registration` par défaut — c'est le motif d'OTP le
    // plus courant — et une connexion ordinaire ne le change jamais,
    // faute d'OTP. C'est exactement ce qui renvoyait tout le monde aux
    // autorisations après chaque connexion.
    const state = AuthFlowState(step: AuthFlowStep.done);

    expect(state.purpose, OtpPurpose.registration);
    expect(state.justRegistered, isFalse);
  });

  test('l’indicateur survit aux copies', () {
    const registered = AuthFlowState(justRegistered: true);

    expect(registered.copyWith(step: AuthFlowStep.done).justRegistered, isTrue);
  });
}
