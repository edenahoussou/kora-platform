import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../auth/domain/entities/phone_country.dart';
import '../../../auth/presentation/widgets/phone_country_picker.dart';
import '../../../payment/presentation/pages/mobile_money_page.dart';
import '../../../payment/presentation/widgets/labelled_field.dart';
import '../../../payment/presentation/widgets/operator_tile.dart';
import '../../../payment/presentation/widgets/payment_amount_card.dart';
import '../../data/datasources/colis_network_mock.dart';
import 'colis_checkout_args.dart';
import 'colis_missing_checkout.dart';

/// Le règlement d'une expédition par FedaPay : l'opérateur, le numéro,
/// puis l'attente.
///
/// Même écran que pour un billet, et pour la même raison : FedaPay pousse
/// la demande sur le téléphone du payeur, et il lui faut donc le réseau
/// et le numéro **avant** l'appel. KKiaPay, lui, les recueille dans sa
/// propre feuille — c'est [ColisGatewayPage] qui le sert.
///
/// **Aucun débit réel n'est déclenché** : aucun endpoint `parcel`
/// n'existe, et le ticket l'interdit. Ce que cet écran simule, c'est le
/// geste, pas la transaction.
class ColisMomoPage extends StatefulWidget {
  const ColisMomoPage({super.key, this.args});

  /// `null` si l'écran est ouvert sans données (lien profond).
  final ColisCheckoutArgs? args;

  @override
  State<ColisMomoPage> createState() => _ColisMomoPageState();
}

class _ColisMomoPageState extends State<ColisMomoPage> {
  final _phone = TextEditingController();

  String? _network;
  var _country = PhoneCountry.benin;

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  /// Le numéro tient quand il a le compte de chiffres du pays composé.
  bool get _phoneIsValid {
    final digits = _phone.text.replaceAll(RegExp(r'\D'), '');
    return digits.length == _country.digits;
  }

  bool get _canPay => _network != null && _phoneIsValid;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final args = widget.args;

    if (args == null) return const ColisMissingCheckout();

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.colisCheckoutEyebrow,
                title: l10n.paymentMomoTitle,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    // Le montant d'abord, comme sur un terminal de
                    // paiement : c'est ce qu'on vérifie avant de choisir
                    // par où l'on paie.
                    PaymentAmountCard(total: l10n.colisAmount(args.due)),
                    const SizedBox(height: AppSpacing.md),
                    SectionTitle(title: l10n.paymentMomoOperator),
                    // Deux par ligne : en colonne, les trois opérateurs
                    // repoussaient le champ du numéro sous le pli.
                    for (var row = 0; row < kColisMomoNetworks.length; row += 2)
                      Padding(
                        padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                        // `IntrinsicHeight` donne aux deux vignettes la
                        // hauteur de la plus haute : « Celtiis Cash »
                        // tient sur deux lignes là où « MTN » n'en prend
                        // qu'une.
                        child: IntrinsicHeight(
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              for (var col = row; col < row + 2; col++)
                                Expanded(
                                  // La ligne impaire garde sa moitié
                                  // vide : sans elle, le dernier
                                  // opérateur s'étirerait sur toute la
                                  // largeur et ne ressemblerait plus
                                  // aux autres.
                                  child: col < kColisMomoNetworks.length
                                      ? Padding(
                                          padding: EdgeInsets.only(
                                            left: col == row
                                                ? 0
                                                : AppSpacing.sm / 2,
                                            right: col == row
                                                ? AppSpacing.sm / 2
                                                : 0,
                                          ),
                                          child: OperatorTile(
                                            network: kColisMomoNetworks[col],
                                            selected:
                                                _network ==
                                                kColisMomoNetworks[col],
                                            onTap: () => setState(
                                              () => _network =
                                                  kColisMomoNetworks[col],
                                            ),
                                          ),
                                        )
                                      : const SizedBox.shrink(),
                                ),
                            ],
                          ),
                        ),
                      ),
                    const SizedBox(height: AppSpacing.sm),
                    LabelledField(
                      label: l10n.paymentMomoPhone,
                      child: Row(
                        children: [
                          // Même paire indicatif + numéro que l'écran
                          // des destinataires : on débite parfois un
                          // compte togolais depuis le Bénin.
                          PhoneCountryButton(
                            country: _country,
                            onCountry: (country) =>
                                setState(() => _country = country),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: KoraInput(
                              hint: _country.example,
                              controller: _phone,
                              keyboardType: TextInputType.phone,
                              onChanged: (_) => setState(() {}),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    // Le rappel qui protège le payeur : personne ne doit
                    // lui demander son code secret.
                    KoraAlert(
                      icon: Icons.shield_outlined,
                      title: l10n.paymentMomoNoticeTitle,
                      message: l10n.paymentMomoNotice,
                      variant: KoraAlertVariant.warning,
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    KoraAlert(
                      icon: Icons.lock_outline_rounded,
                      title: l10n.colisPaymentMockTitle,
                      message: l10n.colisPaymentMockText,
                    ),
                    // Le bouton d'assistance flotte au-dessus de cette
                    // liste : sans cet espace, il couvrirait le dernier
                    // élément une fois la liste arrivée en bout.
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
                  // Inerte tant que la saisie ne tient pas : un bouton
                  // qui refuse après coup fait recommencer pour rien.
                  onPressed: _canPay
                      ? () => context.push(
                          AppRouter.colisPendingPath,
                          extra: args.withPayer(
                            network: _network,
                            payerLabel: mobileMoneyLabel(_network!),
                          ),
                        )
                      : null,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
