import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/config/app_config.dart';
import '../../../../app/config/terminal_mode.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/main_app_bar.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';
import '../cubit/scan_boarding_cubit.dart';

/// Onglet Scan boarding (MEZ-91) : déclenché uniquement par l'adaptateur
/// matériel injecté (`BarcodeScanner`), **jamais par un appel direct à
/// `mobile_scanner`** — voir critère d'acceptation MEZ-91. En mode P501
/// (`AppConfig.terminalMode`), c'est le SDK du terminal (Mock en dev,
/// réel en prod — MEZ-10) ; en mode téléphone, c'est la caméra de
/// l'appareil (`PhoneCameraBarcodeScanner`, voir ticket "Supporter le
/// scan par caméra téléphone en alternative au P501") — dans les deux
/// cas via le même point d'appel `ScanBoardingCubit.scan()`, qui ignore
/// totalement la source matérielle. Une recherche manuelle par code
/// court reste disponible pour les QR illisibles, sans jamais afficher
/// de prix (le manifeste local n'en contient pas).
class ScanBoardingPage extends StatefulWidget {
  const ScanBoardingPage({super.key});

  @override
  State<ScanBoardingPage> createState() => _ScanBoardingPageState();
}

class _ScanBoardingPageState extends State<ScanBoardingPage> {
  final TextEditingController _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  /// Un résultat métier est soit un embarquement accepté (immédiat ou en
  /// attente de synchro hors ligne) — le convoyeur voit alors le billet,
  /// comme depuis le Manifeste (`DetailPassagerPage`) — soit un rejet, qui
  /// n'a besoin que d'être signalé, sans page dédiée (échange du
  /// 23/09/2026 : la précédente page plein écran par résultat était jugée
  /// peu professionnelle pour un simple refus).
  static const Set<ScanBoardingStatus> _acceptedOutcomes = <ScanBoardingStatus>{
    ScanBoardingStatus.valide,
    ScanBoardingStatus.enAttenteSync,
  };

  String _rejectionTitle(ScanBoardingStatus status) => switch (status) {
        ScanBoardingStatus.mauvaisDepart => 'Mauvais départ',
        ScanBoardingStatus.billetInconnu => 'Billet inconnu',
        ScanBoardingStatus.dejaEmbarque => 'Déjà embarqué',
        ScanBoardingStatus.annule => 'Billet annulé',
        ScanBoardingStatus.conflit => 'Conflit terminal',
        _ => 'Billet invalide',
      };

  Future<void> _afterSubmit(BuildContext context) async {
    final ScanBoardingCubit cubit = context.read<ScanBoardingCubit>();
    if (!context.mounted) return;

    final ScanBoardingState state = cubit.state;

    if (state.status == ScanBoardingStatus.erreur) {
      await KoraDialogs.error(context,
          title: 'Scanner', description: state.message);
      return;
    }

    if (!state.status.isOutcome) return;

    if (!_acceptedOutcomes.contains(state.status)) {
      await KoraDialogs.error(
        context,
        title: _rejectionTitle(state.status),
        description: state.message ?? 'Ce billet ne peut pas être embarqué.',
      );
      cubit.reset();
      _searchController.clear();
      return;
    }

    final String? passengerId = state.matchedPassenger?.id;
    if (passengerId != null) {
      await context.push(AppRoutes.detailPassager, extra: passengerId);
    }
    if (context.mounted) {
      cubit.reset();
      _searchController.clear();
    }
  }

  Future<void> _scan(BuildContext context) async {
    await context.read<ScanBoardingCubit>().scan();
    if (context.mounted) await _afterSubmit(context);
  }

  Future<void> _submitManual(BuildContext context, Passenger passenger) async {
    await context.read<ScanBoardingCubit>().submitManual(passenger);
    if (context.mounted) await _afterSubmit(context);
  }

