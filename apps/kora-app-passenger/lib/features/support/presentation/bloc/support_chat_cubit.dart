import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/support_chat_configuration.dart';
import '../../domain/repositories/support_chat_repository.dart';

/// Couche PRESENTATION : savoir s'il y a un chat, et où.
///
/// Le silence est la règle. Un serveur qui ne répond pas, une intégration
/// éteinte, une configuration incomplète : dans les trois cas l'entrée
/// « Discuter avec un agent » ne s'affiche pas, et rien ne s'affiche à la
/// place. Le voyageur garde WhatsApp, le téléphone et l'e-mail, qui sont
/// là sans condition — lui annoncer qu'un canal qu'il n'a jamais vu est
/// indisponible n'avancerait à rien.
class SupportChatCubit extends Cubit<SupportChatState> {
  SupportChatCubit(this._repository) : super(const SupportChatState());

  final SupportChatRepository _repository;

  /// Demande la configuration au serveur. À appeler à l'ouverture de
  /// l'écran de contact : la réponse peut changer d'un jour à l'autre,
  /// puisqu'elle se règle depuis OPS.
  Future<void> load() async {
    if (state.isLoading) return;
    emit(const SupportChatState(isLoading: true));

    final result = await _repository.configuration();

    if (isClosed) return;
    emit(
      result.fold(
        (_) => const SupportChatState(),
        (configuration) => SupportChatState(configuration: configuration),
      ),
    );
  }
}

class SupportChatState extends Equatable {
  const SupportChatState({
    this.configuration = const SupportChatConfiguration(),
    this.isLoading = false,
  });

  final SupportChatConfiguration configuration;
  final bool isLoading;

  /// Vrai quand l'entrée « Discuter avec un agent » a lieu d'être.
  bool get isAvailable => configuration.isUsable;

  @override
  List<Object?> get props => [configuration, isLoading];
}
