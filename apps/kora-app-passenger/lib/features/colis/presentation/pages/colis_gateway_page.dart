import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../payment/presentation/widgets/operator_badge.dart';
import '../../../payment/presentation/widgets/payment_amount_card.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../widgets/colis_surface.dart';
import 'colis_checkout_args.dart';
import 'colis_missing_checkout.dart';

/// Le règlement d'une expédition par la feuille de l'encaisseur, carte
/// ou Mobile Money.
///
/// **Rien n'est saisi ici, et c'est volontaire** — comme pour un billet.
/// KKiaPay recueille lui-même le numéro de carte **ou** l'opérateur et le
/// numéro Mobile Money, chez le prestataire : faire transiter un numéro
/// de carte par nos écrans y ferait entrer les obligations PCI-DSS.
/// L'écran se réduit donc à ce qu'il doit dire — le montant, qui
/// encaisse, et un bouton qui ouvre la feuille.
///
/// L'autre passerelle, FedaPay, pousse au contraire la demande sur le
/// téléphone du payeur : il lui faut l'opérateur et le numéro avant
/// l'appel, et c'est [ColisMomoPage] qui les demande.
class ColisGatewayPage extends StatelessWidget {
  const ColisGatewayPage({super.key, required this.isCard, this.args});

  /// Ce que la feuille encaissera. Porté par la route plutôt que par les
  /// arguments : c'est le chemin emprunté qui le fixe, et un lien
  /// profond sans arguments doit quand même savoir de quel écran il
  /// s'agit.
  final bool isCard;

  /// `null` si l'écran est ouvert sans données (lien profond).
  final ColisCheckoutArgs? args;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final args = this.args;

    if (args == null) return const ColisMissingCheckout();

    final gateway = args.gateway;
    // La carte bancaire n'a pas de marque à elle : c'est KKiaPay qui
    // l'encaisse, et c'est son nom qu'il faut annoncer avant que sa
    // fenêtre s'ouvre — sans quoi le payeur la croit étrangère.
    final payer = isCard ? _cardGatewayName : gateway.name;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.colisCheckoutEyebrow,
                title: isCard ? l10n.paymentCardTitle : l10n.paymentMomoTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    PaymentAmountCard(total: l10n.colisAmount(args.due)),
                    const SizedBox(height: AppSpacing.md),
                    KoraCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            l10n.colisGatewaySheetLead(payer),
                            style: AppTextStyles.titleMedium(
                              context.koraTitleColor,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            isCard
                                ? l10n.paymentCardLead
                                : l10n.paymentGatewayMomoLead(payer),
                            style: AppTextStyles.body(koraColors.textMuted),
                          ),
                          // Les opérateurs servis, en vignettes : on
                          // reconnaît son réseau avant d'avoir lu la
                          // ligne.
                          if (!isCard) ...[
                            const SizedBox(height: 10),
                            Row(
                              children: [
                                for (final network in kColisMomoNetworks)
                                  Padding(
                                    padding: const EdgeInsets.only(right: 6),
                                    child: OperatorBadge(
                                      network: network,
                                      size: 30,
                                    ),
                                  ),
                              ],
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    // Le rappel qui protège le payeur — et seulement sur
                    // le chemin Mobile Money, où il y a un code secret à
                    // ne pas donner. Sur celui de la carte, il répétait
                    // mot pour mot la phrase du pavé au-dessus, et
                    // parlait d'un numéro de carte là où le Mobile Money
                    // n'en demande aucun.
                    if (!isCard) ...[
                      KoraAlert(
                        icon: Icons.shield_outlined,
                        title: l10n.paymentMomoNoticeTitle,
                        message: l10n.paymentMomoNotice,
                        variant: KoraAlertVariant.warning,
                      ),
                      const SizedBox(height: AppSpacing.sm),
                    ],
                    ColisNote(
                      icon: Icons.science_outlined,
                      title: l10n.colisPaymentMockTitle,
                      message: l10n.colisPaymentMockText,
                    ),
                    const SizedBox(height: 104),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: KoraButton(
                  label: l10n.colisPayCta(args.due),
                  icon: Icons.arrow_forward_rounded,
                  iconPosition: KoraButtonIconPosition.edges,
                  onPressed: () => context.push(
                    AppRouter.colisPendingPath,
                    extra: args.withPayer(
                      payerLabel: payer,
                      startedAt: DateTime.now(),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// L'encaisseur de la carte bancaire : la carte est un moyen, pas une
  /// marque, et c'est KKiaPay qui ouvre sa fenêtre.
  static String get _cardGatewayName =>
      colisGatewayOf(ColisGateway.kkiapay).name;
}
