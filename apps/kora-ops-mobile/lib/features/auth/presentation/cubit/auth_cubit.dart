import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../domain/entities/user_entity.dart';
import '../../domain/usecases/get_current_user.dart';
import '../../domain/usecases/login_user.dart';
import '../../domain/usecases/logout_user.dart';

part 'auth_state.dart';

/// AuthCubit plutôt qu'un AuthBloc : le flux d'authentification de ce
/// scaffold n'a pas besoin d'événements distincts (pas de debounce, pas de
/// combinaison de flux) — voir document.md § Bloc vs Cubit.
class AuthCubit extends Cubit<AuthState> {
  AuthCubit({
    required LoginUser loginUser,
    required LogoutUser logoutUser,
    required GetCurrentUser getCurrentUser,
  })  : _loginUser = loginUser,
        _logoutUser = logoutUser,
        _getCurrentUser = getCurrentUser,
        super(const AuthInitial());

  final LoginUser _loginUser;
  final LogoutUser _logoutUser;
  final GetCurrentUser _getCurrentUser;

  Future<void> checkAuthStatus() async {
    emit(const AuthLoading());
    final result = await _getCurrentUser();
    result.when(
      onSuccess: (UserEntity? user) {
        emit(user == null ? const AuthUnauthenticated() : AuthAuthenticated(user));
      },
      onFailure: (failure) => emit(const AuthUnauthenticated()),
    );
  }

  Future<void> login({required String username, required String password}) async {
    emit(const AuthLoading());
    final result = await _loginUser(username: username, password: password);
    result.when(
      onSuccess: (UserEntity user) => emit(AuthAuthenticated(user)),
      onFailure: (failure) => emit(AuthFailure(failure.message)),
    );
  }

  Future<void> logout() async {
    emit(const AuthLoading());
    final result = await _logoutUser();
    result.when(
      onSuccess: (_) => emit(const AuthUnauthenticated()),
      onFailure: (failure) => emit(AuthFailure(failure.message)),
    );
  }
}
