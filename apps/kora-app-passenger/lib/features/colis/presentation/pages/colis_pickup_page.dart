import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/title_color.dart';
import '../../data/datasources/colis_tracking_mock.dart';
import '../widgets/colis_banner.dart';
import 'colis_shipments_page.dart';

/// Écran 12 — « Colis arrivé, prêt à retirer ».
///
/// Le dernier écran du parcours côté app : il dit où aller, quand, et
/// avec quoi. Le code de retrait n'y figure pas — il est arrivé par SMS
/// au destinataire, et c'est ce qui le rend opposable au guichet.
class ColisPickupPage extends StatelessWidget {
  const ColisPickupPage({super.key, this.reference});

  final String? reference;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final parcel = colisParcelByReference(reference ?? '');
    final counter = parcel.counter;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeaderless(title: l10n.colisPickupTitle),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                  children: [
                    ColisBanner(
                      tag: l10n.colisStatusReady,
                      caption: parcel.reference,
                      title: l10n.colisArrivedTitle,
                      text: l10n.colisArrivedText,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    _CounterCard(counter: counter),
                    const SizedBox(height: AppSpacing.sm),
                    // Les deux choses à apporter. Sans elles, le guichet
                    // ne remet rien, et le voyage était pour rien.
                    KoraAlert(
                      icon: Icons.key_outlined,
                      title: l10n.colisBringTitle,
                      message: l10n.colisBringText,
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Container(
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
                          Text(
                            l10n.colisParcelTitle,
                            style: TextStyle(
                              color: context.koraTitleColor,
                              fontSize: 14,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: AppSpacing.md),
                          ColisAmountRow(
                            label: l10n.colisDeparture,
                            value: parcel.originCity,
                          ),
                          const SizedBox(height: 9),
                          ColisAmountRow(
                            label: l10n.colisArrival,
                            value: parcel.destinationCity,
                          ),
                          const SizedBox(height: 9),
                          ColisAmountRow(
                            label: l10n.colisCertifiedWeightLabel,
                            value: l10n.colisWeightWithUnit(
                              parcel.certifiedWeightKg
                                  .toStringAsFixed(1)
                                  .replaceAll('.', ','),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Le guichet de remise : son adresse et ses horaires.
class _CounterCard extends StatelessWidget {
  const _CounterCard({required this.counter});

  final ColisPickupCounter counter;

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
                Icons.storefront_outlined,
                size: 17,
                color: koraColors.textMuted,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.colisCounterTitle,
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Text(
            counter.hub,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.place_outlined, size: 15, color: koraColors.textMuted),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  counter.address,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _Hours(
                  label: l10n.colisWeekdays,
                  value: counter.weekdayHours,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _Hours(
                  label: l10n.colisWeekend,
                  value: counter.weekendHours,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Hours extends StatelessWidget {
  const _Hours({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: koraColors.background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(label, style: AppTextStyles.caption(koraColors.textMuted)),
          const SizedBox(height: 3),
          Text(
            value,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}
