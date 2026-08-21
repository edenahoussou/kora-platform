import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/entities/otp_challenge.dart';
import '../../domain/entities/otp_purpose.dart';
import '../../domain/repositories/auth_repository.dart';

part 'auth_flow_event.dart';
part 'auth_flow_state.dart';

/// Couche PRESENTATION : la chorégraphie e-mail → code → PIN.
///
/// Le même parcours sert quatre motifs (inscription, appareil inconnu, PIN
/// oublié, déverrouillage) : c'est [AuthFlowState.purpose] qui décide de
/// ce qui suit un code vérifié. Les écrans, eux, ne connaissent que
/// l'étape courante.
///
/// Il ne vit que le temps du parcours. La session, une fois ouverte, part
/// vers [AuthBloc] par `AuthSessionOpened` — c'est lui qui la porte
/// ensuite.
class AuthFlowBloc extends Bloc<AuthFlowEvent, AuthFlowState> {
  AuthFlowBloc(this._repository) : super(const AuthFlowState()) {
    on<AuthFlowEmailSubmitted>(_onEmailSubmitted);
    on<AuthFlowLoginSubmitted>(_onLoginSubmitted);
    on<AuthFlowCodeSubmitted>(_onCodeSubmitted);
    on<AuthFlowCodeResent>(_onCodeResent);
    on<AuthFlowPinSubmitted>(_onPinSubmitted);
    on<AuthFlowProviderSubmitted>(_onProviderSubmitted);
    on<AuthFlowReset>((_, emit) => emit(const AuthFlowState()));
  }

  final AuthRepository _repository;

  Future<void> _onEmailSubmitted(
    AuthFlowEmailSubmitted event,
    Emitter<AuthFlowState> emit,
  ) async {
    emit(
      state.copyWith(
        isSubmitting: true,
        errorMessage: '',
        email: event.email,
        purpose: event.purpose,
      ),
    );

    final result = await _repository.requestOtp(
      email: event.email,
      purpose: event.purpose,
    );

    emit(
      result.fold(
        (failure) => _failed(failure),
        (challenge) => state.copyWith(
          isSubmitting: false,
          step: AuthFlowStep.code,
          challenge: challenge,
        ),
      ),
    );
  }

  Future<void> _onLoginSubmitted(
    AuthFlowLoginSubmitted event,
    Emitter<AuthFlowState> emit,
  ) async {
    emit(
      state.copyWith(
        isSubmitting: true,
        errorMessage: '',
        isAccountLocked: false,
        email: event.email,
        pendingPin: event.pin,
      ),
    );

    final result = await _repository.login(
      email: event.email,
      pin: event.pin,
      // Présent seulement au second passage : après avoir relevé le défi
      // d'un appareil inconnu.
      verification: state.verification,
    );

    emit(
      result.fold((failure) => _failed(failure), (outcome) {
        return switch (outcome) {
          LoginSucceeded(:final session) => state.copyWith(
            isSubmitting: false,
            step: AuthFlowStep.done,
            session: session,
          ),
          // Appareil inconnu : le serveur a déjà créé le défi, l'app
          // enchaîne sur la saisie du code sans repasser par l'e-mail.
          LoginNeedsOtp(:final challenge) => state.copyWith(
            isSubmitting: false,
            step: AuthFlowStep.code,
            purpose: OtpPurpose.loginNewDevice,
            challenge: OtpChallenge(
              uuid: challenge.uuid,
              purpose: OtpPurpose.loginNewDevice,
              email: event.email,
              expiresAt: challenge.expiresAt,
            ),
          ),
        };
      }),
    );
  }

