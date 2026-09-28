import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/errors/failures.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/entities/otp_challenge.dart';
import '../../domain/entities/otp_purpose.dart';
import '../../domain/repositories/auth_repository.dart';

part 'auth_flow_event.dart';
part 'auth_flow_state.dart';

/// Couche PRESENTATION : la chorégraphie identifiant → PIN → OTP (si
/// appareil inconnu) → session, et la variante PIN oublié → OTP → nouveau
/// PIN. [AuthFlowState.identifier] est un email OU un numéro de téléphone
/// (E.164) — voir migration backend du 25/09/2026 (colonne `identifier`).
///
/// Ni inscription (`register/complete`), ni connexion par fournisseur —
/// des comptes provisionnés en interne n'en ont pas l'usage. C'est
/// [AuthFlowState.purpose] qui décide de ce qui suit un code vérifié.
///
/// Ne vit que le temps du parcours. La session, une fois ouverte, part
/// vers [AuthCubit] via `AuthCubit.authenticate` — c'est lui qui la porte
/// ensuite pour le reste de l'app.
class AuthFlowBloc extends Bloc<AuthFlowEvent, AuthFlowState> {
  AuthFlowBloc(this._repository) : super(const AuthFlowState()) {
    on<AuthFlowIdentifierEntered>(_onIdentifierEntered);
    on<AuthFlowLoginSubmitted>(_onLoginSubmitted);
    on<AuthFlowCodeSubmitted>(_onCodeSubmitted);
    on<AuthFlowCodeResent>(_onCodeResent);
    on<AuthFlowPinResetRequested>(_onPinResetRequested);
    on<AuthFlowNewPinSubmitted>(_onNewPinSubmitted);
    on<AuthFlowReset>((_, emit) => emit(const AuthFlowState()));
  }

  final AuthRepository _repository;

