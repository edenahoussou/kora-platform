import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/domain/entities/price.dart';
import '../../../booking/presentation/bloc/seat_hold_bloc.dart';
import '../../domain/entities/payment_catalog.dart';
import '../bloc/payment_catalog_bloc.dart';
import 'payment_args.dart';

/// Écran `payment-method` de la maquette : le choix du moyen de paiement.
///
/// **La liste vient du serveur**, pas de la maquette. `GET
/// /payments/methods` dit ce qui est encaissable ici et maintenant : un
/// provider dont les identifiants ont expiré, un moyen hors périmètre, et
/// l'écran doit s'y plier. La maquette montrait trois pavés dont la carte
/// bancaire ; le serveur n'annonce pas ce moyen, l'écran ne le propose
/// donc pas — mieux vaut un choix en moins qu'un paiement refusé après que
/// le voyageur a saisi son numéro.
///
/// Un moyen désactivé n'est pas masqué pour autant : le serveur le renvoie
/// exprès avec sa raison, et l'écran le grise. Le faire disparaître sans
/// explication laisserait croire à un bug.
///
/// Le rappel du délai de protection, juste au-dessus du total, n'est pas
/// décoratif : c'est le dernier écran où changer d'avis coûte encore
/// quelque chose, et le voyageur doit savoir combien de temps il lui reste
/// avant que ses sièges repartent.
class PaymentMethodPage extends StatelessWidget {
  const PaymentMethodPage({super.key, this.args});

  /// `null` si l'écran est ouvert sans données (lien profond).
  final PaymentArgs? args;

  /// Le solde du Wallet, repris tel quel de l'écran Wallet — la valeur de
  /// démonstration de la maquette, l'API n'exposant pas encore de solde.
  static const Price _walletBalance = Price(18750);

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = this.args;

    if (args == null) return const PaymentMissingArgs();

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.paymentMethodEyebrow,
                title: l10n.paymentMethodTitle,
              ),
              Expanded(
                child: BlocBuilder<PaymentCatalogBloc, PaymentCatalogState>(
                  builder: (context, catalog) => ListView(
                    padding: EdgeInsets.zero,
                    children: [
                      ..._methods(context, catalog, args),
                      const SizedBox(height: AppSpacing.md),
                      const _HoldReminder(),
                      const SizedBox(height: AppSpacing.md),
                      KoraCard(
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              l10n.paymentMethodTotal,
                              style: AppTextStyles.body(
                                context.koraColors.textMuted,
                              ),
                            ),
                            Text(
                              args.total.formatted,
                              style: AppTextStyles.titleMedium(
                                context.koraTitleColor,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: AppSpacing.xl),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Les pavés à afficher, selon l'état du catalogue.
  ///
  /// Le total reste visible dans les trois cas : même sans savoir *comment*
  /// payer, le voyageur doit voir *combien* il s'apprête à payer.
  List<Widget> _methods(
    BuildContext context,
    PaymentCatalogState state,
    PaymentArgs args,
  ) {
    final l10n = context.l10n;

    if (state.isLoading) {
      return [_CatalogNotice(message: l10n.paymentMethodLoading)];
    }

    if (state.hasFailed) {
      return [
        _CatalogNotice(
          message: state.errorMessage ?? l10n.paymentMethodFailed,
          // Un échec réseau se rejoue : `force` contourne le cache du bloc,
          // sans quoi « Réessayer » ne rappellerait jamais le serveur.
          onRetry: () => context.read<PaymentCatalogBloc>().add(
            const PaymentCatalogRequested(force: true),
          ),
        ),
      ];
    }

    final methods = state.catalog.methods;
    if (methods.isEmpty) {
      return [_CatalogNotice(message: l10n.paymentMethodNone)];
    }

    return [
      for (final method in methods) ...[
        _MethodTile(
          symbol: _symbols[method.code] ?? '•',
          title: _title(context, method),
          lead: _lead(context, method),
          // Un moyen annoncé actif mais sans provider utilisable ne mène
          // nulle part : `isUsable` exige les deux.
          onTap: method.isUsable ? () => _open(context, method, args) : null,
        ),
        const SizedBox(height: 10),
      ],
      // Des pavés tous inertes, et rien d'autre : le voyageur reste devant
      // un écran qui refuse sans dire pourquoi. Le catalogue n'est pas vide
      // — il n'a simplement rien d'encaissable — et ce cas mérite le même
      // message que le catalogue vide.
      if (!methods.any((method) => method.isUsable))
        _CatalogNotice(message: l10n.paymentMethodNone),
    ];
  }

  /// Le nom du moyen, dans la langue du voyageur quand l'app le connaît.
  ///
  /// Le libellé du serveur sert de repli : un moyen ajouté côté back après
  /// cette version s'affiche alors sous son nom rédigé plutôt que sous son
  /// code.
  String _title(BuildContext context, PaymentMethod method) {
    return switch (method.code) {
      PaymentCatalog.mobileMoneyCode => context.l10n.paymentMethodMomo,
      PaymentCatalog.walletCode => context.l10n.paymentMethodWallet,
      _ => method.label,
    };
  }

  /// La ligne sous le nom : ce qu'on gagne à choisir ce moyen, ou pourquoi
  /// il n'est pas disponible.
  String _lead(BuildContext context, PaymentMethod method) {
    final l10n = context.l10n;

    if (!method.isUsable) {
      return method.availability == _outOfMvp
          ? l10n.paymentMethodOutOfMvp
          : l10n.paymentMethodUnavailable;
    }

    return switch (method.code) {
      PaymentCatalog.mobileMoneyCode => l10n.paymentMethodMomoLead,
      PaymentCatalog.walletCode => l10n.paymentMethodWalletLead(
        _walletBalance.formatted,
      ),
      _ => method.label,
    };
  }

  void _open(BuildContext context, PaymentMethod method, PaymentArgs args) {
    final path = switch (method.code) {
      PaymentCatalog.mobileMoneyCode => AppRouter.paymentMobileMoneyPath,
      PaymentCatalog.walletCode => AppRouter.paymentWalletPath,
      _ => null,
    };

    if (path != null) context.push(path, extra: args);
  }

  /// Le pictogramme de `.transaction-icon`, repris de la maquette.
  static const Map<String, String> _symbols = {
    PaymentCatalog.mobileMoneyCode: 'M',
    PaymentCatalog.walletCode: '◫',
  };

  /// Valeur d'`availability` pour un moyen hors périmètre du MVP.
  static const String _outOfMvp = 'out_of_mvp';
}

/// Le catalogue en cours de chargement, en échec, ou vide.
class _CatalogNotice extends StatelessWidget {
  const _CatalogNotice({required this.message, this.onRetry});

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return KoraCard(
      variant: KoraCardVariant.mist,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            message,
            style: AppTextStyles.body(context.koraColors.textMuted),
          ),
          if (onRetry != null) ...[
            const SizedBox(height: AppSpacing.md),
            KoraButton(
              label: context.l10n.paymentMethodRetry,
              variant: KoraButtonVariant.ghost,
              onPressed: onRetry,
            ),
          ],
        ],
      ),
    );
  }
}