  Future<void> _onCodeSubmitted(
    AuthFlowCodeSubmitted event,
    Emitter<AuthFlowState> emit,
  ) async {
    final challenge = state.challenge;
    if (challenge == null) return;

    emit(state.copyWith(isSubmitting: true, errorMessage: ''));

    final result = await _repository.verifyOtp(
      challengeUuid: challenge.uuid,
      purpose: state.purpose,
      code: event.code,
    );

    final verified = result.fold<AuthFlowState?>(
      (failure) {
        emit(_failed(failure));
        return null;
      },
      (verification) {
        final next = state.copyWith(
          isSubmitting: false,
          verification: verification,
        );
        emit(next);
        return next;
      },
    );

    if (verified == null) return;

    // Ce qui suit un code vérifié dépend du motif : créer le compte,
    // rejouer la connexion, choisir un nouveau PIN, ou déverrouiller.
    switch (state.purpose) {
      case OtpPurpose.registration:
      case OtpPurpose.pinReset:
        emit(state.copyWith(step: AuthFlowStep.pin));
      case OtpPurpose.loginNewDevice:
        // On rappelle `login` avec le jeton : c'est ce que demande la
        // documentation, et le PIN a été gardé pour ne pas le retaper.
        add(AuthFlowLoginSubmitted(email: state.email, pin: state.pendingPin));
      case OtpPurpose.accountUnlock:
        await _unlock(emit, verified.verification!);
    }
  }

  Future<void> _onCodeResent(
    AuthFlowCodeResent event,
    Emitter<AuthFlowState> emit,
  ) async {
    if (state.email.isEmpty) return;

    emit(state.copyWith(isSubmitting: true, errorMessage: ''));

    final result = await _repository.requestOtp(
      email: state.email,
      purpose: state.purpose,
    );

    emit(
      result.fold(
        (failure) => _failed(failure),
        (challenge) =>
            state.copyWith(isSubmitting: false, challenge: challenge),
      ),
    );
  }

  Future<void> _onPinSubmitted(
    AuthFlowPinSubmitted event,
    Emitter<AuthFlowState> emit,
  ) async {
    final verification = state.verification;
    if (verification == null) return;

    emit(state.copyWith(isSubmitting: true, errorMessage: ''));

    // Un PIN choisi après un `pin_reset` ne crée pas de compte : il
    // remplace l'ancien, et l'utilisateur se reconnecte ensuite.
    if (state.purpose == OtpPurpose.pinReset) {
      final result = await _repository.resetPin(
        verification: verification,
        pin: event.pin,
      );

      emit(
        result.fold(
          (failure) => _failed(failure),
          (_) => state.copyWith(isSubmitting: false, step: AuthFlowStep.email),
        ),
      );
      return;
    }

    final result = await _repository.completeRegistration(
      verification: verification,
      pin: event.pin,
    );

    emit(
      result.fold(
        (failure) => _failed(failure),
        (session) => state.copyWith(
          isSubmitting: false,
          step: AuthFlowStep.done,
          session: session,
        ),
      ),
    );
  }

  Future<void> _onProviderSubmitted(
    AuthFlowProviderSubmitted event,
    Emitter<AuthFlowState> emit,
  ) async {
    emit(state.copyWith(isSubmitting: true, errorMessage: ''));

    final result = await _repository.loginWithProvider(
      provider: event.provider,
      providerToken: event.providerToken,
    );

    emit(
      result.fold(
        (failure) => _failed(failure),
        (session) => state.copyWith(
          isSubmitting: false,
          step: AuthFlowStep.done,
          session: session,
        ),
      ),
    );
  }

  Future<void> _unlock(
    Emitter<AuthFlowState> emit,
    OtpVerification verification,
  ) async {
    final result = await _repository.unlockAccount(verification);

    emit(
      result.fold(
        (failure) => _failed(failure),
        // Compte rouvert : on repart de la connexion, PIN à ressaisir.
        (_) => const AuthFlowState(),
      ),
    );
  }

  /// Un échec arrête la soumission sans faire reculer l'étape : l'écran
  /// affiche l'erreur là où l'utilisateur se trouve.
  AuthFlowState _failed(Failure failure) {
    return state.copyWith(
      isSubmitting: false,
      errorMessage: failure.message,
      isAccountLocked: failure is AccountLockedFailure,
    );
  }
}
