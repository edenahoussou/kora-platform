import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/entities/otp_challenge.dart';
import '../../domain/entities/otp_purpose.dart';
import '../../domain/repositories/auth_repository.dart';

part 'auth_flow_event.dart';
part 'auth_flow_state.dart';

/// Couche PRESENTATION : la chorégraphie compte → code → PIN.
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
    on<AuthFlowIdentifierSubmitted>(_onIdentifierSubmitted);
    on<AuthFlowLoginSubmitted>(_onLoginSubmitted);
    on<AuthFlowNewDeviceRequested>(_onNewDeviceRequested);
    on<AuthFlowNewDeviceConfirmed>(_onNewDeviceConfirmed);
    on<AuthFlowCodeSubmitted>(_onCodeSubmitted);
    on<AuthFlowCodeResent>(_onCodeResent);
    on<AuthFlowPinSubmitted>(_onPinSubmitted);
    on<AuthFlowReset>((_, emit) => emit(const AuthFlowState()));
  }

  final AuthRepository _repository;

  Future<void> _onIdentifierSubmitted(
    AuthFlowIdentifierSubmitted event,
    Emitter<AuthFlowState> emit,
  ) async {
    emit(
      state.copyWith(
        isSubmitting: true,
        errorMessage: '',
        accountExists: false,
        identifier: event.identifier,
        purpose: event.purpose,
        registrationName: event.name,
      ),
    );

    final result = await _repository.requestOtp(
      identifier: event.identifier,
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
      result.fold((failure) => _failed(failure), (outcome) {
        return switch (outcome) {
          LoginSucceeded(:final session) => state.copyWith(
            isSubmitting: false,
            step: AuthFlowStep.done,
            session: session,
          ),
          // Appareil inconnu : le serveur a déjà créé le défi et envoyé
          // le code. On passe d'abord par l'écran qui explique pourquoi,
          // plutôt que de faire surgir une saisie de code sans raison.
          LoginNeedsOtp(:final challenge) => state.copyWith(
            isSubmitting: false,
            step: AuthFlowStep.newDevice,
            purpose: OtpPurpose.loginNewDevice,
            challenge: OtpChallenge(
              uuid: challenge.uuid,
              purpose: OtpPurpose.loginNewDevice,
              identifier: event.identifier,
              expiresAt: challenge.expiresAt,
            ),
          ),
        };
      }),
    );

    // Le nom de l'appareil se lit après coup : `fold` est synchrone, et
    // l'écran qui l'affiche n'apparaît qu'ici.
    if (state.step == AuthFlowStep.newDevice && state.deviceName.isEmpty) {
      emit(state.copyWith(deviceName: await _deviceName()));
    }
  }

  /// Le lien « Connexion sur un nouvel appareil » de l'écran de
  /// connexion. Rien n'est demandé au serveur ici : l'écran explique, et
  /// c'est son bouton qui déclenche l'envoi du code.
  Future<void> _onNewDeviceRequested(
    AuthFlowNewDeviceRequested event,
    Emitter<AuthFlowState> emit,
  ) async {
    emit(
      state.copyWith(
        step: AuthFlowStep.newDevice,
        purpose: OtpPurpose.loginNewDevice,
        identifier: event.identifier,
        errorMessage: '',
        deviceName: await _deviceName(),
      ),
    );
  }

  Future<void> _onNewDeviceConfirmed(
    AuthFlowNewDeviceConfirmed event,
    Emitter<AuthFlowState> emit,
  ) async {
    // Venu d'un 202, le défi existe déjà et le code est parti : en
    // redemander un invaliderait celui que l'utilisateur vient de
    // recevoir, le serveur n'en gardant qu'un actif à la fois.
    if (state.challenge != null) {
      emit(state.copyWith(step: AuthFlowStep.code, errorMessage: ''));
      return;
    }

    add(
      AuthFlowIdentifierSubmitted(
        identifier: state.identifier,
        purpose: OtpPurpose.loginNewDevice,
      ),
    );
  }

  /// Le nom que l'API a reçu pour cet appareil. Un échec de lecture ne
  /// doit pas retenir le parcours : l'écran s'en passe.
  Future<String> _deviceName() async {
    try {
      final device = await _repository.currentDevice();
      return device.name;
    } on Object {
      return '';
    }
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
        // Sans PIN en mémoire — l'utilisateur est passé par le lien de
        // l'écran de connexion, sans tentative préalable — on le lui
        // demande. Le jeton de vérification reste en main et partira
        // avec cette connexion-là.
        if (state.pendingPin.isEmpty) {
          emit(state.copyWith(step: AuthFlowStep.identifier));
          return;
        }

        // Sinon on rappelle `login` avec le jeton : c'est ce que demande
        // la documentation, et le PIN a été gardé pour ne pas le retaper.
        add(
          AuthFlowLoginSubmitted(
            identifier: state.identifier,
            pin: state.pendingPin,
          ),
        );
      case OtpPurpose.accountUnlock:
        await _unlock(emit, verified.verification!);
    }
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
          (_) => state.copyWith(
            isSubmitting: false,
            step: AuthFlowStep.identifier,
          ),
        ),
      );
      return;
    }

    final result = await _repository.completeRegistration(
      verification: verification,
      pin: event.pin,
      // Renseigné pour une inscription par téléphone, vide sinon : le
      // serveur dérive alors le nom de l'adresse.
      name: state.registrationName,
    );

    emit(
      result.fold(
        (failure) => _failed(failure),
        (session) => state.copyWith(
          isSubmitting: false,
          step: AuthFlowStep.done,
          session: session,
          // Le seul chemin qui crée réellement un compte : c'est lui, et
          // lui seul, qui mène ensuite aux autorisations.
          justRegistered: true,
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
      accountExists: failure is AccountExistsFailure,
    );
  }
}