/// `<button class="card clickable">` : un moyen de paiement, son sous-titre
/// et le chevron d'entrée.
class _MethodTile extends StatelessWidget {
  const _MethodTile({
    required this.symbol,
    required this.title,
    required this.lead,
    required this.onTap,
  });

  /// Le pictogramme de `.transaction-icon`, repris de la maquette.
  final String symbol;
  final String title;
  final String lead;

  /// `null` pour un moyen que le serveur annonce mais n'encaisse pas : la
  /// carte devient inerte et perd son chevron, au lieu d'ouvrir un écran
  /// qui échouerait à la fin.
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      onTap: onTap,
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.gold,
              borderRadius: BorderRadius.circular(AppRadius.input),
            ),
            child: Text(
              symbol,
              style: const TextStyle(
                color: AppColors.kora,
                fontSize: 16,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: AppTextStyles.titleMedium(
                    onTap == null
                        ? koraColors.textMuted
                        : context.koraTitleColor,
                  ),
                ),
                const SizedBox(height: 2),
                Text(lead, style: AppTextStyles.body(koraColors.textMuted)),
              ],
            ),
          ),
          if (onTap != null)
            Icon(Icons.chevron_right_rounded, color: koraColors.textMuted),
        ],
      ),
    );
  }
}

/// `Vos sièges restent réservés pendant 06:42.`
///
/// Lit le même blocage que le décompte des écrans précédents ; disparaît
/// avec lui, parce qu'annoncer une protection qui n'existe plus serait
/// pire que de ne rien dire.
class _HoldReminder extends StatelessWidget {
  const _HoldReminder();

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<SeatHoldBloc, SeatHoldState>(
      builder: (context, state) {
        if (!state.isHeld) return const SizedBox.shrink();

        return KoraCard(
          variant: KoraCardVariant.mist,
          child: Text(
            context.l10n.paymentMethodHold(state.formatted),
            style: AppTextStyles.body(context.koraColors.textMuted),
          ),
        );
      },
    );
  }
}

/// Écran ouvert sans montant — un lien profond, ou un retour après
/// expiration. On ne devine pas un total à payer.
/// Revient à l'écran des moyens de paiement déjà ouvert.
///
/// Surtout pas `context.go` : l'écran s'ouvre avec le total arrêté au
/// récapitulatif, et un `go` le reconstruisait **sans** — le voyageur
/// tombait sur un écran vide, dont le retour, la pile ayant été remplacée,
/// ne menait plus qu'à un écran noir. On dépile donc jusqu'à lui : il est
/// encore là, avec son total.
///
/// go_router nomme chaque page d'après son chemin, d'où la comparaison.
void backToPaymentMethods(BuildContext context) {
  Navigator.of(context).popUntil(
    (route) =>
        route.settings.name == AppRouter.paymentMethodPath || route.isFirst,
  );
}

class PaymentMissingArgs extends StatelessWidget {
  const PaymentMissingArgs({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.paymentMethodEyebrow,
                title: l10n.paymentMethodTitle,
              ),
              const Spacer(),
              KoraButton(
                label: l10n.holdExpiredSecondary,
                onPressed: () => context.go('/home'),
              ),
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
      ),
    );
  }
}