  void _onIdentifierEntered(
    AuthFlowIdentifierEntered event,
    Emitter<AuthFlowState> emit,
  ) {
    emit(
      state.copyWith(
        identifier: event.identifier,
        step: AuthFlowStep.pin,
        errorMessage: '',
        isAccountLocked: false,
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
        identifier: event.identifier,
        pendingPin: event.pin,
      ),
    );

    final result = await _repository.login(
      identifier: event.identifier,
      pin: event.pin,
      // Présent seulement au second passage : après avoir relevé le défi
      // d'un appareil inconnu.
      verification: state.verification,
    );

    emit(
      result.when(
        onSuccess: (outcome) => switch (outcome) {
          LoginSucceeded(:final session) => state.copyWith(
              isSubmitting: false,
              step: AuthFlowStep.done,
              session: session,
            ),
          // Appareil inconnu : le serveur a déjà créé le défi et envoyé
          // le code — direct sur l'écran de saisie, le PIN reste en
          // mémoire ([pendingPin]) pour ne pas le refaire taper.
          LoginNeedsOtp(:final challenge) => state.copyWith(
              isSubmitting: false,
              step: AuthFlowStep.code,
              purpose: OtpPurpose.loginNewDevice,
              challenge: OtpChallenge(
                uuid: challenge.uuid,
                purpose: OtpPurpose.loginNewDevice,
                identifier: event.identifier,
                expiresAt: challenge.expiresAt,
              ),
            ),
        },
        onFailure: _failed,
      ),
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

    // On stocke le jeton de vérification sans repasser `isSubmitting` à
    // `false` ici : le faire créerait une émission intermédiaire
    // (`isSubmitting: false`, mais `step` encore égal à `code`) que le
    // `firstWhere(!isSubmitting)` de la page capterait *avant* la fin
    // réelle du parcours (rejeu de connexion ou déverrouillage).
    final OtpVerification? verification = result.when(
      onSuccess: (verification) => verification,
      onFailure: (failure) {
        emit(_failed(failure));
        return null;
      },
    );

    if (verification == null) return;

    final AuthFlowState verifiedState = state.copyWith(
      verification: verification,
    );

    switch (state.purpose) {
      case OtpPurpose.loginNewDevice:
        emit(verifiedState);
        await _retryLogin(emit);
      case OtpPurpose.pinReset:
        emit(verifiedState.copyWith(
          isSubmitting: false,
          step: AuthFlowStep.newPin,
        ));
      case OtpPurpose.accountUnlock:
        emit(verifiedState);
        await _unlock(emit, verification);
      case OtpPurpose.registration:
        // Hors périmètre de ce ticket — aucun écran n'y mène, filet de
        // sécurité seulement.
        emit(const AuthFlowState());
    }
  }

  /// Rejoue `login` avec le jeton de vérification tout juste obtenu — le
  /// PIN saisi avant l'OTP ([AuthFlowState.pendingPin]) n'est pas
  /// redemandé.
  Future<void> _retryLogin(Emitter<AuthFlowState> emit) async {
    emit(state.copyWith(isSubmitting: true, errorMessage: ''));

    final result = await _repository.login(
      identifier: state.identifier,
      pin: state.pendingPin,
      verification: state.verification,
    );

    emit(
      result.when(
        onSuccess: (outcome) => switch (outcome) {
          LoginSucceeded(:final session) => state.copyWith(
              isSubmitting: false,
              step: AuthFlowStep.done,
              session: session,
            ),
          LoginNeedsOtp(:final challenge) => state.copyWith(
              isSubmitting: false,
              step: AuthFlowStep.code,
              challenge: OtpChallenge(
                uuid: challenge.uuid,
                purpose: OtpPurpose.loginNewDevice,
                identifier: state.identifier,
                expiresAt: challenge.expiresAt,
              ),
            ),
        },
        onFailure: _failed,
      ),
    );
  }

  Future<void> _onCodeResent(
    AuthFlowCodeResent event,
    Emitter<AuthFlowState> emit,
  ) async {
    if (state.identifier.isEmpty) return;

    emit(state.copyWith(isSubmitting: true, errorMessage: ''));

    final result = await _repository.requestOtp(
      identifier: state.identifier,
      purpose: state.purpose,
    );

    emit(
      result.when(
        onSuccess: (challenge) =>
            state.copyWith(isSubmitting: false, challenge: challenge),
        onFailure: _failed,
      ),
    );
  }

  Future<void> _onPinResetRequested(
    AuthFlowPinResetRequested event,
    Emitter<AuthFlowState> emit,
  ) async {
    emit(
      state.copyWith(
        isSubmitting: true,
        errorMessage: '',
        identifier: event.identifier,
        purpose: OtpPurpose.pinReset,
      ),
    );

    final result = await _repository.requestOtp(
      identifier: event.identifier,
      purpose: OtpPurpose.pinReset,
    );

    emit(
      result.when(
        onSuccess: (challenge) => state.copyWith(
          isSubmitting: false,
          step: AuthFlowStep.code,
          challenge: challenge,
        ),
        onFailure: _failed,
      ),
    );
  }

  Future<void> _onNewPinSubmitted(
    AuthFlowNewPinSubmitted event,
    Emitter<AuthFlowState> emit,
  ) async {
    final verification = state.verification;
    if (verification == null) return;

    emit(state.copyWith(isSubmitting: true, errorMessage: ''));

    // Un PIN choisi après un `pin_reset` ne crée pas de session : il
    // remplace l'ancien, et l'utilisateur se reconnecte ensuite avec.
    final result = await _repository.resetPin(
      verification: verification,
      pin: event.pin,
    );

    emit(
      result.when(
        onSuccess: (_) => const AuthFlowState(),
        onFailure: _failed,
      ),
    );
  }

  Future<void> _unlock(
    Emitter<AuthFlowState> emit,
    OtpVerification verification,
  ) async {
    final result = await _repository.unlockAccount(verification);

    emit(
      result.when(
        onSuccess: (_) => const AuthFlowState(),
        onFailure: _failed,
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
