import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../bloc/colis_draft_cubit.dart';
import 'colis_checkout_args.dart';
import 'colis_missing_checkout.dart';

/// La demande est partie, sa confirmation se fait attendre.
///
/// C'est le seul écran d'attente du règlement, comme pour un billet. Mais
/// là où le billet attend le verdict du serveur, celui-ci n'attend rien :
/// aucun endpoint `parcel` n'existe, et le ticket interdit tout paiement
/// réel. Le bouton le dit en toutes lettres — **simuler** la confirmation
/// — plutôt qu'un faux délai qui ferait croire à un vrai opérateur.
///
/// C'est ici, et nulle part ailleurs, que naît la référence officielle :
/// elle suit le paiement, jamais l'intention de payer.
class ColisPendingPage extends StatelessWidget {
  const ColisPendingPage({super.key, this.args});

  /// `null` si l'écran est ouvert sans données (lien profond).
  final ColisCheckoutArgs? args;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = this.args;

    if (args == null) return const ColisMissingCheckout();

    final payer = args.payerLabel ?? args.gateway.name;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: ListView(
            children: [
              const SizedBox(height: AppSpacing.lg),
              StateView(
                icon: Icons.autorenew_rounded,
                title: l10n.colisPendingTitle,
                message: l10n.colisPendingText(payer, args.due),
                showLoader: true,
                primaryLabel: l10n.colisPendingSimulate,
                onPrimary: () {
                  // La référence officielle naît ici, et nulle part
                  // ailleurs.
                  context.read<ColisDraftCubit>().settle(
                    colisNewReference(DateTime.now()),
                  );
                  // `pushReplacement` : le reçu ne doit pas ramener à
                  // une attente déjà close.
                  context.pushReplacement(AppRouter.colisReceiptPath);
                },
                secondaryLabel: l10n.colisPendingSwitch,
                onSecondary: () => context.pop(),
              ),
              const SizedBox(height: AppSpacing.md),
              KoraAlert(
                icon: Icons.science_outlined,
                title: l10n.colisPaymentMockTitle,
                message: l10n.colisPaymentMockText,
              ),
              const SizedBox(height: AppSpacing.xl),
            ],
          ),
        ),
      ),
    );
  }
}
