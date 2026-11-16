import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/group_quote.dart';
import '../../domain/entities/journey.dart';
import '../../domain/repositories/reservation_repository.dart';

/// Le devis du serveur pour un nombre de voyageurs donné.
///
/// Il ne sert qu'à **afficher** le prix : tarif unitaire, remise de groupe
/// et total. Le montant réellement dû reste celui que le blocage puis le
/// paiement arrêtent — l'app ne calcule jamais une remise elle-même, sous
/// peine d'annoncer un prix que la caisse ne reconnaîtrait pas.
///
/// L'échec est silencieux et c'est voulu : le récapitulatif retombe sur le
/// tarif affiché par la recherche, qui reste juste à la remise près. Barrer
/// tout l'écran parce qu'une ligne de détail manque coûterait plus au
/// voyageur que de lui montrer un total sans son détail.
class GroupQuoteCubit extends Cubit<GroupQuoteState> {
  GroupQuoteCubit(this._repository) : super(const GroupQuoteState());

  final ReservationRepository _repository;

  Future<void> load({required Journey journey, required int passengers}) async {
    // Le même devis ne se redemande pas : le récapitulatif se reconstruit
    // à chaque battement du décompte de blocage.
    if (state.isLoading || state.matches(journey.outbound.id, passengers)) {
      return;
    }

    emit(state.copyWith(isLoading: true));

    final result = await _repository.groupQuote(
      journey: journey,
      passengers: passengers,
    );

    result.fold(
      (_) => emit(state.copyWith(isLoading: false, hasFailed: true)),
      (quote) => emit(
        GroupQuoteState(
          quote: quote,
          departureId: journey.outbound.id,
          passengers: passengers,
        ),
      ),
    );
  }
}

/// Couche PRESENTATION : le devis affichable, et rien d'autre.
class GroupQuoteState extends Equatable {
  const GroupQuoteState({
    this.quote,
    this.departureId,
    this.passengers,
    this.isLoading = false,
    this.hasFailed = false,
  });

  final GroupQuote? quote;

  /// De quoi reconnaître un devis déjà chargé.
  final String? departureId;
  final int? passengers;

  final bool isLoading;
  final bool hasFailed;

  /// Vrai quand le devis en mémoire décrit bien ce départ et ce nombre.
  bool matches(String id, int count) =>
      quote != null && departureId == id && passengers == count;

  GroupQuoteState copyWith({bool? isLoading, bool? hasFailed}) {
    return GroupQuoteState(
      quote: quote,
      departureId: departureId,
      passengers: passengers,
      isLoading: isLoading ?? this.isLoading,
      hasFailed: hasFailed ?? this.hasFailed,
    );
  }

  @override
  List<Object?> get props => [
    quote,
    departureId,
    passengers,
    isLoading,
    hasFailed,
  ];
}
