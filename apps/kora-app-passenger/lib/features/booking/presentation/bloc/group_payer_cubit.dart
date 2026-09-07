import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/company_account.dart';
import '../../domain/entities/group_payer.dart';
import '../../domain/repositories/reservation_repository.dart';

/// Qui règle la réservation de groupe, et sur quels comptes il peut
/// s'appuyer.
///
/// Il vit au niveau du tunnel : le payeur se choisit sur l'écran de
/// réservation de groupe, et c'est le blocage — deux écrans plus loin —
/// qui l'envoie au serveur. Le faire voyager d'écran en écran par des
/// arguments l'aurait perdu au premier retour en arrière.
class GroupPayerCubit extends Cubit<GroupPayerState> {
  GroupPayerCubit(this._repository) : super(const GroupPayerState());

  final ReservationRepository _repository;

  /// Charge les comptes entreprise du voyageur.
  ///
  /// Une liste vide est un succès : la plupart des voyageurs n'appartiennent
  /// à aucune entreprise, et l'écran se contente alors de ne pas proposer ce
  /// choix.
  Future<void> loadAccounts() async {
    if (state.isLoading || state.isLoaded) return;

    emit(state.copyWith(isLoading: true));

    final result = await _repository.companyAccounts();

    result.fold(
      // Un échec ne bloque rien : le paiement personnel reste possible, et
      // c'est le cas de l'immense majorité.
      (_) => emit(state.copyWith(isLoading: false, isLoaded: true)),
      (accounts) => emit(
        state.copyWith(isLoading: false, isLoaded: true, accounts: accounts),
      ),
    );
  }

  /// Le titulaire paie lui-même.
  void choosePersonal() =>
      emit(state.copyWith(payer: const GroupPayer.personal()));

  /// Le compte entreprise règle la note.
  void chooseCompany(CompanyAccount account) => emit(
    state.copyWith(
      payer: GroupPayer.company(accountId: account.id, name: account.name),
    ),
  );
}

/// Couche PRESENTATION : le payeur retenu et les comptes disponibles.
class GroupPayerState extends Equatable {
  const GroupPayerState({
    this.payer = const GroupPayer.personal(),
    this.accounts = const [],
    this.isLoading = false,
    this.isLoaded = false,
  });

  /// Personnel par défaut : c'est le cas ordinaire, et il n'exige rien du
  /// serveur.
  final GroupPayer payer;

  final List<CompanyAccount> accounts;
  final bool isLoading;

  /// La question a été posée au serveur, quelle qu'ait été sa réponse.
  final bool isLoaded;

  /// Vrai quand il y a un choix à offrir : sans compte entreprise, afficher
  /// un sélecteur à une seule option n'apprendrait rien.
  bool get hasChoice => accounts.isNotEmpty;

  GroupPayerState copyWith({
    GroupPayer? payer,
    List<CompanyAccount>? accounts,
    bool? isLoading,
    bool? isLoaded,
  }) {
    return GroupPayerState(
      payer: payer ?? this.payer,
      accounts: accounts ?? this.accounts,
      isLoading: isLoading ?? this.isLoading,
      isLoaded: isLoaded ?? this.isLoaded,
    );
  }

  @override
  List<Object?> get props => [payer, accounts, isLoading, isLoaded];
}