  @override
  Widget build(BuildContext context) {
    // Contrôle d'accès par permission : un profil sans `boarding.scan`
    // (ex. agent d'agence) ne doit jamais atteindre le scan — masquer la
    // fonction plutôt que de la laisser échouer en 403 au premier scan.
    final AuthState authState = context.watch<AuthCubit>().state;
    if (authState is AuthAuthenticated &&
        !authState.user.can('boarding.scan')) {
      return const _ScanNotAuthorized();
    }

    final Depart? depart = context.watch<SelectedDepartCubit>().state;

    return Scaffold(
      appBar: const MainAppBar(eyebrow: 'Embarquement', title: 'Scan boarding'),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: depart == null
              ? Center(
                  child: Text(
                    'Sélectionnez un départ à gérer pour scanner des billets.',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                )
              : SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      const Align(
                          alignment: Alignment.centerLeft,
                          child: SyncStatusBanner()),
                      const SizedBox(height: AppSpacing.sm),
                      Text(depart.route,
                          style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: AppSpacing.lg),
                      _ScanCard(onScan: () => _scan(context)),
                      const SizedBox(height: AppSpacing.lg),
                      Text('QR illisible ? Recherche par code court',
                          style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: AppSpacing.sm),
                      TextField(
                        controller: _searchController,
                        textCapitalization: TextCapitalization.characters,
                        decoration: InputDecoration(
                          hintText: 'Code court (ex. AB12CD)',
                          prefixIcon: const Icon(Icons.search),
                          suffixIcon: IconButton(
                            icon: const Icon(Icons.search),
                            tooltip: 'Rechercher',
                            onPressed: () => context
                                .read<ScanBoardingCubit>()
                                .searchManual(_searchController.text),
                          ),
                        ),
                        onChanged: (String value) => context
                            .read<ScanBoardingCubit>()
                            .searchManual(value),
                        onSubmitted: (String value) => context
                            .read<ScanBoardingCubit>()
                            .searchManual(value),
                      ),
                      BlocBuilder<ScanBoardingCubit, ScanBoardingState>(
                        buildWhen: (previous, current) =>
                            previous.manualResults != current.manualResults ||
                            previous.manualQuery != current.manualQuery,
                        builder: (context, state) {
                          if (state.manualResults.isEmpty) {
                            if (state.manualQuery.trim().isEmpty) {
                              return const SizedBox.shrink();
                            }
                            return Padding(
                              padding:
                                  const EdgeInsets.only(top: AppSpacing.sm),
                              child: Text(
                                'Aucun billet ne correspond à ce code dans '
                                'le manifeste chargé. Vérifiez le code, ou '
                                'que le manifeste est à jour (Manifeste → '
                                'tirer vers le bas pour rafraîchir).',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                            );
                          }
                          return Column(
                            children: state.manualResults
                                .map((Passenger p) => _ManualResultTile(
                                      passenger: p,
                                      onTap: () => _submitManual(context, p),
                                    ))
                                .toList(),
                          );
                        },
                      ),
                    ],
                  ),
                ),
        ),
      ),
    );
  }
}

class _ScanCard extends StatelessWidget {
  const _ScanCard({required this.onScan});

  final VoidCallback onScan;

  bool get _isPhoneMode =>
      AppConfig.instance.terminalMode == TerminalMode.phone;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: context.koraColors.surface,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Column(
        children: <Widget>[
          Icon(_isPhoneMode ? Icons.qr_code_scanner : Icons.point_of_sale,
              size: 48),
          const SizedBox(height: AppSpacing.sm),
          Text(
            _isPhoneMode
                ? 'Cadrez le QR code du billet avec la caméra.'
                : 'Présentez le billet au scanner du terminal P501.',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: AppSpacing.md),
          BlocBuilder<ScanBoardingCubit, ScanBoardingState>(
            buildWhen: (previous, current) => previous.status != current.status,
            builder: (context, state) => KoraButton(
              label: _isPhoneMode ? 'Scanner (caméra)' : 'Scanner (P501)',
              icon: Icons.qr_code_scanner,
              isLoading: state.status == ScanBoardingStatus.scanning,
              onPressed: onScan,
            ),
          ),
        ],
      ),
    );
  }
}

class _ManualResultTile extends StatelessWidget {
  const _ManualResultTile({required this.passenger, required this.onTap});

  final Passenger passenger;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(top: AppSpacing.sm),
      child: ListTile(
        title: Text(passenger.nomComplet),
        subtitle: Text('Siège ${passenger.siege} · ${passenger.ticketId}'),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}

/// Affiché à la place du scan pour un profil sans la permission
/// `boarding.scan` (ex. agent d'agence) — voir périmètre du ticket
/// "empêcher l'accès aux fonctions et aux données qui ne sont pas
/// autorisées pour le profil connecté".
class _ScanNotAuthorized extends StatelessWidget {
  const _ScanNotAuthorized();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      appBar: MainAppBar(eyebrow: 'Embarquement', title: 'Scan boarding'),
      body: Center(
        child: Padding(
          padding: EdgeInsets.all(AppSpacing.lg),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(Icons.lock_outline, size: 40),
              SizedBox(height: AppSpacing.sm),
              Text(
                'Votre profil n\'est pas autorisé à scanner les embarquements.',
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
