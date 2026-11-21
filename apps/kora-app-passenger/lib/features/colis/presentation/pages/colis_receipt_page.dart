import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../../data/datasources/colis_network_mock.dart';
import '../bloc/colis_draft_cubit.dart';

/// Écran 09 — « Paiement confirmé ».
///
/// C'est le seul écran du parcours qui porte une **référence officielle**.
/// Elle n'existait pas une seconde plus tôt : le pré-enregistrement
/// l'annonçait comme une promesse, le devis la redisait, et c'est le
/// règlement qui la crée. Tout le ticket tient dans cette séquence.
class ColisReceiptPage extends StatelessWidget {
  const ColisReceiptPage({super.key});

  Future<void> _copy(BuildContext context, String reference) async {
    final confirmation = context.l10n.parcelsReferenceCopied;
    await Clipboard.setData(ClipboardData(text: reference));
    if (context.mounted) KoraToaster.show(context, confirmation);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return BlocBuilder<ColisDraftCubit, ColisDraft>(
      builder: (context, draft) {
        final reference = draft.reference ?? '';

        return Scaffold(
          body: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(
                    child: ListView(
                      padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                      children: [
                        // Le médaillon des succès KORA, celui du billet
                        // payé : un colis réglé est le même événement, et
                        // deux confirmations dessinées différemment dans
                        // la même app se remarquent.
                        StateView(
                          icon: Icons.check_rounded,
                          title: l10n.colisPaidTitle,
                          message: l10n.colisPaidText,
                        ),
                        const SizedBox(height: AppSpacing.lg),
                        _ReferenceCard(
                          reference: reference,
                          onCopy: () => _copy(context, reference),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        _AssignedRoute(draft: draft),
                        const SizedBox(height: AppSpacing.sm),
                        KoraAlert(
                          icon: Icons.sms_outlined,
                          title: l10n.colisPickupCodeTitle,
                          message: l10n.colisPickupCodeText,
                        ),
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                    child: KoraButton(
                      label: l10n.colisBackToParcels,
                      // Le dossier est clos côté app : revenir en
                      // arrière rouvrirait un paiement déjà réglé.
                      onPressed: () => context.go('/colis'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

/// La référence officielle, enfin émise.
class _ReferenceCard extends StatelessWidget {
  const _ReferenceCard({required this.reference, required this.onCopy});

  final String reference;
  final VoidCallback onCopy;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.kora,
        borderRadius: BorderRadius.circular(19),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            l10n.colisOfficialReference.toUpperCase(),
            style: AppTextStyles.eyebrow(AppColors.gold),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: Text(
                  reference,
                  style: const TextStyle(
                    color: AppColors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    // Une référence se dicte au guichet : ses chiffres
                    // doivent garder la même largeur.
                    fontFeatures: [FontFeature.tabularFigures()],
                  ),
                ),
              ),
              InkWell(
                onTap: onCopy,
                borderRadius: BorderRadius.circular(8),
                child: const Padding(
                  padding: EdgeInsets.all(6),
                  child: Icon(
                    Icons.copy_rounded,
                    size: 18,
                    color: AppColors.white,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            l10n.colisReceiptNote,
            style: TextStyle(
              color: AppColors.white.withValues(alpha: 0.72),
              fontSize: 11,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }
}

/// L'itinéraire assigné, scellé au hub de départ.
class _AssignedRoute extends StatelessWidget {
  const _AssignedRoute({required this.draft});

  final ColisDraft draft;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(19),
        border: Border.all(color: koraColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Icon(
                Icons.alt_route_rounded,
                size: 17,
                color: koraColors.textMuted,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisAssignedRoute,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              KoraTag(
                label: l10n.colisSealedAt(draft.originHub?.city ?? ''),
                variant: KoraTagVariant.success,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Text(
            '${draft.originHub?.label}  →  ${draft.destinationHub?.label}',
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 13,
              height: 1.3,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            l10n.colisPaidWeight(colisWeightText(draft.billableWeightKg)),
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ],
      ),
    );
  }
}
